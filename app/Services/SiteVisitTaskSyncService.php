<?php

namespace App\Services;

use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Carbon\Carbon;

class SiteVisitTaskSyncService
{
    public function syncReminderTask(SiteVisit $siteVisit, ?User $actor = null, array $options = []): ?Task
    {
        $siteVisit->loadMissing(['lead', 'assignedTo', 'creator']);

        if (!$siteVisit->scheduled_at || $siteVisit->status !== 'scheduled') {
            return null;
        }

        $assignedTo = (int) ($options['assigned_to'] ?? $siteVisit->assigned_to ?? $siteVisit->created_by ?? 0);
        if ($assignedTo <= 0) {
            return null;
        }

        $visitTime = Carbon::parse($siteVisit->scheduled_at);
        $reminderAt = $visitTime->copy()->subHour();
        $leadName = trim((string) ($siteVisit->customer_name ?: $siteVisit->lead?->name ?: 'Customer'));
        $projectName = trim((string) ($siteVisit->project ?: $siteVisit->property_name ?: 'selected project'));
        $createdBy = (int) ($options['created_by'] ?? $actor?->id ?? $siteVisit->created_by ?? $assignedTo);
        $priority = (string) ($options['priority'] ?? 'medium');

        $notesLines = array_values(array_filter([
            trim((string) ($options['notes_prefix'] ?? '')),
            'Linked site visit #' . $siteVisit->id,
            'Actual visit: ' . $visitTime->format('d M Y, h:i A'),
            $projectName !== '' ? 'Project: ' . $projectName : null,
        ]));

        $taskPayload = [
            'lead_id' => $siteVisit->lead_id,
            'assigned_to' => $assignedTo,
            'type' => 'site_visit',
            'title' => 'Site Visit: ' . $leadName,
            'description' => 'Scheduled site visit for ' . ($projectName !== '' ? $projectName : 'selected project') . ' on ' . $visitTime->format('d M Y, h:i A'),
            'status' => 'pending',
            'scheduled_at' => $reminderAt,
            'created_by' => $createdBy,
            'notes' => implode("\n", $notesLines),
        ];

        if (Task::supportsColumn('priority')) {
            $taskPayload['priority'] = $priority;
        }

        if (Task::supportsColumn('meeting_id')) {
            $taskPayload['meeting_id'] = null;
        }

        if (Task::supportsColumn('site_visit_id')) {
            $taskPayload['site_visit_id'] = $siteVisit->id;
        }

        $existingOpenTasks = $this->queryLinkedTasks($siteVisit)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->get()
            ->sortByDesc('id')
            ->values();

        $primaryTask = $existingOpenTasks->shift();

        foreach ($existingOpenTasks as $duplicateTask) {
            $duplicateNotes = trim((string) $duplicateTask->notes);
            $duplicateTask->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'notes' => trim($duplicateNotes . "\nCancelled duplicate site visit reminder task."),
            ]);

            if (Task::supportsQueueArchiving()) {
                $duplicateTask->archiveForQueue('duplicate_site_visit_reminder');
            }
        }

        if ($primaryTask) {
            $primaryTask->fill($taskPayload);
            $primaryTask->completed_at = null;
            $primaryTask->save();
            $task = $primaryTask;
        } else {
            $task = Task::create($taskPayload);
        }

        if (in_array('reminder_task_id', $siteVisit->getFillable(), true) && (int) ($siteVisit->reminder_task_id ?? 0) !== (int) $task->id) {
            $siteVisit->forceFill([
                'reminder_task_id' => $task->id,
            ])->save();
        }

        $this->cancelDuplicateTelecallerReminderTasks($siteVisit, $task, $visitTime);

        return $task;
    }

    private function queryLinkedTasks(SiteVisit $siteVisit)
    {
        return Task::withoutGlobalScopes()
            ->where(function ($query) use ($siteVisit) {
                if (Task::supportsColumn('site_visit_id')) {
                    $query->where('site_visit_id', $siteVisit->id);
                }

                if (!empty($siteVisit->reminder_task_id)) {
                    $query->orWhere('id', $siteVisit->reminder_task_id);
                }

                $query->orWhere('notes', 'like', '%Linked site visit #' . $siteVisit->id . '%');
            });
    }

    private function cancelDuplicateTelecallerReminderTasks(SiteVisit $siteVisit, Task $managerTask, Carbon $visitTime): void
    {
        $telecallerReminderAt = $visitTime->copy()->subMinutes(10);

        $query = TelecallerTask::withoutGlobalScopes()
            ->where('lead_id', $siteVisit->lead_id)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($siteVisit, $telecallerReminderAt) {
                if (TelecallerTask::supportsColumn('site_visit_id')) {
                    $query->where('site_visit_id', $siteVisit->id);
                }

                $query->orWhere(function ($fallbackQuery) use ($siteVisit, $telecallerReminderAt) {
                    $fallbackQuery
                        ->where('lead_id', $siteVisit->lead_id)
                        ->where('scheduled_at', $telecallerReminderAt)
                        ->where('notes', 'like', '%site visit%');
                });
            });

        $query->get()->each(function (TelecallerTask $task) use ($managerTask): void {
            $notes = trim((string) $task->notes . "\nCancelled duplicate reminder; manager site visit task #{$managerTask->id} is active.");

            $task->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'notes' => $notes !== '' ? $notes : null,
            ]);

            if (TelecallerTask::supportsQueueArchiving()) {
                $task->archiveForQueue('duplicate_site_visit_reminder');
            }
        });
    }
}
