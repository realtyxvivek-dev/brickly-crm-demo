<?php

namespace App\Console\Commands;

use App\Models\SiteVisit;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendSiteVisitReminders extends Command
{
    protected $signature = 'notifications:site-visit-reminders';

    protected $description = 'Send site visit reminder notifications to assigned users';

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

            $siteVisits = SiteVisit::whereBetween('scheduled_at', [$windowStart, $windowEnd])
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

            foreach ($siteVisits as $siteVisit) {
                try {
                    $notifications = $this->notificationService->notifySiteVisitReminder($siteVisit, $minutes);
                    if ($notifications->isNotEmpty()) {
                        $siteVisit->forceFill([$column => $now])->save();
                        $notifiedCount += $notifications->count();
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to send {$minutes}-minute notification for site visit {$siteVisit->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Sent {$notifiedCount} site visit reminder notification(s).");

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
