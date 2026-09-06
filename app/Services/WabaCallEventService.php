<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Role;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\WabaCallEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class WabaCallEventService
{
    public function __construct(private readonly TelecallerTaskService $taskService)
    {
    }

    public function processWebhookPayload(array $payload): int
    {
        $processed = 0;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ($this->extractCallItems((array) $change) as $item) {
                    $event = $this->upsertCallEvent($item, $payload, (array) $change);
                    if ($event) {
                        $this->createFollowUpTaskIfNeeded($event);
                        $processed++;
                    }
                }
            }
        }

        return $processed;
    }

    public function extractCallItems(array $change): array
    {
        $value = (array) ($change['value'] ?? []);
        $field = (string) ($change['field'] ?? '');
        $candidates = [];

        foreach (['calls', 'call_events', 'callback_requests', 'callbacks'] as $key) {
            foreach ((array) Arr::get($value, $key, []) as $item) {
                if (is_array($item)) {
                    $candidates[] = [$key, $item];
                }
            }
        }

        foreach (['call', 'call_event', 'callback_request', 'callback'] as $key) {
            $item = Arr::get($value, $key);
            if (is_array($item)) {
                $candidates[] = [$key, $item];
            }
        }

        if (str_contains(strtolower($field), 'call') && empty($candidates)) {
            $candidates[] = [$field ?: 'call', $value];
        }

        return collect($candidates)
            ->map(fn (array $candidate) => ['source_key' => $candidate[0], 'payload' => $candidate[1], 'value' => $value, 'field' => $field])
            ->filter(fn (array $candidate) => $this->looksLikeCallEvent($candidate['payload'], $candidate['source_key'], $candidate['field']))
            ->values()
            ->all();
    }

    private function upsertCallEvent(array $item, array $rootPayload, array $change): ?WabaCallEvent
    {
        $call = (array) $item['payload'];
        $value = (array) $item['value'];
        $metaCallId = $this->firstFilled($call, ['id', 'call_id', 'wamid', 'event_id'])
            ?: $this->firstFilled($value, ['id', 'call_id', 'event_id']);
        $phone = $this->normalizePhone(
            $this->firstFilled($call, ['from', 'wa_id', 'phone', 'customer_phone', 'caller'])
            ?: $this->firstFilled($value, ['from', 'wa_id', 'phone'])
            ?: data_get($value, 'contacts.0.wa_id')
        );

        if (!$metaCallId && !$phone) {
            return null;
        }

        $lead = $phone ? $this->matchLeadByPhone($phone) : null;
        $assignedTo = $lead?->activeAssignments?->first()?->assigned_to
            ?: $lead?->latestAssignment?->assigned_to;
        $occurredAt = $this->normalizeTimestamp(
            $this->firstFilled($call, ['timestamp', 'created_time', 'event_time', 'start_time', 'end_time'])
                ?: $this->firstFilled($value, ['timestamp', 'event_time'])
        );
        $eventType = $this->normalizeEventType((string) ($item['source_key'] ?? 'call'), $call);
        $status = strtolower((string) (
            $this->firstFilled($call, ['status', 'event', 'state', 'result', 'call_status'])
            ?: ($eventType === 'callback_request' ? 'callback_requested' : 'received')
        ));

        $attributes = $metaCallId
            ? ['meta_call_id' => (string) $metaCallId]
            : ['phone' => $phone, 'occurred_at' => $occurredAt, 'event_type' => $eventType];

        return WabaCallEvent::updateOrCreate($attributes, [
            'meta_call_id' => $metaCallId ? (string) $metaCallId : null,
            'lead_id' => $lead?->id,
            'assigned_to' => $assignedTo,
            'phone' => $phone,
            'customer_name' => $this->firstFilled($call, ['profile_name', 'name', 'customer_name']) ?: data_get($value, 'contacts.0.profile.name'),
            'direction' => strtolower((string) ($this->firstFilled($call, ['direction']) ?: 'incoming')),
            'event_type' => $eventType,
            'status' => $status,
            'duration_seconds' => $this->normalizeDuration($this->firstFilled($call, ['duration', 'duration_seconds', 'call_duration'])),
            'raw_payload' => [
                'call' => $call,
                'change' => $change,
                'webhook_payload' => $rootPayload,
            ],
            'occurred_at' => $occurredAt,
        ]);
    }

    private function createFollowUpTaskIfNeeded(WabaCallEvent $event): void
    {
        if (!$event->shouldCreateFollowUpTask() || !$event->lead_id || !$event->assigned_to || $event->telecaller_task_id) {
            return;
        }

        $lead = Lead::find($event->lead_id);
        $assignedUser = User::find($event->assigned_to);
        if (!$lead || !$assignedUser) {
            return;
        }

        try {
            $note = 'Meta WABA ' . $event->event_type_label . ' captured'
                . ($event->status ? ' (' . $event->status_label . ')' : '')
                . '. Customer phone: ' . ($event->phone ?: 'unknown') . '.';

            $task = $this->taskService->createScheduledCallingTask(
                $lead,
                $assignedUser,
                now()->addMinutes(10),
                $this->systemUserId(),
                $note
            );

            $event->update([
                'telecaller_task_id' => $task->id,
                'task_created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::info('WABA call event task not created', [
                'event_id' => $event->id,
                'lead_id' => $event->lead_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function matchLeadByPhone(string $phone): ?Lead
    {
        $last10 = substr($phone, -10);

        return Lead::query()
            ->with(['activeAssignments.assignedTo', 'latestAssignment'])
            ->where(function ($query) use ($phone, $last10) {
                $query->where('phone', $phone);
                if ($last10 !== '') {
                    $query->orWhere('phone', $last10)
                        ->orWhere('phone', 'like', '%' . $last10);
                }
            })
            ->latest('id')
            ->first();
    }

    private function looksLikeCallEvent(array $payload, string $sourceKey, string $field): bool
    {
        $haystack = strtolower($sourceKey . ' ' . $field . ' ' . implode(' ', array_keys($payload)));

        return str_contains($haystack, 'call') || str_contains($haystack, 'callback');
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return strlen($digits) === 10 ? '91' . $digits : $digits;
    }

    private function normalizeTimestamp(mixed $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        return $timestamp ? Carbon::parse($timestamp) : now();
    }

    private function normalizeDuration(mixed $duration): ?int
    {
        return is_numeric($duration) ? max(0, (int) $duration) : null;
    }

    private function normalizeEventType(string $sourceKey, array $payload): string
    {
        $raw = strtolower((string) ($payload['type'] ?? $payload['event_type'] ?? $sourceKey));
        if (str_contains($raw, 'callback')) {
            return 'callback_request';
        }

        return 'call';
    }

    private function firstFilled(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function systemUserId(): int
    {
        return (int) (auth()->id()
            ?: User::whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))->value('id')
            ?: User::query()->value('id')
            ?: 1);
    }
}
