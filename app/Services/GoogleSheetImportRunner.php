<?php

namespace App\Services;

use App\Models\GoogleSheetImportLog;
use App\Models\GoogleSheetImportState;
use App\Models\GoogleSheetsConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GoogleSheetImportRunner
{
    public function __construct(
        private GoogleSheetsService $sheetsService,
        private GoogleSheetsLeadIntakeService $leadIntakeService,
    )
    {
    }

    public function run(GoogleSheetsConfig $config, string $triggerSource = 'cron', int $timeoutSeconds = 50): array
    {
        $startedAt = now();
        $startedMicro = microtime(true);

        $result = [
            'success' => false,
            'status' => 'failed',
            'imported' => 0,
            'already_exists' => 0,
            'already_synced' => 0,
            'missing_required' => 0,
            'errors' => 0,
            'last_processed_row_before' => 1,
            'last_processed_row_after' => 1,
            'duration_ms' => 0,
            'timed_out' => false,
            'message' => null,
            'last_row' => null,
            'processed_until_row' => null,
        ];

        $lock = Cache::lock("google_sheet_import_runner_{$config->id}", 300);
        if (!$lock->get()) {
            $result['status'] = 'no_changes';
            $result['success'] = true;
            $result['message'] = 'Sync already running for this sheet.';
            $result['duration_ms'] = (int) round((microtime(true) - $startedMicro) * 1000);
            return $result;
        }

        $hasStateTable = Schema::hasTable('google_sheet_import_state');
        $hasLogsTable = Schema::hasTable('google_sheet_import_logs');
        if (!$hasStateTable || !$hasLogsTable) {
            $missing = [];
            if (!$hasStateTable) {
                $missing[] = 'google_sheet_import_state';
            }
            if (!$hasLogsTable) {
                $missing[] = 'google_sheet_import_logs';
            }

            $result['status'] = 'failed';
            $result['success'] = false;
            $result['errors'] = 1;
            $result['message'] = 'Missing required tables: ' . implode(', ', $missing) . '. Run: php artisan migrate --force';
            $result['duration_ms'] = (int) round((microtime(true) - $startedMicro) * 1000);
            optional($lock)->release();
            return $result;
        }

        try {
            $state = GoogleSheetImportState::firstOrCreate(
                ['google_sheets_config_id' => $config->id],
                ['last_processed_row' => max((int) ($config->last_synced_row ?? 1), 1)]
            );
        } catch (\Throwable $e) {
            $result['status'] = 'failed';
            $result['success'] = false;
            $result['errors'] = 1;
            $result['message'] = 'Unable to initialize import state: ' . $e->getMessage();
            $result['duration_ms'] = (int) round((microtime(true) - $startedMicro) * 1000);
            optional($lock)->release();
            return $result;
        }
        $result['last_processed_row_before'] = (int) $state->last_processed_row;

        $lastError = null;
        $meta = [];

        try {
            if (!$config->is_active) {
                throw new \RuntimeException('Configuration is inactive.');
            }

            $sheetId = GoogleSheetsConfig::extractSheetId($config->sheet_id);
            if (!$sheetId) {
                throw new \RuntimeException('Invalid sheet ID format.');
            }

            $anchorColumn = $this->resolveAnchorColumn($config);
            $maxColumn = $this->resolveMaxRequiredColumn($config);

            // Optimization #1: metadata/light check first.
            $lastRow = $this->sheetsService->fetchSheetLastRow(
                $sheetId,
                $config->sheet_name,
                $anchorColumn,
                $config->api_key,
                $config->service_account_json_path
            );
            $result['last_row'] = $lastRow;
            $meta['anchor_column'] = $anchorColumn;
            $meta['max_column'] = $maxColumn;

            // Optimization #2: immediate exit when no new rows.
            if ($lastRow <= (int) $state->last_processed_row) {
                $result['success'] = true;
                $result['status'] = 'no_changes';
                $result['message'] = 'No new rows to import.';
                $state->update([
                    'last_run_at' => now(),
                    'last_error' => null,
                ]);
                $result['last_processed_row_after'] = (int) $state->last_processed_row;
            } else {
                $startRow = max(((int) $state->last_processed_row) + 1, 2);
                $endRow = $lastRow;

                // Optimization #3: fetch only new rows range.
                $rows = $this->sheetsService->fetchSheetData(
                    $sheetId,
                    $config->sheet_name,
                    "A{$startRow}:{$maxColumn}{$endRow}",
                    $config->api_key,
                    $config->service_account_json_path
                );

                $mappings = $config->columnMappings()->orderBy('display_order')->get();

                foreach ($rows as $offset => $row) {
                    // Optimization #5: hard timeout guard.
                    if ((microtime(true) - $startedMicro) >= $timeoutSeconds) {
                        $result['timed_out'] = true;
                        $lastError = "Timeout reached at {$timeoutSeconds}s.";
                        break;
                    }

                    $currentRow = $startRow + $offset;
                    $payload = $this->buildPayload($config, $sheetId, $currentRow, $row, $mappings->all());

                    if (empty($payload['name']) || empty($payload['phone'])) {
                        $result['missing_required']++;
                        $result['errors']++;
                        $lastError = "Row {$currentRow}: missing required Name/Phone.";
                        break;
                    }

                    $responseBody = $this->leadIntakeService->handle($config, $payload);

                    if (($responseBody['http_status'] ?? null) === 200
                        && ($responseBody['status'] ?? null) === 'ok'
                        && ($responseBody['message'] ?? '') === 'Lead created successfully') {
                        $result['imported']++;

                        // Requirement: advance state only after successful CRM insert.
                        $state->update([
                            'last_processed_row' => $currentRow,
                            'last_run_at' => now(),
                            'last_error' => null,
                        ]);
                        $config->update([
                            'last_sync_at' => now(),
                            'last_synced_row' => $currentRow,
                        ]);
                        $result['processed_until_row'] = $currentRow;
                        continue;
                    }

                    if (($responseBody['status'] ?? null) === 'ok' && ($responseBody['message'] ?? '') === 'Lead already exists') {
                        $result['already_exists']++;
                        $result['errors']++;
                        $lastError = "Row {$currentRow}: lead already exists; row not advanced.";
                        break;
                    }

                    $result['errors']++;
                    $lastError = "Row {$currentRow}: " . ($responseBody['message'] ?? 'CRM insert failed');
                    break;
                }

                $result['last_processed_row_after'] = (int) $state->fresh()->last_processed_row;
                $state->update([
                    'last_run_at' => now(),
                    'last_error' => $lastError,
                ]);

                if ($result['timed_out']) {
                    $result['status'] = $result['imported'] > 0 ? 'partial' : 'failed';
                    $result['success'] = $result['imported'] > 0;
                    $result['message'] = $lastError;
                } elseif ($result['errors'] > 0) {
                    $result['status'] = $result['imported'] > 0 ? 'partial' : 'failed';
                    $result['success'] = $result['imported'] > 0;
                    $result['message'] = $lastError;
                } else {
                    $result['status'] = 'success';
                    $result['success'] = true;
                    $result['message'] = 'Import completed successfully.';
                }
            }
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
            $result['status'] = 'failed';
            $result['success'] = false;
            $result['message'] = $lastError;
            $result['errors']++;

            $state->update([
                'last_run_at' => now(),
                'last_error' => $lastError,
            ]);

            Log::error('Google sheet import runner failed', [
                'config_id' => $config->id,
                'trigger_source' => $triggerSource,
                'error' => $lastError,
            ]);
        } finally {
            $finishedAt = now();
            $result['duration_ms'] = (int) round((microtime(true) - $startedMicro) * 1000);
            $result['last_processed_row_after'] = (int) $state->fresh()->last_processed_row;

            GoogleSheetImportLog::create([
                'google_sheets_config_id' => $config->id,
                'trigger_source' => $triggerSource,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'duration_ms' => $result['duration_ms'],
                'timed_out' => $result['timed_out'],
                'status' => $result['status'],
                'last_processed_row_before' => $result['last_processed_row_before'],
                'last_processed_row_after' => $result['last_processed_row_after'],
                'imported_count' => $result['imported'],
                'already_exists_count' => $result['already_exists'],
                'already_synced_count' => $result['already_synced'],
                'missing_required_count' => $result['missing_required'],
                'error_count' => $result['errors'],
                'error_message' => $lastError,
                'meta_json' => array_merge($meta, [
                    'last_row' => $result['last_row'],
                    'processed_until_row' => $result['processed_until_row'],
                    'timeout_seconds' => $timeoutSeconds,
                ]),
            ]);

            optional($lock)->release();
        }

        return $result;
    }

    private function resolveAnchorColumn(GoogleSheetsConfig $config): string
    {
        $mappings = $config->columnMappings()->orderBy('display_order')->get();

        $phoneMap = $mappings->firstWhere('lead_field_key', 'phone');
        if ($phoneMap && !empty($phoneMap->sheet_column)) {
            return strtoupper(trim($phoneMap->sheet_column));
        }

        $nameMap = $mappings->firstWhere('lead_field_key', 'name');
        if ($nameMap && !empty($nameMap->sheet_column)) {
            return strtoupper(trim($nameMap->sheet_column));
        }

        if (!empty($config->phone_column)) {
            return strtoupper(trim($config->phone_column));
        }

        if (!empty($config->name_column)) {
            return strtoupper(trim($config->name_column));
        }

        return 'A';
    }

    private function resolveMaxRequiredColumn(GoogleSheetsConfig $config): string
    {
        $maxIndex = 0;
        $mappings = $config->columnMappings()->get();

        foreach ($mappings as $mapping) {
            if (!empty($mapping->sheet_column)) {
                $maxIndex = max($maxIndex, GoogleSheetsConfig::columnLetterToIndex($mapping->sheet_column));
            }
        }

        if (!empty($config->name_column)) {
            $maxIndex = max($maxIndex, GoogleSheetsConfig::columnLetterToIndex($config->name_column));
        }
        if (!empty($config->phone_column)) {
            $maxIndex = max($maxIndex, GoogleSheetsConfig::columnLetterToIndex($config->phone_column));
        }

        return $this->indexToColumnLetter($maxIndex);
    }

    private function buildPayload(
        GoogleSheetsConfig $config,
        string $sheetId,
        int $rowNumber,
        array $row,
        array $mappings
    ): array {
        $payload = [
            'sheet_id' => $sheetId,
            'sheet_row_number' => $rowNumber,
            'sheet_type' => $config->sheet_type,
        ];

        if (!empty($mappings)) {
            foreach ($mappings as $mapping) {
                $leadFieldKey = $mapping->lead_field_key ?? null;
                $sheetColumn = $mapping->sheet_column ?? null;
                if (!$leadFieldKey || !$sheetColumn) {
                    continue;
                }

                $index = GoogleSheetsConfig::columnLetterToIndex($sheetColumn);
                $value = trim((string) ($row[$index] ?? ''));
                if ($value !== '') {
                    $payload[$leadFieldKey] = $value;
                }
            }
        } else {
            $nameIndex = GoogleSheetsConfig::columnLetterToIndex($config->name_column ?: 'A');
            $phoneIndex = GoogleSheetsConfig::columnLetterToIndex($config->phone_column ?: 'B');
            $payload['name'] = trim((string) ($row[$nameIndex] ?? ''));
            $payload['phone'] = trim((string) ($row[$phoneIndex] ?? ''));
        }

        return $payload;
    }

    private function indexToColumnLetter(int $index): string
    {
        $index = max(0, $index);
        $result = '';
        $index++;

        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)) . $result;
            $index = intdiv($index, 26);
        }

        return $result;
    }
}
