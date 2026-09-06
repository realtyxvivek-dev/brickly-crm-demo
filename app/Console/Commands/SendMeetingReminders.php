<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'notifications:meeting-reminders';

    protected $description = 'Send meeting reminder notifications to assigned users';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now();
        $notifiedCount = 0;

        foreach ([15 => 'first_reminder_sent_at', 5 => 'final_reminder_sent_at'] as $minutes => $column) {
            [$windowStart, $windowEnd] = $this->resolveReminderWindow($now, $minutes);

            $meetings = Meeting::whereBetween('scheduled_at', [$windowStart, $windowEnd])
                ->where('status', 'scheduled')
                ->whereNull('completed_at')
                ->whereNull($column)
                ->where('scheduled_at', '>', $now)
                ->where(function ($query) {
                    $query->whereNull('reminder_enabled')
                        ->orWhere('reminder_enabled', true);
                })
                ->with(['lead', 'assignedTo'])
                ->get();

            foreach ($meetings as $meeting) {
                try {
                    $notifications = $this->notificationService->notifyMeetingReminder($meeting, $minutes);
                    if ($notifications->isNotEmpty()) {
                        $payload = [$column => $now];
                        if ($minutes === 5) {
                            $payload['reminder_sent_at'] = $now;
                        }
                        $meeting->forceFill($payload)->save();
                        $notifiedCount += $notifications->count();
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to send {$minutes}-minute notification for meeting {$meeting->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Sent {$notifiedCount} meeting reminder notification(s).");

        return self::SUCCESS;
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
