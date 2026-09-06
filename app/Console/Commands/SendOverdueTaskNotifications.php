<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\TelecallerTask;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendOverdueTaskNotifications extends Command
{
    protected $signature = 'notifications:overdue-tasks';

    protected $description = 'Send overdue task notifications to assigned users and their managers';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $firstReminderAfter = $now->copy()->subMinutes(15);
        $repeatAfter = $now->copy()->subHour();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $tasks = TelecallerTask::whereNull('completed_at')
            ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->where('scheduled_at', '<=', $firstReminderAfter)
            ->where(function ($query) use ($repeatAfter) {
                $query->whereNull('overdue_notified_at')
                    ->orWhere('overdue_notified_at', '<=', $repeatAfter);
            })
            ->with(['lead', 'assignedTo.manager'])
            ->get();

        $notifiedCount = 0;

        foreach ($tasks as $task) {
            try {
                if ($this->overdueReminderCount($task) >= 2) {
                    continue;
                }

                $notifications = $this->notificationService->notifyOverdueTask($task);
                $task->forceFill(['overdue_notified_at' => $now])->save();
                $notifiedCount += $notifications->count();
            } catch (\Exception $e) {
                $this->error("Failed to send overdue notification for task {$task->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$notifiedCount} overdue task notification(s).");

        return self::SUCCESS;
    }

    private function overdueReminderCount(TelecallerTask $task): int
    {
        return AppNotification::query()
            ->where('type', AppNotification::TYPE_TASK_OVERDUE)
            ->where('user_id', $task->assigned_to)
            ->where('data->task_id', $task->id)
            ->count();
    }
}
