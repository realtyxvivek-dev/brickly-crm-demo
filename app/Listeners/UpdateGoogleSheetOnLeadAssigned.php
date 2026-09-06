<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Models\LeadAssignment;
use App\Services\GoogleSheetUpdateService;
use Illuminate\Support\Facades\Log;

class UpdateGoogleSheetOnLeadAssigned
{
    protected $sheetUpdateService;

    public function __construct(GoogleSheetUpdateService $sheetUpdateService)
    {
        $this->sheetUpdateService = $sheetUpdateService;
    }

    /**
     * Handle the event - Update Google Sheet when lead is assigned
     */
    public function handle(LeadAssigned $event): void
    {
        try {
            $lead = $event->lead;
            $assignedUserId = $event->assignedTo;

            // Check if lead has sheet reference
            $assignment = LeadAssignment::where('lead_id', $lead->id)
                ->whereNotNull('sheet_config_id')
                ->whereNotNull('sheet_row_number')
                ->first();

            if (!$assignment || !$assignment->sheetConfig) {
                return;
            }

            // Get assigned user name
            $assignedUser = \App\Models\User::find($assignedUserId);
            $userName = $assignedUser ? $assignedUser->name : 'Unassigned';

            // Update sheet with assigned user
            $this->sheetUpdateService->updateAssignedUser(
                $assignment->sheet_config_id,
                $assignment->sheet_row_number,
                $userName
            );

            Log::info("Updated Google Sheet on lead assignment", [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUserId,
                'sheet_config_id' => $assignment->sheet_config_id,
                'row_number' => $assignment->sheet_row_number,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update Google Sheet on lead assignment", [
                'lead_id' => $event->lead->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
