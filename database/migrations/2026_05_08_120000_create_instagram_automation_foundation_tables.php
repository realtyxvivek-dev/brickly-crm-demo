<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('ig_user_id')->unique();
            $table->string('ig_username')->nullable();
            $table->string('page_id')->nullable();
            $table->string('page_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expiry')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('last_event_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_event_at']);
            $table->index('page_id');
        });

        Schema::create('ig_dm_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'status']);
        });

        Schema::create('ig_dm_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('ig_dm_flows')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->text('message_text')->nullable();
            $table->string('save_reply_as')->nullable();
            $table->string('action_type')->nullable();
            $table->string('message_type')->default('text');
            $table->text('media_url')->nullable();
            $table->string('attachment_type')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'step_order']);
            $table->index(['flow_id', 'message_type']);
        });

        Schema::create('ig_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained('instagram_accounts')->cascadeOnDelete();
            $table->string('media_id')->nullable();
            $table->string('name');
            $table->json('keywords')->nullable();
            $table->text('public_reply_message')->nullable();
            $table->foreignId('dm_flow_id')->nullable()->constrained('ig_dm_flows')->nullOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['instagram_account_id', 'is_active', 'priority'], 'ig_rules_account_active_priority_idx');
            $table->index(['media_id', 'status']);
        });

        Schema::create('ig_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained('instagram_accounts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('instagram_user_id')->nullable();
            $table->string('instagram_username')->nullable();
            $table->text('original_comment')->nullable();
            $table->string('media_id')->nullable();
            $table->foreignId('automation_rule_id')->nullable()->constrained('ig_automation_rules')->nullOnDelete();
            $table->boolean('is_human_taken_over')->default(false);
            $table->foreignId('human_taken_over_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('human_taken_over_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['instagram_account_id', 'instagram_user_id'], 'ig_conversations_account_user_idx');
            $table->index(['status', 'updated_at']);
            $table->index('media_id');
        });

        Schema::create('ig_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ig_conversations')->cascadeOnDelete();
            $table->unsignedInteger('current_step')->default(1);
            $table->string('current_field')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('last_reply_at')->nullable();
            $table->unsignedInteger('retries')->default(0);
            $table->foreignId('flow_id')->nullable()->constrained('ig_dm_flows')->nullOnDelete();
            $table->timestamps();

            $table->unique('conversation_id');
            $table->index(['flow_id', 'completed']);
        });

        Schema::create('ig_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ig_conversations')->cascadeOnDelete();
            $table->string('direction');
            $table->text('message_text')->nullable();
            $table->string('message_type')->default('text');
            $table->text('media_url')->nullable();
            $table->string('attachment_type')->nullable();
            $table->string('meta_message_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('meta_message_id');
        });

        Schema::create('ig_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')->nullable()->constrained('instagram_accounts')->nullOnDelete();
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['instagram_account_id', 'event_type'], 'ig_webhook_events_account_type_idx');
        });

        Schema::create('ig_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')->nullable()->constrained('instagram_accounts')->nullOnDelete();
            $table->string('endpoint')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->unsignedInteger('status_code')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['instagram_account_id', 'created_at'], 'ig_api_logs_account_created_idx');
            $table->index(['status', 'status_code']);
        });

        Schema::create('ig_failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['job_type', 'failed_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ig_failed_jobs');
        Schema::dropIfExists('ig_api_logs');
        Schema::dropIfExists('ig_webhook_events');
        Schema::dropIfExists('ig_messages');
        Schema::dropIfExists('ig_conversation_states');
        Schema::dropIfExists('ig_conversations');
        Schema::dropIfExists('ig_automation_rules');
        Schema::dropIfExists('ig_dm_flow_steps');
        Schema::dropIfExists('ig_dm_flows');
        Schema::dropIfExists('instagram_accounts');
    }
};
