<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBrowserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $notificationData;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, array $notificationData)
    {
        $this->userId = $userId;
        $this->notificationData = $notificationData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // This job will be handled by the frontend polling mechanism
        // The notification data is stored in the AppNotification model
        // Frontend will check for new notifications and show browser notifications
        // This job is mainly for queuing and logging purposes
        
        Log::info("Browser notification queued", [
            'user_id' => $this->userId,
            'notification_data' => $this->notificationData,
        ]);
    }
}
