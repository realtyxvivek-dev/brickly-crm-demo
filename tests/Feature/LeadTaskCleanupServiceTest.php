<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\LeadActivityService;
use App\Services\LeadSingleOpenTaskService;
use App\Services\LeadTaskCleanupService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadTaskCleanupServiceTest extends TestCase
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
        Config::set('logging.default', 'errorlog');

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

    public function test_delete_all_tasks_for_lead_soft_deletes_all_tasks_and_keeps_timeline_history(): void
    {
        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        $completedTask = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'type' => 'phone_call',
            'title' => 'Manager Completed Task',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-03-31 10:00:00'),
        ]);

        TaskActivity::create([
            'task_id' => $completedTask->id,
            'user_id' => $user->id,
            'activity_type' => 'status_changed',
            'old_value' => 'pending',
            'new_value' => 'completed',
            'description' => 'Status changed from pending to completed',
            'created_at' => Carbon::parse('2026-03-31 10:05:00'),
            'updated_at' => Carbon::parse('2026-03-31 10:05:00'),
        ]);

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'type' => 'phone_call',
            'title' => 'Manager Open Task',
            'status' => 'pending',
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'task_type' => 'calling',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-03-31 11:00:00'),
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'task_type' => 'calling',
            'status' => 'pending',
        ]);

        $result = app(LeadTaskCleanupService::class)->deleteAllTasksForLead($lead->id, $user->id, 'lead_deleted');

        $this->assertSame(2, $result['tasks']);
        $this->assertSame(2, $result['telecaller_tasks']);

        $this->assertDatabaseMissing('tasks', [
            'lead_id' => $lead->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseMissing('telecaller_tasks', [
            'lead_id' => $lead->id,
            'deleted_at' => null,
        ]);

        $timeline = app(LeadActivityService::class)->getTimeline($lead);

        $this->assertTrue($timeline->contains(function ($entry) {
            return $entry['type'] === 'task_completed'
                && $entry['description'] === 'Manager Completed Task completed by Cleanup User';
        }));

        $this->assertTrue($timeline->contains(function ($entry) {
            return $entry['type'] === 'task_completed'
                && $entry['description'] === 'Calling task completed by Cleanup User';
        }));

        $this->assertDatabaseHas('activity_logs', [
            'model_id' => $lead->id,
            'action' => 'task_deleted',
        ]);
    }

    public function test_transfer_cleanup_hides_old_owner_tasks_without_deleting_history_rows(): void
    {
        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        $task = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'type' => 'phone_call',
            'title' => 'Transfer me',
            'status' => 'pending',
        ]);

        $telecallerTask = TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'task_type' => 'calling',
            'status' => 'pending',
        ]);

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'type' => 'call',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);

        $meeting = Meeting::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'customer_name' => 'Cleanup Lead',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(2),
        ]);

        $siteVisit = SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(3),
        ]);

        $result = app(LeadTaskCleanupService::class)->deleteTasksForLeadAndOwner($lead->id, $user->id, $user->id, 'lead_transferred');

        $this->assertSame(1, $result['tasks']);
        $this->assertSame(1, $result['telecaller_tasks']);
        $this->assertSame(1, $result['follow_ups']);
        $this->assertSame(1, $result['meetings']);
        $this->assertSame(1, $result['site_visits']);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'id' => $telecallerTask->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('site_visits', [
            'id' => $siteVisit->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertNull(Task::find($task->id));
        $this->assertNull(TelecallerTask::find($telecallerTask->id));
        $this->assertNull(FollowUp::find($followUp->id));
        $this->assertNull(Meeting::find($meeting->id));
        $this->assertNull(SiteVisit::find($siteVisit->id));
        $this->assertNotNull(Task::withQueueHidden()->find($task->id));
        $this->assertNotNull(TelecallerTask::withQueueHidden()->find($telecallerTask->id));
        $this->assertNotNull(FollowUp::withQueueHidden()->find($followUp->id));
        $this->assertNotNull(Meeting::withQueueHidden()->find($meeting->id));
        $this->assertNotNull(SiteVisit::withQueueHidden()->find($siteVisit->id));
    }

    public function test_site_visit_reschedule_does_not_cancel_unrelated_tasks_without_linking_columns(): void
    {
        $user = $this->createUser();
        $lead = $this->createLead($user->id);

        $task = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'type' => 'phone_call',
            'title' => 'Unrelated follow-up',
            'status' => 'pending',
        ]);

        $telecallerTask = TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'task_type' => 'calling',
            'status' => 'pending',
        ]);

        $siteVisit = SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);

        app(LeadSingleOpenTaskService::class)->cancelOpenTasksForSiteVisit(
            $siteVisit,
            'Cancelled due to site visit reschedule: guard test'
        );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'pending',
            'completed_at' => null,
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'id' => $telecallerTask->id,
            'status' => 'pending',
            'completed_at' => null,
        ]);
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
            $table->string('status')->default('connected');
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
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('converted_to_closer_at')->nullable();
            $table->timestamp('closing_verified_at')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            $table->integer('lead_score')->nullable();
            $table->string('verification_status')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type');
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
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            'name' => 'Sales Executive',
            'slug' => Role::SALES_EXECUTIVE,
            'is_active' => true,
        ]);

        return User::create([
            'name' => 'Cleanup User',
            'email' => 'cleanup@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(int $createdBy): Lead
    {
        return Lead::create([
            'name' => 'Cleanup Lead',
            'phone' => '9999991111',
            'source' => 'meta',
            'status' => 'connected',
            'created_by' => $createdBy,
        ]);
    }
}
