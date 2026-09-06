<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asm_cnp_automation_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('asm_cnp_automation_configs', 'transfer_rule_mode')) {
                $table->string('transfer_rule_mode', 40)
                    ->default('count_only')
                    ->after('create_retry_tasks');
            }

            if (!Schema::hasColumn('asm_cnp_automation_configs', 'transfer_window_hours')) {
                $table->unsignedInteger('transfer_window_hours')
                    ->nullable()
                    ->after('retry_delay_minutes');
            }
        });

        DB::table('asm_cnp_automation_configs')
            ->whereNull('transfer_rule_mode')
            ->update(['transfer_rule_mode' => 'count_only']);

        DB::table('asm_cnp_automation_configs')
            ->whereNull('transfer_window_hours')
            ->update([
                'transfer_window_hours' => DB::raw('CASE WHEN transfer_threshold_hours > 0 THEN transfer_threshold_hours ELSE NULL END'),
            ]);
    }

    public function down(): void
    {
        Schema::table('asm_cnp_automation_configs', function (Blueprint $table) {
            if (Schema::hasColumn('asm_cnp_automation_configs', 'transfer_window_hours')) {
                $table->dropColumn('transfer_window_hours');
            }

            if (Schema::hasColumn('asm_cnp_automation_configs', 'transfer_rule_mode')) {
                $table->dropColumn('transfer_rule_mode');
            }
        });
    }
};
