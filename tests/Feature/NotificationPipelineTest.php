<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotificationJob;
use App\Models\AppNotification;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('broadcasting.default', 'log');
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
        Carbon::setTestNow(Carbon::parse('2026-03-28 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lead_assignment_creates_per_lead_notifications_and_queues_fcm(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $assignee = $this->createUser($salesExecutiveRole);
        $firstLead = $this->createLead('Lead One');
        $secondLead = $this->createLead('Lead Two');

        $service = app(NotificationService::class);
        $service->notifyNewLead($assignee, $firstLead, 'https://example.test/leads/1');
        $service->notifyNewLead($assignee, $secondLead, 'https://example.test/leads/2');

        $this->assertEquals(2, AppNotification::where('user_id', $assignee->id)->where('type', AppNotification::TYPE_NEW_LEAD)->count());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
            'title' => 'New Lead Assigned',
            'message' => 'New lead assigned: Lead One',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
            'title' => 'New Lead Assigned',
            'message' => 'New lead assigned: Lead Two',
        ]);

        Queue::assertPushed(SendFcmNotificationJob::class, 2);
    }

    public function test_new_lead_notifications_can_store_long_action_urls(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $assignee = $this->createUser($salesExecutiveRole);
        $lead = $this->createLead('Long URL Lead');

        $longActionUrl = 'https://example.test/leads/1?' . http_build_query([
            'back' => 'https://example.test/sales-manager/tasks',
            'open_task' => 981,
            'task_category' => 'fresh_lead',
            'payload' => str_repeat('x', 400),
        ]);

        $service = app(NotificationService::class);
        $service->notifyNewLead($assignee, $lead, $longActionUrl);

        $notification = AppNotification::query()->latest('id')->first();

        $this->assertNotNull($notification);
        $this->assertSame($longActionUrl, $notification->action_url);
        $this->assertGreaterThan(255, strlen($notification->action_url));
    }

    public function test_followup_reminder_command_notifies_creator_and_current_owner_once(): void
    {
        Queue::fake();

        $managerRole = $this->createRole(Role::SALES_MANAGER);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $manager = $this->createUser($managerRole, ['name' => 'Manager']);
        $creator = $this->createUser($salesExecutiveRole, ['name' => 'Creator', 'manager_id' => $manager->id]);
        $owner = $this->createUser($salesExecutiveRole, ['name' => 'Owner', 'manager_id' => $manager->id]);

        $lead = $this->createLead('Reminder Lead');
        $this->assignLead($lead, $owner);

        $followup = FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $creator->id,
            'type' => 'call',
            'notes' => 'Follow up',
            'scheduled_at' => now()->addMinutes(15)->copy()->startOfMinute()->addSeconds(10),
            'status' => 'scheduled',
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $owner->id,
            'task_type' => 'follow_up_call',
            'status' => 'pending',
            'scheduled_at' => $followup->scheduled_at,
            'follow_up_id' => $followup->id,
            'notes' => 'Follow-up call',
        ]);

        Artisan::call('notifications:followup-reminders');

        $this->assertEquals(2, AppNotification::where('type', AppNotification::TYPE_FOLLOWUP_REMINDER)->count());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $creator->id,
            'type' => AppNotification::TYPE_FOLLOWUP_REMINDER,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => AppNotification::TYPE_FOLLOWUP_REMINDER,
        ]);
        $this->assertSame('15_min', AppNotification::latest('id')->first()->data['stage'] ?? null);
        $this->assertStringContainsString('task_id=', AppNotification::latest('id')->first()->action_url);

        $this->assertNull(FollowUp::first()->reminder_sent_at);
        $this->assertNotNull(FollowUp::first()->first_reminder_sent_at);
        Queue::assertPushed(SendFcmNotificationJob::class, 2);
    }

    public function test_followup_final_reminder_sends_five_minutes_before(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $owner = $this->createUser($salesExecutiveRole, ['name' => 'Owner']);
        $lead = $this->createLead('Five Minute Followup Lead');
        $this->assignLead($lead, $owner);

        $followup = FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'type' => 'call',
            'notes' => 'Five minute follow up',
            'scheduled_at' => now()->addMinutes(5)->copy()->startOfMinute()->addSeconds(10),
            'status' => 'scheduled',
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $owner->id,
            'task_type' => 'follow_up_call',
            'status' => 'pending',
            'scheduled_at' => $followup->scheduled_at,
            'follow_up_id' => $followup->id,
            'notes' => 'Follow-up call',
        ]);

        Artisan::call('notifications:followup-reminders');

        $notification = AppNotification::latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertSame('5_min', $notification->data['stage'] ?? null);
        $this->assertStringContainsString('This lead "Five Minute Followup Lead" has a follow-up at', $notification->message);
        $this->assertNotNull(FollowUp::first()->final_reminder_sent_at);
        $this->assertNotNull(FollowUp::first()->reminder_sent_at);
    }

    public function test_followup_reminder_command_skips_transferred_followups(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $owner = $this->createUser($salesExecutiveRole, ['name' => 'Transferred Owner']);
        $lead = $this->createLead('Transferred Followup Lead');
        $this->assignLead($lead, $owner);

        FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'type' => 'call',
            'notes' => 'Transferred follow-up',
            'scheduled_at' => now()->addMinutes(15)->copy()->startOfMinute()->addSeconds(20),
            'status' => 'scheduled',
            'queue_hidden_at' => now(),
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        Artisan::call('notifications:followup-reminders');
        Artisan::call('notifications:overdue-followups');

        $this->assertEquals(0, AppNotification::whereIn('type', [
            AppNotification::TYPE_FOLLOWUP_REMINDER,
            AppNotification::TYPE_FOLLOWUP_OVERDUE,
        ])->count());
        Queue::assertNotPushed(SendFcmNotificationJob::class);
    }

    public function test_followup_reschedule_resets_tracking_markers(): void
    {
        $role = $this->createRole(Role::SALES_EXECUTIVE);
        $user = $this->createUser($role);
        $lead = $this->createLead();

        $followup = FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'type' => 'call',
            'notes' => 'Scheduled',
            'scheduled_at' => now()->addMinutes(5),
            'status' => 'scheduled',
            'reminder_sent_at' => now(),
            'overdue_notified_at' => now(),
        ]);

        $followup->update([
            'scheduled_at' => now()->addHour(),
        ]);

        $followup->refresh();

        $this->assertNull($followup->reminder_sent_at);
        $this->assertNull($followup->overdue_notified_at);
    }

    public function test_meeting_reminder_command_notifies_assigned_user_once(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $assignee = $this->createUser($salesExecutiveRole, ['name' => 'Meeting Owner']);
        $lead = $this->createLead('Meeting Lead');

        $meeting = Meeting::create([
            'lead_id' => $lead->id,
            'created_by' => $assignee->id,
            'assigned_to' => $assignee->id,
            'customer_name' => 'Meeting Lead',
            'phone' => '9999999999',
            'date_of_visit' => now()->toDateString(),
            'budget_range' => 'Under 50 Lac',
            'property_type' => 'Flat',
            'payment_mode' => 'Self Fund',
            'tentative_period' => 'Within 1 Month',
            'lead_type' => 'Meeting',
            'scheduled_at' => now()->addMinutes(15)->copy()->startOfMinute()->addSeconds(15),
            'status' => 'scheduled',
            'verification_status' => 'pending',
            'reminder_enabled' => true,
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignee->id,
            'task_type' => 'pre_meeting_reminder',
            'status' => 'pending',
            'scheduled_at' => $meeting->scheduled_at,
            'meeting_id' => $meeting->id,
            'notes' => 'Meeting reminder call',
        ]);

        Artisan::call('notifications:meeting-reminders');

        $this->assertEquals(1, AppNotification::where('type', AppNotification::TYPE_MEETING_REMINDER)->count());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
            'type' => AppNotification::TYPE_MEETING_REMINDER,
            'title' => 'Meeting Reminder',
        ]);
        $this->assertSame('15_min', AppNotification::latest('id')->first()->data['stage'] ?? null);
        $this->assertStringContainsString('task_id=', AppNotification::latest('id')->first()->action_url);
        $this->assertNull(Meeting::first()->reminder_sent_at);
        $this->assertNotNull(Meeting::first()->first_reminder_sent_at);
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    public function test_site_visit_reminder_command_notifies_assigned_user_at_five_minutes(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $assignee = $this->createUser($salesExecutiveRole, ['name' => 'Visit Owner']);
        $lead = $this->createLead('Visit Lead');

        $siteVisit = SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $assignee->id,
            'assigned_to' => $assignee->id,
            'customer_name' => 'Visit Lead',
            'phone' => '9999999999',
            'project' => 'Demo Project',
            'lead_type' => 'Site Visit',
            'scheduled_at' => now()->addMinutes(5)->copy()->startOfMinute()->addSeconds(15),
            'status' => 'scheduled',
            'reminder_enabled' => true,
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignee->id,
            'task_type' => 'pre_site_visit_reminder',
            'status' => 'pending',
            'scheduled_at' => $siteVisit->scheduled_at,
            'site_visit_id' => $siteVisit->id,
            'notes' => 'Site visit reminder call',
        ]);

        Artisan::call('notifications:site-visit-reminders');

        $notification = AppNotification::latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertSame(AppNotification::TYPE_SITE_VISIT_REMINDER, $notification->type);
        $this->assertSame('5_min', $notification->data['stage'] ?? null);
        $this->assertStringContainsString('task_id=', $notification->action_url);
        $this->assertNotNull(SiteVisit::first()->final_reminder_sent_at);
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    public function test_meeting_reschedule_resets_reminder_marker(): void
    {
        $role = $this->createRole(Role::SALES_EXECUTIVE);
        $user = $this->createUser($role);
        $lead = $this->createLead();

        $meeting = Meeting::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'customer_name' => 'Reminder Reset Meeting',
            'phone' => '9999999999',
            'date_of_visit' => now()->toDateString(),
            'budget_range' => 'Under 50 Lac',
            'property_type' => 'Flat',
            'payment_mode' => 'Self Fund',
            'tentative_period' => 'Within 1 Month',
            'lead_type' => 'Meeting',
            'scheduled_at' => now()->addMinutes(5),
            'status' => 'scheduled',
            'verification_status' => 'pending',
            'reminder_sent_at' => now(),
        ]);

        $meeting->update([
            'scheduled_at' => now()->addHour(),
        ]);

        $meeting->refresh();

        $this->assertNull($meeting->reminder_sent_at);
    }

    public function test_meeting_reminder_command_skips_cancelled_or_completed_meetings(): void
    {
        Queue::fake();

        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $assignee = $this->createUser($salesExecutiveRole, ['name' => 'Meeting Owner']);
        $lead = $this->createLead('Skipped Meeting Lead');

        Meeting::create([
            'lead_id' => $lead->id,
            'created_by' => $assignee->id,
            'assigned_to' => $assignee->id,
            'customer_name' => 'Cancelled Meeting',
            'phone' => '9999999999',
            'date_of_visit' => now()->toDateString(),
            'budget_range' => 'Under 50 Lac',
            'property_type' => 'Flat',
            'payment_mode' => 'Self Fund',
            'tentative_period' => 'Within 1 Month',
            'lead_type' => 'Meeting',
            'scheduled_at' => now()->addMinutes(5)->copy()->startOfMinute()->addSeconds(5),
            'status' => 'cancelled',
            'verification_status' => 'pending',
        ]);

        Meeting::create([
            'lead_id' => $lead->id,
            'created_by' => $assignee->id,
            'assigned_to' => $assignee->id,
            'customer_name' => 'Completed Meeting',
            'phone' => '9999999999',
            'date_of_visit' => now()->toDateString(),
            'budget_range' => 'Under 50 Lac',
            'property_type' => 'Flat',
            'payment_mode' => 'Self Fund',
            'tentative_period' => 'Within 1 Month',
            'lead_type' => 'Meeting',
            'scheduled_at' => now()->addMinutes(5)->copy()->startOfMinute()->addSeconds(25),
            'status' => 'scheduled',
            'verification_status' => 'pending',
            'completed_at' => now(),
        ]);

        Artisan::call('notifications:meeting-reminders');

        $this->assertEquals(0, AppNotification::where('type', AppNotification::TYPE_MEETING_REMINDER)->count());
        Queue::assertNotPushed(SendFcmNotificationJob::class);
    }

    public function test_overdue_task_notifications_repeat_after_thirty_minutes_and_stop_when_completed(): void
    {
        Queue::fake();

        $managerRole = $this->createRole(Role::SALES_MANAGER);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $manager = $this->createUser($managerRole, ['name' => 'Task Manager']);
        $assignee = $this->createUser($salesExecutiveRole, ['name' => 'Task Owner', 'manager_id' => $manager->id]);
        $lead = $this->createLead('Overdue Lead');

        $task = TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignee->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => now()->subMinutes(20),
            'created_by' => $manager->id,
        ]);
        $task->update(['overdue_notified_at' => now()->subMinutes(20)]);

        Artisan::call('notifications:overdue-tasks');
        $this->assertEquals(0, AppNotification::where('type', AppNotification::TYPE_TASK_OVERDUE)->count());

        $task->update(['overdue_notified_at' => now()->subMinutes(31)]);
        Artisan::call('notifications:overdue-tasks');

        $this->assertEquals(2, AppNotification::where('type', AppNotification::TYPE_TASK_OVERDUE)->count());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
            'type' => AppNotification::TYPE_TASK_OVERDUE,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $manager->id,
            'type' => AppNotification::TYPE_TASK_OVERDUE,
        ]);

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Artisan::call('notifications:overdue-tasks');
        $this->assertEquals(2, AppNotification::where('type', AppNotification::TYPE_TASK_OVERDUE)->count());
    }

    public function test_overdue_followup_notifications_go_to_responsible_user_and_manager(): void
    {
        Queue::fake();

        $managerRole = $this->createRole(Role::SALES_MANAGER);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $manager = $this->createUser($managerRole, ['name' => 'Followup Manager']);
        $creator = $this->createUser($salesExecutiveRole, ['name' => 'Creator', 'manager_id' => $manager->id]);
        $owner = $this->createUser($salesExecutiveRole, ['name' => 'Responsible', 'manager_id' => $manager->id]);
        $lead = $this->createLead('Followup Overdue');
        $this->assignLead($lead, $owner);

        FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $creator->id,
            'type' => 'call',
            'notes' => 'Pending follow-up',
            'scheduled_at' => now()->subMinutes(10),
            'status' => 'scheduled',
            'overdue_notified_at' => now()->subMinutes(31),
        ]);

        Artisan::call('notifications:overdue-followups');

        $this->assertEquals(2, AppNotification::where('type', AppNotification::TYPE_FOLLOWUP_OVERDUE)->count());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => AppNotification::TYPE_FOLLOWUP_OVERDUE,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $manager->id,
            'type' => AppNotification::TYPE_FOLLOWUP_OVERDUE,
        ]);
    }

    public function test_manager_task_reminder_command_notifies_asm_once_fifteen_minutes_before_due_time(): void
    {
        Queue::fake();

        $asmRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);
        $asm = $this->createUser($asmRole, ['name' => 'ASM Owner']);
        $lead = $this->createLead('Manager Reminder Lead');

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $asm->id,
            'type' => 'phone_call',
            'title' => 'Retry call: Manager Reminder Lead (CNP rescheduled - 28 Mar 2026, 10:15 AM)',
            'description' => 'Retry call at selected time - previous call not picked. Original task ID: 1',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(15)->copy()->startOfMinute()->addSeconds(20),
            'created_by' => $asm->id,
            'notes' => 'CNP retry task created from task #1 on 2026-03-28 10:00:00 - Scheduled for 10:15 AM | Remark: Call later',
        ]);

        Artisan::call('notifications:manager-task-reminders');

        $this->assertEquals(1, AppNotification::where('type', AppNotification::TYPE_CALL_REMINDER)->count());
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $asm->id,
            'type' => AppNotification::TYPE_CALL_REMINDER,
            'title' => 'Call Reminder',
        ]);
        $this->assertNotNull(Task::first()->reminder_sent_at);
        Queue::assertPushed(SendFcmNotificationJob::class, 1);

        Artisan::call('notifications:manager-task-reminders');
        $this->assertEquals(1, AppNotification::where('type', AppNotification::TYPE_CALL_REMINDER)->count());
    }

    public function test_manager_task_reminder_includes_regular_followup_call_tasks(): void
    {
        Queue::fake();

        $asmRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);
        $asm = $this->createUser($asmRole, ['name' => 'Vivek Kumar']);
        $lead = $this->createLead('Amit Mishra');

        $task = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $asm->id,
            'type' => 'phone_call',
            'title' => 'Follow-up call: Amit Mishra',
            'description' => 'Follow-up call task scheduled for 2026-03-28 10:05.',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(5)->startOfMinute(),
            'created_by' => $asm->id,
            'notes' => 'Follow-up scheduled for 2026-03-28 10:05',
        ]);

        Artisan::call('notifications:manager-task-reminders');

        $notification = AppNotification::where('type', AppNotification::TYPE_CALL_REMINDER)->first();
        $this->assertNotNull($notification);
        $this->assertSame($asm->id, $notification->user_id);
        $this->assertSame($task->id, data_get($notification->data, 'task_id'));
        $this->assertSame('5_min', data_get($notification->data, 'stage'));
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
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
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('next_followup_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('unassigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('created_by');
            $table->string('type');
            $table->text('notes');
            $table->dateTime('scheduled_at');
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('first_reminder_sent_at')->nullable();
            $table->dateTime('final_reminder_sent_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('outcome')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to');
            $table->string('task_type');
            $table->string('status')->default('pending');
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('notification_sent_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->dateTime('moved_to_pending_at')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('prospect_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name');
            $table->string('phone', 16);
            $table->string('employee')->nullable();
            $table->string('occupation')->nullable();
            $table->date('date_of_visit');
            $table->string('project')->nullable();
            $table->string('budget_range');
            $table->string('team_leader')->nullable();
            $table->string('property_type');
            $table->string('payment_mode');
            $table->string('tentative_period');
            $table->string('lead_type');
            $table->text('photos')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('first_reminder_sent_at')->nullable();
            $table->dateTime('final_reminder_sent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('verification_status')->default('pending');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable();
            $table->text('completion_proof_photos')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->text('dead_reason')->nullable();
            $table->dateTime('marked_dead_at')->nullable();
            $table->unsignedBigInteger('marked_dead_by')->nullable();
            $table->dateTime('rescheduled_at')->nullable();
            $table->unsignedBigInteger('rescheduled_by')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->boolean('is_rescheduled')->default(false);
            $table->unsignedBigInteger('converted_to_site_visit_id')->nullable();
            $table->boolean('is_converted')->default(false);
            $table->integer('meeting_sequence')->nullable();
            $table->string('meeting_mode')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('location')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->integer('reminder_minutes')->nullable();
            $table->unsignedBigInteger('pre_meeting_call_task_id')->nullable();
            $table->string('customer_confirmation_status')->nullable();
            $table->unsignedBigInteger('original_meeting_id')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('project')->nullable();
            $table->string('lead_type')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('first_reminder_sent_at')->nullable();
            $table->dateTime('final_reminder_sent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->boolean('reminder_enabled')->default(true);
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->string('type')->default('phone_call');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
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

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('action_type')->nullable();
            $table->text('action_url')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
        ]);
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'user' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(string $name = 'Lead'): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => '9999999999',
            'status' => 'new',
        ]);
    }

    private function assignLead(Lead $lead, User $owner): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $owner->id,
            'assigned_by' => $owner->manager_id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
    }
}
