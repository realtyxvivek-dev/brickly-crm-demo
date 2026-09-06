<?php

namespace App\Events;

use App\Models\SiteVisit;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteVisitCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $siteVisit;

    public function __construct(SiteVisit $siteVisit)
    {
        $this->siteVisit = $siteVisit->load(['lead', 'creator', 'assignedTo']);
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('site-visits')];

        // Notify assigned user
        if ($this->siteVisit->assigned_to) {
            $channels[] = new PrivateChannel('user.' . $this->siteVisit->assigned_to);
        }

        // Notify managers
        if ($this->siteVisit->assignedTo && $this->siteVisit->assignedTo->manager_id) {
            $channels[] = new PrivateChannel('user.' . $this->siteVisit->assignedTo->manager_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'site-visit.created';
    }

    public function broadcastWith(): array
    {
        return [
            'site_visit' => $this->siteVisit,
            'message' => 'A new site visit has been scheduled.',
        ];
    }
}

