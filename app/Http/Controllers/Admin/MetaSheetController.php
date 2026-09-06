<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleSheetsConfig;
use App\Models\GoogleSheetsColumnMapping;
use App\Models\Lead;
use App\Models\LeadFormField;
use App\Models\LeadAssignment;
use App\Services\FieldMappingService;
use App\Services\GoogleSheetsService;
use App\Services\GoogleSheetImportRunner;
use App\Services\LeadAssignmentService;
use App\Support\TestLeadNameGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaSheetController extends Controller
{
    protected $fieldMappingService;
    protected $sheetsService;
    protected $importRunner;

    public function __construct(FieldMappingService $fieldMappingService, GoogleSheetsService $sheetsService, GoogleSheetImportRunner $importRunner)
    {
        $this->fieldMappingService = $fieldMappingService;
        $this->sheetsService = $sheetsService;
        $this->importRunner = $importRunner;
    }

    /**
     * List all Meta sheet configurations
     */
    public function index()
    {
        $configs = GoogleSheetsConfig::where('created_by', auth()->id())
            ->where('sheet_type', 'meta_facebook')
            ->with('columnMappings')
            ->latest()
            ->get()
            ->map(function($config) {
                // Determine which step to resume from
                $config->resume_step = $this->getResumeStep($config);
                return $config;
            });

        return view('integrations.meta-sheet', compact('configs'));
    }

    /**
     * Determine which step to resume from for a draft config
     */
    private function getResumeStep(GoogleSheetsConfig $config): int
    {
        // If setup is completed, return 6 (test & complete)
        if ($config->setup_completed_at) {
            return 6;
        }

        // If has api_endpoint_url, step 5 is done, resume at step 6
        if ($config->api_endpoint_url) {
            return 6;
        }

        // If has status columns, step 4 is done, resume at step 5
        if ($config->crm_status_columns_json && !empty($config->crm_status_columns_json)) {
            return 5;
        }

        // If has column mappings, step 3 is done, resume at step 4
        if ($config->columnMappings && $config->columnMappings->count() > 0) {
            return 4;
        }

        // If has sheet_id and sheet_name, step 2 is done, resume at step 3
        if ($config->sheet_id && $config->sheet_name) {
            return 3;
        }

        // Otherwise, resume at step 2
        return 2;
    }

    /**
     * Show setup wizard Step 1: Info card (auto-set to meta_facebook)
     */
    public function create()
    {
        return view('integrations.meta-sheet-setup', ['step' => 1]);
    }

    /**
     * Store Step 1: Auto-create config with meta_facebook type
     */
    public function storeStep1(Request $request)
    {
        $config = GoogleSheetsConfig::create([
            'sheet_type' => 'meta_facebook', // Auto-set to Meta/Facebook
            'sheet_id' => '', // Will be filled in step 2
            'sheet_name' => 'Sheet1',
            'created_by' => auth()->id(),
            'is_active' => false, // Not active until setup complete
        ]);

        return redirect()->route('integrations.meta-sheet.step2', $config->id);
    }

    /**
     * Show Step 2: Google Sheet Configuration
     */
    public function step2($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $selectedColumns = $config->selected_columns_json ?? [];
        
        return view('integrations.meta-sheet-setup', [
            'step' => 2, 
            'config' => $config,
            'selectedColumns' => $selectedColumns,
        ]);
    }

    /**
     * Store Step 2: Save sheet configuration
     */
    public function storeStep2(Request $request, $id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $request->validate([
            'sheet_id' => 'required|string',
            'sheet_name' => 'required|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'service_account_json_path' => 'nullable|string|max:500',
            'selected_columns' => 'nullable|string', // JSON string
        ]);

        $sheetId = GoogleSheetsConfig::extractSheetId($request->sheet_id);
        if (!$sheetId) {
            return back()->withErrors(['sheet_id' => 'Invalid sheet ID format'])->withInput();
        }

        // Parse selected columns
        $selectedColumns = [];
        if ($request->selected_columns) {
            $selectedColumns = json_decode($request->selected_columns, true);
            if (!is_array($selectedColumns)) {
                $selectedColumns = [];
            }
        }

        // Validate that at least some columns are selected
        if (empty($selectedColumns)) {
            return back()->withErrors(['selected_columns' => 'Please select at least one column to include.'])->withInput();
        }

        $config->update([
            'sheet_id' => $sheetId,
            'sheet_name' => $request->sheet_name,
            'api_key' => $request->api_key,
            'service_account_json_path' => $request->service_account_json_path,
            'selected_columns_json' => $selectedColumns,
        ]);

        return redirect()->route('integrations.meta-sheet.step3', $config->id);
    }

    /**
     * Show Step 3: Field Mapping (auto-load Meta template)
     */
    public function step3($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        // Get standard CRM fields
        $standardFields = $this->fieldMappingService->getStandardMappings();

        // Get active custom fields from LeadFormField
        $customFields = LeadFormField::where('is_active', true)
            ->orderBy('field_label')
            ->get()
            ->mapWithKeys(function($field) {
                return [$field->field_key => [
                    'required' => $field->is_required,
                    'label' => $field->field_label,
                    'type' => $field->field_type
                ]];
            })
            ->toArray();

        // Merge standard and custom fields
        $allFields = array_merge($standardFields, $customFields);

        // Auto-load Meta template
        $template = $this->fieldMappingService->getFormTemplate('meta_facebook');

        // Get selected columns from config
        $selectedColumns = $config->selected_columns_json ?? [];

        return view('integrations.meta-sheet-setup', [
            'step' => 3,
            'config' => $config,
            'standardFields' => $allFields,
            'customFields' => $customFields,
            'template' => $template,
            'selectedColumns' => $selectedColumns,
        ]);
    }

    /**
     * Auto-detect columns from Google Sheet
     */
    public function autoDetectColumns(Request $request)
    {
        $request->validate([
            'sheet_id' => 'required|string',
            'sheet_name' => 'required|string',
            'api_key' => 'nullable|string',
            'service_account_json_path' => 'nullable|string',
        ]);

        try {
            $sheetId = GoogleSheetsConfig::extractSheetId($request->sheet_id);
            if (!$sheetId) {
                Log::error("Failed to extract sheet ID", [
                    'input' => $request->sheet_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sheet ID format. Please provide a valid Google Sheet URL or Sheet ID.',
                ], 400);
            }

            // Validate sheet name
            $sheetName = trim($request->sheet_name);
            if (empty($sheetName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sheet name cannot be empty',
                ], 400);
            }

            Log::info("Auto-detecting columns", [
                'sheet_id' => $sheetId,
                'sheet_name' => $sheetName,
                'has_api_key' => !empty($request->api_key),
                'has_service_account' => !empty($request->service_account_json_path),
            ]);

            // Fetch headers (row 1)
            $headers = $this->sheetsService->fetchSheetData(
                $sheetId,
                $sheetName,
                'A:Z',
                $request->api_key,
                $request->service_account_json_path,
                1
            );

            if (empty($headers) || !isset($headers[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No headers found in the sheet',
                ], 404);
            }

            $headerRow = $headers[0];
            $columns = [];

            foreach ($headerRow as $index => $headerText) {
                $columnLetter = $this->indexToColumnLetter($index);
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
            Log::error("Failed to auto-detect columns", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch columns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create custom field via AJAX
     */
    public function createCustomField(Request $request)
    {
        $request->validate([
            'field_label' => 'required|string|max:255',
            'field_key' => 'nullable|string|max:255|unique:lead_form_fields,field_key',
            'field_type' => 'required|in:text,textarea,select,date,time,datetime,number,email,tel',
            'help_text' => 'nullable|string',
        ]);

        try {
            // Auto-generate field_key if not provided
            $fieldKey = $request->field_key;
            if (empty($fieldKey)) {
                $fieldKey = $this->generateFieldKey($request->field_label);
                
                // Ensure uniqueness
                $originalKey = $fieldKey;
                $counter = 1;
                while (LeadFormField::where('field_key', $fieldKey)->exists()) {
                    $fieldKey = $originalKey . '_' . $counter;
                    $counter++;
                }
            }

            // Auto-calculate display_order
            $maxOrder = LeadFormField::max('display_order') ?? 0;

            $field = LeadFormField::create([
                'field_key' => $fieldKey,
                'field_label' => $request->field_label,
                'field_type' => $request->field_type,
                'field_level' => 'sales_manager', // Most permissive default
                'is_active' => true,
                'is_required' => false,
                'display_order' => $maxOrder + 1,
                'help_text' => $request->help_text,
            ]);

            return response()->json([
                'success' => true,
                'field' => [
                    'key' => $field->field_key,
                    'label' => $field->field_label,
                    'type' => $field->field_type,
                    'required' => $field->is_required,
                ],
                'message' => 'Custom field created successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create custom field', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create custom field: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate field key from field label
     */
    private function generateFieldKey(string $label): string
    {
        // Convert to lowercase
        $key = strtolower(trim($label));
        
        // Replace spaces and special characters with underscores
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        
        // Remove consecutive underscores
        $key = preg_replace('/_+/', '_', $key);
        
        // Remove leading/trailing underscores
        $key = trim($key, '_');
        
        // Ensure it starts with a letter
        if (!preg_match('/^[a-z]/', $key)) {
            $key = 'field_' . $key;
        }
        
        return $key;
    }

    /**
     * Save as Draft
     */
    public function saveDraft(Request $request, $id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $step = $request->input('step', 2);
        $updateData = [
            'is_draft' => true,
            'is_active' => false, // Draft configs should not be active
        ];

        // Save data based on current step
        if ($step == 2) {
            $request->validate([
                'sheet_id' => 'required|string',
                'sheet_name' => 'required|string|max:255',
                'api_key' => 'nullable|string|max:255',
                'service_account_json_path' => 'nullable|string|max:500',
                'selected_columns' => 'nullable|string',
            ]);

            $sheetId = GoogleSheetsConfig::extractSheetId($request->sheet_id);
            if ($sheetId) {
                $updateData['sheet_id'] = $sheetId;
            }
            $updateData['sheet_name'] = $request->sheet_name;
            $updateData['api_key'] = $request->api_key;
            $updateData['service_account_json_path'] = $request->service_account_json_path;
            
            if ($request->selected_columns) {
                $selectedColumns = json_decode($request->selected_columns, true);
                if (is_array($selectedColumns)) {
                    $updateData['selected_columns_json'] = $selectedColumns;
                }
            }
        } elseif ($step == 3) {
            // Save mappings if provided
            if ($request->has('mappings')) {
                $mappings = $request->mappings;
                DB::beginTransaction();
                try {
                    // Delete existing mappings
                    GoogleSheetsColumnMapping::where('google_sheets_config_id', $config->id)->delete();

                    // Create new mappings
                    $displayOrder = 1;
                    foreach ($mappings as $mapping) {
                        if (!empty($mapping['lead_field_key']) && !empty($mapping['sheet_column'])) {
                            GoogleSheetsColumnMapping::create([
                                'google_sheets_config_id' => $config->id,
                                'sheet_column' => $mapping['sheet_column'],
                                'lead_field_key' => $mapping['lead_field_key'],
                                'field_label' => $mapping['field_label'] ?? $mapping['sheet_column'],
                                'field_type' => 'standard',
                                'is_required' => $mapping['is_required'] ?? false,
                                'display_order' => $displayOrder++,
                            ]);
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Failed to save draft mappings", [
                        'config_id' => $config->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } elseif ($step == 4) {
            $request->validate([
                'crm_status_columns' => 'required|array',
            ]);
            $updateData['crm_status_columns_json'] = $request->crm_status_columns;
        }

        $config->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully. You can resume later.',
            'config_id' => $config->id,
        ]);
    }

    /**
     * Store Step 3: Save field mappings
     */
    public function storeStep3(Request $request, $id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $isAjax = $request->expectsJson() || $request->ajax() || $request->wantsJson();

        try {
            $request->validate([
                'mappings' => 'required|array|min:1',
                'mappings.*.sheet_column' => 'required|string',
                'mappings.*.lead_field_key' => 'nullable|string', // Optional - allow unmapped columns
                'mappings.*.field_label' => 'nullable|string',
                'mappings.*.is_required' => 'boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        }
        
        // Filter out mappings without lead_field_key (unmapped columns)
        $mappings = array_filter($request->mappings, function($mapping) {
            return !empty($mapping['lead_field_key']);
        });
        
        // Validate that at least name and phone are mapped
        $nameMapped = collect($mappings)->contains('lead_field_key', 'name');
        $phoneMapped = collect($mappings)->contains('lead_field_key', 'phone');
        
        if (!$nameMapped || !$phoneMapped) {
            $errorMessage = 'Both Name and Phone fields must be mapped. Please map at least one column to "Customer Name" and one to "Phone Number".';
            
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['mappings' => [$errorMessage]]
                ], 422);
            }
            
            return back()->withErrors([
                'mappings' => $errorMessage
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Delete existing mappings
            GoogleSheetsColumnMapping::where('google_sheets_config_id', $config->id)->delete();

            // Create new mappings (only for mapped columns)
            $displayOrder = 1;
            foreach ($mappings as $mapping) {
                if (!empty($mapping['lead_field_key'])) {
                    GoogleSheetsColumnMapping::create([
                        'google_sheets_config_id' => $config->id,
                        'sheet_column' => $mapping['sheet_column'],
                        'lead_field_key' => $mapping['lead_field_key'],
                        'field_label' => $mapping['field_label'] ?? $mapping['sheet_column'],
                        'field_type' => 'standard',
                        'is_required' => $mapping['is_required'] ?? false,
                        'display_order' => $displayOrder++,
                    ]);
                }
            }

            DB::commit();

            if ($isAjax) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mappings saved successfully!',
                    'redirect' => route('integrations.meta-sheet.step4', $config->id)
                ]);
            }

            return redirect()->route('integrations.meta-sheet.step4', $config->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save field mappings", [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            $errorMessage = 'Failed to save mappings: ' . $e->getMessage();
            
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['error' => [$errorMessage]]
                ], 500);
            }

            return back()->withErrors(['error' => $errorMessage])->withInput();
        }
    }

    /**
     * Show Step 4: CRM Status Columns
     */
    public function step4($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        return view('integrations.meta-sheet-setup', [
            'step' => 4,
            'config' => $config,
        ]);
    }

    /**
     * Store Step 4: Save CRM status columns
     */
    public function storeStep4(Request $request, $id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $request->validate([
            'crm_status_columns' => 'required|array',
            'crm_status_columns.crm_sent_status' => 'nullable|string',
            'crm_status_columns.crm_lead_id' => 'nullable|string',
            'crm_status_columns.crm_assigned_user' => 'nullable|string',
            'crm_status_columns.crm_call_date' => 'nullable|string',
            'crm_status_columns.crm_call_status' => 'nullable|string',
        ]);

        $config->update([
            'crm_status_columns_json' => $request->crm_status_columns,
        ]);

        return redirect()->route('integrations.meta-sheet.step5', $config->id);
    }

    /**
     * Show Step 5: Google Apps Script Setup
     */
    public function step5($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $config = $this->prepareInboundWebhookConfig($config);

        return view('integrations.meta-sheet-setup', [
            'step' => 5,
            'config' => $config,
            'webhookUrl' => $this->webhookUrl($config),
            'inboundApiKey' => $config->inbound_api_key,
        ]);
    }

    /**
     * Generate Google Apps Script
     */
    public function generateScript($id)
    {
        $config = GoogleSheetsConfig::with('columnMappings')->findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $config = $this->prepareInboundWebhookConfig($config);
        $script = $this->generateGoogleAppsScript($config, $this->webhookUrl($config), (string) $config->inbound_api_key);

        return response()->json([
            'success' => true,
            'script' => $script,
        ]);
    }

    /**
     * Store Step 5: Complete setup
     */
    public function storeStep5(Request $request, $id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $config = $this->prepareInboundWebhookConfig($config);
        $config->update([
            'is_active' => true,
            'is_draft' => false,
            'setup_completed_at' => now(),
            'api_endpoint_url' => $this->webhookUrl($config),
        ]);

        return redirect()->route('integrations.meta-sheet.step6', $config->id);
    }

    /**
     * Show Step 6: Test & Complete
     */
    public function step6($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $config = $this->prepareInboundWebhookConfig($config);

        return view('integrations.meta-sheet-setup', [
            'step' => 6,
            'config' => $config,
            'webhookUrl' => $this->webhookUrl($config),
            'inboundApiKey' => $config->inbound_api_key,
        ]);
    }

    /**
     * 1-Click Test Integration
     */
    public function test($id)
    {
        $config = GoogleSheetsConfig::with('columnMappings')->findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        if (!$config->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration is not active. Please complete setup first.',
            ], 400);
        }

        $config = $this->prepareInboundWebhookConfig($config);
        $payload = $this->buildWebhookTestPayload($config);
        $path = $this->webhookUrl($config, false);

        $request = Request::create(
            $path,
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_API_KEY' => (string) $config->inbound_api_key,
            ],
            json_encode($payload)
        );

        $response = app()->handle($request);
        $result = json_decode($response->getContent(), true) ?: [];

        return response()->json([
            'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && ($result['status'] ?? null) === 'ok',
            'message' => $result['message'] ?? 'Test failed.',
            'lead_id' => $result['lead_id'] ?? null,
            'assigned_to' => $result['assigned_to'] ?? null,
            'request_id' => $result['request_id'] ?? null,
        ], $response->getStatusCode());
    }

    public function rotateSecret($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $config = $this->prepareInboundWebhookConfig($config);
        $config->rotateInboundApiKey();

        return redirect()
            ->back()
            ->with('success', 'Inbound API key rotated successfully.');
    }

    /**
     * 1-Click Sync Leads from the configured sheet.
     * Pulls rows from Google Sheet and imports any rows not yet seen in LeadAssignment.
     */
    public function sync($id)
    {
        $config = GoogleSheetsConfig::with('columnMappings')->findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        try {
            $result = $this->importRunner->run($config, 'manual', 50);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Sync completed.',
                'imported' => $result['imported'] ?? 0,
                'already_exists' => $result['already_exists'] ?? 0,
                'already_synced' => $result['already_synced'] ?? 0,
                'missing_required' => $result['missing_required'] ?? 0,
                'errors' => $result['errors'] ?? 0,
                'processed' => ($result['imported'] ?? 0) + ($result['already_exists'] ?? 0),
                'total_rows' => max((int) (($result['last_row'] ?? 1) - 1), 0),
                'status' => $result['status'] ?? null,
                'duration_ms' => $result['duration_ms'] ?? null,
            ], $result['success'] ? 200 : 422);
        } catch (\Exception $e) {
            Log::error('Meta sheet sync failed', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable/Disable integration
     */
    public function toggle($id)
    {
        $config = GoogleSheetsConfig::findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $config->update([
            'is_active' => !$config->is_active,
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $config->is_active,
        ]);
    }

    /**
     * Delete a Meta sheet configuration.
     * Optionally deletes (soft deletes) leads that were created from this sheet.
     */
    public function delete(Request $request, $id)
    {
        $config = GoogleSheetsConfig::with('columnMappings')->findOrFail($id);
        if ($config->created_by !== auth()->id() || $config->sheet_type !== 'meta_facebook') {
            abort(403);
        }

        $deleteLeads = $request->boolean('delete_leads', false);

        DB::beginTransaction();
        try {
            $deletedLeadsCount = 0;
            $skippedLeadsCount = 0;

            if ($deleteLeads) {
                // We only delete leads that are very likely created from THIS sheet:
                // - linked via LeadAssignment.sheet_config_id
                // - source = google_sheets
                // - created_by matches config owner
                // - created_at >= config.created_at (avoids deleting pre-existing leads linked due to duplicates)
                $leadIds = LeadAssignment::where('sheet_config_id', $config->id)
                    ->pluck('lead_id')
                    ->unique()
                    ->values();

                if ($leadIds->isNotEmpty()) {
                    $leads = Lead::whereIn('id', $leadIds)
                        ->where('source', \App\Models\Lead::normalizeSource('google_sheets'))
                        ->where('created_by', $config->created_by)
                        ->where('created_at', '>=', $config->created_at)
                        ->get();

                    foreach ($leads as $lead) {
                        $lead->delete(); // soft delete
                        $deletedLeadsCount++;

                        // Remove assignments for deleted leads so they disappear from dashboards immediately.
                        LeadAssignment::where('lead_id', $lead->id)->delete();
                    }

                    // Leads linked but not matching our safety filter (usually duplicates / pre-existing leads).
                    $skippedLeadsCount = max($leadIds->count() - $deletedLeadsCount, 0);
                }
            }

            // Delete config (mappings + sheet assignment config will cascade via FK)
            $config->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sheet configuration deleted successfully.',
                'deleted_leads' => $deletedLeadsCount,
                'skipped_leads' => $skippedLeadsCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete Meta sheet configuration', [
                'config_id' => $config->id,
                'delete_leads' => $deleteLeads,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper: Convert index to column letter
     */
    private function indexToColumnLetter(int $index): string
    {
        $result = '';
        $index++;
        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)) . $result;
            $index = intval($index / 26);
        }
        return $result;
    }

    /**
     * Generate Google Apps Script code
     */
    private function generateGoogleAppsScript(GoogleSheetsConfig $config, string $apiEndpointUrl, string $inboundApiKey): string
    {
        $sheetId = $config->sheet_id;
        $sheetName = $config->sheet_name;

        // Build field mappings for script (JavaScript object syntax)
        $mappings = [];
        foreach ($config->columnMappings as $mapping) {
            // Escape single quotes in field labels and keys for JavaScript
            $fieldLabel = addslashes($mapping->field_label);
            $leadFieldKey = addslashes($mapping->lead_field_key);
            $mappings[] = "    '{$fieldLabel}': '{$leadFieldKey}'";
        }
        $mappingsStr = implode(",\n", $mappings);

$script = <<<SCRIPT
// Google Apps Script for CRM Integration
// Sheet Type: {$config->sheet_type}
// API Endpoint: {$apiEndpointUrl}

const API_ENDPOINT = '{$apiEndpointUrl}';
const INBOUND_API_KEY = '{$inboundApiKey}';
const SHEET_ID = '{$sheetId}';
const SHEET_NAME = '{$sheetName}';

// Field mappings
const FIELD_MAPPINGS = {
{$mappingsStr}
};

// CRM Status columns
const CRM_STATUS_COLUMNS = {
  sent_status: 'CRM Sent Status',
  lead_id: 'CRM Lead ID',
  assigned_user: 'CRM Assigned User',
  call_date: 'CRM Call Date',
  call_status: 'CRM Call Status'
};

/**
 * Main function - triggered when new row is added
 */
function onEdit(e) {
  const sheet = e.source.getActiveSheet();
  const range = e.range;
  
  // Only process if editing the data sheet
  if (sheet.getName() !== SHEET_NAME) {
    return;
  }
  
  // Get the row that was edited
  const row = range.getRow();
  
  // Skip header row
  if (row === 1) {
    return;
  }
  
  // Check if this row was already processed
  const sentStatusCell = sheet.getRange(row, getColumnIndex(CRM_STATUS_COLUMNS.sent_status));
  if (sentStatusCell.getValue() === 'Sent to CRM') {
    return; // Already sent
  }
  
  // Get row data
  const rowData = getRowData(sheet, row);
  
  // Send to CRM
  sendToCRM(rowData, row);
}

/**
 * Get row data as object
 */
function getRowData(sheet, row) {
  const data = {};
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  
  headers.forEach(function(header, index) {
    const cellValue = sheet.getRange(row, index + 1).getValue();
    if (cellValue) {
      data[header] = cellValue;
    }
  });
  
  return data;
}

/**
 * Send data to CRM
 */
function sendToCRM(rowData, rowNumber) {
  try {
    // Map fields using configuration
    const payload = {
      sheet_id: SHEET_ID,
      sheet_row_number: rowNumber,
      sheet_type: '{$config->sheet_type}'
    };
    
    // Map each field
    Object.keys(FIELD_MAPPINGS).forEach(function(sheetField) {
      const crmField = FIELD_MAPPINGS[sheetField];
      if (rowData[sheetField]) {
        payload[crmField] = rowData[sheetField];
      }
    });
    
    // Make API call
    const response = UrlFetchApp.fetch(API_ENDPOINT, {
      method: 'post',
      contentType: 'application/json',
      headers: {
        'X-API-Key': INBOUND_API_KEY
      },
      payload: JSON.stringify(payload)
    });
    
    const result = JSON.parse(response.getContentText());
    
    // Update CRM status columns
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
    
    if (result.status === 'ok') {
      updateCell(sheet, rowNumber, CRM_STATUS_COLUMNS.sent_status, 'Sent to CRM');
      updateCell(sheet, rowNumber, CRM_STATUS_COLUMNS.lead_id, result.lead_id || '');
      
      if (result.assigned_to) {
        updateCell(sheet, rowNumber, CRM_STATUS_COLUMNS.assigned_user, result.assigned_to.name || '');
      }
    } else {
      updateCell(sheet, rowNumber, CRM_STATUS_COLUMNS.sent_status, 'Error: ' + (result.message || 'Unknown error'));
    }
    
  } catch (error) {
    Logger.log('Error sending to CRM: ' + error.toString());
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
    updateCell(sheet, rowNumber, CRM_STATUS_COLUMNS.sent_status, 'Error: ' + error.toString());
  }
}

/**
 * Update cell value
 */
function updateCell(sheet, row, columnName, value) {
  try {
    const colIndex = getColumnIndex(columnName);
    if (colIndex > 0) {
      sheet.getRange(row, colIndex).setValue(value);
    }
  } catch (e) {
    Logger.log('Error updating cell: ' + e.toString());
  }
}

/**
 * Get column index by name
 */
function getColumnIndex(columnName) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  return headers.indexOf(columnName) + 1;
}

/**
 * Setup trigger (run once)
 * Must use .forSpreadsheet() before .onEdit() - TriggerBuilder does not have .onEdit() directly.
 */
function setupTrigger() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  ScriptApp.newTrigger('onEdit')
    .forSpreadsheet(ss)
    .onEdit()
    .create();
  Logger.log('Trigger setup complete!');
}
SCRIPT;

        return $script;
    }

    private function prepareInboundWebhookConfig(GoogleSheetsConfig $config): GoogleSheetsConfig
    {
        $config->ensureInboundCredentials();

        return $config->fresh(['columnMappings']);
    }

    private function webhookUrl(GoogleSheetsConfig $config, bool $absolute = true): string
    {
        return route('api.webhooks.google-sheets', ['integration' => $config->inbound_slug], $absolute);
    }

    private function buildWebhookTestPayload(GoogleSheetsConfig $config): array
    {
        $payload = [
            'sheet_id' => $config->sheet_id,
            'sheet_row_number' => 999,
            'sheet_type' => $config->sheet_type,
            'name' => TestLeadNameGenerator::make(),
            'phone' => '9999999999',
            'email' => 'test@example.com',
        ];

        foreach ($config->columnMappings as $mapping) {
            $crmFieldKey = $mapping->lead_field_key;
            if (in_array($crmFieldKey, ['name', 'phone', 'email'], true)) {
                continue;
            }

            $payload[$crmFieldKey] = 'Test ' . ($mapping->field_label ?: $crmFieldKey);
        }

        return $payload;
    }
}
