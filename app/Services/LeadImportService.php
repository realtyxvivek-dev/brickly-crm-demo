<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\LeadAssignmentService;
use App\Services\NotificationService;
use App\Services\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LeadImportService
{
    private const TEMP_DISK = 'local';
    private const SIMPLE_IMPORT_DIR = 'simple-imports';
    private const SIMPLE_IMPORT_RUN_DIR = 'simple-import-runs';
    private const SIMPLE_IMPORT_CHUNK_SIZE = 1;
    private const SIMPLE_IMPORT_FIELDS = [
        'skip' => 'Skip Column',
        'created_on' => 'Created On',
        'name' => 'Lead Name',
        'phone' => 'Phone Number',
        'source' => 'Lead Source',
        'owner' => 'Owner',
        'lead_stage' => 'Lead Stage',
    ];
    private const SIMPLE_STAGE_BUCKETS = [
        'lead' => 'Lead',
        'follow_up' => 'Follow Up',
        'cnp' => 'CNP',
        'meeting' => 'Meeting',
        'site_visit' => 'Site Visit',
        'site_visit_done' => 'Visit Done',
        'closer' => 'Closer',
        'junk' => 'Junk',
        'not_interested' => 'Not Interested',
    ];

    protected $assignmentService;
    protected $taskService;
    protected $notificationService;

    public function __construct(
        LeadAssignmentService $assignmentService,
        TaskService $taskService,
        NotificationService $notificationService
    ) {
        $this->assignmentService = $assignmentService;
        $this->taskService = $taskService;
        $this->notificationService = $notificationService;
    }

    public function getSimpleImportContext(int $userId): array
    {
        $assignableUsers = User::query()
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereNotIn('slug', ['admin', 'crm', 'hr_manager', 'finance_manager']);
            })
            ->with('role:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'role_id'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role?->name,
            ])
            ->values()
            ->all();

        return [
            'users' => $assignableUsers,
            'stage_bucket_options' => collect(self::SIMPLE_STAGE_BUCKETS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'field_options' => collect(self::SIMPLE_IMPORT_FIELDS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'duplicate_policy' => 'skip',
            'default_create_calling_task' => false,
            'current_user_id' => $userId,
        ];
    }

    public function analyzeSimpleImportFile(UploadedFile $file, int $userId): array
    {
        $token = $this->storeSimpleImportFile($file);
        $rows = $this->parseUploadedImportFile($file);
        $analysis = $this->buildSimpleImportAnalysis($rows, $userId);
        unset($analysis['data_rows']);

        return array_merge($analysis, [
            'file_token' => $token,
            'original_file_name' => $file->getClientOriginalName(),
            'context' => $this->getSimpleImportContext($userId),
        ]);
    }

    public function importSimpleFile(
        string $fileToken,
        int $userId,
        array $columnMapping,
        array $stageMapping,
        array $ownerMapping,
        string $duplicatePolicy = 'skip',
        ?string $originalFileName = null
    ): array {
        $start = $this->startSimpleImport($fileToken, $userId, $columnMapping, $duplicatePolicy, $originalFileName, false, $ownerMapping, $stageMapping);
        $batchId = (int) $start['batch']['id'];
        $progress = $start;

        while (($progress['completed'] ?? false) !== true) {
            $progress = $this->processSimpleImportChunk($batchId, $userId, self::SIMPLE_IMPORT_CHUNK_SIZE);
        }

        return [
            'batch' => ImportBatch::findOrFail($batchId),
            'skipped_duplicates' => $progress['progress']['skipped_duplicates'] ?? 0,
        ];
    }

    public function startSimpleImport(
        string $fileToken,
        int $userId,
        array $columnMapping,
        string $duplicatePolicy = 'skip',
        ?string $originalFileName = null,
        bool $createCallingTask = false,
        array $ownerMapping = [],
        array $stageMapping = [],
        array $rowOverrides = []
    ): array {
        if ($duplicatePolicy !== 'skip') {
            throw new \InvalidArgumentException('Unsupported duplicate policy.');
        }

        $rows = $this->parseSimpleImportToken($fileToken);
        $analysis = $this->buildSimpleImportAnalysis($rows, $userId);
        $normalizedColumnMapping = $this->normalizeSimpleColumnMapping($columnMapping, $analysis['headers']);
        $normalizedOwnerMapping = $this->normalizeSimpleOwnerMapping($ownerMapping);
        $normalizedStageMapping = $this->normalizeSimpleValueMapping($stageMapping, array_keys(self::SIMPLE_STAGE_BUCKETS));
        $normalizedRowOverrides = $this->normalizeSimpleRowOverrides($rowOverrides);

        $nameColumn = $this->findMappedColumnIndex($normalizedColumnMapping, 'name');
        $phoneColumn = $this->findMappedColumnIndex($normalizedColumnMapping, 'phone');
        if ($nameColumn === null || $phoneColumn === null) {
            throw new \InvalidArgumentException('Name and phone column mappings are required.');
        }

        $batch = ImportBatch::create([
            'user_id' => $userId,
            'source_type' => $this->resolveSimpleImportSourceType($fileToken),
            'import_kind' => 'simple_import',
            'file_name' => $originalFileName ?: $fileToken,
            'total_leads' => count($analysis['data_rows']),
            'status' => 'processing',
            'error_log' => [
                'import_kind' => 'simple_import',
                'errors' => [],
                'skipped_duplicates' => 0,
                'processed_rows' => 0,
            ],
        ]);

        $state = [
            'batch_id' => $batch->id,
            'user_id' => $userId,
            'file_token' => $fileToken,
            'original_file_name' => $originalFileName,
            'column_mapping' => $normalizedColumnMapping,
            'owner_mapping' => $normalizedOwnerMapping,
            'stage_mapping' => $normalizedStageMapping,
            'row_overrides' => $normalizedRowOverrides,
            'headers' => $analysis['headers'],
            'data_rows' => $analysis['data_rows'],
            'next_index' => 0,
            'processed_rows' => 0,
            'imported' => 0,
            'failed' => 0,
            'skipped_duplicates' => 0,
            'errors' => [],
            'seen_phones' => [],
            'duplicate_policy' => $duplicatePolicy,
            'create_calling_task' => $createCallingTask,
            'bulk_notification_sent' => false,
        ];

        $this->storeSimpleImportRunState($batch->id, $state);

        return $this->buildSimpleImportProgressPayload($batch, $state, false, 'Import initialized');
    }

    public function processSimpleImportChunk(int $batchId, int $userId, ?int $chunkSize = null): array
    {
        $batch = ImportBatch::query()
            ->where('id', $batchId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $state = $this->loadSimpleImportRunState($batchId);
        if (!$state) {
            throw new \RuntimeException('Import progress session expired. Please start the import again.');
        }

        $chunkSize = max(1, (int) ($chunkSize ?: self::SIMPLE_IMPORT_CHUNK_SIZE));
        $headers = $state['headers'] ?? [];
        $dataRows = $state['data_rows'] ?? [];
        $columnMapping = $state['column_mapping'] ?? [];
        $ownerMapping = $state['owner_mapping'] ?? [];
        $stageMapping = $state['stage_mapping'] ?? [];
        $rowOverrides = $state['row_overrides'] ?? [];
        $createCallingTask = (bool) ($state['create_calling_task'] ?? false);
        $nextIndex = (int) ($state['next_index'] ?? 0);
        $totalRows = count($dataRows);

        if ($batch->status === 'completed' || $nextIndex >= $totalRows) {
            $state['next_index'] = $totalRows;
            $state['processed_rows'] = $totalRows;
            $this->storeSimpleImportRunState($batchId, $state);
            return $this->buildSimpleImportProgressPayload($batch->fresh(), $state, true, 'Import completed');
        }

        $slice = array_slice($dataRows, $nextIndex, $chunkSize);

        foreach ($slice as $offset => $row) {
            $rowIndex = $nextIndex + $offset;
            $rowNumber = $row['_row_number'] ?? ($rowIndex + 2);
            $mapped = $this->mapSimpleImportRow($row, $headers, $columnMapping, $ownerMapping, $stageMapping, $rowOverrides);
            $phone = $mapped['lead']['phone'] ?? '';

            if ($phone !== '' && in_array($phone, $state['seen_phones'], true)) {
                $mapped['errors'][] = "Duplicate phone {$phone} found inside this import file.";
            }

            if ($phone !== '' && $this->leadExistsByPhone($phone)) {
                $mapped['errors'][] = "Phone {$phone} already exists in CRM.";
            }

            if (!empty($mapped['errors'])) {
                $state['failed']++;
                if (collect($mapped['errors'])->contains(fn ($message) => str_contains($message, 'already exists') || str_contains($message, 'Duplicate phone'))) {
                    $state['skipped_duplicates']++;
                }

                foreach (array_unique($mapped['errors']) as $error) {
                    $state['errors'][] = "Row {$rowNumber}: {$error}";
                }

                $state['processed_rows']++;
                continue;
            }

            if ($phone !== '') {
                $state['seen_phones'][] = $phone;
            }

            try {
                DB::transaction(function () use ($mapped, $userId, $columnMapping, $batch, $createCallingTask, &$state) {
                    $leadData = $mapped['lead'];
                    $leadData['created_by'] = $userId;
                    $leadData['status_auto_update_enabled'] = !in_array($leadData['status'], ['closed', 'dead', 'junk', 'not_interested'], true);
                    $leadData['notes'] = $this->buildImportNotes(array_merge($leadData, $mapped['meta']));

                    $lead = Lead::create($leadData);

                    $importedCreatedAt = $this->parseImportedDateValue($mapped['meta']['created_on'] ?? null);
                    if ($importedCreatedAt) {
                        $lead->forceFill([
                            'created_at' => $importedCreatedAt,
                            'updated_at' => $importedCreatedAt,
                        ])->saveQuietly();
                    }

                    $assignedUserId = $mapped['assigned_user_id'] ?? null;
                    if ($assignedUserId) {
                        $this->assignImportedLeadToUser($lead, $assignedUserId, $userId, $createCallingTask);
                    }

                    $this->createSimplePipelineArtifacts($lead, $mapped, $assignedUserId, $userId);

                    ImportedLead::create([
                        'import_batch_id' => $batch->id,
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedUserId,
                        'assigned_at' => $assignedUserId ? now() : null,
                        'import_data' => [
                            'kind' => 'simple_import',
                            'metadata' => $mapped['meta'],
                            'raw_row' => $mapped['raw_row'],
                            'column_mapping' => $columnMapping,
                        ],
                    ]);

                    $state['imported']++;
                });
            } catch (\Throwable $e) {
                $state['failed']++;
                $state['errors'][] = "Row {$rowNumber}: {$e->getMessage()}";
            }

            $state['processed_rows']++;
        }

        $state['next_index'] = min($totalRows, $nextIndex + count($slice));
        $completed = $state['next_index'] >= $totalRows;

        $batch->update([
            'imported_leads' => $state['imported'],
            'failed_leads' => $state['failed'],
            'status' => $completed ? 'completed' : 'processing',
            'error_log' => [
                'import_kind' => 'simple_import',
                'errors' => array_slice($state['errors'], 0, 100),
                'skipped_duplicates' => $state['skipped_duplicates'],
                'processed_rows' => $state['processed_rows'],
            ],
        ]);

        if ($completed && empty($state['bulk_notification_sent']) && (int) ($state['imported'] ?? 0) > 0) {
            $user = User::find($userId);
            if ($user) {
                $this->notificationService->notifyBulkLeadImport(
                    $user,
                    (int) $state['imported'],
                    url('/lead-import'),
                    [
                        'batch_id' => $batch->id,
                        'failed_count' => (int) ($state['failed'] ?? 0),
                        'skipped_duplicates' => (int) ($state['skipped_duplicates'] ?? 0),
                    ]
                );
            }

            $state['bulk_notification_sent'] = true;
        }

        $this->storeSimpleImportRunState($batchId, $state);

        return $this->buildSimpleImportProgressPayload(
            $batch->fresh(),
            $state,
            $completed,
            $completed ? 'Import completed' : 'Import in progress'
        );
    }

    public function importFromCsv(array $leads, int $userId, ?int $ruleId = null, array $options = []): array
    {
        $selectedStages = collect($options['selected_stages'] ?? [])
            ->filter(fn ($stage) => is_string($stage) && trim($stage) !== '')
            ->map(fn ($stage) => $this->normalizeStageValue($stage))
            ->unique()
            ->values()
            ->all();

        $stageFilterMode = in_array($options['stage_filter_mode'] ?? 'include', ['include', 'exclude'], true)
            ? $options['stage_filter_mode']
            : 'include';
        $importMode = ($options['import_mode'] ?? 'all') === 'demo' ? 'demo' : 'all';

        $filterResult = $this->applyStageFilter($leads, $selectedStages, $stageFilterMode);
        $filteredLeads = $filterResult['leads'];
        $skippedByFilter = $filterResult['skipped_by_filter'];

        if ($importMode === 'demo' && count($filteredLeads) > 1) {
            $skippedByFilter += count($filteredLeads) - 1;
            $filteredLeads = array_slice($filteredLeads, 0, 1);
        }

        $batch = ImportBatch::create([
            'user_id' => $userId,
            'source_type' => 'csv',
            'total_leads' => count($leads),
            'status' => 'processing',
            'assignment_rule_id' => $ruleId,
        ]);

        $imported = 0;
        $failed = 0;
        $skippedDuplicates = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($filteredLeads as $index => $leadData) {
                try {
                    // Validate required fields
                    if (empty($leadData['name']) || empty($leadData['phone'])) {
                        $failed++;
                        $errors[] = "Row " . ($leadData['_row_number'] ?? ($index + 2)) . ": Missing name or phone";
                        continue;
                    }

                    if ($this->leadExistsByPhone($leadData['phone'])) {
                        $skippedDuplicates++;
                        $errors[] = "Row " . ($leadData['_row_number'] ?? ($index + 2)) . ": Duplicate phone skipped";
                        continue;
                    }

                    // Create lead
                    $lead = Lead::create([
                        'name' => $leadData['name'],
                        'phone' => $leadData['phone'],
                        'email' => $leadData['email'] ?? null,
                        'address' => $leadData['address'] ?? null,
                        'city' => $leadData['city'] ?? null,
                        'state' => $leadData['state'] ?? null,
                        'pincode' => $leadData['pincode'] ?? null,
                        'source' => Lead::normalizeSource($leadData['source'] ?? 'other'),
                        'status' => 'new',
                        'notes' => $this->buildImportNotes($leadData),
                        'created_by' => $userId,
                    ]);

                    // Assign lead using rule
                    $assignedTo = null;
                    if ($ruleId) {
                        try {
                            $assignedTo = $this->assignmentService->assignLead($lead, $ruleId, $userId);
                        } catch (\Exception $e) {
                            Log::error("Assignment error for lead {$lead->id}: " . $e->getMessage());
                        }
                    }

                    // Track imported lead
                    ImportedLead::create([
                        'import_batch_id' => $batch->id,
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedTo,
                        'assigned_at' => $assignedTo ? now() : null,
                        'import_data' => $leadData,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Row " . ($leadData['_row_number'] ?? ($index + 2)) . ": " . $e->getMessage();
                    Log::error("Lead import error: " . $e->getMessage());
                }
            }

            $batch->update([
                'imported_leads' => $imported,
                'failed_leads' => $failed,
                'status' => 'completed',
                'error_log' => $errors,
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
            'batch' => $batch->fresh(),
            'skipped_by_filter' => $skippedByFilter,
            'skipped_duplicates' => $skippedDuplicates,
            'stage_filter_mode' => $stageFilterMode,
            'selected_stages' => $selectedStages,
            'import_mode' => $importMode,
        ];
    }

    public function parseCsvFile($file): array
    {
        return $this->analyzeCsvFile($file)['leads'];
    }

    public function analyzeCsvFile($file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new \Exception('CSV file is empty or invalid');
        }

        $headerMap = [];
        foreach ($headers as $index => $header) {
            $headerMap[$this->normalizeHeader($header)] = $index;
        }

        $nameIndex = $this->findHeaderIndex($headerMap, ['name', 'full name', 'customer name']);
        $phoneIndex = $this->findHeaderIndex($headerMap, ['phone', 'number', 'phone number']);
        $mobileIndex = $this->findHeaderIndex($headerMap, ['mobile', 'mobile number']);

        if ($nameIndex === null || ($phoneIndex === null && $mobileIndex === null)) {
            throw new \Exception('CSV must contain name/full name and phone/phone number/mobile number columns');
        }

        $emailIndex = $this->findHeaderIndex($headerMap, ['email', 'email address']);
        $addressIndex = $this->findHeaderIndex($headerMap, ['address']);
        $cityIndex = $this->findHeaderIndex($headerMap, ['city']);
        $stateIndex = $this->findHeaderIndex($headerMap, ['state']);
        $pincodeIndex = $this->findHeaderIndex($headerMap, ['pincode', 'pin code', 'zipcode', 'zip code']);
        $sourceIndex = $this->findHeaderIndex($headerMap, ['source', 'lead source']);
        $remarksIndex = $this->findHeaderIndex($headerMap, ['remarks', 'remark', 'notes', 'note', 'comment', 'comments']);
        $stageIndex = $this->findHeaderIndex($headerMap, ['lead stage', 'stage', 'status']);
        $scoreIndex = $this->findHeaderIndex($headerMap, ['lead score', 'score']);
        $ownerIndex = $this->findHeaderIndex($headerMap, ['owner']);
        $createdOnIndex = $this->findHeaderIndex($headerMap, ['created on', 'created at']);
        $sourceCampaignIndex = $this->findHeaderIndex($headerMap, ['source campaign', 'campaign']);
        $prospectIdIndex = $this->findHeaderIndex($headerMap, ['prospect id', 'lead id']);

        $leads = [];
        $stageSummary = [];
        $duplicatePhonesInFile = [];
        $seenPhones = [];

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (empty(array_filter($row, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $primaryPhone = $this->normalizePhone($this->rowValue($row, $phoneIndex));
            $mobilePhone = $this->normalizePhone($this->rowValue($row, $mobileIndex));
            $selectedPhone = $primaryPhone ?: $mobilePhone;
            $alternatePhone = $primaryPhone && $mobilePhone && $primaryPhone !== $mobilePhone
                ? $mobilePhone
                : null;

            $stageValue = trim((string) $this->rowValue($row, $stageIndex));
            $normalizedStage = $this->normalizeStageValue($stageValue);
            $summaryKey = $normalizedStage === '' ? '(Blank Stage)' : $stageValue;
            $stageSummary[$summaryKey] = ($stageSummary[$summaryKey] ?? 0) + 1;

            if ($selectedPhone !== '') {
                if (isset($seenPhones[$selectedPhone])) {
                    $duplicatePhonesInFile[$selectedPhone] = ($duplicatePhonesInFile[$selectedPhone] ?? 1) + 1;
                } else {
                    $seenPhones[$selectedPhone] = true;
                }
            }

            $leads[] = [
                '_row_number' => $rowNumber,
                'name' => trim((string) $this->rowValue($row, $nameIndex)),
                'phone' => $selectedPhone,
                'alternate_phone' => $alternatePhone,
                'email' => $this->emptyToNull($this->rowValue($row, $emailIndex)),
                'address' => $this->emptyToNull($this->rowValue($row, $addressIndex)),
                'city' => $this->emptyToNull($this->rowValue($row, $cityIndex)),
                'state' => $this->emptyToNull($this->rowValue($row, $stateIndex)),
                'pincode' => $this->emptyToNull($this->rowValue($row, $pincodeIndex)),
                'source' => $this->emptyToNull($this->rowValue($row, $sourceIndex)) ?? 'other',
                'old_remark' => $this->emptyToNull($this->rowValue($row, $remarksIndex)),
                'lead_stage' => $stageValue,
                'lead_score' => $this->emptyToNull($this->rowValue($row, $scoreIndex)),
                'owner' => $this->emptyToNull($this->rowValue($row, $ownerIndex)),
                'created_on' => $this->emptyToNull($this->rowValue($row, $createdOnIndex)),
                'source_campaign' => $this->emptyToNull($this->rowValue($row, $sourceCampaignIndex)),
                'prospect_id' => $this->emptyToNull($this->rowValue($row, $prospectIdIndex)),
                'raw_headers' => $headers,
                'raw_row' => $row,
            ];
        }

        fclose($handle);

        return [
            'leads' => $leads,
            'headers' => $headers,
            'detected_columns' => [
                'name' => $this->columnLabel($headers, $nameIndex),
                'phone' => $this->columnLabel($headers, $phoneIndex),
                'mobile' => $this->columnLabel($headers, $mobileIndex),
                'email' => $this->columnLabel($headers, $emailIndex),
                'source' => $this->columnLabel($headers, $sourceIndex),
                'remarks' => $this->columnLabel($headers, $remarksIndex),
                'lead_stage' => $this->columnLabel($headers, $stageIndex),
            ],
            'stage_summary' => $stageSummary,
            'has_stage_column' => $stageIndex !== null,
            'duplicate_phones_in_file' => array_keys($duplicatePhonesInFile),
        ];
    }

    protected function buildSimpleImportAnalysis(array $rows, ?int $userId = null): array
    {
        if (count($rows) < 2) {
            throw new \RuntimeException('Import file must contain a header row and at least one data row.');
        }

        $rawHeaders = array_shift($rows);
        $headers = $this->buildSimpleHeaders($rawHeaders, $rows);
        $detectedColumns = $this->detectSimpleImportColumns($headers);
        $distinctValuesByColumn = $this->buildDistinctValuesByColumn($rows, $headers);
        $preview = [];
        $duplicatePhonesInFile = [];
        $duplicatePhonesInCrm = [];
        $seenPhones = [];
        $missingNameRows = [];
        $missingPhoneRows = [];

        $nameColumn = $this->findDetectedSimpleColumn($detectedColumns, 'name');
        $phoneColumn = $this->findDetectedSimpleColumn($detectedColumns, 'phone');
        $sourceColumn = $this->findDetectedSimpleColumn($detectedColumns, 'source');
        $stageColumn = $this->findDetectedSimpleColumn($detectedColumns, 'lead_stage');
        $ownerColumn = $this->findDetectedSimpleColumn($detectedColumns, 'owner');
        $unknownStageValues = [];
        $unknownOwnerValues = [];

        $dataRows = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_values($row);
            if (empty(array_filter($row, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $name = $nameColumn !== null ? trim((string) ($row[$nameColumn] ?? '')) : '';
            $phone = $phoneColumn !== null ? $this->normalizePhone((string) ($row[$phoneColumn] ?? '')) : '';
            $source = $sourceColumn !== null ? trim((string) ($row[$sourceColumn] ?? '')) : '';
            $stage = $stageColumn !== null ? trim((string) ($row[$stageColumn] ?? '')) : '';
            $owner = $ownerColumn !== null ? trim((string) ($row[$ownerColumn] ?? '')) : '';

            if ($stage !== '' && $this->resolveSimpleStageDefinition($stage) === null) {
                $unknownStageValues[$stage] = true;
            }

            if ($owner !== '' && $this->resolveSimpleOwnerId($owner) === null) {
                $unknownOwnerValues[$owner] = true;
            }

            if ($name === '') {
                $missingNameRows[] = $rowNumber;
            }

            if ($phone === '') {
                $missingPhoneRows[] = $rowNumber;
            }

            if ($phone !== '') {
                if (isset($seenPhones[$phone])) {
                    $duplicatePhonesInFile[$phone] = true;
                } else {
                    $seenPhones[$phone] = true;
                }

                if ($this->leadExistsByPhone($phone)) {
                    $duplicatePhonesInCrm[$phone] = true;
                }
            }

            $preview[] = [
                'row_number' => $rowNumber,
                'name' => $name,
                'phone' => $phone,
                'source' => $source,
                'lead_stage' => $stage,
                'owner' => $owner,
            ];

            $dataRows[] = [
                '_row_number' => $rowNumber,
                'values' => $row,
            ];
        }

        return [
            'headers' => $headers,
            'data_rows' => $dataRows,
            'total_rows' => count($dataRows),
            'preview' => array_slice($preview, 0, 10),
            'detected_columns' => $detectedColumns,
            'distinct_values_by_column' => $distinctValuesByColumn,
            'stage_values' => $stageColumn !== null ? ($distinctValuesByColumn[$stageColumn] ?? []) : [],
            'owner_values' => $ownerColumn !== null ? ($distinctValuesByColumn[$ownerColumn] ?? []) : [],
            'duplicate_phones_in_file' => array_keys($duplicatePhonesInFile),
            'duplicate_phones_in_crm' => array_keys($duplicatePhonesInCrm),
            'missing_name_rows' => $missingNameRows,
            'missing_phone_rows' => $missingPhoneRows,
            'unknown_stage_values' => array_keys($unknownStageValues),
            'unknown_owner_values' => array_keys($unknownOwnerValues),
        ];
    }

    protected function buildSimpleHeaders(array $headerRow, array $rows): array
    {
        $labels = [];
        $headers = [];
        foreach (array_values($headerRow) as $index => $label) {
            $label = trim((string) $label);
            $label = $label !== '' ? $label : "Column {$index}";
            $labels[$label] = ($labels[$label] ?? 0) + 1;
            $finalLabel = $labels[$label] > 1 ? "{$label} ({$labels[$label]})" : $label;
            $sample = null;

            foreach ($rows as $row) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $sample = $value;
                    break;
                }
            }

            $headers[] = [
                'index' => $index,
                'label' => $finalLabel,
                'sample' => $sample,
            ];
        }

        return $headers;
    }

    protected function detectSimpleImportColumns(array $headers): array
    {
        $aliases = [
            'created_on' => ['created on', 'created at', 'created date', 'date created'],
            'name' => ['firstname', 'first name', 'name', 'full name', 'customer name'],
            'phone' => ['phone number', 'phone', 'mobile', 'mobile number', 'whatsapp number'],
            'source' => ['lead source', 'source'],
            'owner' => ['owner', 'assigned to', 'assign to', 'lead owner'],
            'lead_stage' => ['lead stage', 'stage', 'status'],
        ];

        $detected = [];
        foreach ($aliases as $field => $fieldAliases) {
            foreach ($headers as $header) {
                $normalized = $this->normalizeHeader($header['label'] ?? '');
                if (in_array($normalized, array_map(fn ($item) => $this->normalizeHeader($item), $fieldAliases), true)) {
                    $detected[$field] = $header['index'];
                    break;
                }
            }
        }

        return $detected;
    }

    protected function findDetectedSimpleColumn(array $detectedColumns, string $field): ?int
    {
        return array_key_exists($field, $detectedColumns) ? (int) $detectedColumns[$field] : null;
    }

    protected function buildDistinctValuesByColumn(array $rows, array $headers): array
    {
        $values = [];
        foreach ($headers as $header) {
            $index = (int) $header['index'];
            $columnValues = [];
            foreach ($rows as $row) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $columnValues[$value] = true;
                }
                if (count($columnValues) >= 50) {
                    break;
                }
            }

            $values[$index] = array_keys($columnValues);
        }

        return $values;
    }

    protected function storeSimpleImportFile(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $token = Str::uuid()->toString() . '.' . $extension;
        Storage::disk(self::TEMP_DISK)->putFileAs(self::SIMPLE_IMPORT_DIR, $file, $token);

        return $token;
    }

    protected function resolveSimpleImportPath(string $fileToken): string
    {
        return self::SIMPLE_IMPORT_DIR . '/' . ltrim($fileToken, '/');
    }

    protected function resolveSimpleImportRunPath(int $batchId): string
    {
        return self::SIMPLE_IMPORT_RUN_DIR . '/' . $batchId . '.json';
    }

    protected function storeSimpleImportRunState(int $batchId, array $state): void
    {
        Storage::disk(self::TEMP_DISK)->put(
            $this->resolveSimpleImportRunPath($batchId),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    protected function loadSimpleImportRunState(int $batchId): ?array
    {
        $path = $this->resolveSimpleImportRunPath($batchId);
        if (!Storage::disk(self::TEMP_DISK)->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk(self::TEMP_DISK)->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function buildSimpleImportProgressPayload(
        ImportBatch $batch,
        array $state,
        bool $completed,
        string $message
    ): array {
        $total = max(0, (int) $batch->total_leads);
        $processed = min($total, (int) ($state['processed_rows'] ?? 0));
        $percentage = $total > 0 ? (int) floor(($processed / $total) * 100) : 100;
        if ($completed) {
            $percentage = 100;
        }

        return [
            'batch' => [
                'id' => $batch->id,
                'status' => $batch->status,
                'file_name' => $batch->file_name,
            ],
            'completed' => $completed,
            'message' => $message,
            'progress' => [
                'percentage' => $percentage,
                'total_rows' => $total,
                'processed_rows' => $processed,
                'imported' => (int) ($state['imported'] ?? 0),
                'failed' => (int) ($state['failed'] ?? 0),
                'skipped_duplicates' => (int) ($state['skipped_duplicates'] ?? 0),
                'remaining_rows' => max(0, $total - $processed),
            ],
        ];
    }

    protected function parseSimpleImportToken(string $fileToken): array
    {
        $path = $this->resolveSimpleImportPath($fileToken);
        if (!Storage::disk(self::TEMP_DISK)->exists($path)) {
            throw new \RuntimeException('Uploaded import session expired. Please upload the file again.');
        }

        return $this->parseStoredImportFile(
            Storage::disk(self::TEMP_DISK)->path($path),
            pathinfo($fileToken, PATHINFO_EXTENSION)
        );
    }

    protected function parseUploadedImportFile(UploadedFile $file): array
    {
        return $this->parseStoredImportFile($file->getRealPath(), $file->getClientOriginalExtension());
    }

    protected function parseStoredImportFile(string $path, ?string $extension): array
    {
        $extension = strtolower((string) $extension);

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseExcelRows($path);
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsvRows($path);
        }

        throw new \RuntimeException("Unsupported import file type: {$extension}");
    }

    protected function parseExcelRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $rows = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $worksheet->getCell([$col, $row]);
                $value = $cell->getFormattedValue();
                $rowData[] = is_scalar($value) ? trim((string) $value) : '';
            }

            if (!empty(array_filter($rowData, fn ($value) => trim((string) $value) !== ''))) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    protected function parseCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open import file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $row = array_map(fn ($value) => trim((string) $value), $row);
            if (!empty(array_filter($row, fn ($value) => trim((string) $value) !== ''))) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    protected function normalizeSimpleColumnMapping(array $columnMapping, array $headers): array
    {
        $allowed = array_keys(self::SIMPLE_IMPORT_FIELDS);
        $headerIndexes = collect($headers)->pluck('index')->map(fn ($index) => (int) $index)->all();

        return collect($columnMapping)
            ->mapWithKeys(function ($field, $index) use ($allowed, $headerIndexes) {
                $index = (int) $index;
                $field = trim((string) $field);
                if (!in_array($index, $headerIndexes, true) || !in_array($field, $allowed, true) || $field === 'skip') {
                    return [];
                }

                return [$index => $field];
            })
            ->all();
    }

    protected function normalizeSimpleValueMapping(array $mapping, array $allowedBuckets): array
    {
        $normalized = [];
        foreach ($mapping as $value => $bucket) {
            $bucket = trim((string) $bucket);
            if ($bucket === '' || !in_array($bucket, $allowedBuckets, true)) {
                continue;
            }
            $normalized[$this->normalizeLookupValue($value)] = $bucket;
        }

        return $normalized;
    }

    protected function normalizeSimpleOwnerMapping(array $ownerMapping): array
    {
        $normalized = [];
        foreach ($ownerMapping as $value => $userId) {
            $normalizedValue = $this->normalizeLookupValue($value);
            if ($normalizedValue === '') {
                continue;
            }

            if ($userId === '' || $userId === null || $userId === '__unassigned__') {
                $normalized[$normalizedValue] = null;
                continue;
            }

            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }

            $normalized[$normalizedValue] = $userId;
        }

        return $normalized;
    }

    protected function normalizeSimpleRowOverrides(array $rowOverrides): array
    {
        $normalized = [];
        $allowed = ['name', 'phone', 'owner', 'lead_stage', 'source'];

        foreach ($rowOverrides as $rowNumber => $values) {
            $rowNumber = (int) $rowNumber;
            if ($rowNumber <= 0 || !is_array($values)) {
                continue;
            }

            foreach ($allowed as $field) {
                if (!array_key_exists($field, $values)) {
                    continue;
                }

                $value = trim((string) $values[$field]);
                if ($value !== '') {
                    $normalized[$rowNumber][$field] = $value;
                }
            }
        }

        return $normalized;
    }

    protected function resolveSimpleStageBucket(?string $value): ?string
    {
        return $this->resolveSimpleStageDefinition($value)['pipeline_stage'] ?? null;
    }

    protected function resolveSimpleStageDefinition(?string $value): ?array
    {
        $normalized = $this->normalizeLookupValue($value);
        if ($normalized === '') {
            return null;
        }

        $definitions = [
            'lead' => ['pipeline_stage' => 'lead', 'status' => 'new', 'cnp_count' => 0],
            'new' => ['pipeline_stage' => 'lead', 'status' => 'new', 'cnp_count' => 0],
            'new lead' => ['pipeline_stage' => 'lead', 'status' => 'new', 'cnp_count' => 0],
            'prospect' => ['pipeline_stage' => 'lead', 'status' => 'new', 'cnp_count' => 0],
            'opportunity' => ['pipeline_stage' => 'lead', 'status' => 'connected', 'cnp_count' => 0],
            'connected' => ['pipeline_stage' => 'lead', 'status' => 'connected', 'cnp_count' => 0],
            'follow up' => ['pipeline_stage' => 'follow_up', 'status' => 'new', 'cnp_count' => 0],
            'followup' => ['pipeline_stage' => 'follow_up', 'status' => 'new', 'cnp_count' => 0],
            'callback' => ['pipeline_stage' => 'follow_up', 'status' => 'new', 'cnp_count' => 0],
            'call back' => ['pipeline_stage' => 'follow_up', 'status' => 'new', 'cnp_count' => 0],
            'cnp' => ['pipeline_stage' => 'cnp', 'status' => 'new', 'cnp_count' => 1],
            'meeting' => ['pipeline_stage' => 'meeting', 'status' => 'meeting_scheduled', 'cnp_count' => 0],
            'meeting pending' => ['pipeline_stage' => 'meeting', 'status' => 'meeting_scheduled', 'cnp_count' => 0],
            'meeting scheduled' => ['pipeline_stage' => 'meeting', 'status' => 'meeting_scheduled', 'cnp_count' => 0],
            'site visit pending' => ['pipeline_stage' => 'site_visit', 'status' => 'visit_scheduled', 'cnp_count' => 0],
            'site visit' => ['pipeline_stage' => 'site_visit', 'status' => 'visit_scheduled', 'cnp_count' => 0],
            'visit pending' => ['pipeline_stage' => 'site_visit', 'status' => 'visit_scheduled', 'cnp_count' => 0],
            'visit scheduled' => ['pipeline_stage' => 'site_visit', 'status' => 'visit_scheduled', 'cnp_count' => 0],
            'visit done' => ['pipeline_stage' => 'site_visit_done', 'status' => 'visit_done', 'cnp_count' => 0],
            'visit completed' => ['pipeline_stage' => 'site_visit_done', 'status' => 'visit_done', 'cnp_count' => 0],
            'closer' => ['pipeline_stage' => 'closer', 'status' => 'closed', 'cnp_count' => 0],
            'closed' => ['pipeline_stage' => 'closer', 'status' => 'closed', 'cnp_count' => 0],
            'booked' => ['pipeline_stage' => 'closer', 'status' => 'closed', 'cnp_count' => 0],
            'sale done' => ['pipeline_stage' => 'closer', 'status' => 'closed', 'cnp_count' => 0],
            'junk' => ['pipeline_stage' => 'junk', 'status' => 'junk', 'cnp_count' => 0],
            'not interested' => ['pipeline_stage' => 'not_interested', 'status' => 'not_interested', 'cnp_count' => 0],
        ];

        return $definitions[$normalized] ?? null;
    }

    protected function resolveSimpleStageDefinitionFromBucket(?string $bucket): ?array
    {
        return match ($bucket) {
            'lead' => ['pipeline_stage' => 'lead', 'status' => 'new', 'cnp_count' => 0],
            'follow_up' => ['pipeline_stage' => 'follow_up', 'status' => 'new', 'cnp_count' => 0],
            'cnp' => ['pipeline_stage' => 'cnp', 'status' => 'new', 'cnp_count' => 1],
            'meeting' => ['pipeline_stage' => 'meeting', 'status' => 'meeting_scheduled', 'cnp_count' => 0],
            'site_visit' => ['pipeline_stage' => 'site_visit', 'status' => 'visit_scheduled', 'cnp_count' => 0],
            'site_visit_done' => ['pipeline_stage' => 'site_visit_done', 'status' => 'visit_done', 'cnp_count' => 0],
            'closer' => ['pipeline_stage' => 'closer', 'status' => 'closed', 'cnp_count' => 0],
            'junk' => ['pipeline_stage' => 'junk', 'status' => 'junk', 'cnp_count' => 0],
            'not_interested' => ['pipeline_stage' => 'not_interested', 'status' => 'not_interested', 'cnp_count' => 0],
            default => null,
        };
    }

    protected function resolveSimpleOwnerId(?string $value): ?int
    {
        $normalized = $this->normalizeLookupValue($value);
        if ($normalized === '') {
            return null;
        }

        static $userMap = null;
        if ($userMap === null) {
            $userMap = User::query()
                ->where('is_active', true)
                ->get(['id', 'name'])
                ->mapWithKeys(function (User $user) {
                    return [$this->normalizeLookupValue($user->name) => $user->id];
                })
                ->all();
        }

        return $userMap[$normalized] ?? null;
    }

    protected function findMappedColumnIndex(array $columnMapping, string $field): ?int
    {
        foreach ($columnMapping as $index => $mappedField) {
            if ($mappedField === $field) {
                return (int) $index;
            }
        }

        return null;
    }

    protected function mapSimpleImportRow(array $row, array $headers, array $columnMapping, array $ownerMapping = [], array $stageMapping = [], array $rowOverrides = []): array
    {
        $leadData = [
            'status' => 'new',
            'source' => 'other',
        ];
        $metaData = [];
        $errors = [];
        $rawRow = [];
        $assignedUserId = null;
        $assignedUserName = null;
        $pipelineStage = 'lead';
        $stageDefinition = null;
        $rowNumber = (int) ($row['_row_number'] ?? 0);
        $overrides = $rowOverrides[$rowNumber] ?? [];

        foreach ($headers as $header) {
            $rawRow[$header['label']] = $row['values'][$header['index']] ?? null;
        }

        foreach ($columnMapping as $index => $field) {
            $value = trim((string) ($overrides[$field] ?? ($row['values'][$index] ?? '')));
            if ($value === '') {
                continue;
            }

            if ($field === 'name') {
                $leadData['name'] = $value;
                continue;
            }

            if ($field === 'phone') {
                $leadData['phone'] = $this->normalizePhone($value);
                continue;
            }

            if ($field === 'source') {
                $leadData['source'] = Lead::normalizeSource($value);
                $metaData['source'] = $value;
                continue;
            }

            if ($field === 'created_on') {
                $metaData['created_on'] = $value;
                continue;
            }

            if ($field === 'owner') {
                $metaData['owner'] = $value;
                $normalizedOwner = $this->normalizeLookupValue($value);
                if (array_key_exists($normalizedOwner, $ownerMapping)) {
                    $assignedUserId = $ownerMapping[$normalizedOwner] ? (int) $ownerMapping[$normalizedOwner] : null;
                } else {
                    $assignedUserId = $this->resolveSimpleOwnerId($value);
                }

                if (!$assignedUserId && !array_key_exists($normalizedOwner, $ownerMapping)) {
                    $errors[] = "Owner value '{$value}' does not match any CRM user.";
                    continue;
                }

                $assignedUserName = $assignedUserId ? User::find($assignedUserId)?->name : null;
                continue;
            }

            if ($field === 'lead_stage') {
                $metaData['lead_stage'] = $value;
                $normalizedStage = $this->normalizeLookupValue($value);
                $stageDefinition = array_key_exists($normalizedStage, $stageMapping)
                    ? $this->resolveSimpleStageDefinitionFromBucket($stageMapping[$normalizedStage])
                    : $this->resolveSimpleStageDefinition($value);
                $pipelineStage = $stageDefinition['pipeline_stage'] ?? null;
                if (!$stageDefinition || !$pipelineStage) {
                    $errors[] = "Stage value '{$value}' is not supported for simple import.";
                    continue;
                }

                $leadData['status'] = $stageDefinition['status'] ?? 'new';
                $leadData['cnp_count'] = (int) ($stageDefinition['cnp_count'] ?? 0);
            }
        }

        if (blank($leadData['name'] ?? null)) {
            $errors[] = 'Mapped lead name is missing.';
        }

        if (blank($leadData['phone'] ?? null)) {
            $errors[] = 'Mapped phone is missing.';
        }

        if ($this->findMappedColumnIndex($columnMapping, 'owner') !== null && blank($metaData['owner'] ?? null)) {
            $errors[] = 'Mapped owner is missing.';
        }

        if ($this->findMappedColumnIndex($columnMapping, 'lead_stage') !== null && blank($metaData['lead_stage'] ?? null)) {
            $errors[] = 'Mapped lead stage is missing.';
        }

        $leadData['status'] = $leadData['status'] ?? 'new';
        $leadData['cnp_count'] = (int) ($leadData['cnp_count'] ?? 0);

        if (!empty($metaData['created_on'])) {
            $scheduledAt = $this->parseImportedDateValue($metaData['created_on']);
            if ($scheduledAt && $pipelineStage === 'follow_up') {
                $leadData['next_followup_at'] = $scheduledAt;
            }
        }

        return [
            'lead' => $leadData,
            'meta' => $metaData,
            'assigned_user_id' => $assignedUserId,
            'assigned_user_name' => $assignedUserName,
            'pipeline_stage' => $pipelineStage,
            'errors' => array_values(array_unique($errors)),
            'raw_row' => $rawRow,
        ];
    }

    protected function normalizeLookupValue(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function parseImportedDateValue(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'd-M-Y h:i A',
            'd-M-y h:i A',
            'd-M-Y g:i A',
            'd-M-y g:i A',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y h:i A',
            'd/m/y h:i A',
            'd/m/Y g:i A',
            'd/m/y g:i A',
            'd/m/Y H:i:s',
            'd/m/y H:i:s',
            'd/m/Y H:i',
            'd/m/y H:i',
            'd-m-Y h:i A',
            'd-m-y h:i A',
            'd-m-Y g:i A',
            'd-m-y g:i A',
            'd-m-Y H:i:s',
            'd-m-y H:i:s',
            'd-m-Y H:i',
            'd-m-y H:i',
            'd-M-Y',
            'd-M-y',
            'Y-m-d',
            'd/m/Y',
            'd/m/y',
            'd-m-Y',
            'd-m-y',
            'm/d/Y',
            'm/d/y',
        ];

        if (is_numeric($value)) {
            try {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                );
            } catch (\Throwable $e) {
            }
        }

        // Numeric dates with day-month-year intent should never be guessed when ambiguous.
        if (preg_match('/^(\d{1,2})([\/\-])(\d{1,2})\2(\d{2,4})(?:\s+(.*))?$/', $value, $matches)) {
            $first = (int) $matches[1];
            $separator = $matches[2];
            $second = (int) $matches[3];
            $year = $matches[4];
            $time = trim((string) ($matches[5] ?? ''));

            if (strlen($year) === 2) {
                $year = (string) (2000 + (int) $year);
            }

            $format = $separator === '/' && $first <= 12 ? 'm-d-Y' : 'd-m-Y';
            $candidate = str_pad((string) $first, 2, '0', STR_PAD_LEFT) . '-'
                . str_pad((string) $second, 2, '0', STR_PAD_LEFT) . '-' . $year;
            $timeFormats = ['H:i:s', 'H:i', 'h:i A', 'g:i A'];

            if ($time !== '') {
                foreach ($timeFormats as $timeFormat) {
                    try {
                        $parsed = Carbon::createFromFormat('!' . $format . ' ' . $timeFormat, $candidate . ' ' . $time);
                        if ($parsed !== false && $parsed->format($format) === $candidate) {
                            return $parsed;
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }

            try {
                $parsed = Carbon::createFromFormat('!' . $format, $candidate);
                if ($parsed !== false && $parsed->format($format) === $candidate) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable $e) {
            }
        }

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(.*))?$/', $value, $matches)) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            $time = trim((string) ($matches[4] ?? ''));
            $candidate = $year . '-' . $month . '-' . $day;
            $timeFormats = ['H:i:s', 'H:i', 'h:i A', 'g:i A'];

            if ($time !== '') {
                foreach ($timeFormats as $timeFormat) {
                    try {
                        $parsed = Carbon::createFromFormat('Y-m-d ' . $timeFormat, $candidate . ' ' . $time);
                        if ($parsed !== false) {
                            return $parsed;
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }

            try {
                return Carbon::createFromFormat('Y-m-d', $candidate)->startOfDay();
            } catch (\Throwable $e) {
            }
        }

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    $normalized = $parsed->copy();

                    if ($normalized->year < 100) {
                        $normalized->year($normalized->year + 2000);
                    }

                    // If imported value has only a date, keep import flexible and default time to start of day.
                    if (!str_contains($format, 'H') && !str_contains($format, 'h') && !str_contains($format, 'g')) {
                        $normalized->startOfDay();
                    }

                    return $normalized;
                }
            } catch (\Throwable $e) {
            }
        }

        if (preg_match('/[A-Za-z]/', $value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    protected function assignImportedLeadToUser(Lead $lead, int $assignedUserId, int $assignedBy, bool $dispatchEvent = true): void
    {
        $lead->assignments()->update(['is_active' => false, 'unassigned_at' => now()]);

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedUserId,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
            'notes' => 'Assigned during simple import',
        ]);

        if ($dispatchEvent) {
            event(new LeadAssigned($lead, $assignedUserId, $assignedBy));
        }
    }

    protected function createSimplePipelineArtifacts(Lead $lead, array $mapped, ?int $assignedUserId, int $userId): void
    {
        $stage = $mapped['pipeline_stage'] ?? 'lead';
        $scheduledAt = $this->parseImportedDateValue($mapped['meta']['created_on'] ?? null) ?? now();

        if ($stage === 'follow_up') {
            FollowUp::create([
                'lead_id' => $lead->id,
                'created_by' => $userId,
                'type' => 'call',
                'notes' => $this->buildSimpleFlowNote($mapped['meta'], 'Imported into Follow Up stage'),
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
            ]);

            $lead->update([
                'next_followup_at' => $scheduledAt,
            ]);
            return;
        }

        if ($stage === 'meeting') {
            Meeting::create([
                'lead_id' => $lead->id,
                'created_by' => $userId,
                'assigned_to' => $assignedUserId,
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'date_of_visit' => $scheduledAt->copy()->toDateString(),
                'budget_range' => 'Under 50 Lac',
                'property_type' => 'Just Exploring',
                'payment_mode' => 'Self Fund',
                'tentative_period' => 'More than 6 Months',
                'lead_type' => 'Meeting',
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
                'verification_status' => 'pending',
                'meeting_notes' => $this->buildSimpleFlowNote($mapped['meta'], 'Imported into Meeting stage'),
            ]);
            return;
        }

        if (in_array($stage, ['site_visit', 'site_visit_done', 'closer'], true)) {
            $visit = SiteVisit::create([
                'lead_id' => $lead->id,
                'created_by' => $userId,
                'assigned_to' => $assignedUserId,
                'scheduled_at' => $scheduledAt,
                'status' => in_array($stage, ['site_visit_done', 'closer'], true) ? 'completed' : 'scheduled',
                'completed_at' => in_array($stage, ['site_visit_done', 'closer'], true) ? $scheduledAt : null,
                'visit_notes' => $this->buildSimpleFlowNote($mapped['meta'], $stage === 'closer'
                    ? 'Imported into Closer stage'
                    : ($stage === 'site_visit_done' ? 'Imported into Visit Done stage' : 'Imported into Site Visit stage')),
                'verification_status' => in_array($stage, ['site_visit_done', 'closer'], true) ? 'verified' : 'pending',
                'verified_by' => in_array($stage, ['site_visit_done', 'closer'], true) ? $userId : null,
                'verified_at' => in_array($stage, ['site_visit_done', 'closer'], true) ? now() : null,
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'date_of_visit' => $scheduledAt->copy()->toDateString(),
                'budget_range' => 'Under 50 Lac',
                'property_type' => 'Just Exploring',
                'payment_mode' => 'Self Fund',
                'tentative_period' => 'More than 6 Months',
                'lead_type' => $stage === 'closer' ? 'Meeting' : 'New Visit',
            ]);

            if ($stage === 'closer') {
                $visit->update([
                    'closer_status' => 'pending',
                    'converted_to_closer_at' => now(),
                    'closing_verification_status' => 'pending',
                ]);
            }
        }
    }

    protected function buildSimpleFlowNote(array $metaData, string $prefix): string
    {
        $parts = [$prefix];

        if (!blank($metaData['lead_stage'] ?? null)) {
            $parts[] = 'Imported Stage: ' . $metaData['lead_stage'];
        }
        if (!blank($metaData['owner'] ?? null)) {
            $parts[] = 'Imported Owner: ' . $metaData['owner'];
        }
        if (!blank($metaData['source'] ?? null)) {
            $parts[] = 'Imported Source: ' . $metaData['source'];
        }
        if (!blank($metaData['created_on'] ?? null)) {
            $parts[] = 'Imported Created On: ' . $metaData['created_on'];
        }

        return implode("\n", $parts);
    }

    protected function resolveSimpleImportSourceType(string $fileToken): string
    {
        return 'csv';
    }

    protected function normalizeHeader(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    protected function findHeaderIndex(array $headerMap, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $normalized = $this->normalizeHeader($alias);
            if (array_key_exists($normalized, $headerMap)) {
                return $headerMap[$normalized];
            }
        }

        return null;
    }

    protected function rowValue(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        return isset($row[$index]) ? trim((string) $row[$index]) : null;
    }

    protected function emptyToNull(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    protected function normalizePhone(?string $value): string
    {
        $value = trim((string) $value);
        $value = trim($value, " \t\n\r\0\x0B'\"");
        $value = preg_replace('/[^0-9+]+/', '', $value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '+')) {
            return '+' . ltrim(substr($value, 1), '+');
        }

        return $value;
    }

    protected function normalizeStageValue(?string $value): string
    {
        if ($value === '__blank__') {
            return '';
        }

        return strtolower(trim((string) $value));
    }

    protected function applyStageFilter(array $leads, array $selectedStages, string $mode): array
    {
        if (empty($selectedStages)) {
            return [
                'leads' => $leads,
                'skipped_by_filter' => 0,
            ];
        }

        $filtered = [];
        $skipped = 0;

        foreach ($leads as $lead) {
            $stage = $this->normalizeStageValue($lead['lead_stage'] ?? '');
            $matches = in_array($stage, $selectedStages, true);
            $keep = $mode === 'exclude' ? !$matches : $matches;

            if ($keep) {
                $filtered[] = $lead;
            } else {
                $skipped++;
            }
        }

        return [
            'leads' => $filtered,
            'skipped_by_filter' => $skipped,
        ];
    }

    protected function leadExistsByPhone(string $phone): bool
    {
        if ($phone === '') {
            return false;
        }

        return app(DuplicateDetectionService::class)->findExistingLeadByPhone($phone) !== null;
    }

    protected function buildImportNotes(array $leadData): ?string
    {
        $lines = [];

        if (!empty($leadData['old_remark'])) {
            $lines[] = 'Old CRM Remark:';
            $lines[] = trim((string) $leadData['old_remark']);
            $lines[] = '';
        }

        $lines[] = 'Imported from External CRM';

        $metaMap = [
            'prospect_id' => 'Old Prospect ID',
            'lead_stage' => 'Lead Stage',
            'lead_score' => 'Lead Score',
            'owner' => 'Old Owner',
            'source' => 'Lead Source',
            'source_campaign' => 'Source Campaign',
            'created_on' => 'Created On',
            'alternate_phone' => 'Alternate Number',
        ];

        foreach ($metaMap as $key => $label) {
            $value = $leadData[$key] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $lines[] = $label . ': ' . trim((string) $value);
            }
        }

        $notes = trim(implode("\n", $lines));

        return $notes !== '' ? $notes : null;
    }

    protected function columnLabel(array $headers, ?int $index): ?string
    {
        return $index !== null && isset($headers[$index]) ? $headers[$index] : null;
    }

    /**
     * @deprecated — Smart Import removed. Method kept as stub to avoid DI errors.
     */
    public function importFromCsvWithAutomation(array $leads, int $userId, $automation = null): \App\Models\ImportBatch
    {
        $batch = ImportBatch::create([
            'user_id' => $userId,
            'source_type' => 'csv',
            'total_leads' => count($leads),
            'status' => 'processing',
            'automation_id' => $automation->id,
        ]);

        $imported = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($leads as $index => $leadData) {
                try {
                    // Validate required fields
                    if (empty($leadData['name']) || empty($leadData['phone'])) {
                        $failed++;
                        $errors[] = "Row " . ($index + 1) . ": Missing name or phone";
                        continue;
                    }

                    // Create lead
                    $lead = Lead::create([
                        'name' => $leadData['name'],
                        'phone' => $leadData['phone'],
                        'email' => $leadData['email'] ?? null,
                        'address' => $leadData['address'] ?? null,
                        'city' => $leadData['city'] ?? null,
                        'state' => $leadData['state'] ?? null,
                        'pincode' => $leadData['pincode'] ?? null,
                        'source' => $leadData['source'] ?? 'other',
                        'status' => 'new',
                        'created_by' => $userId,
                    ]);

                    // Assign lead using automation config
                    $assignedTo = null;
                    $automationConfig = [
                        'assignment_mode' => $automation->assignment_mode,
                        'distribution_config' => $automation->distribution_config,
                        'conditions' => $automation->conditions ?? [],
                        'fallback_user_id' => $automation->fallback_user_id,
                    ];

                    try {
                        $assignedTo = $this->smartAssignmentService->assignLead($lead, $automationConfig, $userId);
                        
                        // Create phone call task if enabled
                        if ($assignedTo && ($automation->auto_create_call_task ?? true)) {
                            try {
                                $this->taskService->createPhoneCallTask($lead, $assignedTo, $userId);
                            } catch (\Exception $e) {
                                Log::warning("Failed to create phone call task for lead {$lead->id}: " . $e->getMessage());
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Assignment error for lead {$lead->id}: " . $e->getMessage());
                    }

                    // Track imported lead
                    ImportedLead::create([
                        'import_batch_id' => $batch->id,
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedTo,
                        'assigned_at' => $assignedTo ? now() : null,
                        'import_data' => $leadData,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    Log::error("Lead import error: " . $e->getMessage());
                }
            }

            $batch->update([
                'imported_leads' => $imported,
                'failed_leads' => $failed,
                'status' => 'completed',
                'error_log' => $errors,
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

        return $batch->fresh();
    }
}
