<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\ExecutionTask;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendExecutionTaskReminders extends Command
{
    protected $signature = 'notifications:execution-task-reminders';

    protected $description = 'Send due and overdue reminders for execution tasks';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $dueMinutes = (int) config('execution-desk.due_reminder_minutes', 15);
        $repeatMinutes = (int) config('execution-desk.overdue_repeat_minutes', 60);
        $openStatuses = config('execution-desk.open_statuses', []);
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $firstReminderAfter = $now->copy()->subMinutes(15);

        $dueTasks = ExecutionTask::query()
            ->with(['creator', 'assignee'])
            ->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->whereNull('due_reminder_sent_at')
            ->whereBetween('due_at', [$now, $now->copy()->addMinutes($dueMinutes)])
            ->get();

        foreach ($dueTasks as $task) {
            $this->notificationService->notifyExecutionTaskDueReminder($task);
            $task->forceFill(['due_reminder_sent_at' => $now])->save();
        }

        $repeatAfter = $now->copy()->subMinutes($repeatMinutes);
        $overdueTasks = ExecutionTask::query()
            ->with(['creator', 'assignee'])
            ->whereIn('status', $openStatuses)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$todayStart, $todayEnd])
            ->where('due_at', '<=', $firstReminderAfter)
            ->where(function ($query) use ($repeatAfter): void {
                $query->whereNull('overdue_notified_at')
                    ->orWhere('overdue_notified_at', '<=', $repeatAfter);
            })
            ->get();

        foreach ($overdueTasks as $task) {
            if (!$task->assignee || $this->overdueReminderCount($task) >= 2) {
                continue;
            }

            $this->notificationService->notifyExecutionTaskOverdue($task);
            $task->forceFill(['overdue_notified_at' => $now])->save();
        }

        $this->info('Execution task reminders processed. Due: ' . $dueTasks->count() . ', overdue: ' . $overdueTasks->count());

        return self::SUCCESS;
    }

    private function overdueReminderCount(ExecutionTask $task): int
    {
        return AppNotification::query()
            ->where('type', AppNotification::TYPE_EXECUTION_TASK_OVERDUE)
            ->where('user_id', $task->assigned_to)
            ->where('data->execution_task_id', $task->id)
            ->count();
    }
}
