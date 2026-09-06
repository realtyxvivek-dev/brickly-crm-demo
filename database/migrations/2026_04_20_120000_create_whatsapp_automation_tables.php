<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_automation_journeys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_preset')->default(false);
            $table->boolean('test_mode')->default(false);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->text('description')->nullable();
            $table->json('default_filters')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'status']);
        });

        Schema::create('whatsapp_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id');
            $table->string('name');
            $table->string('trigger');
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->boolean('test_mode')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->json('conditions')->nullable();
            $table->json('variable_map')->nullable();
            $table->json('send_timing')->nullable();
            $table->json('quiet_hour_policy')->nullable();
            $table->boolean('once_per_lead')->default(true);
            $table->unsignedInteger('resend_cap')->default(1);
            $table->json('stop_statuses')->nullable();
            $table->timestamp('last_executed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('journey_id')->references('id')->on('whatsapp_automation_journeys')->cascadeOnDelete();
            $table->index(['trigger', 'is_active', 'priority'], 'wa_rules_trigger_active_priority_idx');
        });

        Schema::create('whatsapp_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('trigger');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('execution_key')->unique();
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->json('resolved_variables')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->foreign('journey_id')->references('id')->on('whatsapp_automation_journeys')->nullOnDelete();
            $table->foreign('rule_id')->references('id')->on('whatsapp_automation_rules')->nullOnDelete();
            $table->index(['status', 'scheduled_for'], 'wa_logs_status_scheduled_idx');
            $table->index(['lead_id', 'rule_id'], 'wa_logs_lead_rule_idx');
            $table->index(['journey_id', 'status'], 'wa_logs_journey_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_automation_logs');
        Schema::dropIfExists('whatsapp_automation_rules');
        Schema::dropIfExists('whatsapp_automation_journeys');
    }
};
