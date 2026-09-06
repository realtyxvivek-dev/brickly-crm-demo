<?php

namespace App\Listeners;

use App\Services\GoogleSheetUpdateService;
use Illuminate\Support\Facades\Log;

class UpdateGoogleSheetOnLeadError
{
    protected $sheetUpdateService;

    public function __construct(GoogleSheetUpdateService $sheetUpdateService)
    {
        $this->sheetUpdateService = $sheetUpdateService;
    }

    /**
     * Handle the event - Update Google Sheet when lead creation fails
     */
    public function handle($event): void
    {
        try {
            $sheetConfigId = $event->sheet_config_id ?? null;
            $rowNumber = $event->sheet_row_number ?? null;
            $error = $event->error ?? 'Unknown error';

            if (!$sheetConfigId || !$rowNumber) {
                return;
            }

            // Update sheet with error status
            $this->sheetUpdateService->updateErrorStatus(
                $sheetConfigId,
                $rowNumber,
                $error
            );

            Log::info("Updated Google Sheet with error status", [
                'sheet_config_id' => $sheetConfigId,
                'row_number' => $rowNumber,
                'error' => $error,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update Google Sheet with error", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
