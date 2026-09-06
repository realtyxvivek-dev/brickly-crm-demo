<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\SystemSettings;
use App\Models\Task;
use App\Models\TelecallerTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileIncomingCallController extends Controller
{
    public function lookup(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->incomingCallCardEnabledForEmail((string) $user->email)) {
            return response()->json(['found' => false, 'canCreate' => false]);
        }

        $digits = $this->normalizePhone((string) $request->query('phone', ''));
        $needle = substr($digits, -10);
        if (strlen($needle) < 8) {
            return response()->json(['found' => false, 'canCreate' => false]);
        }

        $lead = Lead::query()
            ->with(['activeAssignments.assignedTo:id,name', 'callLogs' => function ($query) {
                $query->latest('start_time')->limit(1);
            }])
            ->whereHas('activeAssignments', function ($query) use ($user) {
                $query->where('assigned_to', $user->id);
            })
            ->where(function ($query) use ($needle) {
                $query->where('phone', 'like', '%' . $needle)
                    ->orWhere('phone', 'like', '%' . $needle . '%');
            })
            ->latest('updated_at')
            ->first();

        if (!$lead) {
            return response()->json([
                'found' => false,
                'canCreate' => true,
                'phone' => $request->query('phone'),
                'normalizedPhone' => $needle,
                'actions' => [
                    'createUrl' => url('/api/mobile/incoming-call-leads'),
                ],
            ]);
        }

        $openTask = $this->openTaskForLead($lead->id, $user->id);
        $lastCall = $lead->callLogs->first();
        $lastRemark = $this->lastRemarkForLead($lead);

        return response()->json([
            'found' => true,
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name ?: 'CRM Lead',
                'phone' => $lead->phone,
                'status' => $this->label($lead->status ?: 'new'),
                'source' => $this->label($lead->source ?: 'other'),
                'lastRemark' => $lastRemark,
                'nextFollowupAt' => optional($lead->next_followup_at)->toIso8601String(),
                'assignedTo' => optional($lead->activeAssignments->first()?->assignedTo)->name,
                'lastCall' => $lastCall ? [
                    'duration' => (int) $lastCall->duration,
                    'status' => $lastCall->status,
                    'startTime' => optional($lastCall->start_time)->toIso8601String(),
                ] : null,
            ],
            'task' => $openTask,
            'actions' => [
                'leadUrl' => url('/leads/' . $lead->id),
                'taskUrl' => $openTask && isset($openTask['url']) ? $openTask['url'] : url('/leads/' . $lead->id),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->incomingCallCardEnabledForEmail((string) $user->email)) {
            return response()->json(['message' => 'Incoming call card is not enabled.'], 403);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $digits = $this->normalizePhone($validated['phone']);
        $needle = substr($digits, -10);
        if (strlen($needle) < 8) {
            return response()->json(['message' => 'Valid phone number required.'], 422);
        }

        $lead = Lead::query()
            ->where(function ($query) use ($needle) {
                $query->where('phone', 'like', '%' . $needle)
                    ->orWhere('phone', 'like', '%' . $needle . '%');
            })
            ->latest('updated_at')
            ->first();

        DB::transaction(function () use (&$lead, $user, $validated) {
            $created = false;
            if (!$lead) {
                $intake = app(\App\Services\LeadIntakeService::class)->createOrReenquire([
                    'name' => 'Incoming Call ' . $validated['phone'],
                    'phone' => $validated['phone'],
                    'source' => 'ivr',
                    'status' => 'new',
                    'notes' => 'Created from Base CRM Android incoming call card.',
                    'created_by' => $user->id,
                ], 'ivr', $user->id);
                $lead = $intake['lead'];
                $created = $intake['was_created'];
            } else {
                app(\App\Services\LeadReenquiryService::class)->markGenericReenquiry($lead, 'ivr', $user->id);
            }

            if ($created || !$lead->activeAssignments()->exists()) {
                LeadAssignment::updateOrCreate(
                [
                    'lead_id' => $lead->id,
                    'assigned_to' => $user->id,
                    'is_active' => true,
                ],
                [
                    'assigned_by' => $user->id,
                    'assignment_type' => 'primary',
                    'assignment_method' => 'manual',
                    'notes' => 'Assigned from Android incoming call card.',
                    'assigned_at' => now(),
                    'unassigned_at' => null,
                ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
            ],
            'actions' => [
                'leadUrl' => url('/leads/' . $lead->id),
                'taskUrl' => url('/leads/' . $lead->id),
            ],
        ], 201);
    }

    private function openTaskForLead(int $leadId, int $userId): ?array
    {
        $telecallerTask = TelecallerTask::query()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $userId)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->oldest('scheduled_at')
            ->first();

        if ($telecallerTask) {
            return [
                'type' => 'calling',
                'status' => $this->label($telecallerTask->status),
                'scheduledAt' => optional($telecallerTask->scheduled_at)->toIso8601String(),
                'url' => url('/tasks'),
            ];
        }

        $task = Task::query()
            ->withoutGlobalScopes()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $userId)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->oldest('scheduled_at')
            ->first();

        if (!$task) {
            return null;
        }

        return [
            'type' => $this->label($task->type ?: 'task'),
            'status' => $this->label($task->status),
            'scheduledAt' => optional($task->scheduled_at ?? $task->due_date)->toIso8601String(),
            'url' => url('/tasks'),
        ];
    }

    private function incomingCallCardEnabledForEmail(string $email): bool
    {
        $configured = (string) SystemSettings::get('mobile_incoming_call_card_user_emails', 'test@gmail.com');
        $allowedEmails = collect(explode(',', $configured))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->values();

        if ($allowedEmails->isEmpty()) {
            $allowedEmails = collect(['test@gmail.com']);
        }

        return $allowedEmails->contains(strtolower(trim($email)));
    }

    private function lastRemarkForLead(Lead $lead): ?string
    {
        $candidates = collect([
            $lead->notes,
            $lead->requirements,
            $lead->other_lead_reason,
        ])
            ->map(fn ($value) => trim(strip_tags((string) $value)))
            ->filter();

        if ($candidates->isEmpty()) {
            return null;
        }

        return Str::limit($candidates->first(), 90);
    }

    private function label(?string $value): string
    {
        return str($value ?: '')->replace(['_', '-'], ' ')->title()->toString();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }
}
