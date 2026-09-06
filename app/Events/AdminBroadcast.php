<?php

namespace App\Events;

use App\Models\BroadcastMessage;
use App\Models\AppNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcast;
    public $notifications;

    public function __construct(BroadcastMessage $broadcast, array $notifications)
    {
        $this->broadcast = $broadcast->load('sender');
        $this->notifications = $notifications;
    }

    public function broadcastOn(): array
    {
        // Broadcast to all users via admin channel
        return [
            new Channel('admin-broadcast'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'broadcast.new';
    }

    public function broadcastWith(): array
    {
        return [
            'broadcast' => [
                'id' => $this->broadcast->id,
                'title' => $this->broadcast->title,
                'message' => $this->broadcast->message,
                'sender_name' => $this->broadcast->sender->name,
                'created_at' => $this->broadcast->created_at->toIso8601String(),
            ],
            'notifications' => array_map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'action_type' => $notification->action_type,
                    'action_url' => $notification->action_url,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            }, $this->notifications),
        ];
    }
}
