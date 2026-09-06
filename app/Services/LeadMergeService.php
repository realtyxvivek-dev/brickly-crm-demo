<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadMergeAudit;
use App\Models\Task;
use App\Models\TelecallerTask;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class LeadMergeService
{
    private const BLANK_FILL_FIELDS = [
        'name', 'email', 'address', 'city', 'state', 'pincode', 'property_type',
        'budget', 'budget_min', 'budget_max', 'requirements', 'preferred_location',
        'preferred_size', 'preferred_projects', 'use_end_use', 'possession_status',
    ];

    public function duplicateGroups(?string $phone = null, ?int $limit = null): Collection
    {
        $query = Lead::query()
            ->select('normalized_phone')
            ->whereNotNull('normalized_phone')
            ->whereNull('merged_into_lead_id')
            ->groupBy('normalized_phone')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('normalized_phone');

        if ($phone) {
            $query->where('normalized_phone', app(DuplicateDetectionService::class)->normalizeLeadPhone($phone));
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('normalized_phone');
    }

    public function preview(string $normalizedPhone, bool $includeLinkedCounts = true): array
    {
        $leads = Lead::query()
            ->where('normalized_phone', $normalizedPhone)
            ->whereNull('merged_into_lead_id')
            ->orderByDesc('id')
            ->get();

        return [
            'normalized_phone' => $normalizedPhone,
            'master_lead_id' => $leads->first()?->id,
            'duplicate_lead_ids' => $leads->slice(1)->pluck('id')->all(),
            'sources' => $leads->pluck('source')->filter()->unique()->values()->all(),
            'statuses' => $leads->pluck('status')->filter()->unique()->values()->all(),
            'linked_counts' => $includeLinkedCounts ? $this->criticalLinkedCounts($leads->pluck('id')->all()) : [],
        ];
    }

    private function criticalLinkedCounts(array $leadIds): array
    {
        $tables = [
            'lead_assignments', 'tasks', 'telecaller_tasks', 'site_visits', 'meetings',
            'follow_ups', 'call_logs', 'prospects', 'lead_proposals',
            'lead_form_field_values', 'fb_leads', 'imported_leads',
        ];
        $counts = [];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $column = $table === 'fb_leads' ? 'crm_lead_id' : 'lead_id';
            if (Schema::hasColumn($table, $column)) {
                $counts[$table] = DB::table($table)->whereIn($column, $leadIds)->count();
            }
        }

        return array_filter($counts);
    }

    public function merge(string $normalizedPhone, ?int $actorId = null): array
    {
        try {
            return DB::transaction(function () use ($normalizedPhone, $actorId) {
                $leads = Lead::query()
                    ->where('normalized_phone', $normalizedPhone)
                    ->whereNull('merged_into_lead_id')
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->get();

                if ($leads->count() < 2) {
                    throw new RuntimeException("No active duplicate group remains for {$normalizedPhone}.");
                }

                $master = $leads->shift();
                $duplicates = $leads->values();
                $masterSnapshot = $master->getAttributes();
                $selectedAssignmentId = $this->selectedActiveAssignmentId($master->id, $duplicates->pluck('id')->all());

                $audits = $duplicates->mapWithKeys(function (Lead $duplicate) use ($master, $masterSnapshot, $actorId, $normalizedPhone) {
                    $remark = $this->mergeRemark($duplicate, $master);
                    $audit = LeadMergeAudit::updateOrCreate(
                        ['duplicate_lead_id' => $duplicate->id],
                        [
                            'master_lead_id' => $master->id,
                            'normalized_phone' => $normalizedPhone,
                            'status' => 'processing',
                            'master_snapshot' => $masterSnapshot,
                            'duplicate_snapshot' => $duplicate->getAttributes(),
                            'moved_record_counts' => null,
                            'remark' => $remark,
                            'failure_details' => null,
                            'actor_id' => $actorId,
                            'merged_at' => null,
                        ]
                    );

                    return [$duplicate->id => $audit];
                });

                $this->fillMasterBlanks($master, $duplicates);
                $counts = $this->moveLinkedRecords($master->id, $duplicates->pluck('id')->all());
                $this->consolidateAssignments($master->id, $selectedAssignmentId);
                $counts['open_tasks_closed'] = $this->consolidateOpenTasks($master);
                $this->moveActivityLogs($master->id, $duplicates->pluck('id')->all(), $counts);

                foreach ($duplicates as $duplicate) {
                    $remark = $this->mergeRemark($duplicate, $master);
                    DB::table('leads')->where('id', $duplicate->id)->update([
                        'normalized_phone' => null,
                        'merged_into_lead_id' => $master->id,
                        'merged_at' => now(),
                        'merge_reason' => 'duplicate_normalized_phone',
                    ]);
                    $duplicate->forceFill(['normalized_phone' => null, 'merged_into_lead_id' => $master->id, 'merged_at' => now(), 'merge_reason' => 'duplicate_normalized_phone']);
                    $duplicate->delete();

                    $audits[$duplicate->id]->update([
                        'status' => 'merged',
                        'moved_record_counts' => $counts,
                        'remark' => $remark,
                        'merged_at' => now(),
                    ]);
                }

                $master->notes = $this->appendMergeRemarks($master->notes, $audits->pluck('remark')->all());
                $master->saveQuietly();

                return [
                    'master_lead_id' => $master->id,
                    'merged_lead_ids' => $duplicates->pluck('id')->all(),
                    'moved_record_counts' => $counts,
                ];
            }, 3);
        } catch (Throwable $exception) {
            $this->recordFailure($normalizedPhone, $actorId, $exception);
            throw $exception;
        }
    }

    private function fillMasterBlanks(Lead $master, Collection $duplicates): void
    {
        foreach (self::BLANK_FILL_FIELDS as $field) {
            if (filled($master->{$field})) {
                continue;
            }

            $value = $duplicates->first(fn (Lead $lead) => filled($lead->{$field}))?->{$field};
            if (filled($value)) {
                $master->{$field} = $value;
            }
        }

        if ($duplicates->contains(fn (Lead $lead) => $lead->status === 'closed')) {
            $master->status = 'closed';
            $master->status_auto_update_enabled = false;
        }

        $master->saveQuietly();
    }

    private function moveLinkedRecords(int $masterId, array $duplicateIds): array
    {
        $counts = [];

        foreach ($this->leadReferenceColumns() as [$table, $column]) {
            if ($table === 'leads' || $table === 'lead_merge_audits' || !Schema::hasTable($table)) {
                continue;
            }

            try {
                [$moved, $deduped] = $this->moveTableRows($table, $column, $masterId, $duplicateIds);
            } catch (QueryException $exception) {
                throw new RuntimeException("Linked-record conflict in {$table}.{$column}: {$exception->getMessage()}", 0, $exception);
            }

            if ($moved || $deduped) {
                $counts["{$table}.{$column}"] = ['moved' => $moved, 'deduped' => $deduped];
            }
        }

        return $counts;
    }

    private function leadReferenceColumns(): array
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return collect(DB::select(
                "SELECT DISTINCT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name
                 FROM information_schema.COLUMNS AS c
                 LEFT JOIN information_schema.KEY_COLUMN_USAGE AS k
                   ON k.TABLE_SCHEMA = c.TABLE_SCHEMA
                  AND k.TABLE_NAME = c.TABLE_NAME
                  AND k.COLUMN_NAME = c.COLUMN_NAME
                  AND k.REFERENCED_TABLE_NAME = 'leads'
                 WHERE c.TABLE_SCHEMA = DATABASE()
                   AND (c.COLUMN_NAME IN ('lead_id', 'crm_lead_id') OR k.REFERENCED_TABLE_NAME = 'leads')"
            ))->map(fn ($row) => [$row->table_name, $row->column_name])->all();
        }

        $references = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table'") as $row) {
            foreach (DB::select("PRAGMA table_info('{$row->name}')") as $column) {
                if ($column->name === 'lead_id' || $column->name === 'crm_lead_id') {
                    $references[] = [$row->name, $column->name];
                }
            }

            foreach (DB::select("PRAGMA foreign_key_list('{$row->name}')") as $foreignKey) {
                if ($foreignKey->table === 'leads') {
                    $references[] = [$row->name, $foreignKey->from];
                }
            }
        }

        return array_values(array_unique($references, SORT_REGULAR));
    }

    private function moveTableRows(string $table, string $leadColumn, int $masterId, array $duplicateIds): array
    {
        $uniqueIndexes = array_values(array_filter(
            $this->uniqueIndexes($table),
            fn (array $columns) => in_array($leadColumn, $columns, true)
        ));

        if (!$uniqueIndexes || !Schema::hasColumn($table, 'id')) {
            return [DB::table($table)->whereIn($leadColumn, $duplicateIds)->update([$leadColumn => $masterId]), 0];
        }

        $moved = 0;
        $removed = 0;
        foreach (DB::table($table)->whereIn($leadColumn, $duplicateIds)->orderByDesc('id')->get() as $row) {
            $hasCollision = false;
            foreach ($uniqueIndexes as $columns) {
                $collision = DB::table($table)->where($leadColumn, $masterId);
                foreach (array_diff($columns, [$leadColumn]) as $column) {
                    $value = $row->{$column};
                    $value === null ? $collision->whereNull($column) : $collision->where($column, $value);
                }
                if ($collision->exists()) {
                    $hasCollision = true;
                    break;
                }
            }

            if ($hasCollision) {
                DB::table($table)->where('id', $row->id)->delete();
                $removed++;
                continue;
            }

            DB::table($table)->where('id', $row->id)->update([$leadColumn => $masterId]);
            $moved++;
        }

        return [$moved, $removed];
    }

    private function uniqueIndexes(string $table): array
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return collect(DB::select(
                "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
                 ORDER BY INDEX_NAME, SEQ_IN_INDEX",
                [$table]
            ))->groupBy('INDEX_NAME')->map(fn ($rows) => $rows->pluck('COLUMN_NAME')->all())->values()->all();
        }

        $indexes = [];
        foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
            if (!(int) $index->unique) {
                continue;
            }
            $indexes[] = collect(DB::select("PRAGMA index_info('{$index->name}')"))->pluck('name')->all();
        }

        return $indexes;
    }

    private function selectedActiveAssignmentId(int $masterId, array $duplicateIds): ?int
    {
        if (!Schema::hasTable('lead_assignments')) {
            return null;
        }

        return DB::table('lead_assignments')
            ->whereIn('lead_id', array_merge([$masterId], $duplicateIds))
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN lead_id = ? THEN 0 ELSE 1 END', [$masterId])
            ->orderByDesc('id')
            ->value('id');
    }

    private function consolidateAssignments(int $masterId, ?int $selectedAssignmentId): void
    {
        if (!Schema::hasTable('lead_assignments')) {
            return;
        }

        DB::table('lead_assignments')->where('lead_id', $masterId)->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        if ($selectedAssignmentId) {
            DB::table('lead_assignments')->where('id', $selectedAssignmentId)->update([
                'is_active' => true,
                'unassigned_at' => null,
            ]);
        }
    }

    private function consolidateOpenTasks(Lead $master): int
    {
        $ownerId = Schema::hasTable('lead_assignments')
            ? DB::table('lead_assignments')->where('lead_id', $master->id)->where('is_active', true)->value('assigned_to')
            : null;
        $candidates = collect();

        foreach ([['tasks', Task::OPEN_STATUSES], ['telecaller_tasks', TelecallerTask::OPEN_STATUSES]] as [$table, $statuses]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('lead_id', $master->id)->whereIn('status', $statuses)
                ->whereNull('completed_at')->get(['id', 'assigned_to'])->each(function ($row) use ($candidates, $table, $ownerId) {
                    $candidates->push(['table' => $table, 'id' => $row->id, 'owner_match' => $ownerId && (int) $row->assigned_to === (int) $ownerId]);
                });
        }

        $keep = in_array($master->status, ['closed', 'dead', 'junk', 'not_interested'], true)
            ? null
            : $candidates->sortByDesc(fn ($row) => [(int) $row['owner_match'], $row['id']])->first();
        $closed = 0;

        foreach ($candidates as $task) {
            if ($keep && $task['table'] === $keep['table'] && $task['id'] === $keep['id']) {
                continue;
            }
            DB::table($task['table'])->where('id', $task['id'])->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
            $closed++;
        }

        return $closed;
    }

    private function moveActivityLogs(int $masterId, array $duplicateIds, array &$counts): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $moved = DB::table('activity_logs')
            ->whereIn('model_id', $duplicateIds)
            ->whereIn('model_type', ['Lead', Lead::class])
            ->update(['model_id' => $masterId]);
        if ($moved) {
            $counts['activity_logs.model_id'] = ['moved' => $moved, 'deduped' => 0];
        }
    }

    private function mergeRemark(Lead $duplicate, Lead $master): string
    {
        $ownerId = Schema::hasTable('lead_assignments')
            ? DB::table('lead_assignments')->where('lead_id', $duplicate->id)->where('is_active', true)->value('assigned_to')
            : null;

        return "Lead #{$duplicate->id} merged into Lead #{$master->id}; original phone {$duplicate->phone}, source {$duplicate->source}, owner "
            . ($ownerId ?: 'none') . ", status {$duplicate->status} preserved in merge audit.";
    }

    private function appendMergeRemarks(?string $notes, array $remarks): string
    {
        return trim(implode(PHP_EOL, array_filter([trim((string) $notes), ...$remarks])));
    }

    private function recordFailure(string $normalizedPhone, ?int $actorId, Throwable $exception): void
    {
        try {
            if (!Schema::hasTable('lead_merge_audits')) {
                return;
            }

            $preview = $this->preview($normalizedPhone, false);
            $duplicateId = $preview['duplicate_lead_ids'][0] ?? null;
            if (!$preview['master_lead_id'] || !$duplicateId) {
                return;
            }

            LeadMergeAudit::updateOrCreate(
                ['duplicate_lead_id' => $duplicateId],
                [
                    'master_lead_id' => $preview['master_lead_id'],
                    'normalized_phone' => $normalizedPhone,
                    'status' => 'failed',
                    'failure_details' => mb_strimwidth($exception->getMessage(), 0, 4000, '...'),
                    'actor_id' => $actorId,
                ]
            );
        } catch (Throwable) {
            // Preserve the original merge failure.
        }
    }
}
