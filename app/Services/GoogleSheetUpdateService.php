<?php

namespace App\Services;

use App\Models\GoogleSheetsConfig;
use App\Models\LeadAssignment;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class GoogleSheetUpdateService
{
    protected $sheetsService;

    public function __construct(GoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
    }

    /**
     * Update multiple columns in Google Sheet
     */
    public function updateMultipleColumns(int $sheetConfigId, int $rowNumber, array $updates): bool
    {
        try {
            $config = GoogleSheetsConfig::find($sheetConfigId);
            if (!$config || !$config->is_active) {
                Log::warning("Sheet config not found or inactive", ['config_id' => $sheetConfigId]);
                return false;
            }

            $sheetId = $config->sheet_id;
            $sheetName = $config->sheet_name;

            // Get CRM status columns mapping
            $statusColumns = $config->crm_status_columns_json ?? [];

            // Prepare batch update
            $batchUpdates = [];
            foreach ($updates as $columnName => $value) {
                if (isset($statusColumns[$columnName])) {
                    $columnLetter = $statusColumns[$columnName];
                    $range = "{$sheetName}!{$columnLetter}{$rowNumber}";
                    $batchUpdates[] = [
                        'range' => $range,
                        'values' => [[$value]],
                    ];
                }
            }

            if (empty($batchUpdates)) {
                Log::warning("No valid columns to update", ['config_id' => $sheetConfigId, 'updates' => $updates]);
                return false;
            }

            // Authenticate and update
            $client = $this->getAuthenticatedClient($config);
            $service = new Sheets($client);

            $body = new \Google\Service\Sheets\BatchUpdateValuesRequest([
                'valueInputOption' => 'RAW',
                'data' => array_map(function ($update) {
                    return new \Google\Service\Sheets\ValueRange([
                        'range' => $update['range'],
                        'values' => $update['values'],
                    ]);
                }, $batchUpdates),
            ]);

            $service->spreadsheets_values->batchUpdate($sheetId, $body);

            Log::info("Updated Google Sheet columns", [
                'config_id' => $sheetConfigId,
                'row_number' => $rowNumber,
                'updates' => $updates,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to update Google Sheet", [
                'config_id' => $sheetConfigId,
                'row_number' => $rowNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update lead status in Google Sheet
     */
    public function updateLeadStatus(int $sheetConfigId, int $rowNumber, array $statusData): bool
    {
        $updates = [];
        
        if (isset($statusData['sent_status'])) {
            $updates['crm_sent_status'] = $statusData['sent_status'];
        }
        
        if (isset($statusData['lead_id'])) {
            $updates['crm_lead_id'] = $statusData['lead_id'];
        }

        return $this->updateMultipleColumns($sheetConfigId, $rowNumber, $updates);
    }

    /**
     * Update assigned user in Google Sheet
     */
    public function updateAssignedUser(int $sheetConfigId, int $rowNumber, string $userName): bool
    {
        return $this->updateMultipleColumns($sheetConfigId, $rowNumber, [
            'crm_assigned_user' => $userName,
        ]);
    }

    /**
     * Update call info in Google Sheet
     */
    public function updateCallInfo(int $sheetConfigId, int $rowNumber, array $callLog): bool
    {
        $updates = [];
        
        if (isset($callLog['call_date'])) {
            $updates['crm_call_date'] = $callLog['call_date'];
        }
        
        if (isset($callLog['call_status'])) {
            $updates['crm_call_status'] = $callLog['call_status'];
        }

        return $this->updateMultipleColumns($sheetConfigId, $rowNumber, $updates);
    }

    /**
     * Update error status in Google Sheet
     */
    public function updateErrorStatus(int $sheetConfigId, int $rowNumber, string $error): bool
    {
        return $this->updateMultipleColumns($sheetConfigId, $rowNumber, [
            'crm_sent_status' => 'Error: ' . $error,
        ]);
    }

    /**
     * Get authenticated Google Client
     */
    protected function getAuthenticatedClient(GoogleSheetsConfig $config): Client
    {
        $client = new Client();
        
        if ($config->service_account_json_path) {
            // Use service account
            $jsonPath = $config->service_account_json_path;
            $fullPath = null;
            
            if (Storage::exists($jsonPath)) {
                $fullPath = Storage::path($jsonPath);
            } elseif (file_exists(storage_path('app/' . $jsonPath))) {
                $fullPath = storage_path('app/' . $jsonPath);
            } elseif (file_exists($jsonPath)) {
                $fullPath = $jsonPath;
            }
            
            if ($fullPath && file_exists($fullPath)) {
                $client->setAuthConfig($fullPath);
                $client->addScope('https://www.googleapis.com/auth/spreadsheets');
                return $client;
            }
        }
        
        // Fallback: try to use API key (limited functionality)
        if ($config->api_key) {
            $client->setDeveloperKey($config->api_key);
        }
        
        return $client;
    }

    /**
     * Get sheet column index by name
     */
    public function getSheetColumnIndex(string $sheetId, string $columnName): ?int
    {
        try {
            // This would require fetching headers first
            // For now, return null and use column letters
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get column index", [
                'sheet_id' => $sheetId,
                'column_name' => $columnName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get sheet column letter by name
     */
    public function getSheetColumnLetter(string $sheetId, string $columnName): ?string
    {
        try {
            // This would require fetching headers and finding column
            // For now, return null and use direct column letters from config
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get column letter", [
                'sheet_id' => $sheetId,
                'column_name' => $columnName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
