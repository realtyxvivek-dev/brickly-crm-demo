<?php

namespace App\Listeners;

use App\Events\LeadStatusUpdated;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

class UpdateGoogleSheetOnStatusChange
{
    protected $sheetsService;

    public function __construct(GoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
    }

    /**
     * Handle the event.
     */
    public function handle(LeadStatusUpdated $event): void
    {
        $lead = $event->lead;
        $newStatus = $event->newStatus;

        // Only update for specific statuses that need to sync back to sheet
        // Statuses that should trigger sheet update
        $statusesToSync = [
            'closed', // Map to 'Interested' or 'Closed Won'
            'dead', // Map to 'Not Interested' or 'Closed Lost'
            'verified_prospect', // Map to 'Interested' or 'Qualified'
            'visit_done', // Map to 'Site Visit Completed'
            'revisited_completed', // Map to 'Site Visit Completed'
        ];
        
        if (!in_array($newStatus, $statusesToSync)) {
            return; // Don't sync this status
        }
        
        // Map new statuses to sheet values
        $statusMap = [
            'closed' => 'Closed Won',
            'dead' => 'Closed Lost',
            'verified_prospect' => 'Qualified',
            'visit_done' => 'Site Visit Completed',
            'revisited_completed' => 'Site Visit Completed',
        ];
        
        $sheetStatus = $statusMap[$newStatus] ?? $newStatus;

        // Find active assignment with sheet tracking
        $assignment = $lead->activeAssignments()
            ->whereNotNull('sheet_config_id')
            ->whereNotNull('sheet_row_number')
            ->first();

        if (!$assignment) {
            return; // Lead not imported from Google Sheets
        }

        try {
            // Get username for notes
            $username = $lead->creator->name ?? null;
            $notes = $lead->notes;

            // Update Google Sheet with mapped status
            $this->sheetsService->updateGoogleSheetStatus(
                $assignment->sheet_config_id,
                $assignment->sheet_row_number,
                $sheetStatus,
                $notes,
                $username
            );

        } catch (\Exception $e) {
            // Log error but don't fail the status update
            Log::error("Failed to update Google Sheet for lead {$lead->id}: " . $e->getMessage());
        }
    }
}
