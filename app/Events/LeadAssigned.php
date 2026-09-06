<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\AppNotification;
use App\Services\NotificationSoundService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;
    public $assignedTo;
    public $assignedBy;
    public bool $suppressNotifications;

    public function __construct(Lead $lead, int $assignedTo, int $assignedBy, bool $suppressNotifications = false)
    {
        $this->lead = $lead->load(['creator', 'activeAssignments.assignedTo']);
        $this->assignedTo = $assignedTo;
        $this->assignedBy = $assignedBy;
        $this->suppressNotifications = $suppressNotifications;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->assignedTo),
            new PrivateChannel('user.' . $this->assignedBy),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lead.assigned';
    }

    public function broadcastWith(): array
    {
        $sound = app(NotificationSoundService::class)->resolveForNotification(
            AppNotification::TYPE_NEW_LEAD,
            ['kind' => 'lead_assigned']
        );

        return [
            'lead' => $this->lead,
            'assigned_to' => $this->assignedTo,
            'assigned_by' => $this->assignedBy,
            'suppress_popup' => $this->suppressNotifications,
            'message' => 'A new lead has been assigned to you.',
            'sound_key' => $sound['sound_key'],
            'sound_url' => $sound['sound_url'],
        ];
    }
}
