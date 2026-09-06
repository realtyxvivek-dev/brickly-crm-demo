<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFormField;
use App\Models\LeadFormFieldValue;
use App\Models\Meeting;
use App\Models\OldCrmImportProfile;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OldCrmImportService
{
    private const TEMP_DISK = 'local';
    private const TEMP_DIR = 'old-crm-imports';

    private const LEAD_FIELDS = [
        'name' => 'Lead Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'address' => 'Address',
        'city' => 'City',
        'state' => 'State',
        'pincode' => 'Pincode',
        'source' => 'Source',
        'status' => 'Pipeline Stage',
        'owner' => 'Owner Assignment',
        'created_on' => 'Original Created On',
        'requirements' => 'Requirements',
        'notes' => 'Notes',
        'preferred_location' => 'Preferred Location',
        'preferred_size' => 'Preferred Size',
        'use_end_use' => 'Use / End Use',
        'budget' => 'Budget',
        'property_type' => 'Property Type',
        'possession_status' => 'Possession Status',
    ];

    private const META_FIELDS = [
        'old_owner' => 'Old CRM Owner',
        'old_stage' => 'Old CRM Stage',
        'old_source' => 'Original Source Text',
        'old_remark' => 'Old CRM Remark',
        'old_lead_id' => 'Old CRM Lead ID',
        'old_created_at' => 'Old CRM Created At',
    ];

    private const STAGE_BUCKETS = [
        'lead' => 'Lead',
        'follow_up' => 'Follow Up',
        'meeting' => 'Meeting',
        'site_visit' => 'Site Visit',
        'closer' => 'Closer',
    ];

    private const LEAD_BUCKET_STATUSES = [
        'new' => 'New / Not Connected',
        'connected' => 'Connected',
    ];

    public function getWizardContext(int $userId): array
    {
        $profiles = $this->oldCrmProfilesTableExists()
            ? OldCrmImportProfile::where('user_id', $userId)
                ->latest()
                ->get()
                ->map(fn (OldCrmImportProfile $profile) => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'header_signature' => $profile->header_signature,
                    'headers' => $profile->headers ?? [],
                    'mapping_config' => $profile->mapping_config ?? [],
                    'stage_mapping' => $profile->stage_mapping ?? [],
                    'lead_status_mapping' => $profile->mapping_config['_lead_status_mapping'] ?? [],
                    'updated_at' => optional($profile->updated_at)->toDateTimeString(),
                ])->all()
            : [];

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
            'lead_fields' => collect(self::LEAD_FIELDS)->map(fn ($label, $key) => [
                'value' => "lead:{$key}",
                'label' => $label,
            ])->values()->all(),
            'meta_fields' => collect(self::META_FIELDS)->map(fn ($label, $key) => [
                'value' => "meta:{$key}",
                'label' => $label,
            ])->values()->all(),
            'custom_fields' => LeadFormField::active()->orderBy('display_order')->get([
                'field_key',
                'field_label',
                'field_type',
                'field_level',
                'is_required',
            ])->map(fn (LeadFormField $field) => [
                'value' => "custom:{$field->field_key}",
                'field_key' => $field->field_key,
                'label' => $field->field_label,
                'field_type' => $field->field_type,
                'field_level' => $field->field_level,
                'is_required' => (bool) $field->is_required,
            ])->values()->all(),
            'profiles' => $profiles,
            'stage_bucket_options' => collect(self::STAGE_BUCKETS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values()->all(),
            'lead_bucket_status_options' => collect(self::LEAD_BUCKET_STATUSES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values()->all(),
            'assignable_users' => $assignableUsers,
            'field_types' => ['text', 'textarea', 'select', 'date', 'time', 'datetime', 'number', 'email', 'tel'],
        ];
    }

    public function analyzeUploadedFile(UploadedFile $file, int $userId): array
    {
        $token = $this->storeTempFile($file);
        $payload = $this->parseCsvFromToken($token);
        $signature = $this->buildHeaderSignature($payload['headers']);

        $matchedProfiles = $this->oldCrmProfilesTableExists()
            ? OldCrmImportProfile::where('user_id', $userId)
                ->where('header_signature', $signature)
                ->latest()
                ->get()
                ->map(fn (OldCrmImportProfile $profile) => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'mapping_config' => $profile->mapping_config ?? [],
                    'stage_mapping' => $profile->stage_mapping ?? [],
                    'lead_status_mapping' => $profile->mapping_config['_lead_status_mapping'] ?? [],
                ])->values()->all()
            : [];

        return [
            'file_token' => $token,
            'headers' => $payload['headers'],
            'preview_rows' => array_slice($payload['rows'], 0, 8),
            'total_rows' => count($payload['rows']),
            'header_signature' => $signature,
            'matched_profiles' => $matchedProfiles,
            'distinct_values_by_column' => $this->buildDistinctValuesByColumn($payload['rows'], $payload['headers']),
        ];
    }

    public function validateImport(string $fileToken, array $mappingConfig, array $stageMapping, array $leadStatusMapping, array $ownerMapping, array $createCustomFields, string $importMode = 'all'): array
    {
        $payload = $this->parseCsvFromToken($fileToken);
        $resolvedMappings = $this->normalizeMappings($mappingConfig);
        $this->assertRequiredMappings($resolvedMappings);
        $resolvedCreatedFields = $this->resolveCustomFieldSpecs($createCustomFields, false);
        $normalizedStageMapping = $this->normalizeStageMapping($stageMapping);
        $normalizedLeadStatusMapping = $this->normalizeLeadStatusMapping($leadStatusMapping);
        $normalizedOwnerMapping = $this->normalizeOwnerMapping($ownerMapping);

        $rows = $payload['rows'];
        if ($importMode === 'demo' && count($rows) > 1) {
            $rows = array_slice($rows, 0, 1);
        }

        $validation = $this->processRows(
            $rows,
            $payload['headers'],
            $resolvedMappings,
            $normalizedStageMapping,
            $normalizedLeadStatusMapping,
            $normalizedOwnerMapping,
            $resolvedCreatedFields,
            false
        );

        return array_merge($validation, [
            'total_rows' => count($payload['rows']),
            'processed_rows' => count($rows),
            'headers' => $payload['headers'],
        ]);
    }

    public function import(string $fileToken, int $userId, array $mappingConfig, array $stageMapping, array $leadStatusMapping, array $ownerMapping, array $createCustomFields, array $options = []): array
    {
        $payload = $this->parseCsvFromToken($fileToken);
        $resolvedMappings = $this->normalizeMappings($mappingConfig);
        $this->assertRequiredMappings($resolvedMappings);
        $normalizedStageMapping = $this->normalizeStageMapping($stageMapping);
        $normalizedLeadStatusMapping = $this->normalizeLeadStatusMapping($leadStatusMapping);
        $normalizedOwnerMapping = $this->normalizeOwnerMapping($ownerMapping);
        $importMode = ($options['import_mode'] ?? 'all') === 'demo' ? 'demo' : 'all';

        $rows = $payload['rows'];
        if ($importMode === 'demo' && count($rows) > 1) {
            $rows = array_slice($rows, 0, 1);
        }

        $profile = null;

        DB::beginTransaction();
        try {
            $resolvedCreatedFields = $this->resolveCustomFieldSpecs($createCustomFields, true);

            if ($this->oldCrmProfilesTableExists() && !empty($options['save_profile']) && !empty($options['profile_name'])) {
                $profileMappings = $this->persistableMappings($resolvedMappings, $resolvedCreatedFields);
                $profileMappings['_lead_status_mapping'] = $normalizedLeadStatusMapping;

                $profile = OldCrmImportProfile::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'name' => trim((string) $options['profile_name']),
                    ],
                    [
                        'header_signature' => $this->buildHeaderSignature($payload['headers']),
                        'headers' => $payload['headers'],
                        'mapping_config' => $profileMappings,
                        'stage_mapping' => $normalizedStageMapping,
                    ]
                );
            } elseif ($this->oldCrmProfilesTableExists() && !empty($options['profile_id'])) {
                $profile = OldCrmImportProfile::where('user_id', $userId)->find($options['profile_id']);
            }

            $batch = ImportBatch::create([
                'user_id' => $userId,
                'source_type' => 'csv',
                'import_kind' => 'old_crm',
                'status' => 'processing',
                'total_leads' => count($payload['rows']),
                'import_profile_id' => $profile?->id,
            ]);

            $result = $this->processRows(
                $rows,
                $payload['headers'],
                $resolvedMappings,
                $normalizedStageMapping,
                $normalizedLeadStatusMapping,
                $normalizedOwnerMapping,
                $resolvedCreatedFields,
                true,
                $userId,
                $batch
            );

            $batch->update([
                'imported_leads' => $result['imported'],
                'failed_leads' => $result['failed'],
                'status' => 'completed',
                'error_log' => [
                    'import_kind' => 'old_crm',
                    'warnings' => $result['warnings'],
                    'errors' => $result['errors'],
                    'skipped_duplicates' => $result['skipped_duplicates'],
                    'rows_with_warnings' => $result['rows_with_warnings'],
                ],
            ]);

            DB::commit();

            return [
                'batch' => $batch->fresh(['importProfile']),
                'imported' => $result['imported'],
                'failed' => $result['failed'],
                'warnings' => $result['warnings'],
                'errors' => $result['errors'],
                'skipped_duplicates' => $result['skipped_duplicates'],
                'rows_with_warnings' => $result['rows_with_warnings'],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processRows(
        array $rows,
        array $headers,
        array $resolvedMappings,
        array $stageMapping,
        array $leadStatusMapping,
        array $ownerMapping,
        array $resolvedCreatedFields,
        bool $shouldPersist,
        ?int $userId = null,
        ?ImportBatch $batch = null
    ): array {
        $errors = [];
        $warnings = [];
        $preview = [];
        $imported = 0;
        $failed = 0;
        $skippedDuplicates = 0;
        $rowsWithWarnings = 0;
        $seenPhones = [];

        foreach ($rows as $row) {
            $mapped = $this->mapRow($row, $headers, $resolvedMappings, $stageMapping, $leadStatusMapping, $ownerMapping, $resolvedCreatedFields);
            $rowWarnings = $mapped['warnings'];
            $rowErrors = $mapped['errors'];
            $phone = $mapped['lead']['phone'] ?? '';

            if ($phone !== '' && isset($seenPhones[$phone])) {
                $rowErrors[] = "Duplicate phone {$phone} found inside this import file.";
            }

            if ($phone !== '' && app(DuplicateDetectionService::class)->findExistingLeadByPhone($phone)) {
                $rowErrors[] = "Phone {$phone} already exists in CRM.";
            }

            if (!empty($rowWarnings)) {
                $rowsWithWarnings++;
                foreach ($rowWarnings as $warning) {
                    $warnings[] = "Row {$row['_row_number']}: {$warning}";
                }
            }

            if (!empty($rowErrors)) {
                $failed++;
                if (collect($rowErrors)->contains(fn ($message) => str_contains($message, 'already exists') || str_contains($message, 'Duplicate phone'))) {
                    $skippedDuplicates++;
                }

                foreach ($rowErrors as $error) {
                    $errors[] = "Row {$row['_row_number']}: {$error}";
                }

                continue;
            }

            if ($phone !== '') {
                $seenPhones[$phone] = true;
            }

            $preview[] = [
                'row_number' => $row['_row_number'],
                'name' => $mapped['lead']['name'] ?? null,
                'phone' => $phone,
                'source' => $mapped['lead']['source'] ?? null,
                'status' => $mapped['pipeline_stage_label'] ?? ($mapped['lead']['status'] ?? 'new'),
                'assigned_to' => $mapped['assigned_user_name'] ?? null,
                'meta' => $mapped['meta'],
                'custom' => $mapped['custom'],
            ];

            if (!$shouldPersist) {
                continue;
            }

            $leadData = $mapped['lead'];
            $leadData['status'] = $leadData['status'] ?? 'new';
            $leadData['source'] = $leadData['source'] ?? 'other';
            $leadData['created_by'] = $userId;
            $leadData['status_auto_update_enabled'] = !in_array($leadData['status'], ['closed', 'dead', 'junk'], true);
            $leadData['notes'] = $this->buildLeadNotes($leadData['notes'] ?? null, $mapped['meta']);

            $lead = Lead::create($leadData);

            $assignedUserId = $mapped['assigned_user_id'] ?? null;
            if ($assignedUserId) {
                $this->assignLeadToUser($lead, $assignedUserId, $userId);
            }

            foreach ($mapped['custom'] as $fieldKey => $value) {
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                LeadFormFieldValue::updateOrCreate(
                    ['lead_id' => $lead->id, 'field_key' => $fieldKey],
                    [
                        'field_value' => trim((string) $value),
                        'filled_by_user_id' => $userId,
                        'filled_at' => now(),
                    ]
                );
            }

            $this->createPipelineArtifacts($lead, $mapped, $assignedUserId, $userId);

            ImportedLead::create([
                'import_batch_id' => $batch?->id,
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUserId,
                'assigned_at' => $assignedUserId ? now() : null,
                'import_data' => [
                    'kind' => 'old_crm',
                    'metadata' => $mapped['meta'],
                    'custom_fields' => $mapped['custom'],
                    'raw_row' => $mapped['raw_row'],
                ],
            ]);

            $imported++;
        }

        return [
            'preview' => array_slice($preview, 0, 10),
            'imported' => $imported,
            'failed' => $failed,
            'warnings' => $warnings,
            'errors' => $errors,
            'skipped_duplicates' => $skippedDuplicates,
            'rows_with_warnings' => $rowsWithWarnings,
        ];
    }

    private function mapRow(
        array $row,
        array $headers,
        array $resolvedMappings,
        array $stageMapping,
        array $leadStatusMapping,
        array $ownerMapping,
        array $resolvedCreatedFields
    ): array
    {
        $leadData = [];
        $metaData = [];
        $customData = [];
        $warnings = [];
        $errors = [];
        $rawRow = [];
        $assignedUserId = null;
        $assignedUserName = null;
        $pipelineStage = null;
        $pipelineStageLabel = null;

        foreach ($headers as $header) {
            $rawRow[$header['label']] = $row['values'][$header['index']] ?? null;
        }

        foreach ($resolvedMappings as $columnIndex => $target) {
            $value = trim((string) ($row['values'][$columnIndex] ?? ''));
            if ($value === '') {
                continue;
            }

            [$type, $key] = explode(':', $target, 2);

            if ($type === 'lead') {
                if ($key === 'phone') {
                    $leadData['phone'] = $this->normalizePhone($value);
                    continue;
                }

                if ($key === 'source') {
                    $leadData['source'] = Lead::normalizeSource($value);
                    $metaData['old_source'] = $metaData['old_source'] ?? $value;
                    continue;
                }

                if ($key === 'owner') {
                    $metaData['old_owner'] = $value;
                    $normalizedOwner = $this->normalizeLookupValue($value);
                    $assignedUserId = $ownerMapping[$normalizedOwner] ?? null;
                    if (!$assignedUserId) {
                        $errors[] = "Owner value '{$value}' is not mapped to any CRM user.";
                        continue;
                    }

                    $assignedUserName = User::find($assignedUserId)?->name;
                    continue;
                }

                if ($key === 'created_on') {
                    $metaData['old_created_at'] = $value;
                    continue;
                }

                if ($key === 'status') {
                    $metaData['old_stage'] = $metaData['old_stage'] ?? $value;
                    $pipelineStage = $stageMapping[$this->normalizeLookupValue($value)] ?? null;
                    if ($pipelineStage === null) {
                        $errors[] = "Stage value '{$value}' has no pipeline mapping.";
                        continue;
                    }

                    $pipelineStageLabel = self::STAGE_BUCKETS[$pipelineStage] ?? ucfirst(str_replace('_', ' ', $pipelineStage));
                    $leadData['status'] = match ($pipelineStage) {
                        'meeting' => 'meeting_scheduled',
                        'site_visit' => 'visit_scheduled',
                        'closer' => 'closed',
                        'lead' => $leadStatusMapping[$this->normalizeLookupValue($value)] ?? 'new',
                        default => 'new',
                    };
                    if ($pipelineStage === 'lead' && !array_key_exists($leadData['status'], self::LEAD_BUCKET_STATUSES)) {
                        $errors[] = "Lead stage value '{$value}' has invalid connected/not connected mapping.";
                        unset($leadData['status']);
                    }
                    continue;
                }

                $leadData[$key] = $value;
                continue;
            }

            if ($type === 'meta') {
                $metaData[$key] = $value;
                continue;
            }

            if ($type === 'custom') {
                $customData[$key] = $value;
                continue;
            }

            if ($type === 'create_custom') {
                $fieldKey = $resolvedCreatedFields[$key]['field_key'] ?? null;
                if ($fieldKey) {
                    $customData[$fieldKey] = $value;
                }
            }
        }

        if (empty($leadData['name'])) {
            $errors[] = 'Mapped lead name is missing.';
        }

        if (empty($leadData['phone'])) {
            $errors[] = 'Mapped phone is missing.';
        }

        foreach ($resolvedCreatedFields as $spec) {
            if (($spec['is_required'] ?? false) && blank($customData[$spec['field_key']] ?? null)) {
                $errors[] = "Required custom field '{$spec['field_label']}' is empty.";
            }
        }

        $requiredExistingFields = LeadFormField::active()
            ->where('is_required', true)
            ->whereIn('field_key', array_keys($customData))
            ->get(['field_key', 'field_label']);

        foreach ($requiredExistingFields as $field) {
            if (blank($customData[$field->field_key] ?? null)) {
                $errors[] = "Required custom field '{$field->field_label}' is empty.";
            }
        }

        if (!$pipelineStage) {
            $errors[] = 'Mapped stage is missing.';
        }

        if (!isset($metaData['old_owner'])) {
            $errors[] = 'Mapped owner is missing.';
        }

        if (!isset($leadData['status'])) {
            $leadData['status'] = 'new';
        }

        if (!isset($leadData['source'])) {
            $leadData['source'] = 'other';
        }

        return [
            'lead' => $leadData,
            'meta' => $metaData,
            'custom' => $customData,
            'assigned_user_id' => $assignedUserId,
            'assigned_user_name' => $assignedUserName,
            'pipeline_stage' => $pipelineStage,
            'pipeline_stage_label' => $pipelineStageLabel,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'raw_row' => $rawRow,
        ];
    }

    private function assertRequiredMappings(array $resolvedMappings): void
    {
        $requiredMappings = ['lead:name', 'lead:phone', 'lead:owner', 'lead:status'];
        foreach ($requiredMappings as $requiredMapping) {
            if (!in_array($requiredMapping, $resolvedMappings, true)) {
                throw new \InvalidArgumentException('Name, phone, owner, and stage mappings are required before validation/import.');
            }
        }
    }

    private function normalizeMappings(array $mappingConfig): array
    {
        return collect($mappingConfig)
            ->mapWithKeys(function ($target, $columnIndex) {
                $target = trim((string) $target);
                if ($target === '') {
                    return [];
                }

                return [(int) $columnIndex => $target];
            })
            ->all();
    }

    private function normalizeStageMapping(array $stageMapping): array
    {
        $normalized = [];

        foreach ($stageMapping as $oldValue => $bucket) {
            $bucket = trim((string) $bucket);
            if ($bucket === '' || !array_key_exists($bucket, self::STAGE_BUCKETS)) {
                continue;
            }

            $normalized[$this->normalizeLookupValue($oldValue)] = $bucket;
        }

        return $normalized;
    }

    private function normalizeLeadStatusMapping(array $leadStatusMapping): array
    {
        $normalized = [];

        foreach ($leadStatusMapping as $oldValue => $status) {
            $status = trim((string) $status);
            if ($status === '' || !array_key_exists($status, self::LEAD_BUCKET_STATUSES)) {
                continue;
            }

            $normalized[$this->normalizeLookupValue($oldValue)] = $status;
        }

        return $normalized;
    }

    private function normalizeOwnerMapping(array $ownerMapping): array
    {
        $normalized = [];

        foreach ($ownerMapping as $ownerValue => $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }

            $normalized[$this->normalizeLookupValue($ownerValue)] = $userId;
        }

        return $normalized;
    }

    private function resolveCustomFieldSpecs(array $createCustomFields, bool $persist): array
    {
        $resolved = [];

        foreach ($createCustomFields as $tempKey => $spec) {
            $label = trim((string) ($spec['field_label'] ?? ''));
            $type = trim((string) ($spec['field_type'] ?? 'text'));
            $isRequired = (bool) ($spec['is_required'] ?? false);

            if ($label === '') {
                throw new \InvalidArgumentException("Created custom field '{$tempKey}' is missing a label.");
            }

            if (!in_array($type, ['text', 'textarea', 'select', 'date', 'time', 'datetime', 'number', 'email', 'tel'], true)) {
                throw new \InvalidArgumentException("Created custom field '{$label}' has invalid type '{$type}'.");
            }

            $fieldKey = $this->generateFieldKey($label);

            if ($persist) {
                $field = LeadFormField::firstOrCreate(
                    ['field_key' => $fieldKey],
                    [
                        'field_label' => $label,
                        'field_type' => $type,
                        'field_level' => 'sales_executive',
                        'is_required' => $isRequired,
                        'is_active' => true,
                        'display_order' => (LeadFormField::max('display_order') ?? 0) + 1,
                    ]
                );

                $fieldKey = $field->field_key;
            }

            $resolved[$tempKey] = [
                'field_key' => $fieldKey,
                'field_label' => $label,
                'field_type' => $type,
                'is_required' => $isRequired,
            ];
        }

        return $resolved;
    }

    private function persistableMappings(array $resolvedMappings, array $resolvedCreatedFields): array
    {
        $profileMappings = [];

        foreach ($resolvedMappings as $columnIndex => $target) {
            if (!str_starts_with($target, 'create_custom:')) {
                $profileMappings[$columnIndex] = $target;
                continue;
            }

            $tempKey = explode(':', $target, 2)[1];
            $fieldKey = $resolvedCreatedFields[$tempKey]['field_key'] ?? null;
            if ($fieldKey) {
                $profileMappings[$columnIndex] = "custom:{$fieldKey}";
            }
        }

        return $profileMappings;
    }

    private function buildLeadNotes(?string $leadNotes, array $metaData): ?string
    {
        $parts = [];

        if (!blank($leadNotes)) {
            $parts[] = trim((string) $leadNotes);
        }

        if (!blank($metaData['old_remark'] ?? null)) {
            $parts[] = 'Old CRM Remark: ' . trim((string) $metaData['old_remark']);
        }

        if (!blank($metaData['old_lead_id'] ?? null)) {
            $parts[] = 'Old CRM Lead ID: ' . trim((string) $metaData['old_lead_id']);
        }

        $parts[] = 'Imported from Old CRM';

        $note = trim(implode("\n\n", array_filter($parts)));

        return $note !== '' ? $note : null;
    }

    private function assignLeadToUser(Lead $lead, int $assignedUserId, int $assignedBy): void
    {
        $lead->assignments()->update(['is_active' => false, 'unassigned_at' => now()]);

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedUserId,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
            'notes' => 'Assigned during old CRM import',
        ]);

        event(new LeadAssigned($lead, $assignedUserId, $assignedBy));
    }

    private function createPipelineArtifacts(Lead $lead, array $mapped, ?int $assignedUserId, int $userId): void
    {
        $stage = $mapped['pipeline_stage'] ?? null;
        if (!$stage) {
            return;
        }

        $scheduledAt = $this->parseImportedDate($mapped['meta']['old_created_at'] ?? null) ?? now();

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
                'meeting_notes' => $this->buildFlowNote($mapped['meta'], 'Imported directly into Meeting pipeline'),
            ]);

            return;
        }

        if (in_array($stage, ['site_visit', 'closer'], true)) {
            $visit = SiteVisit::create([
                'lead_id' => $lead->id,
                'created_by' => $userId,
                'assigned_to' => $assignedUserId,
                'scheduled_at' => $scheduledAt,
                'status' => $stage === 'closer' ? 'completed' : 'scheduled',
                'completed_at' => $stage === 'closer' ? $scheduledAt : null,
                'visit_notes' => $this->buildFlowNote($mapped['meta'], $stage === 'closer'
                    ? 'Imported directly into Closer pipeline'
                    : 'Imported directly into Site Visit pipeline'),
                'verification_status' => $stage === 'closer' ? 'verified' : 'pending',
                'verified_by' => $stage === 'closer' ? $userId : null,
                'verified_at' => $stage === 'closer' ? now() : null,
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

                $lead->update([
                    'status' => 'closed',
                    'status_auto_update_enabled' => false,
                ]);
            }
        }
    }

    private function buildFlowNote(array $metaData, string $prefix): string
    {
        $parts = [$prefix];

        if (!blank($metaData['old_stage'] ?? null)) {
            $parts[] = 'Old Stage: ' . $metaData['old_stage'];
        }

        if (!blank($metaData['old_created_at'] ?? null)) {
            $parts[] = 'Old Created On: ' . $metaData['old_created_at'];
        }

        if (!blank($metaData['old_owner'] ?? null)) {
            $parts[] = 'Old Owner: ' . $metaData['old_owner'];
        }

        return implode("\n", $parts);
    }

    private function parseImportedDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseCsvFromToken(string $fileToken): array
    {
        $path = $this->resolveTempPath($fileToken);
        if (!Storage::disk(self::TEMP_DISK)->exists($path)) {
            throw new \RuntimeException('Uploaded CSV session expired. Please upload the file again.');
        }

        $fullPath = Storage::disk(self::TEMP_DISK)->path($path);
        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open uploaded CSV file.');
        }

        $headerRow = fgetcsv($handle);
        if (!$headerRow) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or invalid.');
        }

        $headers = [];
        $labelCounts = [];
        foreach ($headerRow as $index => $label) {
            $label = trim((string) $label);
            $label = $label !== '' ? $label : "Column {$index}";
            $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
            $finalLabel = $labelCounts[$label] > 1 ? "{$label} ({$labelCounts[$label]})" : $label;

            $headers[] = [
                'index' => $index,
                'label' => $finalLabel,
                'normalized' => $this->normalizeLookupValue($label),
            ];
        }

        $rows = [];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (empty(array_filter($row, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $rows[] = [
                '_row_number' => $rowNumber,
                'values' => $row,
            ];
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function buildDistinctValuesByColumn(array $rows, array $headers): array
    {
        $distinct = [];

        foreach ($headers as $header) {
            $values = collect($rows)
                ->map(fn ($row) => trim((string) ($row['values'][$header['index']] ?? '')))
                ->filter(fn ($value) => $value !== '')
                ->unique()
                ->take(100)
                ->values()
                ->all();

            $distinct[$header['index']] = $values;
        }

        return $distinct;
    }

    private function storeTempFile(UploadedFile $file): string
    {
        $token = Str::uuid()->toString();
        $name = $token . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs(self::TEMP_DIR, $name, self::TEMP_DISK);

        if (!$path) {
            throw new \RuntimeException('Unable to store uploaded CSV file.');
        }

        return $token;
    }

    private function resolveTempPath(string $fileToken): string
    {
        $files = Storage::disk(self::TEMP_DISK)->files(self::TEMP_DIR);
        $matched = collect($files)->first(fn ($path) => str_starts_with(basename($path), $fileToken . '_'));

        if (!$matched) {
            throw new \RuntimeException('Uploaded CSV file not found.');
        }

        return $matched;
    }

    private function buildHeaderSignature(array $headers): string
    {
        $signatureSource = collect($headers)
            ->map(fn ($header) => $header['normalized'])
            ->join('|');

        return hash('sha256', $signatureSource);
    }

    private function normalizeLookupValue(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->value();
    }

    private function normalizePhone(?string $value): string
    {
        $phone = trim((string) $value);
        $phone = preg_replace('/[^0-9+]+/', '', $phone);

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '+')) {
            return '+' . ltrim(substr($phone, 1), '+');
        }

        return $phone;
    }

    private function generateFieldKey(string $label): string
    {
        $base = Str::slug($label, '_');
        $base = $base !== '' ? $base : 'old_crm_field';
        $fieldKey = $base;
        $suffix = 2;

        while (LeadFormField::where('field_key', $fieldKey)->exists()) {
            $fieldKey = $base . '_' . $suffix;
            $suffix++;
        }

        return $fieldKey;
    }

    private function oldCrmProfilesTableExists(): bool
    {
        static $exists;

        if ($exists !== null) {
            return $exists;
        }

        $exists = Schema::hasTable('old_crm_import_profiles');

        return $exists;
    }
}
