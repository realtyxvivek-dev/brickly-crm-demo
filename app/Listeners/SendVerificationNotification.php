<?php

namespace App\Listeners;

use App\Events\ProspectSentForVerification;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendVerificationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(ProspectSentForVerification $event): void
    {
        $prospect = $event->prospect;
        $manager = $prospect->manager;

        if ($manager) {
            $actionUrl = url('/sales-manager/prospects');
            
            $this->notificationService->notifyNewVerification(
                $manager,
                'prospect',
                'New Prospect Verification',
                "New prospect '{$prospect->customer_name}' requires verification",
                $actionUrl,
                [
                    'prospect_id' => $prospect->id,
                    'customer_name' => $prospect->customer_name,
                ]
            );
        }
    }
}
