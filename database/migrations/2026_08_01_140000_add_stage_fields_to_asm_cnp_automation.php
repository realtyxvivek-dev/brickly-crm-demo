<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asm_cnp_automation_states', function (Blueprint $table) {
            if (!Schema::hasColumn('asm_cnp_automation_states', 'stage')) {
                $table->string('stage', 40)->default('fresh_lead')->after('last_retry_task_id');
            }

            if (!Schema::hasColumn('asm_cnp_automation_states', 'stage_record_id')) {
                $table->unsignedBigInteger('stage_record_id')->nullable()->after('stage');
            }

            if (!Schema::hasColumn('asm_cnp_automation_states', 'stage_started_at')) {
                $table->timestamp('stage_started_at')->nullable()->after('assignment_started_at');
            }

            if (!Schema::hasColumn('asm_cnp_automation_states', 'reset_reason')) {
                $table->string('reset_reason')->nullable()->after('cancel_reason');
            }

            if (!Schema::hasColumn('asm_cnp_automation_states', 'reset_at')) {
                $table->timestamp('reset_at')->nullable()->after('reset_reason');
            }
        });

        Schema::table('asm_cnp_automation_states', function (Blueprint $table) {
            $table->index(
                ['lead_id', 'lead_assignment_id', 'current_assigned_to', 'stage', 'stage_record_id', 'status'],
                'asm_cnp_states_stage_lookup_idx'
            );
        });

        Schema::table('asm_cnp_automation_audits', function (Blueprint $table) {
            if (!Schema::hasColumn('asm_cnp_automation_audits', 'stage')) {
                $table->string('stage', 40)->nullable()->after('task_id');
            }

            if (!Schema::hasColumn('asm_cnp_automation_audits', 'stage_record_id')) {
                $table->unsignedBigInteger('stage_record_id')->nullable()->after('stage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asm_cnp_automation_audits', function (Blueprint $table) {
            if (Schema::hasColumn('asm_cnp_automation_audits', 'stage_record_id')) {
                $table->dropColumn('stage_record_id');
            }

            if (Schema::hasColumn('asm_cnp_automation_audits', 'stage')) {
                $table->dropColumn('stage');
            }
        });

        Schema::table('asm_cnp_automation_states', function (Blueprint $table) {
            $table->dropIndex('asm_cnp_states_stage_lookup_idx');

            foreach (['reset_at', 'reset_reason', 'stage_started_at', 'stage_record_id', 'stage'] as $column) {
                if (Schema::hasColumn('asm_cnp_automation_states', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
