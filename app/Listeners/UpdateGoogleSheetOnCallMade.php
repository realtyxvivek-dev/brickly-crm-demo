<?php

namespace App\Listeners;

use App\Events\CallLogCreated;
use App\Models\LeadAssignment;
use App\Services\GoogleSheetUpdateService;
use Illuminate\Support\Facades\Log;

class UpdateGoogleSheetOnCallMade
{
    protected $sheetUpdateService;

    public function __construct(GoogleSheetUpdateService $sheetUpdateService)
    {
        $this->sheetUpdateService = $sheetUpdateService;
    }

    /**
     * Handle the event - Update Google Sheet when call is made
     */
    public function handle(CallLogCreated $event): void
    {
        try {
            $callLog = $event->callLog;
            $lead = $callLog->lead;

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

            // Update sheet with call info
            $this->sheetUpdateService->updateCallInfo(
                $assignment->sheet_config_id,
                $assignment->sheet_row_number,
                [
                    'call_date' => $callLog->start_time->format('Y-m-d H:i:s'),
                    'call_status' => ucfirst(str_replace('_', ' ', $callLog->status ?? 'completed')),
                ]
            );

            Log::info("Updated Google Sheet on call made", [
                'lead_id' => $lead->id,
                'call_log_id' => $callLog->id,
                'sheet_config_id' => $assignment->sheet_config_id,
                'row_number' => $assignment->sheet_row_number,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update Google Sheet on call made", [
                'call_log_id' => $event->callLog->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
