<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_lead_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('default_mode')->default('assign_to_receiver');
            $table->string('distribution_method')->default('receiver');
            $table->string('fallback_mode')->default('receiver');
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->unsignedBigInteger('fixed_user_id')->nullable();
            $table->unsignedBigInteger('default_team_manager_user_id')->nullable();
            $table->boolean('receiver_assignment_allowed')->default(true);
            $table->unsignedBigInteger('last_round_robin_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_lead_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('allocation_percentage', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['config_id', 'user_id'], 'ivr_lead_automation_pool_unique');
        });

        Schema::create('ivr_lead_automation_receiver_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('can_assign_to_self')->default(true);
            $table->string('target_type')->default('self');
            $table->string('distribution_method')->default('receiver');
            $table->unsignedBigInteger('team_manager_user_id')->nullable();
            $table->unsignedBigInteger('fixed_user_id')->nullable();
            $table->string('fallback_mode')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->unsignedBigInteger('last_round_robin_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['config_id', 'user_id'], 'ivr_lead_automation_receiver_unique');
        });

        Schema::create('ivr_lead_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('receiver_override_id')->nullable();
            $table->unsignedBigInteger('webhook_log_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('receiver_user_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('rule_source')->default('hard_default');
            $table->string('target_type')->nullable();
            $table->string('strategy_used')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->string('fallback_mode')->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_lead_automation_audits');
        Schema::dropIfExists('ivr_lead_automation_receiver_overrides');
        Schema::dropIfExists('ivr_lead_automation_pool_users');
        Schema::dropIfExists('ivr_lead_automation_configs');
    }
};
