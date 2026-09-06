<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Task;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendManagerTaskReminders extends Command
{
    protected $signature = 'notifications:manager-task-reminders';

    protected $description = 'Send reminder notifications for ASM and Senior Manager phone call tasks';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now();
        $notifiedCount = 0;

        foreach ([15, 5, 1] as $minutesBefore) {
            $windowStart = $now->copy()->addMinutes($minutesBefore)->startOfMinute();
            $windowEnd = $windowStart->copy()->endOfMinute();

            $tasks = Task::query()
                ->where('type', 'phone_call')
                ->where('status', 'pending')
                ->whereNull('completed_at')
                ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
                ->whereHas('assignedTo.role', function ($query) {
                    $query->whereIn('slug', [
                        Role::ASSISTANT_SALES_MANAGER,
                        Role::SENIOR_MANAGER,
                    ]);
                })
                ->with(['lead', 'assignedTo.role'])
                ->get();

            foreach ($tasks as $task) {
                if ($this->alreadySent($task->id, $minutesBefore)) {
                    continue;
                }

                try {
                    $notifications = $this->notificationService->notifyManagerTaskReminder($task, $minutesBefore);
                    if ($notifications->isNotEmpty()) {
                        if ($minutesBefore === 15) {
                            $task->forceFill(['reminder_sent_at' => $now])->save();
                        }

                        $notifiedCount += $notifications->count();
                    }
                } catch (\Throwable $e) {
                    $this->error("Failed to send {$minutesBefore}-minute manager task reminder for task {$task->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Sent {$notifiedCount} manager task reminder notification(s).");

        return self::SUCCESS;
    }

    private function alreadySent(int $taskId, int $minutesBefore): bool
    {
        return \App\Models\AppNotification::query()
            ->where('type', \App\Models\AppNotification::TYPE_CALL_REMINDER)
            ->where('data->kind', 'manager_task_reminder')
            ->where('data->task_id', $taskId)
            ->where('data->stage', $minutesBefore . '_min')
            ->exists();
    }
}
