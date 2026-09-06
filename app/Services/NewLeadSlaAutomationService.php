<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Mail\NewLeadSlaEscalationMail;
use App\Models\AppNotification;
use App\Models\FbLead;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\MailDeliveryLog;
use App\Models\NewLeadSlaAutomationAudit;
use App\Models\NewLeadSlaAutomationConfig;
use App\Models\NewLeadSlaAutomationState;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class NewLeadSlaAutomationService
{
    private const RESPONSE_OUTCOMES = ['interested', 'follow_up', 'cnp', 'meeting', 'visit', 'not_interested', 'junk'];

    public function __construct(
        private readonly LeadTaskCleanupService $leadTaskCleanupService,
        private readonly UserStatusService $userStatusService,
    ) {
    }

    public function processAutomationCycle(): array
    {
        return [
            'assigned' => $this->assignUnassignedConfiguredLeads(),
            'started' => $this->startTrackingAssignedLeadsWithoutState(),
            ...$this->processDueStates(),
        ];
    }

    public function getConfigsForUi(): Collection
    {
        return NewLeadSlaAutomationConfig::query()
            ->with(['fbForm:id,form_name,form_id', 'poolUsers.user.role', 'recipients.user.role'])
            ->orderBy('source')
            ->orderBy('fb_form_id')
            ->get();
    }

    public function getDashboardSummary(): array
    {
        $activeStates = NewLeadSlaAutomationState::query()->where('status', 'active');

        return [
            'active_leads' => (clone $activeStates)->count(),
            'escalated_leads' => NewLeadSlaAutomationState::query()->where('status', 'escalated')->count(),
            'at_risk_leads' => (clone $activeStates)->whereBetween('sla_deadline_at', [now(), now()->addHour()])->count(),
            'recent_escalations' => NewLeadSlaAutomationState::query()
                ->with(['lead:id,name,phone,source', 'currentAssignee:id,name'])
                ->where('status', 'escalated')
                ->latest('escalated_at')
                ->limit(10)
                ->get(),
            'user_misses' => NewLeadSlaAutomationAudit::query()
                ->selectRaw('from_user_id, COUNT(*) as total')
                ->with('fromUser:id,name')
                ->where('action', 'sla_missed')
                ->whereNotNull('from_user_id')
                ->groupBy('from_user_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'source_misses' => NewLeadSlaAutomationAudit::query()
                ->selectRaw('new_lead_sla_automation_configs.source, COUNT(*) as total')
                ->join('new_lead_sla_automation_configs', 'new_lead_sla_automation_configs.id', '=', 'new_lead_sla_automation_audits.config_id')
                ->where('new_lead_sla_automation_audits.action', 'sla_missed')
                ->groupBy('new_lead_sla_automation_configs.source')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }

    public function countLostLeadsForUserThisMonth(int $userId): int
    {
        return NewLeadSlaAutomationAudit::query()
            ->where('action', 'reassigned')
            ->where('from_user_id', $userId)
            ->whereBetween('acted_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])
            ->count();
    }

    public function syncForAssignment(Lead $lead, LeadAssignment $assignment): ?NewLeadSlaAutomationState
    {
        $config = $this->getConfigForLead($lead);
        if (!$config || $lead->status !== 'new' || !$this->isUserEligibleForConfig($config, $assignment->assigned_to, [])) {
            return null;
        }

        $existing = NewLeadSlaAutomationState::query()
            ->where('lead_id', $lead->id)
            ->where('config_id', $config->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($existing) {
            if ((int) $existing->current_assignment_id === (int) $assignment->id) {
                return $existing;
            }
            $this->cancelState($existing, 'Lead reassigned outside SLA automation.');
        }

        $state = NewLeadSlaAutomationState::create([
            'lead_id' => $lead->id,
            'config_id' => $config->id,
            'current_assignment_id' => $assignment->id,
            'original_assignment_id' => $assignment->id,
            'current_assigned_to' => $assignment->assigned_to,
            'original_assigned_to' => $assignment->assigned_to,
            'attempt_number' => 1,
            'sla_started_at' => now(),
            'sla_deadline_at' => $this->calculateDeadline(now(), $config),
            'status' => 'active',
        ]);

        $this->createAudit($state, 'assigned', [
            'to_user_id' => $assignment->assigned_to,
            'assignment_id' => $assignment->id,
            'message' => 'Lead entered new lead SLA automation.',
            'meta' => [
                'sla_started_at' => $state->sla_started_at?->toDateTimeString(),
                'sla_deadline_at' => $state->sla_deadline_at?->toDateTimeString(),
                'attempt_number' => 1,
            ],
        ]);

        return $state;
    }

    public function storeConfig(array $data, int $userId): NewLeadSlaAutomationConfig
    {
        return DB::transaction(function () use ($data, $userId) {
            $config = NewLeadSlaAutomationConfig::create($this->mapConfigPayload($data, $userId, true));
            $this->syncPoolUsers($config, $data['pool_user_ids'] ?? []);
            $this->syncRecipients($config, $data['recipient_user_ids'] ?? []);
            return $config->fresh(['poolUsers.user.role', 'recipients.user.role']);
        });
    }

    public function updateConfig(NewLeadSlaAutomationConfig $config, array $data, int $userId): NewLeadSlaAutomationConfig
    {
        return DB::transaction(function () use ($config, $data, $userId) {
            $config->update($this->mapConfigPayload($data, $userId, false));
            $this->syncPoolUsers($config, $data['pool_user_ids'] ?? []);
            $this->syncRecipients($config, $data['recipient_user_ids'] ?? []);
            return $config->fresh(['poolUsers.user.role', 'recipients.user.role']);
        });
    }

    public function deleteConfig(NewLeadSlaAutomationConfig $config): void
    {
        DB::transaction(function () use ($config) {
            NewLeadSlaAutomationState::query()
                ->where('config_id', $config->id)
                ->where('status', 'active')
                ->get()
                ->each(fn (NewLeadSlaAutomationState $state) => $this->cancelState($state, 'Automation config deleted.'));
            $config->delete();
        });
    }

    private function assignUnassignedConfiguredLeads(): int
    {
        $count = 0;
        $configs = NewLeadSlaAutomationConfig::query()->where('is_active', true)->get();
        $activeSources = $configs->pluck('source')->unique()->values()->all();
        $leads = Lead::query()
            ->where('status', 'new')
            ->whereIn('source', $activeSources)
            ->whereDoesntHave('activeAssignments')
            ->orderBy('id')
            ->get();

        foreach ($leads as $lead) {
            $config = $this->getConfigForLead($lead);
            if (!$config) {
                continue;
            }

            $targetUser = $this->resolveNextPoolUser($config, []);
            if (!$targetUser) {
                $this->escalateUnassignedLead($lead, $config, 'No eligible user available for initial SLA assignment.');
                continue;
            }

            DB::transaction(function () use ($lead, $config, $targetUser) {
                $assignment = LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $targetUser->id,
                    'assigned_by' => $config->updated_by ?? $config->created_by ?? 1,
                    'assignment_type' => 'primary',
                    'assignment_method' => 'round_robin',
                    'assigned_at' => now(),
                    'is_active' => true,
                    'notes' => 'Assigned by new lead SLA automation.',
                ]);
                $config->update(['last_round_robin_user_id' => $targetUser->id]);
                Event::dispatch(new LeadAssigned($lead, $targetUser->id, $config->updated_by ?? $config->created_by ?? 1));
                $this->syncForAssignment($lead, $assignment);
            });

            $count++;
        }

        return $count;
    }

    private function startTrackingAssignedLeadsWithoutState(): int
    {
        $count = 0;
        $assignments = LeadAssignment::query()->activeWithLiveLead()->with(['lead'])->whereHas('lead', fn ($q) => $q->where('status', 'new'))->get();

        foreach ($assignments as $assignment) {
            $config = $this->getConfigForLead($assignment->lead);
            if (!$config) {
                continue;
            }
            $existing = NewLeadSlaAutomationState::query()
                ->where('lead_id', $assignment->lead_id)
                ->where('config_id', $config->id)
                ->where('status', 'active')
                ->exists();
            if ($existing || !$this->isUserEligibleForConfig($config, $assignment->assigned_to, [])) {
                continue;
            }
            $this->syncForAssignment($assignment->lead, $assignment);
            $count++;
        }

        return $count;
    }

    private function processDueStates(): array
    {
        $stats = ['responded' => 0, 'transferred' => 0, 'escalated' => 0, 'cancelled' => 0];
        $states = NewLeadSlaAutomationState::query()
            ->with(['lead', 'config.poolUsers.user.role', 'config.recipients.user.role', 'currentAssignment', 'audits'])
            ->where('status', 'active')
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<=', now())
            ->get();

        foreach ($states as $state) {
            if ($state->fresh()?->status !== 'active') continue;
            $state->update(['last_checked_at' => now()]);
            if (!$state->lead || $state->lead->status !== 'new' || !$state->config || !$state->config->is_active) {
                $this->cancelState($state, 'Lead is no longer eligible for new lead SLA automation.');
                $stats['cancelled']++;
                continue;
            }

            $response = $this->findResponseForState($state);
            if ($response) {
                $this->markResponded($state, $response);
                $stats['responded']++;
                continue;
            }

            $targetUser = $this->resolveNextPoolUser($state->config, $this->getAttemptedUserIds($state));
            if (!$targetUser || $state->attempt_number >= $state->config->max_transfer_attempts) {
                $this->escalateState($state);
                $stats['escalated']++;
                continue;
            }

            $this->transferState($state, $targetUser);
            $stats['transferred']++;
        }

        return $stats;
    }

    private function transferState(NewLeadSlaAutomationState $state, User $targetUser): void
    {
        DB::transaction(function () use ($state, $targetUser) {
            Lead::whereKey($state->lead_id)->lockForUpdate()->firstOrFail();
            if ($state->fresh()?->status !== 'active') return;
            $oldAssignment = LeadAssignment::query()->find($state->current_assignment_id);
            if (!$oldAssignment || !$oldAssignment->is_active) {
                $this->cancelState($state, 'Active assignment missing before SLA transfer.');
                return;
            }

            $this->createAudit($state, 'sla_missed', [
                'from_user_id' => $state->current_assigned_to,
                'assignment_id' => $oldAssignment->id,
                'message' => 'Lead missed SLA window without response.',
                'meta' => ['attempt_number' => $state->attempt_number, 'sla_deadline_at' => $state->sla_deadline_at?->toDateTimeString()],
            ]);

            $this->leadTaskCleanupService->deleteTasksForLeadAndOwner($state->lead_id, (int) $state->current_assigned_to, $state->config->updated_by ?? $state->config->created_by, 'lead_transferred');
            $oldAssignment->update(['is_active' => false, 'unassigned_at' => now(), 'notes' => trim(($oldAssignment->notes ?? '') . PHP_EOL . 'Transferred by new lead SLA automation.')]);

            $newAssignment = LeadAssignment::create([
                'lead_id' => $state->lead_id,
                'assigned_to' => $targetUser->id,
                'assigned_by' => $state->config->updated_by ?? $state->config->created_by ?? 1,
                'assignment_type' => 'primary',
                'assignment_method' => 'round_robin',
                'assigned_at' => now(),
                'is_active' => true,
                'notes' => 'Auto-transferred by new lead SLA automation after no response.',
            ]);

            $state->config->update(['last_round_robin_user_id' => $targetUser->id]);
            Event::dispatch(new LeadAssigned($state->lead, $targetUser->id, $state->config->updated_by ?? $state->config->created_by ?? 1));

            $state->update([
                'current_assignment_id' => $newAssignment->id,
                'current_assigned_to' => $targetUser->id,
                'attempt_number' => $state->attempt_number + 1,
                'sla_started_at' => now(),
                'sla_deadline_at' => $this->calculateDeadline(now(), $state->config),
                'last_transferred_at' => now(),
            ]);

            $this->createAudit($state->fresh(), 'reassigned', [
                'from_user_id' => $oldAssignment->assigned_to,
                'to_user_id' => $targetUser->id,
                'assignment_id' => $newAssignment->id,
                'message' => 'Lead reassigned to next SLA pool user.',
                'meta' => ['attempt_number' => $state->attempt_number + 1, 'new_deadline_at' => $state->fresh()->sla_deadline_at?->toDateTimeString()],
            ]);
        });
    }

    private function escalateState(NewLeadSlaAutomationState $state): void
    {
        $state->update(['status' => 'escalated', 'escalated_at' => now()]);
        $attemptTrail = $this->buildAttemptTrail($state);
        $this->createAudit($state, 'escalated', ['from_user_id' => $state->current_assigned_to, 'message' => 'Lead escalated after all configured users missed SLA.', 'meta' => ['trail' => $attemptTrail]]);

        $this->dispatchEscalationNotifications($state, $attemptTrail);
    }

    private function dispatchEscalationNotifications(NewLeadSlaAutomationState $state, array $attemptTrail): void
    {
        $recipients = $state->config->recipients()->with('user.role')->where('is_active', true)->get()->pluck('user')->filter();
        foreach ($recipients as $recipient) {
            if ($state->config->in_app_enabled) {
                AppNotification::create([
                    'user_id' => $recipient->id,
                    'type' => AppNotification::TYPE_NEW_LEAD,
                    'title' => 'New Lead SLA Escalation',
                    'message' => "Lead {$state->lead->name} was not responded to within SLA by the full pool.",
                    'action_type' => AppNotification::ACTION_LEAD,
                    'action_url' => route('crm.automation.sla.index'),
                    'data' => ['lead_id' => $state->lead_id, 'automation' => 'new_lead_sla', 'state_id' => $state->id],
                ]);
            }
        }

        if ($state->config->email_enabled) {
            $emails = $recipients->pluck('email')->filter()->unique()->values()->all();
            foreach ($emails as $email) {
                app(MailDeliveryLogger::class)->sendMailable(
                    MailDeliveryLog::TYPE_NEW_LEAD_SLA_ESCALATION,
                    'New Lead SLA Escalation: ' . ($state->lead?->name ?? 'Lead'),
                    (string) $email,
                    new NewLeadSlaEscalationMail($state->fresh('lead'), $attemptTrail),
                    [
                        'lead_id' => $state->lead_id,
                        'state_id' => $state->id,
                        'attempt_number' => $state->attempt_number,
                    ],
                    $state->lead
                );
            }
        }
    }

    private function findResponseForState(NewLeadSlaAutomationState $state): ?array
    {
        $deadline = $state->sla_deadline_at;
        $boundary = app(LeadReopenService::class)->boundary((int) $state->lead_id);
        $managerTask = Task::query()->withoutGlobalScope('visible_in_queue')->where('lead_id', $state->lead_id)->where('id', '>', $boundary['tasks'] ?? 0)->where('assigned_to', $state->current_assigned_to)->whereIn('outcome', self::RESPONSE_OUTCOMES)->orderByRaw('COALESCE(outcome_recorded_at, completed_at, updated_at) asc')->first();
        $telecallerTask = TelecallerTask::query()->withoutGlobalScope('visible_in_queue')->where('lead_id', $state->lead_id)->where('id', '>', $boundary['telecaller_tasks'] ?? 0)->where('assigned_to', $state->current_assigned_to)->whereIn('outcome', self::RESPONSE_OUTCOMES)->orderByRaw('COALESCE(completed_at, updated_at) asc')->first();
        $candidates = [];

        if ($managerTask) {
            $respondedAt = $managerTask->outcome_recorded_at ?? $managerTask->completed_at ?? $managerTask->updated_at;
            if ($respondedAt && $respondedAt->lte($deadline)) {
                $candidates[] = ['responded_at' => $respondedAt, 'outcome' => $managerTask->outcome, 'task_model' => Task::class, 'task_id' => $managerTask->id];
            }
        }
        if ($telecallerTask) {
            $respondedAt = $telecallerTask->completed_at ?? $telecallerTask->updated_at;
            if ($respondedAt && $respondedAt->lte($deadline)) {
                $candidates[] = ['responded_at' => $respondedAt, 'outcome' => $telecallerTask->outcome, 'task_model' => TelecallerTask::class, 'task_id' => $telecallerTask->id];
            }
        }
        if ($state->lead->other_lead_marked_by === $state->current_assigned_to && in_array($state->lead->status, ['junk', 'not_interested'], true) && $state->lead->other_lead_marked_at && $state->lead->other_lead_marked_at->lte($deadline)) {
            $candidates[] = ['responded_at' => $state->lead->other_lead_marked_at, 'outcome' => $state->lead->status, 'task_model' => Lead::class, 'task_id' => $state->lead->id];
        }
        if (empty($candidates)) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $a['responded_at']->timestamp <=> $b['responded_at']->timestamp);
        return $candidates[0];
    }

    private function markResponded(NewLeadSlaAutomationState $state, array $response): void
    {
        $state->update([
            'status' => 'responded',
            'responded_at' => $response['responded_at'],
            'response_outcome' => $response['outcome'],
            'response_task_model' => $response['task_model'],
            'response_task_id' => $response['task_id'],
        ]);
        $this->createAudit($state, 'responded', ['from_user_id' => $state->current_assigned_to, 'task_model' => $response['task_model'], 'task_id' => $response['task_id'], 'message' => 'Lead received a valid response within SLA.', 'meta' => ['responded_at' => $response['responded_at']->toDateTimeString(), 'outcome' => $response['outcome']]]);
    }

    private function cancelState(NewLeadSlaAutomationState $state, string $reason): void
    {
        $state->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        $this->createAudit($state, 'cancelled', ['from_user_id' => $state->current_assigned_to, 'message' => $reason]);
    }

    private function resolveNextPoolUser(NewLeadSlaAutomationConfig $config, array $excludeUserIds): ?User
    {
        $poolUsers = $config->poolUsers->filter(fn ($poolUser) => $poolUser->is_active && $poolUser->user && $this->isUserEligibleForConfig($config, $poolUser->user_id, $excludeUserIds))->values();
        if ($poolUsers->isEmpty()) {
            return null;
        }
        if (!$config->last_round_robin_user_id) {
            return $poolUsers->first()->user;
        }
        $index = $poolUsers->search(fn ($poolUser) => (int) $poolUser->user_id === (int) $config->last_round_robin_user_id);
        if ($index === false) {
            return $poolUsers->first()->user;
        }
        return $poolUsers->get(($index + 1) % $poolUsers->count())?->user;
    }

    private function isUserEligibleForConfig(NewLeadSlaAutomationConfig $config, int $userId, array $excludeUserIds): bool
    {
        if (in_array($userId, $excludeUserIds, true)) {
            return false;
        }
        $user = User::query()->with('role')->find($userId);
        if (!$user || !$user->isSalesExecutive()) {
            return false;
        }
        if ($config->skip_inactive_users && !$user->is_active) {
            return false;
        }
        if ($config->skip_absent_users && $this->userStatusService->isUserAbsent($userId)) {
            return false;
        }
        return true;
    }

    private function calculateDeadline(Carbon $start, NewLeadSlaAutomationConfig $config): Carbon
    {
        $remaining = max(1, (int) $config->sla_minutes);
        $cursor = $start->copy()->seconds(0);
        while ($remaining > 0) {
            $cursor = $this->moveCursorIntoBusinessWindow($cursor, $config);
            $windowEnd = Carbon::parse($cursor->format('Y-m-d') . ' ' . $config->business_end_time, $cursor->timezone);
            $available = max(0, $cursor->diffInMinutes($windowEnd, false));
            if ($remaining <= $available) {
                return $cursor->copy()->addMinutes($remaining);
            }
            $remaining -= $available;
            $cursor = $windowEnd->copy()->addMinute();
        }
        return $cursor;
    }

    private function moveCursorIntoBusinessWindow(Carbon $cursor, NewLeadSlaAutomationConfig $config): Carbon
    {
        while (true) {
            if ($config->weekends_off && $cursor->isWeekend()) {
                $cursor = $cursor->copy()->next(Carbon::MONDAY)->setTimeFromTimeString($config->business_start_time);
                continue;
            }
            $dayStart = Carbon::parse($cursor->format('Y-m-d') . ' ' . $config->business_start_time, $cursor->timezone);
            $dayEnd = Carbon::parse($cursor->format('Y-m-d') . ' ' . $config->business_end_time, $cursor->timezone);
            if ($cursor->lt($dayStart)) {
                return $dayStart;
            }
            if ($cursor->gte($dayEnd)) {
                $cursor = $cursor->copy()->addDay()->setTimeFromTimeString($config->business_start_time);
                continue;
            }
            return $cursor;
        }
    }

    private function getConfigForLead(?Lead $lead): ?NewLeadSlaAutomationConfig
    {
        if (!$lead) {
            return null;
        }

        $source = Lead::normalizeSource($lead->source);
        $query = NewLeadSlaAutomationConfig::query()
            ->with(['fbForm:id,form_name,form_id', 'poolUsers.user.role', 'recipients.user.role'])
            ->where('source', $source)
            ->where('is_active', true);

        if ($source !== 'meta') {
            return $query->whereNull('fb_form_id')->first();
        }

        $fbFormId = $this->resolveMetaFormIdForLead($lead);
        if (!$fbFormId) {
            return null;
        }

        return $query->where('fb_form_id', $fbFormId)->first();
    }

    private function resolveMetaFormIdForLead(Lead $lead): ?int
    {
        return FbLead::query()
            ->where('crm_lead_id', $lead->id)
            ->value('fb_form_id');
    }

    private function getAttemptedUserIds(NewLeadSlaAutomationState $state): array
    {
        return $state->audits->pluck('to_user_id')->merge($state->audits->pluck('from_user_id'))->filter()->map(fn ($id) => (int) $id)->push((int) $state->current_assigned_to)->unique()->values()->all();
    }

    private function buildAttemptTrail(NewLeadSlaAutomationState $state): array
    {
        return $state->audits()->with(['fromUser:id,name', 'toUser:id,name'])->whereIn('action', ['assigned', 'reassigned', 'sla_missed'])->orderBy('acted_at')->get()->map(function (NewLeadSlaAutomationAudit $audit) {
            return [
                'user_name' => $audit->toUser?->name ?? $audit->fromUser?->name ?? 'Unknown',
                'assigned_at' => optional($audit->acted_at)->format('d M Y h:i A'),
                'deadline_at' => data_get($audit->meta, 'new_deadline_at') ?? data_get($audit->meta, 'sla_deadline_at'),
                'status' => ucfirst(str_replace('_', ' ', $audit->action)),
            ];
        })->values()->all();
    }

    private function mapConfigPayload(array $data, int $userId, bool $isCreate): array
    {
        $payload = [
            'name' => $data['name'] ?? null,
            'source' => Lead::normalizeSource($data['source']),
            'fb_form_id' => Lead::normalizeSource($data['source']) === 'meta' ? (int) $data['fb_form_id'] : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sla_minutes' => (int) $data['sla_minutes'],
            'business_start_time' => $data['business_start_time'],
            'business_end_time' => $data['business_end_time'],
            'weekends_off' => (bool) ($data['weekends_off'] ?? false),
            'max_transfer_attempts' => (int) $data['max_transfer_attempts'],
            'email_enabled' => (bool) ($data['email_enabled'] ?? false),
            'in_app_enabled' => (bool) ($data['in_app_enabled'] ?? false),
            'dashboard_alert_enabled' => (bool) ($data['dashboard_alert_enabled'] ?? false),
            'skip_inactive_users' => (bool) ($data['skip_inactive_users'] ?? false),
            'skip_absent_users' => (bool) ($data['skip_absent_users'] ?? false),
            'updated_by' => $userId,
        ];

        if ($isCreate) {
            $payload['created_by'] = $userId;
        }

        return $payload;
    }

    private function syncPoolUsers(NewLeadSlaAutomationConfig $config, array $userIds): void
    {
        $config->poolUsers()->delete();
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $index => $userId) {
            $config->poolUsers()->create(['user_id' => $userId, 'is_active' => true, 'sort_order' => $index]);
        }
    }

    private function syncRecipients(NewLeadSlaAutomationConfig $config, array $userIds): void
    {
        $config->recipients()->delete();
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            $config->recipients()->create(['user_id' => $userId, 'is_active' => true]);
        }
    }

    private function createAudit(NewLeadSlaAutomationState $state, string $action, array $payload = []): NewLeadSlaAutomationAudit
    {
        return NewLeadSlaAutomationAudit::create([
            'state_id' => $state->id,
            'lead_id' => $state->lead_id,
            'config_id' => $state->config_id,
            'from_user_id' => $payload['from_user_id'] ?? null,
            'to_user_id' => $payload['to_user_id'] ?? null,
            'assignment_id' => $payload['assignment_id'] ?? null,
            'task_model' => $payload['task_model'] ?? null,
            'task_id' => $payload['task_id'] ?? null,
            'action' => $action,
            'message' => $payload['message'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'acted_at' => now(),
        ]);
    }

    private function escalateUnassignedLead(Lead $lead, NewLeadSlaAutomationConfig $config, string $message): void
    {
        $state = NewLeadSlaAutomationState::firstOrCreate(
            ['lead_id' => $lead->id, 'config_id' => $config->id],
            [
                'status' => 'escalated',
                'attempt_number' => 0,
                'sla_started_at' => now(),
                'escalated_at' => now(),
            ]
        );

        $state->update([
            'status' => 'escalated',
            'escalated_at' => now(),
            'cancelled_at' => null,
            'cancel_reason' => null,
        ]);

        $this->createAudit($state, 'escalated', [
            'message' => $message,
            'meta' => ['trail' => $this->buildAttemptTrail($state)],
        ]);

        $this->dispatchEscalationNotifications($state->fresh(['lead', 'config.recipients.user.role']), $this->buildAttemptTrail($state));
    }
}
