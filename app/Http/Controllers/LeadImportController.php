<?php

namespace App\Http\Controllers;

use App\Models\GoogleSheetsConfig;
use App\Models\GoogleSheetsColumnMapping;
use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Services\LeadImportService;
use App\Services\GoogleSheetsService;
use App\Services\OldCrmImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadImportController extends Controller
{
    protected $importService;
    protected $sheetsService;
    protected $oldCrmImportService;

    public function __construct(
        LeadImportService $importService,
        GoogleSheetsService $sheetsService,
        OldCrmImportService $oldCrmImportService
    ) {
        $this->importService = $importService;
        $this->sheetsService = $sheetsService;
        $this->oldCrmImportService = $oldCrmImportService;
    }
    
    /**
     * Convert index to column letter (0=A, 1=B, ..., 25=Z, 26=AA, 27=AB, etc.)
     */
    private static function indexToColumnLetter(int $index): string
    {
        $result = '';
        $index++; // Convert to 1-based
        
        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)) . $result;
            $index = intval($index / 26);
        }
        
        return $result;
    }

    /**
     * Lead Import Dashboard
     */
    public function index()
    {
        $configs = GoogleSheetsConfig::where('created_by', auth()->id())
            ->where('is_active', true)
            ->latest()
            ->get();

        $recentImports = ImportBatch::where('user_id', auth()->id())
            ->with('importProfile')
            ->latest()
            ->limit(10)
            ->get();

        // Get last 5 imported leads with actual assignments
        $recentImportedLeads = ImportedLead::whereHas('importBatch', function($query) {
                $query->where('user_id', auth()->id());
            })
            ->with([
                'lead.activeAssignments.assignedTo', // Load actual assignments
                'assignedTo', // Load ImportedLead.assigned_to
                'importBatch'
            ])
            ->latest()
            ->limit(5)
            ->get();

        $stats = [
            'total_imports' => ImportBatch::where('user_id', auth()->id())->count(),
            'total_leads_imported' => ImportBatch::where('user_id', auth()->id())->sum('imported_leads'),
            'pending_imports' => ImportBatch::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'processing'])->count(),
            'failed_imports' => ImportBatch::where('user_id', auth()->id())
                ->where('status', 'failed')->count(),
        ];

        return view('lead-import.index', compact('configs', 'recentImports', 'recentImportedLeads', 'stats'));
    }

    /**
     * Get Google Sheets Config
     */
    public function getGoogleSheetsConfig(Request $request)
    {
        $configId = $request->get('config_id');

        if ($configId) {
            $config = GoogleSheetsConfig::with('columnMappings')
                ->where('id', $configId)
                ->where('created_by', auth()->id())
                ->firstOrFail();

            // Remove sensitive data
            $configData = $config->toArray();
            unset($configData['api_key'], $configData['refresh_token'], $configData['service_account_json_path']);
            
            // Add column mappings
            $configData['column_mappings'] = $config->columnMappings->map(function($mapping) {
                return [
                    'id' => $mapping->id,
                    'sheet_column' => $mapping->sheet_column,
                    'lead_field_key' => $mapping->lead_field_key,
                    'field_type' => $mapping->field_type,
                    'field_label' => $mapping->field_label,
                    'is_required' => $mapping->is_required,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'config' => $configData,
            ]);
        }

        // Return all active configs
        $configs = GoogleSheetsConfig::where('created_by', auth()->id())
            ->where('is_active', true)
            ->get()
            ->map(function ($config) {
                $data = $config->toArray();
                unset($data['api_key'], $data['refresh_token'], $data['service_account_json_path']);
                return $data;
            });

        return response()->json([
            'success' => true,
            'configs' => $configs,
        ]);
    }

    /**
     * Save Google Sheets Config
     */
    public function saveGoogleSheetsConfig(Request $request)
    {
        $request->validate([
            'config_id' => 'nullable|exists:google_sheets_config,id',
            'sheet_id' => 'required|string',
            'sheet_name' => 'required|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'service_account_json_path' => 'nullable|string|max:500',
            'range' => 'required|string|max:50',
            'name_column' => 'required|string|size:1|regex:/^[A-Z]$/',
            'phone_column' => 'required|string|size:1|regex:/^[A-Z]$/',
            'auto_sync_enabled' => 'boolean',
            'sync_interval_minutes' => 'required|integer|min:1',
            'automation_id' => 'nullable',
            'custom_columns_json' => 'nullable|string',
        ]);

        try {
            // Extract sheet ID from URL if needed
            $sheetId = GoogleSheetsConfig::extractSheetId($request->sheet_id);
            if (!$sheetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sheet ID format. Please provide a valid Google Sheet ID or URL.',
                ], 400);
            }

            $data = $request->only([
                'sheet_name',
                'api_key',
                'service_account_json_path',
                'range',
                'name_column',
                'phone_column',
                'auto_sync_enabled',
                'sync_interval_minutes',
                'automation_id',
            ]);

            // Apply defaults if not provided
            $data['auto_sync_enabled'] = $request->boolean('auto_sync_enabled', true);
            $data['sync_interval_minutes'] = $data['sync_interval_minutes'] ?? 2;

            $data['sheet_id'] = $sheetId;
            $data['created_by'] = auth()->id();

            DB::beginTransaction();
            try {
                if ($request->config_id) {
                    $config = GoogleSheetsConfig::where('id', $request->config_id)
                        ->where('created_by', auth()->id())
                        ->firstOrFail();
                    $config->update($data);
                } else {
                    $config = GoogleSheetsConfig::create($data);
                }
                
                // Handle custom column mappings
                if ($request->has('custom_columns_json') && $request->custom_columns_json) {
                    $customColumns = json_decode($request->custom_columns_json, true);
                    
                    if (is_array($customColumns)) {
                        // Get existing mapping IDs to keep
                        $existingIds = collect($customColumns)->pluck('id')->filter()->toArray();
                        
                        // Delete mappings that are not in the new list
                        GoogleSheetsColumnMapping::where('google_sheets_config_id', $config->id)
                            ->whereNotIn('id', $existingIds)
                            ->delete();
                        
                        // Create or update mappings
                        foreach ($customColumns as $index => $columnData) {
                            if (empty($columnData['sheet_column']) || empty($columnData['lead_field_key']) || empty($columnData['field_label'])) {
                                continue;
                            }
                            
                            $mappingData = [
                                'google_sheets_config_id' => $config->id,
                                'sheet_column' => strtoupper($columnData['sheet_column']),
                                'lead_field_key' => $columnData['lead_field_key'],
                                'field_type' => $columnData['field_type'] ?? 'custom',
                                'field_label' => $columnData['field_label'],
                                'is_required' => $columnData['is_required'] ?? false,
                                'display_order' => $index,
                            ];
                            
                            if (!empty($columnData['id'])) {
                                GoogleSheetsColumnMapping::where('id', $columnData['id'])
                                    ->where('google_sheets_config_id', $config->id)
                                    ->update($mappingData);
                            } else {
                                GoogleSheetsColumnMapping::create($mappingData);
                            }
                        }
                    }
                } else {
                    // If no custom columns JSON provided, delete all existing custom mappings
                    GoogleSheetsColumnMapping::where('google_sheets_config_id', $config->id)->delete();
                }
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Reload config with relationships
            $config->load('columnMappings');
            
            // Remove sensitive data from response
            $configData = $config->toArray();
            unset($configData['api_key'], $configData['refresh_token'], $configData['service_account_json_path']);
            
            // Add column mappings to response
            $configData['column_mappings'] = $config->columnMappings->map(function($mapping) {
                return [
                    'id' => $mapping->id,
                    'sheet_column' => $mapping->sheet_column,
                    'lead_field_key' => $mapping->lead_field_key,
                    'field_type' => $mapping->field_type,
                    'field_label' => $mapping->field_label,
                    'is_required' => $mapping->is_required,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'config' => $configData,
                'message' => $request->config_id ? 'Configuration updated successfully.' : 'Configuration created successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error("Error saving Google Sheets config: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get All Google Sheets Configs
     */
    public function getAllGoogleSheetsConfigs()
    {
        $configs = GoogleSheetsConfig::where('created_by', auth()->id())
            ->where('is_active', true)
            ->with(['assignmentRule', 'creator'])
            ->latest()
            ->get()
            ->map(function ($config) {
                $data = $config->toArray();
                unset($data['api_key'], $data['refresh_token'], $data['service_account_json_path']);
                return $data;
            });

        return response()->json([
            'success' => true,
            'configs' => $configs,
        ]);
    }

    /**
     * Delete Google Sheets Config
     */
    public function deleteGoogleSheetsConfig($id)
    {
        try {
            $config = GoogleSheetsConfig::where('id', $id)
                ->where('created_by', auth()->id())
                ->firstOrFail();

            // Soft delete
            $config->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Configuration deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete configuration.',
            ], 500);
        }
    }

    /**
     * Fetch Sheet Headers (First Row)
     */
    public function fetchSheetHeaders(Request $request)
    {
        $request->validate([
            'sheet_id' => 'required|string',
            'sheet_name' => 'required|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'service_account_json_path' => 'nullable|string|max:500',
        ]);

        try {
            // Extract sheet ID from URL if needed
            $sheetId = GoogleSheetsConfig::extractSheetId($request->sheet_id);
            if (!$sheetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sheet ID format. Please provide a valid Google Sheet ID or URL.',
                ], 400);
            }

            // Fetch only row 1 (headers)
            $headers = $this->sheetsService->fetchSheetData(
                $sheetId,
                $request->sheet_name,
                'A:Z', // Default range
                $request->api_key,
                $request->service_account_json_path,
                1 // Start from row 1
            );

            if (empty($headers) || !isset($headers[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No headers found in the sheet. Please check the sheet name and ensure row 1 contains column headers.',
                ], 404);
            }

            // Get first row as headers
            $headerRow = $headers[0];
            
            // Convert to column format with positions
            $columns = [];
            
            foreach ($headerRow as $index => $headerText) {
                // Convert index to column letter (A=0, B=1, ..., Z=25, AA=26, AB=27, etc.)
                $columnLetter = self::indexToColumnLetter($index);
                
                $columns[] = [
                    'position' => $columnLetter,
                    'header' => trim($headerText ?? ''),
                    'index' => $index,
                ];
            }

            return response()->json([
                'success' => true,
                'columns' => $columns,
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching sheet headers: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch headers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync Google Sheets
     */
    public function syncGoogleSheets(Request $request)
    {
        $request->validate([
            'config_id' => 'required|exists:google_sheets_config,id',
        ]);

        try {
            $config = GoogleSheetsConfig::where('id', $request->config_id)
                ->where('created_by', auth()->id())
                ->where('is_active', true)
                ->firstOrFail();

            $result = $this->sheetsService->syncGoogleSheets($config);

            return response()->json([
                'success' => true,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'total_rows' => $result['imported'] + $result['skipped'],
                'errors' => $result['errors'],
                'is_complete' => empty($result['errors']),
                'last_synced_row' => $result['last_synced_row'],
                'message' => "Successfully imported {$result['imported']} leads. {$result['skipped']} skipped.",
            ]);

        } catch (\Exception $e) {
            Log::error("Google Sheets sync error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show CSV Import Form
     */
    public function showCsvForm()
    {
        $automations = collect();
        return view('lead-import.csv', compact('automations'));
    }

    public function showSimpleForm(Request $request)
    {
        $simpleImportContext = $this->importService->getSimpleImportContext($request->user()->id);

        return view('lead-import.simple', compact('simpleImportContext'));
    }

    public function downloadSimpleSample(): StreamedResponse
    {
        $rows = [
            ['Created On', 'FirstName', 'Lead Stage', 'Owner', 'Phone Number', 'Lead Source'],
            ['14-Nov-2025 10:30 AM', 'Namita Tripathi', 'Follow Up', 'Omkar Yadav', '9920368440', 'FB Lead Ads'],
            ['15-Nov-2025 11:00 AM', 'Vinod Kumar Shukla', 'Site Visit Pending', 'Omkar Yadav', '9415347186', 'FB Lead Ads'],
            ['16-Nov-2025 04:15 PM', 'Rohit Anand', 'Visit Done', 'Omkar Yadav', '9956275256', 'FB Lead Ads'],
            ['17-Nov-2025 12:00 PM', 'Dr. Mohammad Zeeshan Hakim', 'Opportunity', 'Omkar Yadav', '7355775573', 'Organic Search'],
            ['18-Nov-2025 09:45 AM', 'Ajit Lic', 'CNP', 'Omkar Yadav', '9838778845', 'Meta'],
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'simple-import-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function showOldCrmForm(Request $request)
    {
        $wizardContext = $this->oldCrmImportService->getWizardContext($request->user()->id);

        return view('lead-import.old-crm', compact('wizardContext'));
    }

    /**
     * Import CSV
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            'stage_filter_mode' => 'nullable|in:include,exclude',
            'selected_stages' => 'nullable|array',
            'selected_stages.*' => 'nullable|string|max:255',
            'import_mode' => 'nullable|in:all,demo',
        ]);

        try {
            $file = $request->file('csv_file');
            
            // Parse CSV
            $leads = $this->importService->parseCsvFile($file);

            if (empty($leads)) {
                return back()->withErrors(['csv_file' => 'No valid leads found in CSV file.']);
            }

            // Store file
            $fileName = 'imports/' . time() . '_' . $file->getClientOriginalName();
            Storage::putFileAs('public', $file, $fileName);

            // Import leads
            $result = $this->importService->importFromCsv(
                $leads,
                $request->user()->id,
                null,
                [
                    'stage_filter_mode' => $request->input('stage_filter_mode', 'include'),
                    'selected_stages' => $request->input('selected_stages', []),
                    'import_mode' => $request->input('import_mode', 'all'),
                ]
            );

            $batch = $result['batch'];
            $batch->update(['file_name' => $fileName]);

            $successPrefix = ($request->input('import_mode') === 'demo')
                ? 'Demo import completed.'
                : 'Import completed.';

            return redirect()
                ->route('lead-import.index')
                ->with(
                    'success',
                    "{$successPrefix} Imported {$batch->imported_leads} leads, skipped {$result['skipped_by_filter']} by stage filter, skipped {$result['skipped_duplicates']} duplicates, {$batch->failed_leads} failed."
                );

        } catch (\Exception $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }
    }

    public function previewSimpleImport(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480',
        ]);

        try {
            $analysis = $this->importService->analyzeSimpleImportFile(
                $request->file('import_file'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Throwable $e) {
            Log::error('Simple import start failed', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importSimple(Request $request)
    {
        return redirect()
            ->route('lead-import.simple')
            ->withErrors(['import_file' => 'Simple Import ab progress mode me chalta hai. Page refresh karke Import Leads button se dobara run karo.']);
    }

    public function startSimpleImport(Request $request)
    {
        $request->validate([
            'file_token' => 'required|string',
            'original_file_name' => 'nullable|string|max:255',
            'column_mapping' => 'required',
            'owner_mapping' => 'nullable',
            'stage_mapping' => 'nullable',
            'row_overrides' => 'nullable',
            'duplicate_policy' => 'nullable|in:skip',
            'create_calling_task' => 'nullable|boolean',
        ]);

        try {
            $result = $this->importService->startSimpleImport(
                $request->input('file_token'),
                $request->user()->id,
                $this->decodeJsonInput($request->input('column_mapping')),
                $request->input('duplicate_policy', 'skip'),
                $request->input('original_file_name'),
                $request->boolean('create_calling_task'),
                $this->decodeJsonInput($request->input('owner_mapping', '{}')),
                $this->decodeJsonInput($request->input('stage_mapping', '{}')),
                $this->decodeJsonInput($request->input('row_overrides', '{}'))
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Simple import process failed', [
                'user_id' => $request->user()?->id,
                'batch_id' => $request->input('batch_id'),
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function processSimpleImport(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|integer',
        ]);

        try {
            $result = $this->importService->processSimpleImportChunk(
                (int) $request->input('batch_id'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview CSV
     */
    public function previewCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $file = $request->file('csv_file');
            $analysis = $this->importService->analyzeCsvFile($file);
            $leads = $analysis['leads'];

            return response()->json([
                'success' => true,
                'total' => count($leads),
                'preview' => array_slice($leads, 0, 10), // First 10 rows
                'stage_summary' => $analysis['stage_summary'] ?? [],
                'has_stage_column' => $analysis['has_stage_column'] ?? false,
                'detected_columns' => $analysis['detected_columns'] ?? [],
                'duplicate_phones_in_file' => $analysis['duplicate_phones_in_file'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function analyzeOldCrm(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->oldCrmImportService->analyzeUploadedFile(
                    $request->file('csv_file'),
                    $request->user()->id
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function validateOldCrm(Request $request)
    {
        $request->validate([
            'file_token' => 'required|string',
            'mapping_config' => 'required',
            'stage_mapping' => 'nullable',
            'lead_status_mapping' => 'nullable',
            'owner_mapping' => 'nullable',
            'create_custom_fields' => 'nullable',
            'import_mode' => 'nullable|in:all,demo',
        ]);

        try {
            $result = $this->oldCrmImportService->validateImport(
                $request->input('file_token'),
                $this->decodeJsonInput($request->input('mapping_config')),
                $this->decodeJsonInput($request->input('stage_mapping', '{}')),
                $this->decodeJsonInput($request->input('lead_status_mapping', '{}')),
                $this->decodeJsonInput($request->input('owner_mapping', '{}')),
                $this->decodeJsonInput($request->input('create_custom_fields', '{}')),
                $request->input('import_mode', 'all')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importOldCrm(Request $request)
    {
        $request->validate([
            'file_token' => 'required|string',
            'mapping_config' => 'required',
            'stage_mapping' => 'nullable',
            'lead_status_mapping' => 'nullable',
            'owner_mapping' => 'nullable',
            'create_custom_fields' => 'nullable',
            'profile_name' => 'nullable|string|max:255',
            'profile_id' => 'nullable|integer',
            'save_profile' => 'nullable|boolean',
            'import_mode' => 'nullable|in:all,demo',
        ]);

        try {
            $result = $this->oldCrmImportService->import(
                $request->input('file_token'),
                $request->user()->id,
                $this->decodeJsonInput($request->input('mapping_config')),
                $this->decodeJsonInput($request->input('stage_mapping', '{}')),
                $this->decodeJsonInput($request->input('lead_status_mapping', '{}')),
                $this->decodeJsonInput($request->input('owner_mapping', '{}')),
                $this->decodeJsonInput($request->input('create_custom_fields', '{}')),
                [
                    'profile_name' => $request->input('profile_name'),
                    'profile_id' => $request->input('profile_id'),
                    'save_profile' => $request->boolean('save_profile'),
                    'import_mode' => $request->input('import_mode', 'all'),
                ]
            );

            $batch = $result['batch'];
            $successPrefix = $request->input('import_mode') === 'demo'
                ? 'Demo old CRM import completed.'
                : 'Old CRM import completed.';

            return redirect()
                ->route('lead-import.index')
                ->with(
                    'success',
                    "{$successPrefix} Imported {$batch->imported_leads} leads, skipped {$result['skipped_duplicates']} duplicates, {$batch->failed_leads} failed."
                );
        } catch (\Throwable $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }
    }

    /**
     * Import History
     */
    public function history()
    {
        $imports = ImportBatch::where('user_id', auth()->id())
            ->with(['assignmentRule', 'user', 'importProfile'])
            ->latest()
            ->paginate(20);

        return view('lead-import.history', compact('imports'));
    }

    private function decodeJsonInput($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
