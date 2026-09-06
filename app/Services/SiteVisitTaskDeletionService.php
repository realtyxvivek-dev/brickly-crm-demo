<?php

namespace App\Services;

use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;

class SiteVisitTaskDeletionService
{
    /**
     * Remove an open scheduled site visit when its reminder task is manually deleted.
     * Completed and historical visits are intentionally never removed through this flow.
     */
    public function deleteLinkedScheduledVisit(Task $task, ?User $actor = null): ?SiteVisit
    {
        $siteVisit = $this->findLinkedVisit($task);

        if (!$siteVisit || !$this->isOpenScheduledVisit($siteVisit)) {
            return null;
        }

        $actorName = trim((string) ($actor?->name ?? 'System'));
        $auditLine = sprintf(
            'Site visit cancelled and removed automatically after linked task #%d was deleted by %s.',
            $task->id,
            $actorName !== '' ? $actorName : 'System'
        );

        $siteVisit->update([
            'status' => 'cancelled',
            'visit_notes' => $this->appendNote($siteVisit->getRawOriginal('visit_notes'), $auditLine),
        ]);

        // A visit can have more than one reminder task. Hide the remaining open reminders
        // so the deleted visit cannot continue to appear in any user queue.
        $this->cancelRelatedTasks($siteVisit, $task->id, $auditLine);

        $siteVisit->delete();

        return $siteVisit;
    }

    private function findLinkedVisit(Task $task): ?SiteVisit
    {
        $query = SiteVisit::withoutGlobalScopes()->withTrashed();

        if (Task::supportsColumn('site_visit_id') && $task->site_visit_id) {
            return $query->find($task->site_visit_id);
        }

        return $query->where('reminder_task_id', $task->id)->latest('id')->first();
    }

    private function isOpenScheduledVisit(SiteVisit $siteVisit): bool
    {
        return !$siteVisit->trashed()
            && $siteVisit->status === 'scheduled'
            && $siteVisit->completed_at === null
            && !((bool) $siteVisit->is_dead);
    }

    private function cancelRelatedTasks(SiteVisit $siteVisit, int $deletedTaskId, string $note): void
    {
        $taskUpdate = [
            'status' => 'cancelled',
            'completed_at' => now(),
        ];

        Task::withoutGlobalScopes()
            ->where('site_visit_id', $siteVisit->id)
            ->whereKeyNot($deletedTaskId)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->get()
            ->each(function (Task $relatedTask) use ($taskUpdate, $note) {
                $relatedTask->update(array_merge($taskUpdate, [
                    'notes' => $this->appendNote($relatedTask->getRawOriginal('notes'), $note),
                ]));
            });

        if (!TelecallerTask::supportsColumn('site_visit_id')) {
            return;
        }

        TelecallerTask::withoutGlobalScopes()
            ->where('site_visit_id', $siteVisit->id)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->get()
            ->each(function (TelecallerTask $relatedTask) use ($taskUpdate, $note) {
                $relatedTask->update(array_merge($taskUpdate, [
                    'notes' => $this->appendNote($relatedTask->getRawOriginal('notes'), $note),
                ]));
            });
    }

    private function appendNote(?string $existing, string $line): string
    {
        return trim(trim((string) $existing) . PHP_EOL . $line);
    }
}
