<?php

namespace Tests\Feature;

use App\Events\LeadAssigned;
use App\Listeners\SendNewLeadNotification;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\LeadAssignmentWorkflowService;
use App\Services\LeadOwnerTaskService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeniorManagerAssignmentTaskCreationTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-03-30 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lead_assigned_event_creates_phone_call_task_for_senior_manager(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-senior-task@example.test',
        ]);
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'email' => 'senior-task@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'Senior Manager Event Lead',
            'created_by' => $crm->id,
        ]);

        Event::dispatch(new LeadAssigned($lead, $seniorManager->id, $crm->id));

        $task = Task::where('lead_id', $lead->id)
            ->where('assigned_to', $seniorManager->id)
            ->where('type', 'phone_call')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame('pending', $task->status);
        $this->assertSame($crm->id, (int) $task->created_by);
    }

    public function test_workflow_assignment_creates_single_open_task_for_senior_manager_without_duplicates(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-workflow@example.test',
        ]);
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'email' => 'senior-workflow@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'Senior Manager Workflow Lead',
            'created_by' => $crm->id,
        ]);

        $service = app(LeadAssignmentWorkflowService::class);

        $first = $service->assignLead($lead, $seniorManager->id, $crm->id, notes: 'First assignment');
        $second = $service->assignLead($lead->fresh(), $seniorManager->id, $crm->id, notes: 'Repeat assignment');

        $openTasks = Task::where('lead_id', $lead->id)
            ->where('assigned_to', $seniorManager->id)
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
            ->get();

        $this->assertCount(1, $openTasks);
        $this->assertTrue($first['event_dispatched']);
        $this->assertTrue($second['event_dispatched']);
        $this->assertSame($openTasks->first()->id, $first['task_result']['task_id']);
        $this->assertSame($openTasks->first()->id, $second['task_result']['task_id']);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $seniorManager->id,
            'is_active' => true,
        ]);
    }

    public function test_manager_owner_action_url_points_to_lead_detail_with_task_context(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-manager-url@example.test',
        ]);
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'asm-url@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'ASM Notification Lead',
            'created_by' => $crm->id,
        ]);

        $service = app(LeadOwnerTaskService::class);
        $result = $service->ensureOpenTaskForOwner($lead, $manager, $crm->id, 'Call from notification');

        $task = Task::findOrFail($result['task_id']);
        $parsed = parse_url($result['action_url']);
        parse_str($parsed['query'] ?? '', $query);

        $this->assertSame('/leads/' . $lead->id, $parsed['path'] ?? '');
        $this->assertSame((string) $task->id, (string) ($query['open_task'] ?? null));
        $this->assertSame('fresh_lead', $query['task_category'] ?? null);
        $this->assertSame(route('sales-manager.tasks'), $query['back'] ?? null);
        $this->assertSame(['back', 'open_task', 'task_category'], array_keys($query));
    }

    public function test_sales_executive_owner_action_url_stays_on_telecaller_tasks(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-telecaller-url@example.test',
        ]);
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), [
            'email' => 'telecaller-url@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'Telecaller Notification Lead',
            'created_by' => $crm->id,
        ]);

        $service = app(LeadOwnerTaskService::class);
        $result = $service->ensureOpenTaskForOwner($lead, $executive, $crm->id);

        $this->assertStringStartsWith(url('/telecaller/tasks?status=pending&task_id='), $result['action_url']);
        $this->assertStringContainsString('task_id=' . $result['task_id'], $result['action_url']);
    }

    public function test_asm_and_senior_manager_can_assign_leads(): void
    {
        $assistantSalesManager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'asm-assign@example.test',
        ]);
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'email' => 'senior-assign@example.test',
        ]);

        $this->assertTrue($assistantSalesManager->canAssignLeads());
        $this->assertTrue($seniorManager->canAssignLeads());
    }

    public function test_assignment_workflow_can_transfer_lead_back_to_previous_owner(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-transfer-back@example.test',
        ]);
        $ownerA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'owner-a@example.test',
        ]);
        $ownerB = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'email' => 'owner-b@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'Transfer Back Lead',
            'created_by' => $crm->id,
        ]);

        $service = app(LeadAssignmentWorkflowService::class);

        $service->assignLead($lead, $ownerA->id, $crm->id);
        $service->assignLead($lead->fresh(), $ownerB->id, $crm->id);
        $result = $service->assignLead($lead->fresh(), $ownerA->id, $crm->id);

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $ownerA->id,
            'is_active' => true,
        ]);
        $this->assertSame($ownerB->id, $result['old_owner_ids'][0] ?? null);
        $this->assertNotNull($result['task_result']['action_url']);
    }

    public function test_send_new_lead_notification_failures_do_not_bubble(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-listener-failure@example.test',
        ]);
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'email' => 'listener-manager@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'Listener Failure Lead',
            'created_by' => $crm->id,
        ]);

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('notifyNewLead')
            ->once()
            ->andThrow(new \RuntimeException('Simulated notification failure'));
        $this->app->instance(NotificationService::class, $notificationService);

        $listener = $this->app->make(SendNewLeadNotification::class);

        $listener->handle(new LeadAssigned($lead, $manager->id, $crm->id));

        $this->assertDatabaseCount('app_notifications', 0);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

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
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->string('priority')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->text('recurrence_pattern')->nullable();
            $table->timestamp('recurrence_end_date')->nullable();
            $table->timestamp('rescheduled_from')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('call_status')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamps();
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
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->text('data')->nullable();
            $table->string('action_type')->nullable();
            $table->text('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('fcm_token')->nullable();
            $table->string('device_type')->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $role->slug . ' User',
            'email' => $role->slug . '-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Auto Task Lead',
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'meta',
        ], $attributes));
    }
}
