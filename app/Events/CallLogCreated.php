<?php

namespace App\Events;

use App\Models\CallLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $callLog;

    /**
     * Create a new event instance.
     */
    public function __construct(CallLog $callLog)
    {
        $this->callLog = $callLog->load(['lead:id,name,phone', 'user:id,name', 'telecaller:id,name']);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('call-logs'),
        ];

        // Broadcast to user's private channel
        if ($this->callLog->user_id) {
            $channels[] = new PrivateChannel('user.' . $this->callLog->user_id);
        }

        // Broadcast to manager's channel if user has a manager
        if ($this->callLog->callerUser && $this->callLog->callerUser->manager_id) {
            $channels[] = new PrivateChannel('user.' . $this->callLog->callerUser->manager_id);
        }

        // Broadcast to admin/CRM channels
        $channels[] = new PrivateChannel('admin');
        $channels[] = new PrivateChannel('crm');

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'call-log.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->callLog->id,
            'phone_number' => $this->callLog->phone_number,
            'lead_name' => $this->callLog->lead->name ?? 'N/A',
            'user_name' => $this->callLog->callerUser->name ?? 'N/A',
            'duration' => $this->callLog->formatted_duration,
            'call_type' => $this->callLog->call_type_label,
            'status' => $this->callLog->status_label,
            'start_time' => $this->callLog->start_time->format('Y-m-d H:i:s'),
            'created_at' => $this->callLog->created_at->toIso8601String(),
        ];
    }
}
