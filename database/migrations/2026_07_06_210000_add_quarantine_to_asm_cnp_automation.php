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
            if (!Schema::hasColumn('asm_cnp_automation_configs', 'quarantine_enabled')) {
                $table->boolean('quarantine_enabled')->default(true)->after('fallback_routing');
            }

            if (!Schema::hasColumn('asm_cnp_automation_configs', 'quarantine_after_unique_users')) {
                $table->unsignedInteger('quarantine_after_unique_users')->default(4)->after('quarantine_enabled');
            }

            if (!Schema::hasColumn('asm_cnp_automation_configs', 'quarantine_action')) {
                $table->string('quarantine_action', 30)->default('unassign')->after('quarantine_after_unique_users');
            }
        });

        Schema::table('asm_cnp_automation_states', function (Blueprint $table) {
            if (!Schema::hasColumn('asm_cnp_automation_states', 'quarantined_at')) {
                $table->timestamp('quarantined_at')->nullable()->after('transferred_at');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'cnp_quarantined_at')) {
                $table->timestamp('cnp_quarantined_at')->nullable()->after('transfer_note');
            }

            if (!Schema::hasColumn('leads', 'cnp_quarantined_by')) {
                $table->foreignId('cnp_quarantined_by')->nullable()->after('cnp_quarantined_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('leads', 'cnp_quarantine_reason')) {
                $table->text('cnp_quarantine_reason')->nullable()->after('cnp_quarantined_by');
            }

            if (!Schema::hasColumn('leads', 'cnp_quarantine_cleared_at')) {
                $table->timestamp('cnp_quarantine_cleared_at')->nullable()->after('cnp_quarantine_reason');
            }

            if (!Schema::hasColumn('leads', 'cnp_quarantine_cleared_by')) {
                $table->foreignId('cnp_quarantine_cleared_by')->nullable()->after('cnp_quarantine_cleared_at')->constrained('users')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('asm_cnp_automation_lead_histories')) {
            Schema::create('asm_cnp_automation_lead_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('config_id')->nullable()->constrained('asm_cnp_automation_configs')->nullOnDelete();
                $table->foreignId('state_id')->nullable()->constrained('asm_cnp_automation_states')->nullOnDelete();
                $table->foreignId('lead_assignment_id')->nullable()->constrained('lead_assignments')->nullOnDelete();
                $table->unsignedInteger('completed_cnp_count')->default(0);
                $table->timestamp('max_hit_at')->nullable();
                $table->timestamps();

                $table->unique(['lead_id', 'user_id'], 'asm_cnp_history_lead_user_unique');
                $table->index(['lead_id', 'max_hit_at'], 'asm_cnp_history_lead_max_idx');
            });
        }

        DB::table('asm_cnp_automation_configs')->update([
            'quarantine_enabled' => true,
            'quarantine_after_unique_users' => 4,
            'quarantine_action' => 'unassign',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_cnp_automation_lead_histories');

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'cnp_quarantine_cleared_by')) {
                $table->dropConstrainedForeignId('cnp_quarantine_cleared_by');
            }
            if (Schema::hasColumn('leads', 'cnp_quarantine_cleared_at')) {
                $table->dropColumn('cnp_quarantine_cleared_at');
            }
            if (Schema::hasColumn('leads', 'cnp_quarantine_reason')) {
                $table->dropColumn('cnp_quarantine_reason');
            }
            if (Schema::hasColumn('leads', 'cnp_quarantined_by')) {
                $table->dropConstrainedForeignId('cnp_quarantined_by');
            }
            if (Schema::hasColumn('leads', 'cnp_quarantined_at')) {
                $table->dropColumn('cnp_quarantined_at');
            }
        });

        Schema::table('asm_cnp_automation_states', function (Blueprint $table) {
            if (Schema::hasColumn('asm_cnp_automation_states', 'quarantined_at')) {
                $table->dropColumn('quarantined_at');
            }
        });

        Schema::table('asm_cnp_automation_configs', function (Blueprint $table) {
            foreach (['quarantine_action', 'quarantine_after_unique_users', 'quarantine_enabled'] as $column) {
                if (Schema::hasColumn('asm_cnp_automation_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
