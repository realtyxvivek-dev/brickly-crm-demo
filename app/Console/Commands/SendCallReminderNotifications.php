<?php

namespace App\Console\Commands;

use App\Models\TelecallerTask;
use App\Models\AppNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendCallReminderNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telecaller:send-reminder-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for scheduled calls';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled call reminders...');

        $now = Carbon::now();
        $count = 0;

        foreach ([10, 1] as $minutesBefore) {
            [$windowStart, $windowEnd] = $this->resolveReminderWindow($now, $minutesBefore);

            $tasksToNotify = TelecallerTask::where('status', '!=', 'completed')
                ->whereNull('completed_at')
                ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
                ->with(['lead', 'assignedTo'])
                ->get();

            foreach ($tasksToNotify as $task) {
                if ($this->alreadySent($task->id, $minutesBefore)) {
                    continue;
                }

                try {
                    $notifications = $this->notificationService->notifyTelecallerCallReminder($task, $minutesBefore);

                    if ($notifications->isNotEmpty()) {
                        if ($minutesBefore === 10) {
                            $task->forceFill(['notification_sent_at' => $now])->save();
                        }

                        $count += $notifications->count();
                    }

                    Log::info('Sent call reminder notification', [
                        'task_id' => $task->id,
                        'user_id' => $task->assigned_to,
                        'scheduled_at' => $task->scheduled_at,
                        'minutes_before' => $minutesBefore,
                    ]);
                } catch (\Throwable $e) {
                    $this->error("Failed to send {$minutesBefore}-minute call reminder for task {$task->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Sent {$count} reminder notification(s).");
        
        return Command::SUCCESS;
    }

    private function resolveReminderWindow(Carbon $now, int $minutesBefore): array
    {
        $target = $now->copy()->addMinutes($minutesBefore);

        return [
            $target->copy()->subMinute()->startOfMinute(),
            $target->copy()->endOfMinute(),
        ];
    }

    private function alreadySent(int $taskId, int $minutesBefore): bool
    {
        return AppNotification::query()
            ->where('telecaller_task_id', $taskId)
            ->where('type', AppNotification::TYPE_CALL_REMINDER)
            ->where('data->kind', 'telecaller_call_reminder')
            ->where('data->stage', $minutesBefore . '_min')
            ->exists();
    }
}
