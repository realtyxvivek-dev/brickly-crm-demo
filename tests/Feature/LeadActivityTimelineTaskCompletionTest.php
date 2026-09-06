<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\LeadActivityService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadActivityTimelineTaskCompletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        Carbon::setTestNow(Carbon::parse('2026-03-31 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_adds_task_and_telecaller_task_completion_entries_without_duplicate_task_entries(): void
    {
        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        $task = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'type' => 'phone_call',
            'title' => 'Primary Follow-up Call',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-03-31 10:30:00'),
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'activity_type' => 'status_changed',
            'old_value' => 'pending',
            'new_value' => 'completed',
            'description' => 'Status changed from pending to completed',
            'created_at' => Carbon::parse('2026-03-31 10:31:00'),
            'updated_at' => Carbon::parse('2026-03-31 10:31:00'),
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'task_type' => 'calling',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-03-31 11:15:00'),
        ]);

        $timeline = app(LeadActivityService::class)->getTimeline($lead);
        $completionEntries = $timeline->where('type', 'task_completed')->values();

        $this->assertCount(2, $completionEntries);
        $this->assertTrue($completionEntries->contains(function ($entry) {
            return $entry['description'] === 'Calling task completed by Timeline User'
                && ($entry['metadata']['source'] ?? null) === 'telecaller_task';
        }));
        $this->assertTrue($completionEntries->contains(function ($entry) {
            return $entry['description'] === 'Primary Follow-up Call completed by Timeline User'
                && ($entry['metadata']['source'] ?? null) === 'task_activity';
        }));
    }

    public function test_it_falls_back_to_completed_task_row_when_task_activity_is_missing(): void
    {
        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'type' => 'phone_call',
            'title' => 'Fallback Completion Task',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-03-31 09:00:00'),
        ]);

        $timeline = app(LeadActivityService::class)->getTimeline($lead);
        $completionEntries = $timeline->where('type', 'task_completed')->values();

        $this->assertCount(1, $completionEntries);
        $this->assertSame('Fallback Completion Task completed by Timeline User', $completionEntries->first()['description']);
        $this->assertSame('task_row', $completionEntries->first()['metadata']['source']);
    }

    public function test_manual_transfer_is_not_labelled_as_cnp_automation(): void
    {
        Event::fake();

        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        $lead->markAsFreshTransfer($user->id, 47, $user->id);

        $log = ActivityLog::where('action', 'lead_transferred')->firstOrFail();

        $this->assertArrayNotHasKey('automation_type', $log->new_values);
        $this->assertNull($this->automationMetadataFor($log));
    }

    public function test_real_cnp_transfer_keeps_automation_label(): void
    {
        Event::fake();

        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        $lead->markAsFreshTransfer(
            $user->id,
            47,
            $user->id,
            'Auto-transferred after max fresh-lead CNP attempts.'
        );

        $log = ActivityLog::where('action', 'lead_transferred')->firstOrFail();

        $this->assertSame('ASM Fresh Lead CNP Automation', $this->automationMetadataFor($log)['label']);
    }

    private function automationMetadataFor(ActivityLog $log): ?array
    {
        $method = new \ReflectionMethod(LeadActivityService::class, 'detectAutomationMetadata');
        $method->setAccessible(true);

        return $method->invoke(app(LeadActivityService::class), $log);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->string('pre_transfer_status')->nullable();
            $table->unsignedBigInteger('transferred_from_user_id')->nullable();
            $table->unsignedBigInteger('transferred_to_user_id')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->text('transfer_note')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->string('dead_reason')->nullable();
            $table->string('dead_at_stage')->nullable();
            $table->timestamp('marked_dead_at')->nullable();
            $table->unsignedBigInteger('marked_dead_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('call_type')->nullable();
            $table->string('direction')->nullable();
            $table->integer('duration')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('closing_verified_by')->nullable();
            $table->unsignedBigInteger('rescheduled_by')->nullable();
            $table->string('project')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('converted_to_closer_at')->nullable();
            $table->timestamp('closing_verified_at')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->boolean('is_rescheduled')->default(false);
            $table->timestamp('rescheduled_at')->nullable();
            $table->string('reschedule_reason')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->json('kyc_documents')->nullable();
            $table->integer('rating')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('rescheduled_by')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('customer_name')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_rescheduled')->default(false);
            $table->timestamp('rescheduled_at')->nullable();
            $table->string('reschedule_reason')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('verification_status')->nullable();
            $table->integer('lead_score')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('moved_to_pending_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createUser(): User
    {
        $role = Role::create([
            'name' => 'Assistant Sales Manager',
            'slug' => 'assistant_sales_manager',
            'is_active' => true,
        ]);

        return User::create([
            'name' => 'Timeline User',
            'email' => 'timeline-user@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(int $createdBy): Lead
    {
        return Lead::create([
            'name' => 'Timeline Lead',
            'phone' => '9999999999',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $createdBy,
            'created_at' => Carbon::parse('2026-03-31 08:00:00'),
            'updated_at' => Carbon::parse('2026-03-31 08:00:00'),
        ]);
    }
}
