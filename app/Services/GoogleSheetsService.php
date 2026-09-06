<?php

namespace App\Services;

use App\Models\GoogleSheetsConfig;
use App\Models\GoogleSheetsColumnMapping;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFormFieldValue;
use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Services\LeadAssignmentService;
use App\Services\DuplicateDetectionService;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class GoogleSheetsService
{
    protected $assignmentService;
    protected $duplicateService;
    protected $client;

    public function __construct(
        LeadAssignmentService $assignmentService,
        DuplicateDetectionService $duplicateService
    ) {
        $this->assignmentService = $assignmentService;
        $this->duplicateService = $duplicateService;
    }

    /**
     * Get access token from service account JSON file
     */
    public function getGoogleAccessTokenFromServiceAccount(string $jsonPath): string
    {
        // Check cache first (tokens are valid for 1 hour)
        $cacheKey = 'google_access_token_' . md5($jsonPath);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Try to find file in storage or config directory
        $fullPath = null;
        $checkedPaths = [];
        
        if (Storage::exists($jsonPath)) {
            $fullPath = Storage::path($jsonPath);
        } else {
            $checkedPaths[] = Storage::path($jsonPath);
        }
        
        if (!$fullPath && file_exists(config_path($jsonPath))) {
            $fullPath = config_path($jsonPath);
        } else {
            $checkedPaths[] = config_path($jsonPath);
        }
        
        if (!$fullPath && file_exists($jsonPath)) {
            $fullPath = $jsonPath;
        } else {
            $checkedPaths[] = $jsonPath;
        }
        
        if (!$fullPath) {
            $checkedPathsStr = implode(", ", array_filter($checkedPaths));
            throw new \Exception("Service Account JSON file not found. Checked paths: {$checkedPathsStr}. Please verify the file path is correct.");
        }

        // Read and validate JSON
        $jsonContent = file_get_contents($fullPath);
        $serviceAccount = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid service account JSON format");
        }

        if (!isset($serviceAccount['type']) || $serviceAccount['type'] !== 'service_account') {
            throw new \Exception("Invalid service account JSON: type must be 'service_account'");
        }

        if (!isset($serviceAccount['private_key']) || !isset($serviceAccount['client_email']) || !isset($serviceAccount['token_uri'])) {
            throw new \Exception("Invalid service account JSON: missing required fields");
        }

        // Use Google Client to get access token
        $client = new Client();
        $client->setAuthConfig($fullPath);
        $client->addScope('https://www.googleapis.com/auth/spreadsheets');
        
        $accessToken = $client->fetchAccessTokenWithAssertion();
        
        if (isset($accessToken['error'])) {
            throw new \Exception("Failed to get access token: " . $accessToken['error_description']);
        }

        $token = $accessToken['access_token'];
        
        // Cache token for 55 minutes (tokens are valid for 1 hour)
        Cache::put($cacheKey, $token, now()->addMinutes(55));

        return $token;
    }

    /**
     * Fetch data from Google Sheets
     */
    public function fetchSheetData(
        string $sheetId,
        string $sheetName,
        string $range,
        ?string $apiKey = null,
        ?string $serviceAccountPath = null,
        ?int $startRow = null
    ): array {
        // Build range string
        // If startRow is specified, include row number in range
        if ($startRow !== null && $startRow >= 1) {
            // Adjust range to include specific row number
            $rangeParts = explode(':', $range);
            $startCol = $rangeParts[0];
            $endCol = $rangeParts[1] ?? $rangeParts[0];
            $rangeString = "{$sheetName}!{$startCol}{$startRow}:{$endCol}{$startRow}";
        } else {
            // No startRow specified, use range as-is
            $rangeString = "{$sheetName}!{$range}";
        }

        // Validate and sanitize sheet ID
        $sheetId = trim($sheetId);
        if (empty($sheetId)) {
            throw new \Exception("Sheet ID cannot be empty");
        }

        // Validate sheet ID format (should be alphanumeric with dashes/underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $sheetId)) {
            throw new \Exception("Invalid sheet ID format. Sheet ID should only contain letters, numbers, dashes, and underscores.");
        }

        // URL encode the range string to handle special characters in sheet names
        // Note: Sheet ID should NOT be encoded, only the range part
        $encodedRange = rawurlencode($rangeString);
        
        // Build URL - sheet ID goes directly in path, range is URL encoded
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/{$encodedRange}";

        // Try service account first, then API key, then CSV fallback
        $headers = [];
        $serviceAccountError = null;
        if ($serviceAccountPath) {
            try {
                $accessToken = $this->getGoogleAccessTokenFromServiceAccount($serviceAccountPath);
                $headers['Authorization'] = "Bearer {$accessToken}";
            } catch (\Exception $e) {
                $serviceAccountError = $e->getMessage();
                Log::warning("Service account auth failed: " . $serviceAccountError);
                // Continue to try API key or public access
            }
        }

        if (empty($headers) && $apiKey) {
            $url .= "?key={$apiKey}";
        }

        // Fetch data using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function($key, $value) {
            return "{$key}: {$value}";
        }, array_keys($headers), $headers));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode === 400) {
            throw new \Exception("Invalid sheet ID, sheet name, or range");
        }

        if ($httpCode === 403) {
            // If no authentication was provided, try CSV fallback for public sheets
            if (empty($headers) && !$apiKey && !$serviceAccountPath) {
                Log::info("403 error with no auth - trying CSV fallback for public sheet", [
                    'sheet_id' => $sheetId,
                    'sheet_name' => $sheetName,
                ]);
                try {
                    return $this->fetchSheetDataCsv($sheetId, $sheetName);
                } catch (\Exception $e) {
                    Log::warning("CSV fallback also failed: " . $e->getMessage());
                    // Continue to throw the 403 error below
                }
            }
            
            // If we have auth but still got 403, or CSV fallback failed
            $errorDetails = [];
            $solutions = [];
            
            if ($serviceAccountPath && $serviceAccountError) {
                $errorDetails[] = "Service account authentication failed: {$serviceAccountError}";
                $solutions[] = "Check the service account file path and ensure the file exists and is valid";
                $solutions[] = "Ensure the service account email has been granted access to the sheet";
            } elseif ($serviceAccountPath) {
                $errorDetails[] = "Service account doesn't have access to this sheet";
                $solutions[] = "Grant the service account email access to the sheet";
            }
            
            if ($apiKey) {
                $errorDetails[] = "API key authentication failed or API key doesn't have access";
                $solutions[] = "Verify the API key is correct and has Google Sheets API enabled";
            }
            
            if (empty($errorDetails)) {
                $errorDetails[] = "No authentication provided and sheet is not publicly accessible via API";
                $solutions[] = "Share the sheet as 'Anyone with link' (Viewer access) - Note: Google Sheets API v4 requires authentication even for public sheets";
                $solutions[] = "Alternatively, provide an API key or service account for authentication";
            }
            
            $errorMessage = "Access denied. " . implode(". ", $errorDetails);
            if (!empty($solutions)) {
                $errorMessage .= "\n\nSolutions:\n" . implode("\n", array_map(function($s, $i) { return ($i + 1) . ") " . $s; }, $solutions, array_keys($solutions)));
            }
            
            throw new \Exception($errorMessage);
        }

        if ($httpCode === 404) {
            throw new \Exception("Sheet not found. Check sheet ID and sheet name (tab name).");
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode} error";
            throw new \Exception($errorMsg);
        }

        $data = json_decode($response, true);

        if (!isset($data['values']) || empty($data['values'])) {
            // Try CSV fallback for public sheets
            return $this->fetchSheetDataCsv($sheetId, $sheetName);
        }

        return $data['values'];
    }

    /**
     * Fetch data using CSV fallback
     */
    protected function fetchSheetDataCsv(string $sheetId, string $sheetName): array
    {
        $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $csvData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($csvData)) {
            throw new \Exception("Sheet has no data. Check column mapping and ensure data exists.");
        }

        // Parse CSV
        $lines = str_getcsv($csvData, "\n");
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    /**
     * Fetch lightweight metadata needed for incremental import checks.
     * We compute last non-empty row by reading a single anchor column only.
     */
    public function fetchSheetLastRow(
        string $sheetId,
        string $sheetName,
        string $anchorColumn = 'A',
        ?string $apiKey = null,
        ?string $serviceAccountPath = null
    ): int {
        $anchor = strtoupper(trim($anchorColumn));
        if (!preg_match('/^[A-Z]+$/', $anchor)) {
            $anchor = 'A';
        }

        $columnValues = $this->fetchSheetData(
            $sheetId,
            $sheetName,
            "{$anchor}:{$anchor}",
            $apiKey,
            $serviceAccountPath
        );

        // Includes header row when present. Keep minimum 1 (header row baseline).
        return max(count($columnValues), 1);
    }

    /**
     * Sync Google Sheets - incremental sync with row tracking
     */
    public function syncGoogleSheets($configOrId): array
    {
        // Accept either config object or ID
        if (is_numeric($configOrId)) {
            $config = GoogleSheetsConfig::find($configOrId);
            if (!$config) {
                throw new \Exception("Google Sheets configuration not found (ID: {$configOrId})");
            }
        } else {
            $config = $configOrId;
        }

        if (!$config->is_active) {
            throw new \Exception("Google Sheets config is not active");
        }

        // Extract sheet ID if URL provided
        $sheetId = GoogleSheetsConfig::extractSheetId($config->sheet_id);
        if (!$sheetId) {
            throw new \Exception("Invalid sheet ID format");
        }

        // Determine start row
        // Always start from row 2 (skip header) to catch ALL rows including new ones added anywhere in sheet
        // We'll check for duplicates by phone number to avoid re-importing existing leads from the same sheet
        // This ensures we catch new rows added anywhere in the sheet, not just at the end

        // Fetch data - always fetch from row 2 to get all rows (duplicate check will prevent re-importing)
        // This ensures we catch new rows added anywhere in the sheet, not just at the end
        try {
            $rows = $this->fetchSheetData(
                $sheetId,
                $config->sheet_name,
                $config->range,
                $config->api_key,
                $config->service_account_json_path,
                2 // Always start from row 2 (skip header) to catch all rows
            );
            
            Log::info("Google Sheets sync - Fetched rows", [
                'config_id' => $config->id,
                'sheet_name' => $config->sheet_name,
                'sheet_id' => $sheetId,
                'rows_count' => count($rows),
                'start_row' => 2,
                'sync_interval' => $config->sync_interval_minutes,
                'last_sync_at' => $config->last_sync_at?->format('Y-m-d H:i:s'),
                'auto_sync_enabled' => $config->auto_sync_enabled,
            ]);
        } catch (\Exception $e) {
            Log::error("Google Sheets fetch error for config {$config->id}: " . $e->getMessage(), [
                'config_id' => $config->id,
                'sheet_id' => $sheetId,
                'sheet_name' => $config->sheet_name,
            ]);
            throw $e;
        }

        if (empty($rows)) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => [],
                'last_synced_row' => $config->last_synced_row,
            ];
        }

        // Convert column letters to indices for standard fields
        $nameIndex = GoogleSheetsConfig::columnLetterToIndex($config->name_column);
        $phoneIndex = GoogleSheetsConfig::columnLetterToIndex($config->phone_column);

        // Load custom column mappings
        $customMappings = GoogleSheetsColumnMapping::where('google_sheets_config_id', $config->id)
            ->orderBy('display_order')
            ->get()
            ->map(function($mapping) {
                return [
                    'id' => $mapping->id,
                    'sheet_column' => $mapping->sheet_column,
                    'column_index' => GoogleSheetsConfig::columnLetterToIndex($mapping->sheet_column),
                    'lead_field_key' => $mapping->lead_field_key,
                    'field_type' => $mapping->field_type,
                    'field_label' => $mapping->field_label,
                    'is_required' => $mapping->is_required,
                ];
            });

        // If mappings include explicit Name/Phone columns (common for Meta/Facebook setup),
        // prefer those over legacy name_column/phone_column defaults (A/B).
        $mappedName = $customMappings->first(fn ($m) => ($m['lead_field_key'] ?? null) === 'name');
        $mappedPhone = $customMappings->first(fn ($m) => ($m['lead_field_key'] ?? null) === 'phone');
        if ($mappedName && isset($mappedName['column_index'])) {
            $nameIndex = $mappedName['column_index'];
        }
        if ($mappedPhone && isset($mappedPhone['column_index'])) {
            $phoneIndex = $mappedPhone['column_index'];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $lastSuccessfulRow = 1; // Track highest row processed (will be updated as we process rows)

        // Create import batch
        $batch = ImportBatch::create([
            'user_id' => $config->created_by,
            'source_type' => 'google_sheets',
            'google_sheet_id' => $sheetId,
            'google_sheet_name' => $config->sheet_name,
            'total_leads' => count($rows),
            'status' => 'processing',
            'assignment_rule_id' => $config->assignment_rule_id,
        ]);

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $currentRow = 2 + $rowIndex; // Row 2 is first data row (row 1 is header), rowIndex starts at 0

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                try {
                    // Extract standard data
                    $name = trim($row[$nameIndex] ?? '');
                    $phone = trim($row[$phoneIndex] ?? '');
                    
                    // Extract custom column values
                    $customFieldValues = [];
                    foreach ($customMappings as $mapping) {
                        $columnValue = trim($row[$mapping['column_index']] ?? '');
                        if (!empty($columnValue)) {
                            $customFieldValues[] = [
                                'field_key' => $mapping['lead_field_key'],
                                'field_value' => $columnValue,
                                'field_type' => $mapping['field_type'],
                                'field_label' => $mapping['field_label'],
                            ];
                        }
                    }

                    // Log processing for debugging (especially for specific leads)
                    if (stripos($name, 'Avtar') !== false || stripos($name, 'Singh') !== false || empty($name)) {
                        Log::info("Row {$currentRow} - Processing lead", [
                            'name' => $name,
                            'phone' => $phone,
                            'row_number' => $currentRow,
                            'config_id' => $config->id,
                            'sheet_name' => $config->sheet_name,
                        ]);
                    }

                    // Validate required fields
                    if (empty($name) || empty($phone)) {
                        $skipped++;
                        $errorMsg = "Row {$currentRow}: Missing name or phone (Name: '{$name}', Phone: '{$phone}')";
                        $errors[] = $errorMsg;
                        Log::warning($errorMsg, ['row' => $currentRow, 'row_data' => $row]);
                        continue;
                    }

                    // Sanitize and validate phone
                    $originalPhone = $phone;
                    $phone = $this->duplicateService->sanitizePhone($phone);
                    if (!$this->duplicateService->isValidPhone($phone)) {
                        $skipped++;
                        $errorMsg = "Row {$currentRow}: Invalid phone number (Original: '{$originalPhone}', Sanitized: '{$phone}')";
                        $errors[] = $errorMsg;
                        Log::warning($errorMsg, ['row' => $currentRow, 'original_phone' => $originalPhone, 'sanitized_phone' => $phone]);
                        continue;
                    }

                    // Check if blacklisted
                    if ($this->duplicateService->isBlacklisted($phone)) {
                        $skipped++;
                        $errorMsg = "Row {$currentRow}: Phone number is blacklisted ({$phone})";
                        $errors[] = $errorMsg;
                        Log::info("Row {$currentRow}: Blacklisted number skipped", ['phone' => $phone]);
                        continue;
                    }

                    $existingLead = $this->duplicateService->findExistingLeadByPhone($phone);
                    if ($existingLead) {
                        // Extract sheet ID for comparison
                        $currentSheetId = GoogleSheetsConfig::extractSheetId($config->sheet_id);
                        
                        // Check if this lead was already imported from this specific sheet
                        // We check both ImportedLead and also check if the lead has source tracking from this sheet
                        $alreadyImported = ImportedLead::where('lead_id', $existingLead->id)
                            ->whereHas('importBatch', function($q) use ($currentSheetId, $config) {
                                $q->where('google_sheet_id', $currentSheetId)
                                  ->where('google_sheet_name', $config->sheet_name);
                            })
                            ->exists();
                        
                        // Also check source_sheet_id in lead_form_field_values
                        if (!$alreadyImported) {
                            $sourceSheetId = LeadFormFieldValue::where('lead_id', $existingLead->id)
                                ->where('field_key', 'source_sheet_id')
                                ->where('field_value', $currentSheetId)
                                ->exists();
                            
                            if ($sourceSheetId) {
                                $alreadyImported = true;
                            }
                        }

                        // Also check LeadAssignment sheet linkage (covers imports done via API / meta sync)
                        if (!$alreadyImported) {
                            $linkedViaAssignment = LeadAssignment::where('lead_id', $existingLead->id)
                                ->where('sheet_config_id', $config->id)
                                ->exists();
                            if ($linkedViaAssignment) {
                                $alreadyImported = true;
                            }
                        }
                        
                        if ($alreadyImported) {
                            $skipped++;
                            $errorMsg = "Row {$currentRow}: Duplicate phone number ({$phone}) - already imported from this sheet";
                            $errors[] = $errorMsg;
                            Log::info($errorMsg, [
                                'row' => $currentRow, 
                                'phone' => $phone, 
                                'lead_id' => $existingLead->id,
                                'sheet_id' => $currentSheetId,
                                'sheet_name' => $config->sheet_name,
                                'name' => $name,
                            ]);
                            continue;
                        }
                        app(LeadReenquiryService::class)->markGenericReenquiry($existingLead, 'sheet', $config->created_by);
                        LeadFormFieldValue::updateOrCreate(
                            ['lead_id' => $existingLead->id, 'field_key' => 'source_sheet_id'],
                            ['field_value' => $currentSheetId, 'filled_at' => now()]
                        );
                        LeadFormFieldValue::updateOrCreate(
                            ['lead_id' => $existingLead->id, 'field_key' => 'source_sheet_name'],
                            ['field_value' => $config->sheet_name, 'filled_at' => now()]
                        );
                        $skipped++;
                        Log::info("Row {$currentRow}: Existing phone recorded as a sheet re-enquiry", [
                            'existing_lead_id' => $existingLead->id,
                            'current_sheet_id' => $currentSheetId,
                            'name' => $name,
                        ]);
                        continue;
                    }

                    // Create lead
                    $lead = Lead::create([
                        'name' => $name,
                        'phone' => $phone,
                        'source' => Lead::normalizeSource('google_sheets'),
                        'status' => 'new',
                        'created_by' => $config->created_by,
                    ]);

                    // Store custom field values in lead_form_field_values table
                    foreach ($customFieldValues as $fieldData) {
                        LeadFormFieldValue::updateOrCreate(
                            [
                                'lead_id' => $lead->id,
                                'field_key' => $fieldData['field_key'],
                            ],
                            [
                                'field_value' => $fieldData['field_value'],
                                'filled_at' => now(),
                            ]
                        );
                    }
                    
                    // Store source tracking information
                    LeadFormFieldValue::updateOrCreate(
                        [
                            'lead_id' => $lead->id,
                            'field_key' => 'source_sheet_name',
                        ],
                        [
                            'field_value' => $config->sheet_name,
                            'filled_at' => now(),
                        ]
                    );
                    LeadFormFieldValue::updateOrCreate(
                        [
                            'lead_id' => $lead->id,
                            'field_key' => 'source_sheet_id',
                        ],
                        [
                            'field_value' => $config->sheet_id,
                            'filled_at' => now(),
                        ]
                    );
                    LeadFormFieldValue::updateOrCreate(
                        [
                            'lead_id' => $lead->id,
                            'field_key' => 'source_row_number',
                        ],
                        [
                            'field_value' => (string)$currentRow,
                            'filled_at' => now(),
                        ]
                    );

                    // Assign lead using new assignment system
                    $assignedTo = null;
                    try {
                        // Use new LeadAssignmentService with sheet config
                        $newAssignmentService = app(\App\Services\LeadAssignmentService::class);
                        $assignedTo = $newAssignmentService->assignLead($lead, $config->id, $config->created_by);
                        
                        if ($assignedTo) {
                            // Update assignment with sheet tracking info
                            LeadAssignment::where('lead_id', $lead->id)
                                ->where('assigned_to', $assignedTo)
                                ->where('is_active', true)
                                ->update([
                                    'sheet_config_id' => $config->id,
                                    'sheet_row_number' => $currentRow,
                                ]);
                            
                            Log::info("Lead assigned successfully during import", [
                                'lead_id' => $lead->id,
                                'assigned_to' => $assignedTo,
                                'sheet_config_id' => $config->id,
                                'row' => $currentRow,
                            ]);
                        } else {
                            Log::warning("Lead assignment failed during import - no user assigned", [
                                'lead_id' => $lead->id,
                                'sheet_config_id' => $config->id,
                                'row' => $currentRow,
                                'name' => $name,
                                'phone' => $phone,
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Check if it's a Pusher error - if so, assignment might still have succeeded
                        $isPusherError = strpos($e->getMessage(), 'Pusher') !== false;
                        
                        if ($isPusherError) {
                            // Pusher errors shouldn't prevent assignment - check if assignment actually happened
                            $lead->refresh();
                            $actualAssignment = $lead->activeAssignments()->first();
                            if ($actualAssignment && $actualAssignment->assigned_to) {
                                // Assignment succeeded despite Pusher error
                                $assignedTo = $actualAssignment->assigned_to;
                                Log::warning("Assignment succeeded but Pusher broadcast failed", [
                                    'lead_id' => $lead->id,
                                    'assigned_to' => $assignedTo,
                                    'sheet_config_id' => $config->id,
                                    'row' => $currentRow,
                                    'pusher_error' => $e->getMessage(),
                                ]);
                            } else {
                                // Assignment actually failed
                                Log::error("Assignment failed for lead {$lead->id}: " . $e->getMessage(), [
                                    'lead_id' => $lead->id,
                                    'sheet_config_id' => $config->id,
                                    'row' => $currentRow,
                                    'exception' => $e->getTraceAsString(),
                                ]);
                            }
                        } else {
                            // Non-Pusher error - log as error
                            Log::error("Assignment error for lead {$lead->id}: " . $e->getMessage(), [
                                'lead_id' => $lead->id,
                                'sheet_config_id' => $config->id,
                                'row' => $currentRow,
                                'exception' => $e->getTraceAsString(),
                            ]);
                        }
                    }

                    // Track imported lead
                    $importedLead = ImportedLead::create([
                        'import_batch_id' => $batch->id,
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedTo,
                        'assigned_at' => $assignedTo ? now() : null,
                        'import_data' => [
                            'name' => $name,
                            'phone' => $phone,
                            'row' => $currentRow,
                            'custom_fields' => $customFieldValues,
                        ],
                    ]);
                    
                    // If assignment happened but ImportedLead.assigned_to is null, update it
                    // This handles cases where assignment happens after ImportedLead creation
                    // Also refresh to get latest assignment status
                    $lead->refresh();
                    $actualAssignment = $lead->activeAssignments()->first();
                    if ($actualAssignment && $actualAssignment->assigned_to) {
                        // Update ImportedLead if it doesn't match actual assignment
                        if ($importedLead->assigned_to != $actualAssignment->assigned_to) {
                            $importedLead->update([
                                'assigned_to' => $actualAssignment->assigned_to,
                                'assigned_at' => $actualAssignment->assigned_at ?? now(),
                            ]);
                            Log::info("Updated ImportedLead.assigned_to from LeadAssignment", [
                                'imported_lead_id' => $importedLead->id,
                                'lead_id' => $lead->id,
                                'old_assigned_to' => $importedLead->getOriginal('assigned_to'),
                                'new_assigned_to' => $actualAssignment->assigned_to,
                            ]);
                        }
                    } else if (!$assignedTo && !$actualAssignment) {
                        // No assignment happened - log for debugging
                        Log::warning("Lead imported but not assigned", [
                            'imported_lead_id' => $importedLead->id,
                            'lead_id' => $lead->id,
                            'sheet_config_id' => $config->id,
                            'row' => $currentRow,
                        ]);
                    }

                    $imported++;
                    $lastSuccessfulRow = $currentRow;
                    
                    // Log successful import
                    Log::info("Row {$currentRow} - Lead imported successfully", [
                        'lead_id' => $lead->id,
                        'name' => $lead->name,
                        'phone' => $lead->phone,
                        'assigned_to' => $assignedTo,
                        'row_number' => $currentRow,
                    ]);

                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Row {$currentRow}: " . $e->getMessage();
                    Log::error("Lead import error for row {$currentRow}: " . $e->getMessage());
                }
            }

            // Update config with last synced row
            // Track the highest row number we processed
            // Since we process all rows, maxRowProcessed will be the last row in the sheet
            $maxRowProcessed = $lastSuccessfulRow > 1 ? $lastSuccessfulRow : (count($rows) > 0 ? count($rows) + 1 : 1);
            
            // Always update last_sync_at to track sync attempts
            // Update last_synced_row to the highest row we've seen (for reference, but we always check all rows)
            $config->update([
                'last_sync_at' => now(),
                'last_synced_row' => $maxRowProcessed,
            ]);
            
            Log::info("Google Sheets sync completed", [
                'config_id' => $config->id,
                'sheet_name' => $config->sheet_name,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => count($errors),
                'total_rows_fetched' => count($rows),
                'max_row_processed' => $maxRowProcessed,
                'last_sync_at' => now()->format('Y-m-d H:i:s'),
            ]);

            // Update batch
            $batch->update([
                'imported_leads' => $imported,
                'failed_leads' => $skipped,
                'status' => 'completed',
                'error_log' => array_slice($errors, 0, 20), // Limit to first 20 errors
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $batch->update([
                'status' => 'failed',
                'error_log' => array_merge($errors, [$e->getMessage()]),
            ]);
            throw $e;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
            'last_synced_row' => $lastSuccessfulRow,
        ];
    }

    /**
     * Update Google Sheet status and notes (two-way sync)
     */
    public function updateGoogleSheetStatus(
        int $sheetConfigId,
        int $rowNumber,
        string $status,
        ?string $notes = null,
        ?string $username = null
    ): bool {
        // Sync back functionality removed - status_column and notes_column_sync are no longer used
        // This method is kept for backward compatibility but returns false
        Log::info("Sync back to Google Sheets is disabled - status_column and notes_column_sync removed");
        return false;
    }
}
