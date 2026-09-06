<?php

namespace App\Jobs;

use App\Models\IgFailedJob;
use App\Models\IgWebhookEvent;
use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramCommentAutomationService;
use App\Services\Instagram\InstagramDmFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessInstagramWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(public int $webhookEventId)
    {
        $this->onQueue('instagram');
    }

    public function handle(
        InstagramCommentAutomationService $commentAutomationService,
        InstagramDmFlowService $dmFlowService
    ): void
    {
        $event = IgWebhookEvent::query()->find($this->webhookEventId);

        if (!$event) {
            return;
        }

        $payload = $event->payload ?? [];
        $summary = $this->summarizePayload($payload);
        $account = $this->resolveAccount($payload);

        $event->update([
            'instagram_account_id' => $account?->id,
            'external_event_id' => $event->external_event_id ?: $summary['external_event_id'],
            'event_type' => $summary['event_type'],
            'object_type' => $summary['object_type'],
            'field' => $summary['field'],
        ]);

        if ($summary['event_type'] === 'unknown') {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Unsupported Instagram webhook payload.',
            ]);
            return;
        }

        if (!$account) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'No connected Instagram account matched this webhook payload.',
            ]);
            return;
        }

        if ($summary['event_type'] === 'comments') {
            $commentAutomationService->handleCommentEvent($event->fresh(), $account);
            return;
        }

        if ($summary['event_type'] === 'messages') {
            $dmFlowService->handleMessageEvent($event->fresh(), $account);
            return;
        }

        $event->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $event = IgWebhookEvent::query()->find($this->webhookEventId);

        if ($event) {
            $event->update([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
        }

        IgFailedJob::query()->create([
            'job_type' => 'instagram_webhook_event',
            'related_type' => IgWebhookEvent::class,
            'related_id' => $this->webhookEventId,
            'payload' => [
                'webhook_event_id' => $this->webhookEventId,
            ],
            'attempts' => $this->attempts(),
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }

    private function summarizePayload(array $payload): array
    {
        $entry = data_get($payload, 'entry.0', []);
        $change = data_get($entry, 'changes.0', []);
        $messaging = data_get($entry, 'messaging.0', []);
        $field = data_get($change, 'field') ?: (empty($messaging) ? null : 'messages');

        return [
            'external_event_id' => data_get($change, 'value.id')
                ?: data_get($messaging, 'message.mid')
                ?: data_get($entry, 'id'),
            'object_type' => data_get($payload, 'object'),
            'field' => $field,
            'event_type' => $this->normalizeEventType($field),
        ];
    }

    private function resolveAccount(array $payload): ?InstagramAccount
    {
        $candidates = collect([
            data_get($payload, 'entry.0.id'),
            data_get($payload, 'entry.0.changes.0.value.recipient.id'),
            data_get($payload, 'entry.0.messaging.0.recipient.id'),
            data_get($payload, 'entry.0.messaging.0.sender.id'),
            data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id'),
        ])->filter()->map(fn ($value) => (string) $value)->unique()->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        return InstagramAccount::query()
            ->whereIn('ig_user_id', $candidates->all())
            ->first();
    }

    private function normalizeEventType(?string $field): string
    {
        return match ($field) {
            'comments', 'live_comments' => 'comments',
            'messages', 'messaging_postbacks' => 'messages',
            'message_reactions' => 'message_reactions',
            default => 'unknown',
        };
    }
}
