<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_cnp_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('ASM Fresh Lead CNP Automation');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('retry_delay_minutes')->default(240);
            $table->unsignedInteger('transfer_threshold_hours')->default(48);
            $table->unsignedInteger('max_cnp_attempts')->default(4);
            $table->string('fallback_routing')->default('round_robin');
            $table->foreignId('last_round_robin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('asm_cnp_automation_configs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['config_id', 'user_id']);
        });

        Schema::create('asm_cnp_automation_user_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('asm_cnp_automation_configs')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['config_id', 'from_user_id']);
        });

        Schema::create('asm_cnp_automation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained('lead_assignments')->nullOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('asm_cnp_automation_configs')->nullOnDelete();
            $table->foreignId('original_assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_retry_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->timestamp('assignment_started_at')->nullable();
            $table->timestamp('first_cnp_at')->nullable();
            $table->timestamp('last_cnp_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('eligible_for_transfer_at')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->boolean('transfer_eligible')->default(true);
            $table->string('status')->default('active');
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'transfer_eligible', 'eligible_for_transfer_at'], 'asm_cnp_states_transfer_idx');
            $table->index(['lead_id', 'status'], 'asm_cnp_states_lead_status_idx');
        });

        Schema::create('asm_cnp_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->nullable()->constrained('asm_cnp_automation_states')->nullOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('asm_cnp_automation_configs')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->string('action');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['action', 'acted_at'], 'asm_cnp_audits_action_idx');
        });

        DB::table('asm_cnp_automation_configs')->insert([
            'name' => 'ASM Fresh Lead CNP Automation',
            'is_enabled' => true,
            'is_active' => true,
            'retry_delay_minutes' => 240,
            'transfer_threshold_hours' => 48,
            'max_cnp_attempts' => 4,
            'fallback_routing' => 'round_robin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_cnp_automation_audits');
        Schema::dropIfExists('asm_cnp_automation_states');
        Schema::dropIfExists('asm_cnp_automation_user_overrides');
        Schema::dropIfExists('asm_cnp_automation_pool_users');
        Schema::dropIfExists('asm_cnp_automation_configs');
    }
};
