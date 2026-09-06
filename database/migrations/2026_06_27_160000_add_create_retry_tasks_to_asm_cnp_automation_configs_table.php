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
            if (!Schema::hasColumn('asm_cnp_automation_configs', 'create_retry_tasks')) {
                $table->boolean('create_retry_tasks')
                    ->default(true)
                    ->after('is_active');
            }
        });

        DB::table('asm_cnp_automation_configs')
            ->whereNull('create_retry_tasks')
            ->update(['create_retry_tasks' => true]);
    }

    public function down(): void
    {
        Schema::table('asm_cnp_automation_configs', function (Blueprint $table) {
            if (Schema::hasColumn('asm_cnp_automation_configs', 'create_retry_tasks')) {
                $table->dropColumn('create_retry_tasks');
            }
        });
    }
};
