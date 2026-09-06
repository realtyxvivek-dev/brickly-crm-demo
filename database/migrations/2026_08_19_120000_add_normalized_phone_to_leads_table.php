<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'normalized_phone')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('normalized_phone', 32)->nullable()->after('phone');
            });
        }

        if (!$this->indexExists('leads', 'leads_normalized_phone_index')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index('normalized_phone', 'leads_normalized_phone_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('leads', 'leads_normalized_phone_index')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex('leads_normalized_phone_index');
            });
        }

        if (Schema::hasColumn('leads', 'normalized_phone')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('normalized_phone');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            return !empty(DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$database, $table, $index]
            ));
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        return false;
    }
};
