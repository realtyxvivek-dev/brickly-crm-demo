<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LeadSingleOpenTaskService
{
    public function findOpenTaskForLead(int $leadId, array $except = []): Task|TelecallerTask|null
    {
        $managerTask = Task::withoutGlobalScopes()
            ->where('lead_id', $leadId)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->when(!empty($except['task']), fn ($query) => $query->whereNotIn('id', $except['task']))
            ->latest('id')
            ->first();

        if ($managerTask) {
            return $managerTask;
        }

        return TelecallerTask::withoutGlobalScopes()
            ->where('lead_id', $leadId)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->when(!empty($except['telecaller_task']), fn ($query) => $query->whereNotIn('id', $except['telecaller_task']))
            ->latest('id')
            ->first();
    }

    public function hasOpenTaskForLead(int $leadId, array $except = []): bool
    {
        return $this->findOpenTaskForLead($leadId, $except) !== null;
    }

    public function ensureNoOpenTaskForLead(int $leadId, array $except = [], string $message = 'Please complete old task first.'): void
    {
        if ($this->hasOpenTaskForLead($leadId, $except)) {
            throw new \RuntimeException($message);
        }
    }

    public function closeOpenTasksForLead(int $leadId, array $except = []): void
    {
        Task::withoutGlobalScopes()
            ->where('lead_id', $leadId)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->when(!empty($except['task']), fn ($query) => $query->whereNotIn('id', $except['task']))
            ->get()
            ->each(function (Task $task): void {
                $task->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            });

        TelecallerTask::withoutGlobalScopes()
            ->where('lead_id', $leadId)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->when(!empty($except['telecaller_task']), fn ($query) => $query->whereNotIn('id', $except['telecaller_task']))
            ->get()
            ->each(function (TelecallerTask $task): void {
                $task->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            });
    }

    public function cancelOpenTasksAfterLeadClosure(
        int $leadId,
        ?int $actorId = null,
        string $reason = 'Auto closed because closer was approved and lead was closed.',
        array $except = []
    ): array {
        $managerTasks = Task::withoutGlobalScopes()
            ->with(['assignedTo', 'creator'])
            ->where('lead_id', $leadId)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->when(!empty($except['task']), fn ($query) => $query->whereNotIn('id', $except['task']))
            ->get();

        $telecallerTasks = TelecallerTask::withoutGlobalScopes()
            ->with(['assignedTo', 'createdBy'])
            ->where('lead_id', $leadId)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->when(!empty($except['telecaller_task']), fn ($query) => $query->whereNotIn('id', $except['telecaller_task']))
            ->get();

        $managerTasks->each(function (Task $task) use ($actorId, $reason): void {
            $this->cancelManagerTaskAfterClosure($task, $actorId, $reason);
        });

        $telecallerTasks->each(function (TelecallerTask $task) use ($actorId, $reason): void {
            $this->cancelTelecallerTaskAfterClosure($task, $actorId, $reason);
        });

        $counts = [
            'manager_tasks' => $managerTasks->count(),
            'telecaller_tasks' => $telecallerTasks->count(),
        ];

        if ($counts['manager_tasks'] > 0 || $counts['telecaller_tasks'] > 0) {
            Log::info('Auto closed lead tasks after closer approval', [
                'lead_id' => $leadId,
                'actor_id' => $actorId,
                'reason' => $reason,
                ...$counts,
            ]);
        }

        return $counts;
    }

    private function cancelManagerTaskAfterClosure(Task $task, ?int $actorId, string $reason): void
    {
        $oldValues = [
            'task_id' => $task->id,
            'task_model' => 'Task',
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'assigned_to_name' => $task->assignedTo?->name,
        ];
        $notes = trim(($task->notes ?? '') . PHP_EOL . $reason);

        $task->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'notes' => $notes !== '' ? $notes : null,
        ]);

        if (Task::supportsQueueArchiving()) {
            $task->archiveForQueue($reason);
        }

        $this->logAutoClosedTask($task->lead_id, $actorId, $reason, $oldValues, [
            'task_id' => $task->id,
            'task_model' => 'Task',
            'status' => 'cancelled',
            'completed_at' => optional($task->completed_at)->toDateTimeString(),
        ]);
    }

    private function cancelTelecallerTaskAfterClosure(TelecallerTask $task, ?int $actorId, string $reason): void
    {
        $oldValues = [
            'task_id' => $task->id,
            'task_model' => 'TelecallerTask',
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'assigned_to_name' => $task->assignedTo?->name,
        ];
        $notes = trim(($task->notes ?? '') . PHP_EOL . $reason);

        $task->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'notes' => $notes !== '' ? $notes : null,
        ]);

        if (TelecallerTask::supportsQueueArchiving()) {
            $task->archiveForQueue($reason);
        }

        $this->logAutoClosedTask($task->lead_id, $actorId, $reason, $oldValues, [
            'task_id' => $task->id,
            'task_model' => 'TelecallerTask',
            'status' => 'cancelled',
            'completed_at' => optional($task->completed_at)->toDateTimeString(),
        ]);
    }

    private function logAutoClosedTask(int $leadId, ?int $actorId, string $reason, array $oldValues, array $newValues): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        ActivityLog::create([
            'user_id' => $actorId,
            'action' => 'task_auto_closed_after_closer_approval',
            'model_type' => 'Lead',
            'model_id' => $leadId,
            'description' => 'Open task auto closed after closer approval.',
            'old_values' => $oldValues,
            'new_values' => array_merge($newValues, [
                'cleanup_reason' => $reason,
            ]),
        ]);
    }

    public function cancelOpenTasksForSiteVisit(SiteVisit $siteVisit, string $reason = 'Cancelled due to site visit reschedule.'): void
    {
        $canTargetManagerTasks = Task::supportsColumn('site_visit_id') || !empty($siteVisit->reminder_task_id);

        if ($canTargetManagerTasks) {
            $taskQuery = Task::withoutGlobalScopes()
                ->whereIn('status', Task::OPEN_STATUSES)
                ->whereNull('completed_at')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($siteVisit) {
                    if (Task::supportsColumn('site_visit_id')) {
                        $query->where('site_visit_id', $siteVisit->id);
                    }

                    if (!empty($siteVisit->reminder_task_id)) {
                        $query->orWhere('id', $siteVisit->reminder_task_id);
                    }
                });

            $taskQuery->get()->each(function (Task $task) use ($reason): void {
                $notes = trim(($task->notes ?? '') . PHP_EOL . $reason);

                $task->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                if (Task::supportsQueueArchiving()) {
                    $task->archiveForQueue($reason);
                }
            });
        }

        if (!TelecallerTask::supportsColumn('site_visit_id')) {
            return;
        }

        $telecallerTaskQuery = TelecallerTask::withoutGlobalScopes()
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->where('site_visit_id', $siteVisit->id);

        $telecallerTaskQuery->get()->each(function (TelecallerTask $task) use ($reason): void {
            $notes = trim(($task->notes ?? '') . PHP_EOL . $reason);

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'outcome' => 'rescheduled',
                'notes' => $notes !== '' ? $notes : null,
            ]);

            if (TelecallerTask::supportsQueueArchiving()) {
                $task->archiveForQueue($reason);
            }
        });
    }
}
