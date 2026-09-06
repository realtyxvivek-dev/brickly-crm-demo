<?php

namespace App\Notifications;

use App\Models\SiteVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SiteVisitCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $siteVisit;

    public function __construct(SiteVisit $siteVisit)
    {
        $this->siteVisit = $siteVisit;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'site_visit_created',
            'site_visit_id' => $this->siteVisit->id,
            'lead_id' => $this->siteVisit->lead_id,
            'lead_name' => $this->siteVisit->lead->name ?? 'N/A',
            'scheduled_at' => $this->siteVisit->scheduled_at,
            'message' => "A new site visit has been scheduled for lead '" . ($this->siteVisit->lead->name ?? 'N/A') . "'.",
        ];
    }
}

