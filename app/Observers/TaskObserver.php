<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        $this->logActivity($task, 'created', null, null, 'Task created');
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        $changes = $task->getChanges();
        $original = $task->getOriginal();

        foreach ($changes as $key => $value) {
            if (in_array($key, ['updated_at', 'created_at'])) {
                continue;
            }

            $oldValue = $original[$key] ?? null;
            $newValue = $value;

            $activityType = $this->getActivityType($key);
            $description = $this->getActivityDescription($key, $oldValue, $newValue);

            $this->logActivity($task, $activityType, $oldValue, $newValue, $description);
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        $this->logActivity($task, 'deleted', null, null, 'Task deleted');
    }

    /**
     * Log activity for task
     */
    private function logActivity(Task $task, string $activityType, $oldValue = null, $newValue = null, ?string $description = null): void
    {
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => Auth::id(),
            'activity_type' => $activityType,
            'old_value' => $oldValue ? (is_array($oldValue) ? json_encode($oldValue) : (string)$oldValue) : null,
            'new_value' => $newValue ? (is_array($newValue) ? json_encode($newValue) : (string)$newValue) : null,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Get activity type based on field name
     */
    private function getActivityType(string $field): string
    {
        return match($field) {
            'status' => 'status_changed',
            'priority' => 'priority_changed',
            'assigned_to' => 'assigned',
            'scheduled_at', 'due_date' => 'rescheduled',
            'title' => 'title_changed',
            'description' => 'description_changed',
            'notes' => 'notes_changed',
            default => 'updated',
        };
    }

    /**
     * Get activity description
     */
    private function getActivityDescription(string $field, $oldValue, $newValue): string
    {
        return match($field) {
            'status' => "Status changed from '{$oldValue}' to '{$newValue}'",
            'priority' => "Priority changed from '{$oldValue}' to '{$newValue}'",
            'assigned_to' => "Task reassigned",
            'scheduled_at' => "Task rescheduled",
            'due_date' => "Due date changed",
            'title' => "Title changed",
            'description' => "Description updated",
            'notes' => "Notes updated",
            default => "Task updated",
        };
    }
}
