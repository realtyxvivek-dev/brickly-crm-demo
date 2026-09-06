<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payroll_freeze_items', 'user_id')) {
            Schema::table('payroll_freeze_items', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('payroll_freeze_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });

            DB::statement('
                UPDATE payroll_freeze_items pfi
                INNER JOIN attendance_monthly_rollups amr
                    ON amr.id = pfi.attendance_monthly_rollup_id
                SET pfi.user_id = amr.user_id
                WHERE pfi.user_id IS NULL
            ');

            Schema::table('payroll_freeze_items', function (Blueprint $table) {
                $table->index(['payroll_freeze_id', 'user_id'], 'payroll_freeze_items_freeze_user_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_freeze_items', 'user_id')) {
            Schema::table('payroll_freeze_items', function (Blueprint $table) {
                $table->dropIndex('payroll_freeze_items_freeze_user_idx');
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
