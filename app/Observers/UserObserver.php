<?php

namespace App\Observers;

use App\Models\User;
use App\Services\LeadTransferService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    protected $leadTransferService;

    public function __construct(LeadTransferService $leadTransferService)
    {
        $this->leadTransferService = $leadTransferService;
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // Check if manager_id is being changed
        if ($user->isDirty('manager_id')) {
            $oldManagerId = $user->getOriginal('manager_id');
            $newManagerId = $user->manager_id;

            // Only process if manager actually changed (not just set to null)
            if ($oldManagerId != $newManagerId && $newManagerId !== null) {
                Log::info("Manager change detected for user {$user->id}: {$oldManagerId} -> {$newManagerId}");
                
                // Trigger lead transfer process
                $this->leadTransferService->transferLeadsOnManagerChange($user, $oldManagerId, $newManagerId);
            }
        }
    }
}
