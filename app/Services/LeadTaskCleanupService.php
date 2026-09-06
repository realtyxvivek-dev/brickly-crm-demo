<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CrmAssignment;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

class LeadTaskCleanupService
{
    private const TRANSFER_CANCELLED_STATUS = 'cancelled';

    public function deleteAllTasksForLead(int $leadId, ?int $actedBy = null, string $reason = 'lead_deleted'): array
    {
        return $this->cleanupTasks($leadId, null, $actedBy, $reason);
    }

    public function deleteTasksForLeadAndOwner(int $leadId, int $ownerId, ?int $actedBy = null, string $reason = 'lead_transferred'): array
    {
        return $this->cleanupTasks($leadId, $ownerId, $actedBy, $reason);
    }

    public function logManualTaskDeletion(Task $task, ?int $actedBy = null, string $reason = 'manual_cleanup'): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $task->loadMissing(['assignedTo', 'creator']);

        ActivityLog::create([
            'user_id' => $actedBy,
            'action' => 'task_deleted',
            'model_type' => 'Lead',
            'model_id' => $task->lead_id,
            'description' => $this->buildCleanupDescription(
                $task->title ?: 'Task',
                $task->assignedTo?->name,
                $reason
            ),
            'old_values' => [
                'task_id' => $task->id,
                'task_model' => 'Task',
                'task_type' => $task->type,
                'assigned_to' => $task->assigned_to,
                'assigned_to_name' => $task->assignedTo?->name,
                'status' => $task->status,
            ],
            'new_values' => [
                'cleanup_reason' => $reason,
            ],
        ]);
    }

    public function logManualTelecallerTaskDeletion(TelecallerTask $task, ?int $actedBy = null, string $reason = 'manual_cleanup'): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $task->loadMissing(['assignedTo', 'createdBy']);

        $label = $task->task_type
            ? ucfirst(str_replace('_', ' ', $task->task_type)) . ' task'
            : 'Calling task';

        ActivityLog::create([
            'user_id' => $actedBy,
            'action' => 'task_deleted',
            'model_type' => 'Lead',
            'model_id' => $task->lead_id,
            'description' => $this->buildCleanupDescription(
                $label,
                $task->assignedTo?->name,
                $reason
            ),
            'old_values' => [
                'task_id' => $task->id,
                'task_model' => 'TelecallerTask',
                'task_type' => $task->task_type,
                'assigned_to' => $task->assigned_to,
                'assigned_to_name' => $task->assignedTo?->name,
                'status' => $task->status,
            ],
            'new_values' => [
                'cleanup_reason' => $reason,
            ],
        ]);
    }

    private function cleanupTasks(int $leadId, ?int $ownerId, ?int $actedBy, string $reason): array
    {
        $taskQuery = Task::query()->withoutGlobalScope('visible_in_queue')
            ->where('lead_id', $leadId);

        $telecallerTaskQuery = TelecallerTask::query()->withoutGlobalScope('visible_in_queue')
            ->where('lead_id', $leadId);

        $followUpQuery = Schema::hasTable('follow_ups')
            ? FollowUp::query()->withQueueHidden()->where('lead_id', $leadId)
            : null;

        $meetingQuery = Schema::hasTable('meetings')
            ? Meeting::query()->withQueueHidden()->where('lead_id', $leadId)
            : null;

        $siteVisitQuery = Schema::hasTable('site_visits')
            ? SiteVisit::query()->withQueueHidden()->where('lead_id', $leadId)
            : null;

        if ($ownerId !== null) {
            $taskQuery->where('assigned_to', $ownerId);
            $telecallerTaskQuery->where('assigned_to', $ownerId);

            if ($followUpQuery) {
                $followUpQuery->where('created_by', $ownerId);
            }

            if ($meetingQuery) {
                $meetingQuery->where(function ($query) use ($ownerId) {
                    $query->where('assigned_to', $ownerId)
                        ->orWhere('created_by', $ownerId);
                });
            }

            if ($siteVisitQuery) {
                $siteVisitQuery->where(function ($query) use ($ownerId) {
                    $query->where('assigned_to', $ownerId)
                        ->orWhere('created_by', $ownerId);
                });
            }
        }

        $tasks = $taskQuery->with(['assignedTo', 'creator'])->get();
        $telecallerTasks = $telecallerTaskQuery->with(['assignedTo', 'createdBy'])->get();
        $followUps = $followUpQuery?->get() ?? new EloquentCollection();
        $meetings = $meetingQuery?->get() ?? new EloquentCollection();
        $siteVisits = $siteVisitQuery?->get() ?? new EloquentCollection();

        $this->logCleanupActivities($leadId, $tasks, $telecallerTasks, $actedBy, $reason);

        $deletedTasks = 0;
        $deletedTelecallerTasks = 0;
        $archivedFollowUps = 0;
        $archivedMeetings = 0;
        $archivedSiteVisits = 0;

        if ($reason === 'lead_transferred') {
            foreach ($tasks as $task) {
                $this->closeTaskForTransfer($task, $reason);
                $this->archiveTaskForTransfer($task, $reason);
                $deletedTasks++;
            }

            foreach ($telecallerTasks as $task) {
                $this->closeTelecallerTaskForTransfer($task, $reason);
                $this->archiveTelecallerTaskForTransfer($task, $reason);
                $deletedTelecallerTasks++;
            }

            foreach ($followUps as $followUp) {
                $followUp->archiveForQueue($reason);
                $archivedFollowUps++;
            }

            foreach ($meetings as $meeting) {
                $meeting->archiveForQueue($reason);
                $archivedMeetings++;
            }

            foreach ($siteVisits as $siteVisit) {
                $siteVisit->archiveForQueue($reason);
                $archivedSiteVisits++;
            }
        } else {
            if ($tasks->isNotEmpty()) {
                $deletedTasks = Task::withQueueHidden()
                    ->whereIn('id', $tasks->pluck('id'))
                    ->delete();
            }

            if ($telecallerTasks->isNotEmpty()) {
                $deletedTelecallerTasks = TelecallerTask::withQueueHidden()
                    ->whereIn('id', $telecallerTasks->pluck('id'))
                    ->delete();
            }
        }

        $deletedAssignments = 0;
        if (Schema::hasTable('crm_assignments')) {
            $crmAssignmentQuery = CrmAssignment::query()->where('lead_id', $leadId);
            if ($ownerId !== null) {
                $crmAssignmentQuery->where('assigned_to', $ownerId);
            }
            $deletedAssignments = $crmAssignmentQuery->delete();
        }

        return [
            'tasks' => $deletedTasks,
            'telecaller_tasks' => $deletedTelecallerTasks,
            'crm_assignments' => $deletedAssignments,
            'follow_ups' => $archivedFollowUps,
            'meetings' => $archivedMeetings,
            'site_visits' => $archivedSiteVisits,
        ];
    }

    private function logCleanupActivities(
        int $leadId,
        EloquentCollection $tasks,
        EloquentCollection $telecallerTasks,
        ?int $actedBy,
        string $reason
    ): void {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        foreach ($tasks as $task) {
            ActivityLog::create([
                'user_id' => $actedBy,
                'action' => 'task_deleted',
                'model_type' => 'Lead',
                'model_id' => $leadId,
                'description' => $this->buildCleanupDescription(
                    $task->title ?: 'Task',
                    $task->assignedTo?->name,
                    $reason
                ),
                'old_values' => [
                    'task_id' => $task->id,
                    'task_model' => 'Task',
                    'task_type' => $task->type,
                    'assigned_to' => $task->assigned_to,
                    'assigned_to_name' => $task->assignedTo?->name,
                    'status' => $task->status,
                ],
                'new_values' => [
                    'cleanup_reason' => $reason,
                ],
            ]);
        }

        foreach ($telecallerTasks as $task) {
            $label = $task->task_type
                ? ucfirst(str_replace('_', ' ', $task->task_type)) . ' task'
                : 'Calling task';

            ActivityLog::create([
                'user_id' => $actedBy,
                'action' => 'task_deleted',
                'model_type' => 'Lead',
                'model_id' => $leadId,
                'description' => $this->buildCleanupDescription(
                    $label,
                    $task->assignedTo?->name,
                    $reason
                ),
                'old_values' => [
                    'task_id' => $task->id,
                    'task_model' => 'TelecallerTask',
                    'task_type' => $task->task_type,
                    'assigned_to' => $task->assigned_to,
                    'assigned_to_name' => $task->assignedTo?->name,
                    'status' => $task->status,
                ],
                'new_values' => [
                    'cleanup_reason' => $reason,
                ],
            ]);
        }
    }

    private function buildCleanupDescription(string $label, ?string $assigneeName, string $reason): string
    {
        $ownerText = $assigneeName ? " for {$assigneeName}" : '';

        return match ($reason) {
            'lead_transferred' => "{$label}{$ownerText} removed due to lead transfer",
            'manual_cleanup' => "{$label}{$ownerText} removed manually from lead details",
            default => "{$label}{$ownerText} deleted because the lead was deleted",
        };
    }

    private function closeTaskForTransfer(Task $task, string $reason): void
    {
        if (!in_array($task->status, Task::OPEN_STATUSES, true) && $task->completed_at !== null) {
            return;
        }

        $payload = [
            'status' => self::TRANSFER_CANCELLED_STATUS,
        ];

        if (Task::supportsColumn('completed_at')) {
            $payload['completed_at'] = $task->completed_at ?? now();
        }

        if (Task::supportsColumn('notes')) {
            $payload['notes'] = $this->appendTransferNote($task->notes, $reason);
        }

        $task->forceFill($payload)->saveQuietly();
    }

    private function closeTelecallerTaskForTransfer(TelecallerTask $task, string $reason): void
    {
        if (!in_array($task->status, TelecallerTask::OPEN_STATUSES, true) && $task->completed_at !== null) {
            return;
        }

        $payload = [
            'status' => self::TRANSFER_CANCELLED_STATUS,
        ];

        if (TelecallerTask::supportsColumn('completed_at')) {
            $payload['completed_at'] = $task->completed_at ?? now();
        }

        if (TelecallerTask::supportsColumn('notes')) {
            $payload['notes'] = $this->appendTransferNote($task->notes, $reason);
        }

        $task->forceFill($payload)->saveQuietly();
    }

    private function appendTransferNote(?string $notes, string $reason): ?string
    {
        if ($reason !== 'lead_transferred') {
            return $notes;
        }

        $transferNote = 'Cancelled due to lead transfer.';
        $current = trim((string) $notes);

        if ($current === '') {
            return $transferNote;
        }

        if (str_contains($current, $transferNote)) {
            return $current;
        }

        return $current . PHP_EOL . $transferNote;
    }

    private function archiveTaskForTransfer(Task $task, string $reason): void
    {
        if (!Task::supportsQueueArchiving()) {
            return;
        }

        $task->forceFill([
            'queue_hidden_at' => now(),
            'queue_hidden_reason' => $reason,
        ])->saveQuietly();
    }

    private function archiveTelecallerTaskForTransfer(TelecallerTask $task, string $reason): void
    {
        if (!TelecallerTask::supportsQueueArchiving()) {
            return;
        }

        $task->forceFill([
            'queue_hidden_at' => now(),
            'queue_hidden_reason' => $reason,
        ])->saveQuietly();
    }
}
