<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Models\Lead;
use App\Models\LeadBankAudit;
use App\Models\LeadBankImportRow;
use App\Models\LeadBankImportSession;
use App\Models\LeadTag;
use App\Models\BlacklistedNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LeadBankImportService
{
    private const DISK = 'local';
    private const DIR = 'lead-bank-imports';

    private const FIELD_OPTIONS = [
        'skip' => 'Skip Column',
        'name' => 'Lead Name',
        'phone' => 'Phone Number',
        'email' => 'Email',
        'city' => 'City',
        'state' => 'State',
        'source' => 'Source',
        'budget' => 'Budget',
        'requirements' => 'Requirement',
        'notes' => 'Notes',
    ];

    public function __construct(private readonly DuplicateDetectionService $duplicateService)
    {
    }

    public function fieldOptions(): array
    {
        return self::FIELD_OPTIONS;
    }

    public function createSession(UploadedFile $file, int $userId, string $sourceType, array $defaultTags, ?array $folder = null): LeadBankImportSession
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $storedName = Str::uuid()->toString() . '.' . $extension;
        $storedPath = $file->storeAs(self::DIR, $storedName, self::DISK);
        $rows = $this->parseStoredFile(Storage::disk(self::DISK)->path($storedPath), $extension);

        if (count($rows) < 2) {
            throw new \RuntimeException('Import file must contain a header row and at least one data row.');
        }

        $headers = $this->headersFromRow(array_shift($rows));
        $mapping = $this->detectColumnMapping($headers);

        return DB::transaction(function () use ($userId, $file, $storedPath, $sourceType, $defaultTags, $folder, $headers, $mapping, $rows) {
            $session = LeadBankImportSession::create([
                'user_id' => $userId,
                'original_file_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'source_type' => $sourceType,
                'headers' => $headers,
                'column_mapping' => $mapping,
                'default_tags' => $defaultTags,
                'folder_name' => $folder['name'] ?? null,
                'folder_color' => $folder['color'] ?? null,
                'status' => 'draft',
                'total_rows' => count(array_filter($rows, fn ($values) => !empty(array_filter($values, fn ($value) => trim((string) $value) !== '')))),
                'processed_rows' => 0,
                'processing_error' => null,
            ]);

            $this->audit('import_session_created', null, $session, null, [
                'session_id' => $session->id,
                'file_name' => $session->original_file_name,
                'rows' => $session->total_rows,
            ]);

            return $session->fresh();
        });
    }

    public function updateMapping(LeadBankImportSession $session, array $mapping): void
    {
        $rows = $this->dataRowsForSession($session);
        $normalized = $this->normalizeMapping($mapping, $session->headers ?? []);

        DB::transaction(function () use ($session, $rows, $normalized) {
            $session->rows()->delete();
            $session->update([
                'column_mapping' => $normalized,
                'status' => 'draft',
                'total_rows' => count(array_filter($rows, fn ($values) => !empty(array_filter($values, fn ($value) => trim((string) $value) !== '')))),
                'processed_rows' => 0,
                'processing_error' => null,
                'included_rows' => 0,
                'duplicate_rows' => 0,
                'invalid_rows' => 0,
            ]);
            $this->audit('import_mapping_updated', null, $session, null, [
                'session_id' => $session->id,
                'column_mapping' => $normalized,
            ]);
        });
    }

    public function updateRows(LeadBankImportSession $session, array $rowUpdates): void
    {
        DB::transaction(function () use ($session, $rowUpdates) {
            $rows = $session->rows()->get()->keyBy('id');
            foreach ($rowUpdates as $rowId => $payload) {
                $row = $rows->get((int) $rowId);
                if (!$row) {
                    continue;
                }

                $validation = $row->validation_status;
                $action = (string) ($payload['import_action'] ?? $row->import_action);
                $include = isset($payload['include']);
                if ($action === 'skip') {
                    $include = false;
                }

                if ($validation === 'duplicate' && $action === 'create') {
                    $action = 'skip';
                    $include = false;
                }

                $row->update([
                    'include' => $include,
                    'import_action' => in_array($action, ['create', 'update_existing', 'skip'], true) ? $action : $row->import_action,
                    'tags' => $this->normalizeTags((string) ($payload['tags'] ?? implode(',', $row->tags ?? []))),
                ]);
            }

            $this->refreshSessionCounts($session);
        });
    }

    public function applyBulkTag(LeadBankImportSession $session, string $tag, array $rowIds): int
    {
        $tag = trim($tag);
        if ($tag === '') {
            return 0;
        }

        $rows = $session->rows()->whereIn('id', $rowIds)->get();
        foreach ($rows as $row) {
            $row->update([
                'tags' => array_values(array_unique(array_merge($row->tags ?? [], [$tag]))),
            ]);
        }

        return $rows->count();
    }

    public function confirm(LeadBankImportSession $session, int $userId): array
    {
        if ($session->status === 'imported') {
            throw new \RuntimeException('This import session is already imported.');
        }

        return DB::transaction(function () use ($session, $userId) {
            $rows = $session->rows()->where('include', true)->get();
            $batch = ImportBatch::create([
                'user_id' => $userId,
                'source_type' => 'csv',
                'import_kind' => 'lead_bank',
                'file_name' => $session->original_file_name,
                'total_leads' => $session->total_rows,
                'imported_leads' => 0,
                'failed_leads' => 0,
                'status' => 'processing',
                'error_log' => [
                    'lead_bank_import_session_id' => $session->id,
                    'skipped_rows' => 0,
                    'updated_duplicates' => 0,
                    'errors' => [],
                ],
            ]);

            $created = 0;
            $updated = 0;
            $skipped = $session->rows()->where('include', false)->count();
            $failed = 0;
            $errors = [];
            $folderTag = null;

            foreach ($rows as $row) {
                try {
                    if ($row->import_action === 'update_existing' && $row->existing_lead_id) {
                        $lead = Lead::find($row->existing_lead_id);
                        if ($lead) {
                            $this->attachTags($lead, $row->tags ?? [], $userId);
                            ImportedLead::create([
                                'import_batch_id' => $batch->id,
                                'lead_id' => $lead->id,
                                'import_data' => [
                                    'lead_bank_import_row_id' => $row->id,
                                    'action' => 'update_existing',
                                    'raw_data' => $row->raw_data,
                                    'mapped_data' => $row->mapped_data,
                                ],
                            ]);
                            $this->attachFolderTag($lead, $session, $userId, $folderTag);
                            $updated++;
                        }
                        continue;
                    }

                    if ($row->validation_status !== 'valid' || $row->import_action !== 'create') {
                        $skipped++;
                        continue;
                    }

                    $mapped = $row->mapped_data ?? [];
                    $lead = Lead::create([
                        'name' => $mapped['name'],
                        'email' => $mapped['email'] ?? null,
                        'phone' => $row->normalized_phone,
                        'city' => $mapped['city'] ?? null,
                        'state' => $mapped['state'] ?? null,
                        'source' => Lead::normalizeSource($mapped['source'] ?? $session->source_type),
                        'budget' => $mapped['budget'] ?? null,
                        'requirements' => $mapped['requirements'] ?? null,
                        'notes' => $mapped['notes'] ?? null,
                        'status' => 'new',
                        'created_by' => $userId,
                    ]);

                    $this->attachTags($lead, $row->tags ?? [], $userId);
                    $row->update(['created_lead_id' => $lead->id]);

                    ImportedLead::create([
                        'import_batch_id' => $batch->id,
                        'lead_id' => $lead->id,
                        'import_data' => [
                            'lead_bank_import_row_id' => $row->id,
                            'action' => 'create',
                            'raw_data' => $row->raw_data,
                            'mapped_data' => $row->mapped_data,
                        ],
                    ]);
                    $this->attachFolderTag($lead, $session, $userId, $folderTag);
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$row->row_number}: {$e->getMessage()}";
                }
            }

            $batch->update([
                'imported_leads' => $created + $updated,
                'failed_leads' => $failed,
                'status' => $failed > 0 ? 'failed' : 'completed',
                'error_log' => [
                    'lead_bank_import_session_id' => $session->id,
                    'skipped_rows' => $skipped,
                    'updated_duplicates' => $updated,
                    'errors' => $errors,
                ],
            ]);

            $session->update([
                'status' => 'imported',
                'imported_rows' => $created + $updated,
                'skipped_rows' => $skipped,
                'folder_tag_id' => $folderTag?->id,
                'imported_at' => now(),
            ]);

            $this->audit('import_session_confirmed', null, $session, null, [
                'session_id' => $session->id,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'import_batch_id' => $batch->id,
                'folder_tag_id' => $folderTag?->id,
                'folder_name' => $folderTag?->name,
            ]);

            return compact('created', 'updated', 'skipped', 'failed');
        });
    }

    public function startConfirm(LeadBankImportSession $session, int $userId): LeadBankImportSession
    {
        if ($session->status === 'imported') {
            throw new \RuntimeException('This import session is already imported.');
        }

        if ($session->status !== 'ready') {
            throw new \RuntimeException('Preview is not ready yet.');
        }

        return DB::transaction(function () use ($session, $userId) {
            if ($session->import_batch_id) {
                return $session->fresh();
            }

            $batch = ImportBatch::create([
                'user_id' => $userId,
                'source_type' => 'csv',
                'import_kind' => 'lead_bank',
                'file_name' => $session->original_file_name,
                'total_leads' => $session->rows()->where('include', true)->count(),
                'imported_leads' => 0,
                'failed_leads' => 0,
                'status' => 'processing',
                'error_log' => [
                    'lead_bank_import_session_id' => $session->id,
                    'skipped_rows' => $session->rows()->where('include', false)->count(),
                    'updated_duplicates' => 0,
                    'errors' => [],
                ],
            ]);

            $session->rows()->update(['processed_at' => null, 'created_lead_id' => null]);
            $session->update([
                'import_batch_id' => $batch->id,
                'imported_rows' => 0,
                'skipped_rows' => $session->rows()->where('include', false)->count(),
                'failed_rows' => 0,
                'processing_error' => null,
            ]);

            return $session->fresh();
        });
    }

    public function processConfirmChunk(LeadBankImportSession $session, int $userId, int $chunkSize = 100): array
    {
        if ($session->status === 'imported') {
            return $this->confirmProgressPayload($session->fresh(), true, 0, 0);
        }

        if (!$session->import_batch_id) {
            $session = $this->startConfirm($session, $userId);
        }

        $batch = ImportBatch::findOrFail($session->import_batch_id);
        $rows = $session->rows()
            ->where('include', true)
            ->whereNull('processed_at')
            ->orderBy('id')
            ->limit($chunkSize)
            ->get();

        $created = 0;
        $updated = 0;
        $failed = 0;
        $folderTag = $session->folderTag;
        $errors = data_get($batch->error_log, 'errors', []);

        foreach ($rows as $row) {
            try {
                if ($row->import_action === 'update_existing' && $row->existing_lead_id) {
                    $lead = Lead::find($row->existing_lead_id);
                    if ($lead) {
                        $this->attachTags($lead, $row->tags ?? [], $userId);
                        ImportedLead::create([
                            'import_batch_id' => $batch->id,
                            'lead_id' => $lead->id,
                            'import_data' => [
                                'lead_bank_import_row_id' => $row->id,
                                'action' => 'update_existing',
                                'raw_data' => $row->raw_data,
                                'mapped_data' => $row->mapped_data,
                            ],
                        ]);
                        $this->attachFolderTag($lead, $session, $userId, $folderTag);
                        $updated++;
                    }

                    $row->update(['processed_at' => now()]);
                    continue;
                }

                if ($row->validation_status !== 'valid' || $row->import_action !== 'create') {
                    $row->update(['processed_at' => now()]);
                    continue;
                }

                $mapped = $row->mapped_data ?? [];
                $lead = Lead::create([
                    'name' => $mapped['name'],
                    'email' => $mapped['email'] ?? null,
                    'phone' => $row->normalized_phone,
                    'city' => $mapped['city'] ?? null,
                    'state' => $mapped['state'] ?? null,
                    'source' => Lead::normalizeSource($mapped['source'] ?? $session->source_type),
                    'budget' => $mapped['budget'] ?? null,
                    'requirements' => $mapped['requirements'] ?? null,
                    'notes' => $mapped['notes'] ?? null,
                    'status' => 'new',
                    'created_by' => $userId,
                ]);

                $this->attachTags($lead, $row->tags ?? [], $userId);
                $row->update([
                    'created_lead_id' => $lead->id,
                    'processed_at' => now(),
                ]);

                ImportedLead::create([
                    'import_batch_id' => $batch->id,
                    'lead_id' => $lead->id,
                    'import_data' => [
                        'lead_bank_import_row_id' => $row->id,
                        'action' => 'create',
                        'raw_data' => $row->raw_data,
                        'mapped_data' => $row->mapped_data,
                    ],
                ]);
                $this->attachFolderTag($lead, $session, $userId, $folderTag);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$row->row_number}: {$e->getMessage()}";
                $row->update([
                    'processed_at' => now(),
                    'errors' => array_values(array_unique(array_merge($row->errors ?? [], [$e->getMessage()]))),
                ]);
            }
        }

        if ($folderTag && !$session->folder_tag_id) {
            $session->folder_tag_id = $folderTag->id;
        }

        $processedCount = $session->rows()->where('include', true)->whereNotNull('processed_at')->count();
        $totalIncluded = $session->rows()->where('include', true)->count();
        $totalImported = (int) $session->imported_rows + $created + $updated;
        $totalFailed = (int) $session->failed_rows + $failed;
        $done = $processedCount >= $totalIncluded;

        $batch->update([
            'imported_leads' => $totalImported,
            'failed_leads' => $totalFailed,
            'status' => $done ? ($totalFailed > 0 ? 'failed' : 'completed') : 'processing',
            'error_log' => [
                'lead_bank_import_session_id' => $session->id,
                'skipped_rows' => (int) $session->skipped_rows,
                'updated_duplicates' => ImportedLead::query()
                    ->where('import_batch_id', $batch->id)
                    ->where('import_data->action', 'update_existing')
                    ->count(),
                'errors' => $errors,
            ],
        ]);

        $session->update([
            'folder_tag_id' => $folderTag?->id ?? $session->folder_tag_id,
            'imported_rows' => $totalImported,
            'failed_rows' => $totalFailed,
            'status' => $done ? 'imported' : 'ready',
            'imported_at' => $done ? now() : $session->imported_at,
            'processing_error' => null,
        ]);

        if ($done) {
            $this->audit('import_session_confirmed', null, $session->fresh(), null, [
                'session_id' => $session->id,
                'imported' => $totalImported,
                'skipped' => (int) $session->skipped_rows,
                'failed' => $totalFailed,
                'import_batch_id' => $batch->id,
                'folder_tag_id' => $folderTag?->id ?? $session->folder_tag_id,
            ]);
        }

        return $this->confirmProgressPayload($session->fresh(), $done, $created, $updated);
    }

    public function summary(LeadBankImportSession $session): array
    {
        return [
            'total' => $session->rows()->count(),
            'included' => $session->rows()->where('include', true)->count(),
            'duplicate' => $session->rows()->where('validation_status', 'duplicate')->count(),
            'invalid' => $session->rows()->where('validation_status', 'invalid')->count(),
            'blocked' => $session->rows()->where('validation_status', 'blocked')->count(),
            'malformed' => $session->rows()->where('validation_status', 'malformed')->count(),
            'skipped' => $session->rows()->where('include', false)->count(),
        ];
    }

    public function processPreviewChunk(LeadBankImportSession $session, int $chunkSize = 100): array
    {
        if ($session->status === 'ready') {
            return $this->previewProgressPayload($session->fresh(), true);
        }

        $rows = $this->dataRowsForSession($session);
        $dataRows = array_values(array_filter($rows, fn ($values) => !empty(array_filter($values, fn ($value) => trim((string) $value) !== ''))));
        $total = count($dataRows);
        $offset = max(0, (int) $session->processed_rows);

        if ($total === 0) {
            $session->update([
                'status' => 'ready',
                'total_rows' => 0,
                'processed_rows' => 0,
                'processing_error' => null,
            ]);

            return $this->previewProgressPayload($session->fresh(), true);
        }

        if ($offset === 0 && $session->rows()->exists()) {
            $session->rows()->delete();
        }

        $chunk = array_slice($dataRows, $offset, $chunkSize);
        $seen = $session->rows()
            ->whereNotNull('normalized_phone')
            ->pluck('normalized_phone')
            ->flip()
            ->all();

        $prepared = [];
        foreach ($chunk as $index => $values) {
            $mapped = $this->mappedRow($values, $session->column_mapping ?? []);
            $prepared[] = [
                'index' => $offset + $index,
                'values' => $values,
                'mapped' => $mapped,
                'phone' => $this->normalizeLeadPhone($mapped['phone'] ?? ''),
            ];
        }

        $existingLeadMap = $this->existingLeadMapForPhones(collect($prepared)->pluck('phone')->filter()->all());
        $blacklistedPhones = $this->blacklistedPhoneMap(collect($prepared)->pluck('phone')->filter()->all());

        DB::transaction(function () use ($session, $prepared, &$seen, $existingLeadMap, $blacklistedPhones, $total) {
            foreach ($prepared as $item) {
                $this->createPreviewRow(
                    $session,
                    $item['values'],
                    $item['mapped'],
                    $item['phone'],
                    $item['index'],
                    $seen,
                    $existingLeadMap,
                    $blacklistedPhones
                );
            }

            $this->refreshSessionCounts($session);

            $processed = min((int) $session->processed_rows + count($prepared), $total);
            $session->update([
                'total_rows' => $total,
                'processed_rows' => $processed,
                'status' => $processed >= $total ? 'ready' : 'draft',
                'processing_error' => null,
            ]);
        });

        $session = $session->fresh();

        return $this->previewProgressPayload($session, $session->status === 'ready');
    }

    private function rebuildRows(LeadBankImportSession $session, array $rows, array $mapping, array $defaultTags): void
    {
        $seen = [];
        foreach ($rows as $index => $values) {
            if (empty(array_filter($values, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $raw = $this->rawRow($session->headers ?? [], $values);
            $mapped = $this->mappedRow($values, $mapping);
            $errors = [];
            $validation = 'valid';
            $phone = $this->normalizeLeadPhone($mapped['phone'] ?? '');
            $existingLead = null;

            if (($mapped['name'] ?? '') === '') {
                $errors[] = 'Missing required name.';
                $validation = 'invalid';
            }

            if ($phone === '' || !$this->isValidPhone($phone)) {
                $errors[] = 'Invalid or missing phone.';
                $validation = 'invalid';
            }

            if ($phone !== '') {
                if (isset($seen[$phone])) {
                    $errors[] = 'Duplicate phone inside this file.';
                    $validation = 'duplicate';
                }
                $seen[$phone] = true;

                $existingLead = $this->findExistingLeadByPhone($phone);
                if ($existingLead) {
                    $errors[] = 'Phone already exists in CRM.';
                    $validation = 'duplicate';
                }
            }

            if ($phone !== '' && $this->isBlacklisted($phone)) {
                $errors[] = 'Phone is blacklisted.';
                $validation = 'blocked';
            }

            $include = $validation === 'valid';
            LeadBankImportRow::create([
                'lead_bank_import_session_id' => $session->id,
                'row_number' => $index + 2,
                'raw_data' => $raw,
                'mapped_data' => $mapped,
                'normalized_phone' => $phone ?: null,
                'tags' => $defaultTags,
                'validation_status' => $validation,
                'errors' => $errors,
                'include' => $include,
                'import_action' => $include ? 'create' : 'skip',
                'existing_lead_id' => $existingLead?->id,
            ]);
        }

        $this->refreshSessionCounts($session);
    }

    private function createPreviewRow(
        LeadBankImportSession $session,
        array $values,
        array $mapped,
        string $phone,
        int $index,
        array &$seen,
        array $existingLeadMap,
        array $blacklistedPhones
    ): void {
        $raw = $this->rawRow($session->headers ?? [], $values);
        $errors = [];
        $validation = 'valid';
        $existingLead = null;

        if (($mapped['name'] ?? '') === '') {
            $errors[] = 'Missing required name.';
            $validation = 'invalid';
        }

        if ($phone === '' || !$this->isValidPhone($phone)) {
            $errors[] = 'Invalid or missing phone.';
            $validation = 'invalid';
        }

        if ($phone !== '') {
            if (isset($seen[$phone])) {
                $errors[] = 'Duplicate phone inside this file.';
                $validation = 'duplicate';
            }
            $seen[$phone] = true;

            $existingLead = $existingLeadMap[$phone] ?? null;
            if ($existingLead) {
                $errors[] = 'Phone already exists in CRM.';
                $validation = 'duplicate';
            }
        }

        if ($phone !== '' && isset($blacklistedPhones[$phone])) {
            $errors[] = 'Phone is blacklisted.';
            $validation = 'blocked';
        }

        $include = $validation === 'valid';
        LeadBankImportRow::create([
            'lead_bank_import_session_id' => $session->id,
            'row_number' => $index + 2,
            'raw_data' => $raw,
            'mapped_data' => $mapped,
            'normalized_phone' => $phone ?: null,
            'tags' => $session->default_tags ?? [],
            'validation_status' => $validation,
            'errors' => $errors,
            'include' => $include,
            'import_action' => $include ? 'create' : 'skip',
            'existing_lead_id' => $existingLead?->id,
        ]);
    }

    private function previewProgressPayload(LeadBankImportSession $session, bool $done): array
    {
        $total = max(0, (int) $session->total_rows);
        $processed = min((int) $session->processed_rows, $total);

        return [
            'done' => $done,
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 1) : 100,
        ];
    }

    private function confirmProgressPayload(LeadBankImportSession $session, bool $done, int $created, int $updated): array
    {
        $total = max(0, $session->rows()->where('include', true)->count());
        $processed = $session->rows()->where('include', true)->whereNotNull('processed_at')->count();

        return [
            'done' => $done,
            'processed' => $processed,
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'skipped' => (int) $session->skipped_rows,
            'failed' => (int) $session->failed_rows,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 1) : 100,
        ];
    }

    private function refreshSessionCounts(LeadBankImportSession $session): void
    {
        $session->update([
            'total_rows' => $session->rows()->count(),
            'included_rows' => $session->rows()->where('include', true)->count(),
            'duplicate_rows' => $session->rows()->where('validation_status', 'duplicate')->count(),
            'invalid_rows' => $session->rows()->whereIn('validation_status', ['invalid', 'blocked', 'malformed'])->count(),
        ]);
    }

    private function dataRowsForSession(LeadBankImportSession $session): array
    {
        $extension = strtolower(pathinfo($session->stored_path, PATHINFO_EXTENSION));
        $rows = $this->parseStoredFile(Storage::disk(self::DISK)->path($session->stored_path), $extension);
        array_shift($rows);

        return $rows;
    }

    private function parseStoredFile(string $path, string $extension): array
    {
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
            $rows = [];

            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $value = $worksheet->getCell([$col, $row])->getFormattedValue();
                    $rowData[] = is_scalar($value) ? trim((string) $value) : '';
                }
                if (!empty(array_filter($rowData, fn ($value) => trim((string) $value) !== ''))) {
                    $rows[] = $rowData;
                }
            }

            return $rows;
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            $handle = fopen($path, 'r');
            if (!$handle) {
                throw new \RuntimeException('Unable to open import file.');
            }
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = array_map(fn ($value) => trim((string) $value), $row);
            }
            fclose($handle);

            return $rows;
        }

        throw new \RuntimeException("Unsupported import file type: {$extension}");
    }

    private function headersFromRow(array $row): array
    {
        return collect($row)->map(fn ($label, $index) => [
            'index' => $index,
            'label' => trim((string) $label) !== '' ? trim((string) $label) : 'Column ' . ($index + 1),
        ])->values()->all();
    }

    private function detectColumnMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $label = strtolower((string) $header['label']);
            $field = 'skip';
            if (str_contains($label, 'name')) $field = 'name';
            elseif (str_contains($label, 'phone') || str_contains($label, 'mobile')) $field = 'phone';
            elseif (str_contains($label, 'email')) $field = 'email';
            elseif (str_contains($label, 'city')) $field = 'city';
            elseif (str_contains($label, 'state')) $field = 'state';
            elseif (str_contains($label, 'source')) $field = 'source';
            elseif (str_contains($label, 'budget')) $field = 'budget';
            elseif (str_contains($label, 'requirement') || str_contains($label, 'project')) $field = 'requirements';
            elseif (str_contains($label, 'note') || str_contains($label, 'remark')) $field = 'notes';
            $mapping[(string) $header['index']] = $field;
        }

        return $mapping;
    }

    private function normalizeMapping(array $mapping, array $headers): array
    {
        $validIndexes = collect($headers)->pluck('index')->map(fn ($index) => (string) $index)->all();
        return collect($mapping)
            ->filter(fn ($field, $index) => in_array((string) $index, $validIndexes, true) && array_key_exists($field, self::FIELD_OPTIONS))
            ->map(fn ($field) => (string) $field)
            ->all();
    }

    private function mappedRow(array $values, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $index => $field) {
            if ($field === 'skip') {
                continue;
            }
            $mapped[$field] = trim((string) ($values[(int) $index] ?? ''));
        }

        return $mapped;
    }

    private function rawRow(array $headers, array $values): array
    {
        $raw = [];
        foreach ($headers as $header) {
            $raw[$header['label']] = $values[(int) $header['index']] ?? null;
        }

        return $raw;
    }

    private function normalizeTags(string $tags): array
    {
        return collect(explode(',', $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique(fn ($tag) => strtolower($tag))
            ->values()
            ->all();
    }

    private function attachTags(Lead $lead, array $tagNames, int $userId): void
    {
        foreach ($tagNames as $tagName) {
            $tagName = trim((string) $tagName);
            if ($tagName === '') {
                continue;
            }

            $slug = Str::slug($tagName) ?: 'tag';
            $tag = LeadTag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $tagName, 'type' => 'custom', 'created_by' => $userId]
            );
            $lead->leadTags()->syncWithoutDetaching([
                $tag->id => ['assigned_by' => $userId],
            ]);
        }
    }

    private function attachFolderTag(
        Lead $lead,
        LeadBankImportSession $session,
        int $userId,
        ?LeadTag &$folderTag
    ): void {
        $folderName = trim((string) $session->folder_name);
        if ($folderName === '') {
            return;
        }

        if (!$folderTag) {
            $slug = Str::slug($folderName) ?: 'folder';
            $folderTag = LeadTag::query()
                ->where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [Str::lower($folderName)])
                ->first();

            if ($folderTag) {
                if (!$folderTag->is_folder) {
                    $oldValues = $folderTag->only(['name', 'type', 'color', 'is_folder']);
                    $folderTag->update([
                        'is_folder' => true,
                        'color' => $session->folder_color ?: '#205A44',
                    ]);
                    $this->audit('folder_promoted', null, $folderTag, $oldValues, $folderTag->only(['name', 'type', 'color', 'is_folder']));
                }
            } else {
                $folderTag = LeadTag::create([
                    'name' => $folderName,
                    'slug' => $slug,
                    'type' => 'custom',
                    'color' => $session->folder_color ?: '#205A44',
                    'is_folder' => true,
                    'created_by' => $userId,
                ]);
                $this->audit('folder_created', null, $folderTag, null, $folderTag->only(['id', 'name', 'type', 'color', 'is_folder']));
            }
        }

        $lead->leadTags()->syncWithoutDetaching([
            $folderTag->id => ['assigned_by' => $userId],
        ]);
    }

    private function normalizeLeadPhone(?string $phone): string
    {
        if (method_exists($this->duplicateService, 'normalizeLeadPhone')) {
            return (string) $this->duplicateService->normalizeLeadPhone($phone ?? '');
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, -10);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    private function isValidPhone(string $phone): bool
    {
        if (method_exists($this->duplicateService, 'isValidPhone')) {
            return (bool) $this->duplicateService->isValidPhone($phone);
        }

        return (bool) preg_match('/^[6-9]\d{9}$/', $phone);
    }

    private function findExistingLeadByPhone(string $phone): ?Lead
    {
        if (method_exists($this->duplicateService, 'findExistingLeadByPhone')) {
            return $this->duplicateService->findExistingLeadByPhone($phone);
        }

        $variants = array_values(array_unique([
            $phone,
            '0' . $phone,
            '91' . $phone,
            '+91' . $phone,
            '+91 ' . $phone,
        ]));

        return Lead::query()
            ->whereIn('phone', $variants)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', '') LIKE ?", ['%' . $phone])
            ->first();
    }

    private function existingLeadMapForPhones(array $phones): array
    {
        $phones = collect($phones)->filter()->unique()->values();
        if ($phones->isEmpty()) {
            return [];
        }

        $variants = $phones
            ->flatMap(fn (string $phone) => $this->phoneVariants($phone))
            ->unique()
            ->values()
            ->all();

        $leads = Lead::query()
            ->whereNotNull('phone')
            ->whereIn('phone', $variants)
            ->latest('id')
            ->get(['id', 'phone']);

        $map = [];
        foreach ($leads as $lead) {
            $normalized = $this->normalizeLeadPhone($lead->phone);
            if ($normalized !== '' && !isset($map[$normalized])) {
                $map[$normalized] = $lead;
            }
        }

        return $map;
    }

    private function blacklistedPhoneMap(array $phones): array
    {
        $phones = collect($phones)->filter()->unique()->values();
        if ($phones->isEmpty()) {
            return [];
        }

        $variants = $phones
            ->flatMap(fn (string $phone) => $this->phoneVariants($phone))
            ->unique()
            ->values()
            ->all();

        return BlacklistedNumber::query()
            ->whereIn('phone', $variants)
            ->pluck('phone')
            ->mapWithKeys(fn (string $phone) => [$this->normalizeLeadPhone($phone) => true])
            ->all();
    }

    private function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return array_values(array_unique(array_filter([
            $phone,
            $digits,
            $last10,
            '0' . $last10,
            '91' . $last10,
            '+91' . $last10,
            '+91 ' . $last10,
        ])));
    }

    private function isBlacklisted(string $phone): bool
    {
        if (method_exists($this->duplicateService, 'isBlacklisted')) {
            return (bool) $this->duplicateService->isBlacklisted($phone);
        }

        return false;
    }

    private function audit(string $action, ?Lead $lead, mixed $subject, ?array $oldValues, ?array $newValues): void
    {
        LeadBankAudit::create([
            'lead_id' => $lead?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => is_object($subject) ? $subject::class : null,
            'subject_id' => is_object($subject) && isset($subject->id) ? $subject->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => str_replace('_', ' ', ucfirst($action)),
        ]);
    }
}
