<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Events\DashboardUpdate;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendLeadAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(LeadAssigned $event): void
    {
        $assignedUser = User::find($event->assignedTo);

        if ($assignedUser) {
            $assignedUser->notify(new \App\Notifications\LeadAssignedNotification($event->lead, $event->assignedBy));
            
            // Broadcast dashboard update for sales executives
            if ($assignedUser->isSalesExecutive()) {
                event(new DashboardUpdate($assignedUser->id, 'lead_assigned', [
                    'lead_id' => $event->lead->id,
                    'lead_name' => $event->lead->name,
                ]));
            }
        }
    }
}

