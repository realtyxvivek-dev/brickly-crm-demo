<?php

namespace App\Listeners;

use App\Services\NotificationService;
use App\Models\FollowUp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Carbon\Carbon;

class SendFollowupNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle($event = null): void
    {
        // This will be called by a scheduled command
        // Check for follow-ups due in next hour
        $now = Carbon::now();
        $oneHourLater = $now->copy()->addHour();
        
        $followups = FollowUp::where('scheduled_at', '>=', $now)
            ->where('scheduled_at', '<=', $oneHourLater)
            ->whereNull('completed_at')
            ->where('status', 'scheduled')
            ->with('createdBy')
            ->get();

        foreach ($followups as $followup) {
            $user = $followup->createdBy;
            if ($user) {
                $actionUrl = url('/follow-ups/' . $followup->id);
                
                $this->notificationService->notifyFollowup($user, $followup, $actionUrl);
            }
        }
    }
}
