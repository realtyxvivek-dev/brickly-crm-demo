<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'leads' => [
            'leads_source_created_idx' => ['source', 'created_at'],
        ],
        'meetings' => [
            'meetings_dashboard_user_schedule_idx' => ['assigned_to', 'scheduled_at', 'status', 'is_converted'],
        ],
        'site_visits' => [
            'site_visits_dashboard_user_schedule_idx' => ['assigned_to', 'scheduled_at', 'status'],
        ],
        'tasks' => [
            'tasks_dashboard_user_completed_idx' => ['assigned_to', 'completed_at', 'status', 'lead_id'],
        ],
        'telecaller_tasks' => [
            'telecaller_tasks_dashboard_user_completed_idx' => ['assigned_to', 'completed_at', 'status', 'lead_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->indexExists($table, $name) || !$this->columnsExist($table, $columns)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (!$this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }

    private function columnsExist(string $table, array $columns): bool
    {
        return collect($columns)->every(fn (string $column) => Schema::hasColumn($table, $column));
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $name)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('" . str_replace("'", "''", $table) . "')"))
                ->contains(fn ($index) => ($index->name ?? null) === $name);
        }

        return false;
    }
};
