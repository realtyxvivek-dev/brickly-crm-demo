<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $telecallerPermissions = [
            'calling_center.view',
            'calling_center.agent_queue',
            'calling_center.submit_outcome',
            'calling_center.pause_own_queue',
            'calling_center.view_own_report',
            'calling_center.view_recordings',
        ];

        DB::table('roles')->updateOrInsert(
            ['slug' => 'telecaller'],
            [
                'name' => 'Telecaller',
                'description' => 'Calling Center agent with own queue, attendance, profile, and calling outcome access.',
                'permissions' => json_encode($telecallerPermissions),
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!Schema::hasTable('calling_center_campaigns')) {
            Schema::create('calling_center_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->string('source_type')->default('manual');
                $table->string('folder_type')->nullable();
                $table->string('folder_key')->nullable();
                $table->foreignId('folder_tag_id')->nullable()->constrained('lead_tags')->nullOnDelete();
                $table->json('filters')->nullable();
                $table->enum('status', ['draft', 'running', 'paused', 'completed', 'cancelled'])->default('draft');
                $table->unsignedInteger('delay_seconds')->default(120);
                $table->enum('retry_policy', ['none', 'cnp_no_answer', 'custom'])->default('none');
                $table->unsignedTinyInteger('max_retries')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['assigned_to', 'status'], 'cc_campaigns_assigned_status_idx');
                $table->index(['created_by', 'status'], 'cc_campaigns_created_status_idx');
            });
        }

        if (!Schema::hasTable('calling_center_campaign_items')) {
            Schema::create('calling_center_campaign_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('calling_center_campaigns')->cascadeOnDelete();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->unsignedBigInteger('mcube_outbound_attempt_id')->nullable();
                $table->foreignId('call_log_id')->nullable()->constrained('call_logs')->nullOnDelete();
                $table->string('phone', 32)->nullable();
                $table->enum('status', ['pending', 'queued', 'dialing', 'connected', 'call_ended', 'outcome_pending', 'completed', 'failed', 'skipped'])->default('pending');
                $table->string('call_status')->nullable();
                $table->string('outcome')->nullable();
                $table->text('remark')->nullable();
                $table->timestamp('next_call_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('call_started_at')->nullable();
                $table->timestamp('call_ended_at')->nullable();
                $table->timestamp('outcome_submitted_at')->nullable();
                $table->unsignedTinyInteger('attempt_count')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'lead_id'], 'cc_items_campaign_lead_unique');
                $table->index(['campaign_id', 'status', 'next_call_at'], 'cc_items_campaign_status_next_idx');
                $table->index(['lead_id', 'status'], 'cc_items_lead_status_idx');
            });
        }

        if (!Schema::hasTable('calling_center_push_requests')) {
            Schema::create('calling_center_push_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('calling_center_campaigns')->nullOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('reason')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'cc_push_status_created_idx');
                $table->index(['assigned_to', 'status'], 'cc_push_assigned_status_idx');
            });
        }

        if (Schema::hasTable('mcube_outbound_attempts')) {
            Schema::table('mcube_outbound_attempts', function (Blueprint $table) {
                if (!Schema::hasColumn('mcube_outbound_attempts', 'calling_center_campaign_item_id')) {
                    $table->unsignedBigInteger('calling_center_campaign_item_id')
                        ->nullable()
                        ->after('task_id');
                }
            });
        }

        if (Schema::hasTable('mcube_outbound_attempts') && Schema::hasTable('calling_center_campaign_items')) {
            Schema::table('mcube_outbound_attempts', function (Blueprint $table) {
                if (Schema::hasColumn('mcube_outbound_attempts', 'calling_center_campaign_item_id')) {
                    return;
                }

                $table->foreignId('calling_center_campaign_item_id')
                    ->nullable()
                    ->after('task_id')
                    ->constrained('calling_center_campaign_items')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('mcube_outbound_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('mcube_outbound_attempts', 'calling_center_campaign_item_id')) {
                $table->dropConstrainedForeignId('calling_center_campaign_item_id');
            }
        });

        Schema::dropIfExists('calling_center_push_requests');
        Schema::dropIfExists('calling_center_campaign_items');
        Schema::dropIfExists('calling_center_campaigns');
    }
};
