<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_lead_sla_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('source')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sla_minutes')->default(240);
            $table->time('business_start_time')->default('10:00:00');
            $table->time('business_end_time')->default('19:00:00');
            $table->boolean('weekends_off')->default(true);
            $table->unsignedTinyInteger('max_transfer_attempts')->default(3);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('dashboard_alert_enabled')->default(true);
            $table->boolean('skip_inactive_users')->default(true);
            $table->boolean('skip_absent_users')->default(true);
            $table->foreignId('last_round_robin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('new_lead_sla_automation_configs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['config_id', 'user_id'], 'new_lead_sla_pool_user_unique');
        });

        Schema::create('new_lead_sla_automation_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('new_lead_sla_automation_configs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['config_id', 'user_id'], 'new_lead_sla_recipient_user_unique');
        });

        Schema::create('new_lead_sla_automation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('config_id')->constrained('new_lead_sla_automation_configs')->cascadeOnDelete();
            $table->foreignId('current_assignment_id')->nullable()->constrained('lead_assignments')->nullOnDelete();
            $table->foreignId('original_assignment_id')->nullable()->constrained('lead_assignments')->nullOnDelete();
            $table->foreignId('current_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('original_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->dateTime('sla_started_at')->nullable();
            $table->dateTime('sla_deadline_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->string('response_outcome')->nullable();
            $table->string('response_task_model')->nullable();
            $table->unsignedBigInteger('response_task_id')->nullable();
            $table->dateTime('last_transferred_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->enum('status', ['active', 'responded', 'escalated', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index(['status', 'sla_deadline_at'], 'new_lead_sla_state_status_deadline_idx');
            $table->index(['lead_id', 'config_id'], 'new_lead_sla_state_lead_config_idx');
        });

        Schema::create('new_lead_sla_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->nullable()->constrained('new_lead_sla_automation_states')->nullOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('new_lead_sla_automation_configs')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('lead_assignments')->nullOnDelete();
            $table->string('task_model')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('action');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('acted_at');
            $table->timestamps();

            $table->index(['action', 'acted_at'], 'new_lead_sla_audit_action_acted_idx');
            $table->index(['from_user_id', 'acted_at'], 'new_lead_sla_audit_from_user_acted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_lead_sla_automation_audits');
        Schema::dropIfExists('new_lead_sla_automation_states');
        Schema::dropIfExists('new_lead_sla_automation_recipients');
        Schema::dropIfExists('new_lead_sla_automation_pool_users');
        Schema::dropIfExists('new_lead_sla_automation_configs');
    }
};
