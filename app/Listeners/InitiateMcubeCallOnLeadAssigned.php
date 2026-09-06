<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Models\User;
use App\Services\AutoAssignmentMcubeCallService;

class InitiateMcubeCallOnLeadAssigned
{
    public function __construct(private readonly AutoAssignmentMcubeCallService $service)
    {
    }

    public function handle(LeadAssigned $event): void
    {
        $assignedUser = User::query()->find($event->assignedTo);
        if (!$assignedUser) {
            return;
        }

        $lead = $event->lead->fresh();
        if (!$lead) {
            return;
        }

        $this->service->handle($lead, $assignedUser, (int) $event->assignedBy);
    }
}
