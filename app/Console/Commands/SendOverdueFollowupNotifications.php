<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\FollowUp;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendOverdueFollowupNotifications extends Command
{
    protected $signature = 'notifications:overdue-followups';

    protected $description = 'Send overdue follow-up notifications to the responsible user and manager';

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
        $today = $now->toDateString();

        $followups = FollowUp::whereNull('completed_at')
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', $today)
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->where('scheduled_at', '<=', $firstReminderAfter)
            ->where(function ($query) use ($repeatAfter) {
                $query->whereNull('overdue_notified_at')
                    ->orWhere('overdue_notified_at', '<=', $repeatAfter);
            })
            ->with(['creator.manager', 'lead.activeAssignments.assignedTo.manager'])
            ->get();

        $notifiedCount = 0;

        foreach ($followups as $followup) {
            try {
                if (!$followup->scheduled_at || !$followup->scheduled_at->isSameDay($now)) {
                    continue;
                }

                if ($this->overdueReminderCount($followup) >= 2) {
                    continue;
                }

                $notifications = $this->notificationService->notifyOverdueFollowup($followup);
                $followup->forceFill(['overdue_notified_at' => $now])->save();
                $notifiedCount += $notifications->count();
            } catch (\Exception $e) {
                $this->error("Failed to send overdue notification for follow-up {$followup->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$notifiedCount} overdue follow-up notification(s).");

        return self::SUCCESS;
    }

    private function overdueReminderCount(FollowUp $followup): int
    {
        $responsibleUserId = $followup->lead?->activeAssignments?->first()?->assigned_to
            ?? $followup->created_by;

        $query = AppNotification::query()
            ->where('type', AppNotification::TYPE_FOLLOWUP_OVERDUE)
            ->where('data->followup_id', $followup->id);

        if ($responsibleUserId) {
            $query->where('user_id', $responsibleUserId);
        }

        return $query->count();
    }
}
