<?php

namespace App\Notifications;

use App\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProspectCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $prospect;
    public $telecallerName;

    public function __construct(Prospect $prospect, string $telecallerName)
    {
        $this->prospect = $prospect;
        $this->telecallerName = $telecallerName;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'prospect_created',
            'prospect_id' => $this->prospect->id,
            'customer_name' => $this->prospect->customer_name,
            'phone' => $this->prospect->phone,
            'telecaller_name' => $this->telecallerName,
            'message' => "{$this->telecallerName} marked lead '{$this->prospect->customer_name}' as interested and created a prospect.",
        ];
    }
}
