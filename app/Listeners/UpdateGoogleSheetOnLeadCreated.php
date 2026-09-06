<?php

namespace App\Listeners;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Services\GoogleSheetUpdateService;
use Illuminate\Support\Facades\Log;

class UpdateGoogleSheetOnLeadCreated
{
    protected $sheetUpdateService;

    public function __construct(GoogleSheetUpdateService $sheetUpdateService)
    {
        $this->sheetUpdateService = $sheetUpdateService;
    }

    /**
     * Handle the event - Update Google Sheet when lead is created
     */
    public function handle($event): void
    {
        try {
            $lead = $event->lead ?? null;
            
            if (!$lead) {
                return;
            }

            // Check if lead has sheet reference
            $assignment = LeadAssignment::where('lead_id', $lead->id)
                ->whereNotNull('sheet_config_id')
                ->whereNotNull('sheet_row_number')
                ->first();

            if (!$assignment || !$assignment->sheetConfig) {
                return;
            }

            // Update sheet with lead creation status
            $this->sheetUpdateService->updateLeadStatus(
                $assignment->sheet_config_id,
                $assignment->sheet_row_number,
                [
                    'sent_status' => 'Sent to CRM',
                    'lead_id' => $lead->id,
                ]
            );

            Log::info("Updated Google Sheet on lead creation", [
                'lead_id' => $lead->id,
                'sheet_config_id' => $assignment->sheet_config_id,
                'row_number' => $assignment->sheet_row_number,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update Google Sheet on lead creation", [
                'lead_id' => $lead->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
