<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;
    public $oldStatus;
    public $newStatus;

    public function __construct(Lead $lead, string $oldStatus, string $newStatus)
    {
        $this->lead = $lead->load(['creator', 'activeAssignments.assignedTo']);
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('leads')];

        // Notify assigned users
        foreach ($this->lead->activeAssignments as $assignment) {
            $channels[] = new PrivateChannel('user.' . $assignment->assigned_to);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'lead.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'lead' => $this->lead,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => "Lead status updated from {$this->oldStatus} to {$this->newStatus}",
        ];
    }
}

