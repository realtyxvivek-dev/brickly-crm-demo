<?php

namespace App\Services;

use App\Models\AsmCnpAutomationAudit;
use App\Models\AsmCnpAutomationConfig;
use App\Models\AsmCnpAutomationLeadHistory;
use App\Models\AsmCnpAutomationState;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AsmCnpAutomationService
{
    public const STAGE_FRESH_LEAD = 'fresh_lead';
    public const STAGE_FOLLOW_UP = 'follow_up';
    public const STAGE_MEETING = 'meeting';
    public const STAGE_SITE_VISIT = 'site_visit';
    public const STAGE_REVISIT = 'revisit';
    public const STAGE_CLOSER_FOLLOWUP = 'closer_followup';

    public function getConfig(): AsmCnpAutomationConfig
    {
        return AsmCnpAutomationConfig::query()
            ->with(['poolUsers.user.role', 'overrides'])
            ->firstOrFail();
    }

    public function isFreshLeadTask(Task $task): bool
    {
        return $this->isStageCnpTask($task);
    }

    public function isStageCnpTask(Task $task): bool
    {
        if ($task->type !== 'phone_call') {
            return false;
        }

        $task->loadMissing('lead.prospects', 'lead.meetings', 'lead.siteVisits', 'lead.followUps', 'siteVisit');
        $lead = $task->lead;

        if (!$lead) {
            return false;
        }

        if (!$lead->activeAssignments()
            ->where('assigned_to', $task->assigned_to)
            ->exists()) {
            return false;
        }

        $stage = $this->resolveTaskStage($task);

        if ($stage['stage'] === self::STAGE_FRESH_LEAD && $this->leadHasProgressed($lead)) {
            return false;
        }

        return true;
    }

    public function handleFreshLeadCnp(Task $task, User $user, ?Carbon $requestedRetryAt = null): array
    {
        return $this->handleStageCnp($task, $user, $requestedRetryAt);
    }

    public function handleStageCnp(Task $task, User $user, ?Carbon $requestedRetryAt = null): array
    {
        if (!$this->isStageCnpTask($task)) {
            throw new \RuntimeException('Stage CNP automation is not applicable to this task.');
        }

        $config = $this->getConfig();
        if (!$config->is_enabled) {
            throw new \RuntimeException('ASM CNP retry flow is disabled.');
        }

        $task->loadMissing('lead.activeAssignments');
        $lead = $task->lead;
        $stage = $this->resolveTaskStage($task);

        return DB::transaction(function () use ($config, $task, $user, $lead, $requestedRetryAt, $stage) {
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            app(LeadReopenService::class)->assertCurrentRecord($task);
            $assignment = $lead->activeAssignments()
                ->where('assigned_to', $user->id)
                ->latest('assigned_at')
                ->first();

            if (!$assignment) {
                throw new \RuntimeException('Active lead assignment not found for this user.');
            }

            $state = AsmCnpAutomationState::query()
                ->where('lead_id', $lead->id)
                ->where('lead_assignment_id', $assignment->id)
                ->where('current_assigned_to', $user->id)
                ->where('stage', $stage['stage'])
                ->where(function ($query) use ($stage) {
                    if ($stage['stage_record_id']) {
                        $query->where('stage_record_id', $stage['stage_record_id']);
                    } else {
                        $query->whereNull('stage_record_id');
                    }
                })
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if (!$state) {
                $seededCnpCount = $this->inferCompletedCnpCountForAssignment(
                    $lead->id,
                    $user->id,
                    $stage['stage'],
                    $stage['stage_record_id']
                );

                $state = AsmCnpAutomationState::create([
                    'lead_id' => $lead->id,
                    'lead_assignment_id' => $assignment->id,
                    'config_id' => $config->id,
                    'original_assigned_to' => $user->id,
                    'current_assigned_to' => $user->id,
                    'stage' => $stage['stage'],
                    'stage_record_id' => $stage['stage_record_id'],
                    'cnp_count' => $seededCnpCount,
                    'assignment_started_at' => $assignment->assigned_at ?? $assignment->created_at ?? now(),
                    'stage_started_at' => now(),
                    'status' => 'active',
                    'transfer_eligible' => true,
                ]);

                if ($seededCnpCount > 0) {
                    $this->createAudit($state, 'state_seeded', [
                        'from_user_id' => $user->id,
                        'message' => 'CNP automation state seeded from existing completed CNP task history.',
                        'meta' => [
                            'seeded_cnp_count' => $seededCnpCount,
                            'stage' => $stage['stage'],
                            'stage_record_id' => $stage['stage_record_id'],
                        ],
                    ]);
                }
            }

            $now = now();
            $maxAttempts = max(1, (int) $config->max_cnp_attempts);
            $state = $this->prepareStateWindow($state, $config, $now);
            $cnpCount = (int) $state->cnp_count + 1;
            $retryTask = null;
            $shouldTransferNow = $config->is_active
                && $this->shouldTransferOnAttempt($config, $cnpCount, $maxAttempts);
            $shouldCreateRetryTask = $config->create_retry_tasks && !$shouldTransferNow;

            $taskNote = 'Call not picked (auto CNP) handled on ' . $now->format('Y-m-d H:i:s');
            $task->update([
                'status' => 'cancelled',
                'notes' => trim(($task->notes ?? '') . PHP_EOL . $taskNote),
                'description' => trim(($task->description ?? '') . PHP_EOL . $taskNote),
                'outcome' => 'cnp',
                'outcome_recorded_at' => $now,
            ]);

            $nextRetryAt = null;
            if ($shouldCreateRetryTask) {
                $nextRetryAt = $requestedRetryAt
                    ? $requestedRetryAt->copy()
                    : $now->copy()->addMinutes((int) $config->retry_delay_minutes);
                $retryTask = $this->findExistingPendingTask($lead->id, $user->id, $nextRetryAt, $stage['stage'], $stage['stage_record_id'])
                    ?? Task::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $user->id,
                        'type' => 'phone_call',
                        'title' => $this->stageTaskTitle($stage['stage'], $lead->name),
                        'description' => 'Auto-created CNP retry task for ' . $this->stageLabel($stage['stage']) . ' after attempt #' . $cnpCount,
                        'status' => 'pending',
                        'scheduled_at' => $nextRetryAt,
                        'created_by' => $user->id,
                        'notes' => 'ASM ' . $this->stageLabel($stage['stage']) . ' CNP automation retry task',
                    ] + $this->stageTaskColumns($stage));
            }

            $state->update([
                'config_id' => $config->id,
                'current_assigned_to' => $user->id,
                'stage' => $stage['stage'],
                'stage_record_id' => $stage['stage_record_id'],
                'stage_started_at' => $state->stage_started_at ?? $now,
                'last_retry_task_id' => $retryTask?->id,
                'cnp_count' => $cnpCount,
                'first_cnp_at' => $state->first_cnp_at ?? $now,
                'last_cnp_at' => $now,
                'next_retry_at' => $nextRetryAt,
                'eligible_for_transfer_at' => $shouldTransferNow ? $now : null,
                    'transfer_eligible' => $shouldTransferNow,
                'status' => 'active',
                'cancel_reason' => null,
                'cancelled_at' => null,
                'transferred_at' => null,
                'last_processed_at' => $now,
            ]);

            $this->createAudit($state, 'retry_created', [
                'from_user_id' => $user->id,
                'task_id' => $retryTask?->id,
                'message' => $retryTask
                    ? 'Auto retry task created after CNP #' . $cnpCount
                    : ($shouldTransferNow
                        ? 'Retry cap reached after CNP #' . $cnpCount . ', immediate transfer started.'
                        : 'Retry recorded after CNP #' . $cnpCount . ' without creating a new task.'),
                'meta' => [
                    'retry_delay_minutes' => (int) $config->retry_delay_minutes,
                    'create_retry_tasks' => (bool) $config->create_retry_tasks,
                    'transfer_rule_mode' => $config->transfer_rule_mode,
                    'transfer_window_hours' => $config->transfer_window_hours,
                    'requested_retry_at' => optional($requestedRetryAt)->toDateTimeString(),
                    'eligible_for_transfer_at' => $shouldTransferNow ? $now->toDateTimeString() : null,
                    'stage' => $stage['stage'],
                    'stage_record_id' => $stage['stage_record_id'],
                ],
            ]);

            if ($shouldTransferNow) {
                $this->recordMaxCnpHit($state, $config, $assignment, $user, $cnpCount, $now);

                if ($this->shouldQuarantineLead($lead, $config)) {
                    $quarantine = $this->quarantineStateImmediately($state->fresh([
                        'lead.activeAssignments',
                        'lead.prospects',
                        'lead.meetings',
                        'lead.siteVisits',
                        'lead.followUps',
                        'currentAssignee.role',
                    ]), $config, $user->id);

                    return [
                        'state' => $quarantine['state'],
                        'retry_task' => null,
                        'message' => $quarantine['message'],
                    ];
                }

                $transfer = $this->transferStateImmediately($state->fresh([
                    'lead.activeAssignments',
                    'lead.prospects',
                    'lead.meetings',
                    'lead.siteVisits',
                    'lead.followUps',
                    'currentAssignee.role',
                    'config.overrides',
                    'config.poolUsers.user.role',
                ]), $config);

                return [
                    'state' => $transfer['state'],
                    'retry_task' => $transfer['task'],
                    'message' => $transfer['message'],
                ];
            }

            return [
                'state' => $state->fresh(),
                'retry_task' => $retryTask,
                'message' => $retryTask
                    ? 'Call Not Picked marked. Retry task has been auto-created.'
                    : 'Call Not Picked marked. Retry saved without creating a new task.',
            ];
        });
    }

    public function cancelLeadAutomation(?Lead $lead, string $reason): void
    {
        if (!$lead) {
            return;
        }

        $states = AsmCnpAutomationState::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'active')
            ->get();

        foreach ($states as $state) {
            $state->update([
                'status' => 'cancelled',
                'transfer_eligible' => false,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'last_processed_at' => now(),
            ]);

            $this->createAudit($state, 'cancelled', [
                'from_user_id' => $state->current_assigned_to,
                'message' => $reason,
            ]);
        }
    }

    public function cancelLeadAutomationStage(?Lead $lead, string $stage, ?int $stageRecordId, string $reason): void
    {
        if (!$lead) {
            return;
        }

        $states = AsmCnpAutomationState::query()
            ->where('lead_id', $lead->id)
            ->where('stage', $stage)
            ->where(function ($query) use ($stageRecordId) {
                if ($stageRecordId) {
                    $query->where('stage_record_id', $stageRecordId);
                } else {
                    $query->whereNull('stage_record_id');
                }
            })
            ->where('status', 'active')
            ->get();

        foreach ($states as $state) {
            $this->resetState($state, $reason);
        }
    }

    public function cancelTaskStageAutomation(Task $task, string $reason): void
    {
        $task->loadMissing('lead', 'siteVisit');
        $stage = $this->resolveTaskStage($task);

        $this->cancelLeadAutomationStage($task->lead, $stage['stage'], $stage['stage_record_id'], $reason);
    }

    public function processDueTransfers(): array
    {
        $config = $this->getConfig();
        if (!$config->is_enabled || !$config->is_active) {
            return ['processed' => 0, 'transferred' => 0, 'quarantined' => 0, 'cancelled' => 0, 'skipped' => 0];
        }

        $states = AsmCnpAutomationState::query()
            ->with(['lead.activeAssignments', 'lead.prospects', 'leadAssignment', 'currentAssignee.role', 'config.overrides', 'config.poolUsers.user.role'])
            ->where('status', 'active')
            ->where('transfer_eligible', true)
            ->get();

        $stats = ['processed' => 0, 'transferred' => 0, 'quarantined' => 0, 'cancelled' => 0, 'skipped' => 0];

        foreach ($states as $state) {
            $stats['processed']++;
            $lead = $state->lead;
            $state = $this->prepareStateWindow($state, $config, now());

            if (!$lead || $this->leadStageIncompatible($lead, $state->stage ?? self::STAGE_FRESH_LEAD, $state)) {
                $this->cancelState($state, 'Lead moved out of ' . $this->stageLabel($state->stage ?? self::STAGE_FRESH_LEAD) . ' CNP flow.');
                $stats['cancelled']++;
                continue;
            }

            if (!$this->hasReachedTransferLimit($state, $config)) {
                $stats['skipped']++;
                continue;
            }

            $activeAssignment = $lead->activeAssignments()
                ->where('assigned_to', $state->current_assigned_to)
                ->latest('assigned_at')
                ->first();

            if (!$activeAssignment || ($state->lead_assignment_id && (int) $activeAssignment->id !== (int) $state->lead_assignment_id)) {
                $this->cancelState($state, 'Lead assignment changed before CNP transfer.');
                $stats['cancelled']++;
                continue;
            }

            $this->recordMaxCnpHit($state, $config, $activeAssignment, $state->currentAssignee, (int) $state->cnp_count, now());

            if ($this->shouldQuarantineLead($lead, $config)) {
                $quarantine = $this->quarantineStateImmediately($state, $config, $state->current_assigned_to);
                if ($quarantine['quarantined']) {
                    $stats['quarantined']++;
                } elseif ($quarantine['cancelled']) {
                    $stats['cancelled']++;
                } else {
                    $stats['skipped']++;
                }
                continue;
            }

            $transfer = $this->transferStateImmediately($state, $config);
            if ($transfer['transferred']) {
                $stats['transferred']++;
            } elseif ($transfer['cancelled']) {
                $stats['cancelled']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    protected function transferStateImmediately(AsmCnpAutomationState $state, AsmCnpAutomationConfig $config): array
    {
        $lead = $state->lead;

        if (!$lead || $this->leadStageIncompatible($lead, $state->stage ?? self::STAGE_FRESH_LEAD, $state)) {
            $this->cancelState($state, 'Lead moved out of ' . $this->stageLabel($state->stage ?? self::STAGE_FRESH_LEAD) . ' CNP flow.');

            return [
                'transferred' => false,
                'cancelled' => true,
                'state' => $state->fresh(),
                'task' => null,
                'message' => 'Lead moved out of stage CNP flow before transfer.',
            ];
        }

        $activeAssignment = $lead->activeAssignments()
            ->where('assigned_to', $state->current_assigned_to)
            ->latest('assigned_at')
            ->first();

        if (!$activeAssignment || ($state->lead_assignment_id && (int) $activeAssignment->id !== (int) $state->lead_assignment_id)) {
            $this->cancelState($state, 'Lead assignment changed before CNP transfer.');

            return [
                'transferred' => false,
                'cancelled' => true,
                'state' => $state->fresh(),
                'task' => null,
                'message' => 'Lead assignment changed before transfer.',
            ];
        }

        $targetUser = $this->resolveTransferTarget($config, $state->current_assigned_to);
        if (!$targetUser) {
            $this->markSkipped($state, 'No valid CNP transfer target available.');

            return [
                'transferred' => false,
                'cancelled' => false,
                'state' => $state->fresh(),
                'task' => null,
                'message' => 'No valid transfer target available.',
            ];
        }

        $newTask = null;
        DB::transaction(function () use ($state, $lead, $activeAssignment, $targetUser, $config, &$newTask) {
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_unless($activeAssignment->fresh()?->is_active && $state->fresh()?->status === 'active', 409, 'Lead assignment changed before automation.');
            $activeAssignment->update([
                'is_active' => false,
                'unassigned_at' => now(),
                'notes' => trim(($activeAssignment->notes ?? '') . PHP_EOL . 'Auto transferred by ASM CNP automation'),
            ]);

            $newAssignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $targetUser->id,
                'assigned_by' => auth()->id() ?? $config->updated_by ?? $config->created_by ?? 1,
                'assignment_type' => 'primary',
                'assignment_method' => 'cnp_auto_transfer',
                'notes' => 'Auto-transferred after max fresh-lead CNP attempts.',
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            $lead->markAsFreshTransfer($activeAssignment->assigned_to, $targetUser->id, auth()->id() ?? $config->updated_by ?? $config->created_by, 'Auto-transferred after max fresh-lead CNP attempts.');

            $this->cancelOpenStageTasks($lead->id, $activeAssignment->assigned_to, $state->stage ?? self::STAGE_FRESH_LEAD, $state->stage_record_id, 'Cancelled due to CNP stage transfer.');

            $newTask = $this->findExistingPendingTask($lead->id, $targetUser->id, null, $state->stage ?? self::STAGE_FRESH_LEAD, $state->stage_record_id)
                ?? Task::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $targetUser->id,
                    'type' => 'phone_call',
                    'title' => 'Fresh transfer ' . $this->stageLabel($state->stage ?? self::STAGE_FRESH_LEAD) . ' call: ' . $lead->name,
                    'description' => 'Fresh transfer ' . $this->stageLabel($state->stage ?? self::STAGE_FRESH_LEAD) . ' handoff. Contact this lead as the new owner.',
                    'status' => 'pending',
                    'scheduled_at' => now(),
                    'created_by' => $config->updated_by ?? $config->created_by ?? $targetUser->id,
                    'notes' => 'Fresh transfer ' . $this->stageLabel($state->stage ?? self::STAGE_FRESH_LEAD) . ' handoff. Contact this lead as a new owner.',
                ] + $this->stageTaskColumns([
                    'stage' => $state->stage ?? self::STAGE_FRESH_LEAD,
                    'stage_record_id' => $state->stage_record_id,
                ]));

            $state->update([
                'lead_assignment_id' => $newAssignment->id,
                'current_assigned_to' => $targetUser->id,
                'last_retry_task_id' => $newTask->id,
                'status' => 'transferred',
                'transfer_eligible' => false,
                'transferred_at' => now(),
                'last_processed_at' => now(),
            ]);

            $this->createAudit($state, 'transferred', [
                'from_user_id' => $activeAssignment->assigned_to,
                'to_user_id' => $targetUser->id,
                'task_id' => $newTask->id,
                'message' => 'Lead auto-transferred after max fresh-lead CNP attempts.',
                'meta' => [
                    'new_assignment_id' => $newAssignment->id,
                    'routing' => $config->fallback_routing,
                ],
            ]);
        });

        return [
            'transferred' => true,
            'cancelled' => false,
            'state' => $state->fresh(),
            'task' => $newTask,
            'message' => 'Call Not Picked limit reached. Lead auto-transferred to the next ASM.',
        ];
    }

    protected function quarantineStateImmediately(AsmCnpAutomationState $state, AsmCnpAutomationConfig $config, ?int $actorUserId = null): array
    {
        $lead = $state->lead;

        if (!$lead || $this->leadStageIncompatible($lead, $state->stage ?? self::STAGE_FRESH_LEAD)) {
            $this->cancelState($state, 'Lead moved out of ' . $this->stageLabel($state->stage ?? self::STAGE_FRESH_LEAD) . ' CNP flow before quarantine.');

            return [
                'quarantined' => false,
                'cancelled' => true,
                'state' => $state->fresh(),
                'message' => 'Lead moved out of stage CNP flow before quarantine.',
            ];
        }

        $activeAssignment = $lead->activeAssignments()
            ->where('assigned_to', $state->current_assigned_to)
            ->latest('assigned_at')
            ->first();

        if (!$activeAssignment || ($state->lead_assignment_id && (int) $activeAssignment->id !== (int) $state->lead_assignment_id)) {
            $this->cancelState($state, 'Lead assignment changed before CNP quarantine.');

            return [
                'quarantined' => false,
                'cancelled' => true,
                'state' => $state->fresh(),
                'message' => 'Lead assignment changed before quarantine.',
            ];
        }

        DB::transaction(function () use ($state, $lead, $activeAssignment, $config, $actorUserId) {
            Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_unless($activeAssignment->fresh()?->is_active && $state->fresh()?->status === 'active', 409, 'Lead assignment changed before automation.');
            $reason = sprintf(
                'CNP quarantine: %d unique user(s) reached max CNP limit.',
                $this->maxHitUserCount($lead->id)
            );

            $activeAssignment->update([
                'is_active' => false,
                'unassigned_at' => now(),
                'notes' => trim(($activeAssignment->notes ?? '') . PHP_EOL . $reason),
            ]);

            Task::query()
                ->where('lead_id', $lead->id)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
                ->get()
                ->each(function (Task $task) use ($reason) {
                    $task->update([
                        'status' => 'cancelled',
                        'notes' => trim(($task->notes ?? '') . PHP_EOL . $reason),
                    ]);
                });

            $lead->forceFill([
                'cnp_quarantined_at' => now(),
                'cnp_quarantined_by' => $actorUserId ?? $config->updated_by ?? $config->created_by,
                'cnp_quarantine_reason' => $reason,
                'cnp_quarantine_cleared_at' => null,
                'cnp_quarantine_cleared_by' => null,
            ])->save();

            $state->update([
                'status' => 'quarantined',
                'transfer_eligible' => false,
                'quarantined_at' => now(),
                'last_processed_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->createAudit($state, 'quarantined', [
                'from_user_id' => $activeAssignment->assigned_to,
                'message' => 'CNP limit reached across multiple users. Lead moved to quarantine.',
                'meta' => [
                    'quarantine_after_unique_users' => (int) $config->quarantine_after_unique_users,
                    'quarantine_action' => $config->quarantine_action ?: 'unassign',
                    'unique_max_cnp_users' => $this->maxHitUserCount($lead->id),
                ],
            ]);
        });

        return [
            'quarantined' => true,
            'cancelled' => false,
            'state' => $state->fresh(),
            'message' => 'CNP limit reached across multiple users. Lead moved to quarantine.',
        ];
    }

    protected function cancelState(AsmCnpAutomationState $state, string $reason): void
    {
        $state->update([
            'status' => 'cancelled',
            'transfer_eligible' => false,
            'cancel_reason' => $reason,
            'reset_reason' => $reason,
            'reset_at' => now(),
            'cancelled_at' => now(),
            'last_processed_at' => now(),
        ]);

        $this->createAudit($state, 'cancelled', [
            'from_user_id' => $state->current_assigned_to,
            'message' => $reason,
        ]);
    }

    protected function resetState(AsmCnpAutomationState $state, string $reason): void
    {
        $state->update([
            'status' => 'cancelled',
            'transfer_eligible' => false,
            'cancel_reason' => $reason,
            'reset_reason' => $reason,
            'reset_at' => now(),
            'cancelled_at' => now(),
            'last_processed_at' => now(),
        ]);

        $this->createAudit($state->fresh(), 'stage_reset', [
            'from_user_id' => $state->current_assigned_to,
            'message' => $reason,
        ]);
    }

    protected function markSkipped(AsmCnpAutomationState $state, string $reason): void
    {
        $state->update([
            'status' => 'skipped',
            'transfer_eligible' => false,
            'cancel_reason' => $reason,
            'last_processed_at' => now(),
        ]);

        $this->createAudit($state, 'skipped', [
            'from_user_id' => $state->current_assigned_to,
            'message' => $reason,
        ]);
    }

    protected function recordMaxCnpHit(AsmCnpAutomationState $state, AsmCnpAutomationConfig $config, LeadAssignment $assignment, ?User $user, int $cnpCount, Carbon $now): void
    {
        if (!$user) {
            return;
        }

        $history = AsmCnpAutomationLeadHistory::query()->firstOrNew([
            'lead_id' => $state->lead_id,
            'user_id' => $user->id,
        ]);

        $history->fill([
            'config_id' => $config->id,
            'state_id' => $state->id,
            'lead_assignment_id' => $assignment->id,
            'completed_cnp_count' => max((int) ($history->completed_cnp_count ?? 0), $cnpCount),
            'max_hit_at' => $history->max_hit_at ?: $now,
        ]);

        $history->save();
    }

    protected function shouldQuarantineLead(Lead $lead, AsmCnpAutomationConfig $config): bool
    {
        if (!$config->quarantine_enabled) {
            return false;
        }

        if ($lead->cnp_quarantined_at && !$lead->cnp_quarantine_cleared_at) {
            return true;
        }

        return $this->maxHitUserCount($lead->id) >= max(1, (int) $config->quarantine_after_unique_users);
    }

    protected function maxHitUserCount(int $leadId): int
    {
        return AsmCnpAutomationLeadHistory::query()
            ->where('lead_id', $leadId)
            ->whereNotNull('max_hit_at')
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function resolveTransferTarget(AsmCnpAutomationConfig $config, int $fromUserId): ?User
    {
        $override = $config->overrides
            ->first(fn ($item) => $item->is_active && (int) $item->from_user_id === $fromUserId);

        if ($override) {
            $target = User::query()->with('role')
                ->whereKey($override->to_user_id)
                ->where('is_active', true)
                ->first();

            if ($target && $target->id !== $fromUserId && $target->isAssistantSalesManager()) {
                return $target;
            }
        }

        $poolUsers = $config->poolUsers
            ->filter(function ($poolUser) use ($fromUserId) {
                return $poolUser->is_active
                    && $poolUser->user
                    && $poolUser->user->is_active
                    && $poolUser->user->id !== $fromUserId
                    && $poolUser->user->isAssistantSalesManager();
            })
            ->values();

        if ($poolUsers->isEmpty()) {
            return null;
        }

        $lastUserId = $config->last_round_robin_user_id;
        $next = $this->pickRoundRobinUser($poolUsers, $lastUserId);

        if ($next) {
            $config->update(['last_round_robin_user_id' => $next->id]);
        }

        return $next;
    }

    protected function pickRoundRobinUser(Collection $poolUsers, ?int $lastUserId): ?User
    {
        if ($poolUsers->isEmpty()) {
            return null;
        }

        if (!$lastUserId) {
            return $poolUsers->first()->user;
        }

        $index = $poolUsers->search(fn ($item) => (int) $item->user_id === (int) $lastUserId);
        if ($index === false) {
            return $poolUsers->first()->user;
        }

        $nextIndex = ($index + 1) % $poolUsers->count();
        return $poolUsers->get($nextIndex)?->user;
    }

    protected function findExistingPendingTask(int $leadId, int $userId, ?Carbon $scheduledAt = null, ?string $stage = null, ?int $stageRecordId = null): ?Task
    {
        $query = Task::query()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $userId)
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress']);

        if ($scheduledAt) {
            $query->whereBetween('scheduled_at', [
                $scheduledAt->copy()->subMinutes(1),
                $scheduledAt->copy()->addMinutes(1),
            ]);
        }

        $tasks = $query->latest('id')->get();

        if (!$stage) {
            return $tasks->first();
        }

        return $tasks->first(function (Task $task) use ($stage, $stageRecordId) {
            $taskStage = $this->resolveTaskStage($task);

            return $taskStage['stage'] === $stage
                && (int) ($taskStage['stage_record_id'] ?? 0) === (int) ($stageRecordId ?? 0);
        });
    }

    public function resolveTaskStage(Task $task): array
    {
        $task->loadMissing('siteVisit');

        if (Task::supportsColumn('follow_up_id') && $task->follow_up_id) {
            return ['stage' => self::STAGE_FOLLOW_UP, 'stage_record_id' => (int) $task->follow_up_id];
        }

        if (Task::supportsColumn('meeting_id') && $task->meeting_id) {
            return ['stage' => self::STAGE_MEETING, 'stage_record_id' => (int) $task->meeting_id];
        }

        if (Task::supportsColumn('site_visit_id') && $task->site_visit_id) {
            $siteVisit = $task->siteVisit;
            $stage = str_contains(strtolower((string) ($siteVisit?->lead_type ?? '')), 'revisit')
                ? self::STAGE_REVISIT
                : self::STAGE_SITE_VISIT;

            return ['stage' => $stage, 'stage_record_id' => (int) $task->site_visit_id];
        }

        $text = strtolower(trim(implode(' ', array_filter([
            $task->title,
            $task->description,
            $task->notes,
            $task->outcome,
        ]))));

        if (str_contains($text, 'closer')) {
            return ['stage' => self::STAGE_CLOSER_FOLLOWUP, 'stage_record_id' => null];
        }

        if (str_contains($text, 'revisit')) {
            return ['stage' => self::STAGE_REVISIT, 'stage_record_id' => null];
        }

        if (str_contains($text, 'site visit') || str_contains($text, 'site-visit') || str_contains($text, 'visit')) {
            $siteVisit = $this->inferTaskSiteVisit($task);
            if (!$siteVisit) {
                if (str_contains($text, 'follow')) {
                    return ['stage' => self::STAGE_FOLLOW_UP, 'stage_record_id' => null];
                }

                return ['stage' => self::STAGE_FRESH_LEAD, 'stage_record_id' => null];
            }

            return ['stage' => self::STAGE_SITE_VISIT, 'stage_record_id' => $siteVisit->id];
        }

        if (str_contains($text, 'meeting')) {
            return ['stage' => self::STAGE_MEETING, 'stage_record_id' => null];
        }

        if (str_contains($text, 'follow')) {
            return ['stage' => self::STAGE_FOLLOW_UP, 'stage_record_id' => null];
        }

        return ['stage' => self::STAGE_FRESH_LEAD, 'stage_record_id' => null];
    }

    protected function inferTaskSiteVisit(Task $task): ?SiteVisit
    {
        if (!$task->lead_id) {
            return null;
        }

        $query = SiteVisit::query()
            ->where('lead_id', $task->lead_id)
            ->whereNotIn('status', ['cancelled', 'canceled', 'dead']);

        if ($task->scheduled_at) {
            $scheduledAt = Carbon::parse($task->scheduled_at);
            $nearestVisit = (clone $query)
                ->whereBetween('scheduled_at', [
                    $scheduledAt->copy()->subHours(24),
                    $scheduledAt->copy()->addHours(24),
                ])
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, scheduled_at, ?))', [$scheduledAt->toDateTimeString()])
                ->latest('id')
                ->first();

            if ($nearestVisit) {
                return $nearestVisit;
            }
        }

        return $query
            ->orderByRaw("CASE status WHEN 'scheduled' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->latest('id')
            ->first();
    }

    protected function stageTaskColumns(array $stage): array
    {
        $columns = [];

        if ($stage['stage'] === self::STAGE_FOLLOW_UP && Task::supportsColumn('follow_up_id') && $stage['stage_record_id']) {
            $columns['follow_up_id'] = $stage['stage_record_id'];
        }

        if ($stage['stage'] === self::STAGE_MEETING && Task::supportsColumn('meeting_id') && $stage['stage_record_id']) {
            $columns['meeting_id'] = $stage['stage_record_id'];
        }

        if (in_array($stage['stage'], [self::STAGE_SITE_VISIT, self::STAGE_REVISIT, self::STAGE_CLOSER_FOLLOWUP], true)
            && Task::supportsColumn('site_visit_id')
            && $stage['stage_record_id']) {
            $columns['site_visit_id'] = $stage['stage_record_id'];
        }

        return $columns;
    }

    protected function stageTaskTitle(string $stage, string $leadName): string
    {
        return 'CNP retry ' . $this->stageLabel($stage) . ' call: ' . $leadName;
    }

    protected function stageLabel(string $stage): string
    {
        return str_replace('_', ' ', $stage ?: self::STAGE_FRESH_LEAD);
    }

    protected function leadStageIncompatible(Lead $lead, string $stage, ?AsmCnpAutomationState $state = null): bool
    {
        if ($lead->is_dead) {
            return true;
        }

        if ($lead->cnp_quarantined_at && !$lead->cnp_quarantine_cleared_at) {
            return true;
        }

        if ($stage !== self::STAGE_FRESH_LEAD) {
            return false;
        }

        if (!$this->leadHasProgressed($lead)) {
            return false;
        }

        if (
            $state
            && $lead->status === 'junk'
            && $this->stateHasOpenCnpRetryTask($state)
        ) {
            return false;
        }

        return true;
    }

    protected function stateHasOpenCnpRetryTask(AsmCnpAutomationState $state): bool
    {
        if (!$state->last_retry_task_id) {
            return false;
        }

        return Task::query()
            ->whereKey($state->last_retry_task_id)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->where(function ($query) {
                $query->where('notes', 'like', '%CNP retry task created%')
                    ->orWhere('notes', 'like', '%CNP automation retry task%')
                    ->orWhere('title', 'like', '%CNP rescheduled%')
                    ->orWhere('title', 'like', '%CNP retry%');
            })
            ->exists();
    }

    protected function cancelOpenStageTasks(int $leadId, int $userId, string $stage, ?int $stageRecordId, string $reason): void
    {
        Task::query()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $userId)
            ->where('type', 'phone_call')
            ->whereIn('status', Task::OPEN_STATUSES)
            ->get()
            ->filter(function (Task $task) use ($stage, $stageRecordId) {
                $taskStage = $this->resolveTaskStage($task);

                return $taskStage['stage'] === $stage
                    && (int) ($taskStage['stage_record_id'] ?? 0) === (int) ($stageRecordId ?? 0);
            })
            ->each(function (Task $task) use ($reason) {
                $task->update([
                    'status' => 'cancelled',
                    'notes' => trim(($task->notes ?? '') . PHP_EOL . $reason),
                ]);
            });
    }

    protected function leadHasProgressed(Lead $lead): bool
    {
        $lead->loadMissing(['prospects', 'meetings', 'siteVisits', 'followUps']);

        if ($lead->is_dead) {
            return true;
        }

        if ($lead->cnp_quarantined_at && !$lead->cnp_quarantine_cleared_at) {
            return true;
        }

        if ($lead->prospects->isNotEmpty()) {
            return true;
        }

        if ($lead->meetings->isNotEmpty() || $lead->siteVisits->isNotEmpty() || $lead->followUps->isNotEmpty()) {
            return true;
        }

        return in_array($lead->status, [
            'verified_prospect',
            'meeting_scheduled',
            'meeting_completed',
            'visit_scheduled',
            'visit_done',
            'revisited_scheduled',
            'revisited_completed',
            'follow_up',
            'closed',
            'dead',
            'not_interested',
            'on_hold',
        ], true);
    }

    protected function prepareStateWindow(AsmCnpAutomationState $state, AsmCnpAutomationConfig $config, Carbon $now): AsmCnpAutomationState
    {
        if ($config->transfer_rule_mode === 'count_only' || !$state->first_cnp_at || !$config->transfer_window_hours) {
            return $state;
        }

        $windowExpired = $state->first_cnp_at->copy()->addHours((int) $config->transfer_window_hours)->lte($now);
        if (!$windowExpired) {
            return $state;
        }

        if ($config->transfer_rule_mode === 'count_window_reset') {
            $state->update([
                'cnp_count' => 0,
                'first_cnp_at' => null,
                'last_cnp_at' => null,
                'next_retry_at' => null,
                'eligible_for_transfer_at' => null,
                'last_processed_at' => $now,
            ]);

            $this->createAudit($state->fresh(), 'window_reset', [
                'from_user_id' => $state->current_assigned_to,
                'message' => 'CNP transfer window expired. Counter reset to zero before the next chain.',
                'meta' => [
                    'transfer_rule_mode' => $config->transfer_rule_mode,
                    'transfer_window_hours' => (int) $config->transfer_window_hours,
                ],
            ]);

            return $state->fresh();
        }

        $state->update([
            'cnp_count' => 0,
            'first_cnp_at' => null,
            'last_cnp_at' => null,
            'next_retry_at' => null,
            'eligible_for_transfer_at' => null,
            'last_processed_at' => $now,
        ]);

        $this->createAudit($state->fresh(), 'window_restart', [
            'from_user_id' => $state->current_assigned_to,
            'message' => 'CNP transfer window expired. A fresh counting window will start from the current attempt.',
            'meta' => [
                'transfer_rule_mode' => $config->transfer_rule_mode,
                'transfer_window_hours' => (int) $config->transfer_window_hours,
            ],
        ]);

        return $state->fresh();
    }

    protected function shouldTransferOnAttempt(AsmCnpAutomationConfig $config, int $cnpCount, int $maxAttempts): bool
    {
        return $cnpCount >= max(1, $maxAttempts);
    }

    protected function inferCompletedCnpCountForAssignment(int $leadId, int $userId, string $stage, ?int $stageRecordId): int
    {
        return Task::query()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $userId)
            ->where('type', 'phone_call')
            ->where('outcome', 'cnp')
            ->whereNotNull('outcome_recorded_at')
            ->get()
            ->filter(function (Task $task) use ($stage, $stageRecordId) {
                $taskStage = $this->resolveTaskStage($task);

                return $taskStage['stage'] === $stage
                    && (int) ($taskStage['stage_record_id'] ?? 0) === (int) ($stageRecordId ?? 0);
            })
            ->count();
    }

    protected function hasReachedTransferLimit(AsmCnpAutomationState $state, AsmCnpAutomationConfig $config): bool
    {
        $maxAttempts = max(1, (int) $config->max_cnp_attempts);

        if (!$this->shouldTransferOnAttempt($config, (int) $state->cnp_count, $maxAttempts)) {
            return false;
        }

        if ($config->transfer_rule_mode === 'count_only') {
            return true;
        }

        if (!$state->first_cnp_at || !$config->transfer_window_hours) {
            return false;
        }

        return $state->first_cnp_at->copy()->addHours((int) $config->transfer_window_hours)->gte(now());
    }

    protected function createAudit(AsmCnpAutomationState $state, string $action, array $payload = []): void
    {
        AsmCnpAutomationAudit::create([
            'state_id' => $state->id,
            'lead_id' => $state->lead_id,
            'config_id' => $state->config_id,
            'from_user_id' => $payload['from_user_id'] ?? null,
            'to_user_id' => $payload['to_user_id'] ?? null,
            'task_id' => $payload['task_id'] ?? null,
            'stage' => $payload['stage'] ?? $state->stage ?? self::STAGE_FRESH_LEAD,
            'stage_record_id' => $payload['stage_record_id'] ?? $state->stage_record_id,
            'cnp_count' => $state->cnp_count,
            'action' => $action,
            'message' => $payload['message'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'acted_at' => now(),
        ]);
    }
}
