<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SendActivityLifecycleReminders extends Command
{
    protected $signature = 'notifications:activity-lifecycle-reminders';

    protected $description = 'Send same-day and outcome reminders for meetings and site visits.';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $notifiedCount = 0;

        if ($now->betweenIncluded($now->copy()->setTime(9, 0), $now->copy()->setTime(9, 59, 59))) {
            $notifiedCount += $this->sendMorningReminders($now);
        }

        $notifiedCount += $this->sendPostScheduledOutcomeReminders($now);

        if ($now->betweenIncluded($now->copy()->setTime(19, 0), $now->copy()->setTime(19, 59, 59))) {
            $notifiedCount += $this->sendEveningOutcomeReminders($now);
        }

        $this->info("Sent {$notifiedCount} activity lifecycle reminder notification(s).");

        return self::SUCCESS;
    }

    private function sendMorningReminders(Carbon $now): int
    {
        $count = 0;

        foreach ($this->openMeetingsForDay($now) as $meeting) {
            if ($this->alreadySent('meeting', $meeting->id, 'same_day_morning')) {
                continue;
            }

            $count += $this->notificationService
                ->notifyMeetingSameDayMorningReminder($meeting)
                ->count();
        }

        foreach ($this->openSiteVisitsForDay($now) as $siteVisit) {
            if ($this->alreadySent('site_visit', $siteVisit->id, 'same_day_morning')) {
                continue;
            }

            $count += $this->notificationService
                ->notifySiteVisitSameDayMorningReminder($siteVisit)
                ->count();
        }

        return $count;
    }

    private function sendPostScheduledOutcomeReminders(Carbon $now): int
    {
        $count = 0;
        $dueBefore = $now->copy()->subHour();

        $meetings = Meeting::query()
            ->where('status', 'scheduled')
            ->whereNull('completed_at')
            ->where('scheduled_at', '<=', $dueBefore)
            ->whereBetween('scheduled_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->with(['lead', 'assignedTo'])
            ->get();

        foreach ($meetings as $meeting) {
            if ($this->alreadySent('meeting', $meeting->id, 'post_1_hour_outcome')) {
                continue;
            }

            $count += $this->notificationService
                ->notifyMeetingOutcomeReminder($meeting, 'post_1_hour_outcome')
                ->count();
        }

        $siteVisits = SiteVisit::query()
            ->whereIn('status', ['scheduled', 'in_progress', 'rescheduled'])
            ->whereNull('completed_at')
            ->where('scheduled_at', '<=', $dueBefore)
            ->whereBetween('scheduled_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->with(['lead', 'assignedTo'])
            ->get();

        foreach ($siteVisits as $siteVisit) {
            if ($this->alreadySent('site_visit', $siteVisit->id, 'post_1_hour_outcome')) {
                continue;
            }

            $count += $this->notificationService
                ->notifySiteVisitOutcomeReminder($siteVisit, 'post_1_hour_outcome')
                ->count();
        }

        return $count;
    }

    private function sendEveningOutcomeReminders(Carbon $now): int
    {
        $count = 0;

        foreach ($this->openMeetingsForDay($now) as $meeting) {
            if ($this->alreadySent('meeting', $meeting->id, 'same_day_7pm_outcome')) {
                continue;
            }

            $count += $this->notificationService
                ->notifyMeetingOutcomeReminder($meeting, 'same_day_7pm_outcome')
                ->count();
        }

        foreach ($this->openSiteVisitsForDay($now) as $siteVisit) {
            if ($this->alreadySent('site_visit', $siteVisit->id, 'same_day_7pm_outcome')) {
                continue;
            }

            $count += $this->notificationService
                ->notifySiteVisitOutcomeReminder($siteVisit, 'same_day_7pm_outcome')
                ->count();
        }

        return $count;
    }

    private function openMeetingsForDay(Carbon $day): EloquentCollection
    {
        return Meeting::query()
            ->where('status', 'scheduled')
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->with(['lead', 'assignedTo'])
            ->get();
    }

    private function openSiteVisitsForDay(Carbon $day): EloquentCollection
    {
        return SiteVisit::query()
            ->whereIn('status', ['scheduled', 'in_progress', 'rescheduled'])
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->with(['lead', 'assignedTo'])
            ->get();
    }

    private function alreadySent(string $relatedType, int $relatedId, string $stage): bool
    {
        return AppNotification::query()
            ->where('data->kind', 'activity_lifecycle_reminder')
            ->where('data->related_type', $relatedType)
            ->where('data->related_id', $relatedId)
            ->where('data->stage', $stage)
            ->exists();
    }
}
