<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Models\FollowUp;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendFollowupReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:followup-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follow-up reminder notifications to users';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $notifiedCount = 0;

        foreach ([15 => 'first_reminder_sent_at', 5 => 'final_reminder_sent_at'] as $minutes => $column) {
            [$windowStart, $windowEnd] = $this->resolveReminderWindow($now, $minutes);

            $followups = FollowUp::whereBetween('scheduled_at', [$windowStart, $windowEnd])
                ->whereNull('completed_at')
                ->where('status', 'scheduled')
                ->whereNull($column)
                ->where('scheduled_at', '>', $now)
                ->with(['creator.manager', 'lead.activeAssignments.assignedTo.manager'])
                ->get();

            foreach ($followups as $followup) {
                try {
                    $notifications = $this->notificationService->notifyFollowupReminder($followup, $minutes);
                    if ($notifications->isNotEmpty()) {
                        $payload = [$column => $now];
                        if ($minutes === 5) {
                            $payload['reminder_sent_at'] = $now;
                        }
                        $followup->forceFill($payload)->save();
                        $notifiedCount += $notifications->count();
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to send {$minutes}-minute notification for follow-up {$followup->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Sent {$notifiedCount} follow-up reminder notifications.");

        return 0;
    }

    private function resolveReminderWindow(Carbon $now, int $minutes): array
    {
        $target = $now->copy()->addMinutes($minutes);

        return [
            $target->copy()->subMinute()->startOfMinute(),
            $target->copy()->endOfMinute(),
        ];
    }
}
