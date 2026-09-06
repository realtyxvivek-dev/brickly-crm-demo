<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadTransferService
{
    /**
     * Transfer leads when a user's manager changes
     * 
     * @param User $user The user whose manager changed
     * @param int|null $oldManagerId The previous manager ID
     * @param int|null $newManagerId The new manager ID
     * @return void
     */
    public function transferLeadsOnManagerChange(User $user, ?int $oldManagerId, ?int $newManagerId): void
    {
        if (!$newManagerId) {
            Log::warning("Cannot transfer leads: new manager ID is null for user {$user->id}");
            return;
        }

        $newManager = User::find($newManagerId);
        if (!$newManager) {
            Log::error("Cannot transfer leads: new manager {$newManagerId} not found");
            return;
        }

        // Get all active leads assigned to this user
        $activeLeads = Lead::whereHas('activeAssignments', function ($query) use ($user) {
            $query->where('assigned_to', $user->id);
        })->get();

        if ($activeLeads->isEmpty()) {
            Log::info("No active leads found for user {$user->id} to transfer");
            return;
        }

        DB::beginTransaction();
        try {
            $transferredCount = 0;
            $verificationCount = 0;

            foreach ($activeLeads as $lead) {
                // Mark lead for verification
                $lead->needs_verification = true;
                $lead->verification_requested_by = auth()->id() ?? $user->id;
                $lead->verification_requested_at = now();
                $lead->pending_manager_id = $newManagerId;
                $lead->verification_notes = "Manager change: User {$user->name} (ID: {$user->id}) manager changed. Lead requires verification before transfer to {$newManager->name}.";
                $lead->save();

                $verificationCount++;

                Log::info("Lead {$lead->id} marked for verification due to manager change for user {$user->id}");
            }

            DB::commit();

            Log::info("Manager change processed for user {$user->id}: {$verificationCount} leads marked for verification");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error transferring leads on manager change for user {$user->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify and transfer leads to new manager
     * 
     * @param Lead $lead The lead to verify
     * @param int $verifiedBy User ID who verified
     * @param string|null $notes Verification notes
     * @return bool
     */
    public function verifyAndTransferLead(Lead $lead, int $verifiedBy, ?string $notes = null): bool
    {
        if (!$lead->needs_verification || !$lead->pending_manager_id) {
            return false;
        }

        $newManager = User::find($lead->pending_manager_id);
        if (!$newManager) {
            Log::error("Cannot transfer lead {$lead->id}: pending manager {$lead->pending_manager_id} not found");
            return false;
        }

        DB::beginTransaction();
        try {
            // Get the user whose manager changed (find user assigned to this lead)
            $currentAssignment = $lead->activeAssignments()->first();
            if (!$currentAssignment) {
                Log::warning("No active assignment found for lead {$lead->id}");
                DB::rollBack();
                return false;
            }

            $assignedUser = User::find($currentAssignment->assigned_to);
            if (!$assignedUser) {
                Log::warning("Assigned user not found for lead {$lead->id}");
                DB::rollBack();
                return false;
            }

            // Deactivate current assignments
            $lead->assignments()->update([
                'is_active' => false,
                'unassigned_at' => now()
            ]);

            // Create new assignment to the new manager
            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $lead->pending_manager_id,
                'assigned_by' => $verifiedBy,
                'assignment_type' => 'primary',
                'notes' => "Transferred due to manager change. Previous manager: {$assignedUser->manager_id}, New manager: {$lead->pending_manager_id}",
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            // Store pending_manager_id before clearing it
            $transferredManagerId = $lead->pending_manager_id;

            $lead->markAsFreshTransfer($assignedUser->id, (int) $transferredManagerId, $verifiedBy, $notes);

            // Update lead verification status
            $lead->needs_verification = false;
            $lead->verified_by = $verifiedBy;
            $lead->verified_at = now();
            if ($notes) {
                $lead->verification_notes = ($lead->verification_notes ?? '') . "\nVerification: " . $notes;
            }
            $lead->pending_manager_id = null;
            $lead->save();

            DB::commit();

            Log::info("Lead {$lead->id} verified and transferred to manager {$transferredManagerId}");

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error verifying and transferring lead {$lead->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject verification and keep lead with current assignment
     * 
     * @param Lead $lead The lead to reject verification for
     * @param int $rejectedBy User ID who rejected
     * @param string|null $notes Rejection notes
     * @return bool
     */
    public function rejectVerification(Lead $lead, int $rejectedBy, ?string $notes = null): bool
    {
        if (!$lead->needs_verification) {
            return false;
        }

        DB::beginTransaction();
        try {
            $lead->needs_verification = false;
            $lead->verified_by = $rejectedBy;
            $lead->verified_at = now();
            if ($notes) {
                $lead->verification_notes = ($lead->verification_notes ?? '') . "\nRejected: " . $notes;
            } else {
                $lead->verification_notes = ($lead->verification_notes ?? '') . "\nRejected: Lead kept with current assignment.";
            }
            $lead->pending_manager_id = null;
            $lead->save();

            DB::commit();

            Log::info("Lead {$lead->id} verification rejected, kept with current assignment");

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error rejecting verification for lead {$lead->id}: " . $e->getMessage());
            return false;
        }
    }
}
