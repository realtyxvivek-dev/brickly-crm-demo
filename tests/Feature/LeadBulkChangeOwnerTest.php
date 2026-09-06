<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\LeadTaskCleanupService;
use App\Services\LeadAssignmentService;
use App\Services\NewLeadSlaAutomationService;
use App\Services\NotificationService;
use App\Services\TelecallerLimitService;
use App\Services\TelecallerDashboardService;
use App\Services\TelecallerStatusService;
use App\Services\UserStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LeadBulkChangeOwnerTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bulk_change_owner_reassigns_and_creates_new_owner_task(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $executiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole, 'CRM User');
        $oldOwner = $this->createUser($executiveRole, 'Old Owner');
        $newOwner = $this->createUser($executiveRole, 'New Owner');
        $lead = $this->createLead('Bulk Lead');

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'assigned_by' => $crm->id,
            'assigned_at' => now()->subHour(),
            'is_active' => true,
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => now()->subMinutes(15),
            'created_by' => $crm->id,
        ]);

        TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'task_type' => 'calling',
            'status' => 'completed',
            'completed_at' => now()->subMinutes(45),
            'created_by' => $crm->id,
        ]);

        FollowUp::create([
            'lead_id' => $lead->id,
            'created_by' => $oldOwner->id,
            'type' => 'call',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(20),
        ]);

        Meeting::create([
            'lead_id' => $lead->id,
            'created_by' => $oldOwner->id,
            'assigned_to' => $oldOwner->id,
            'customer_name' => 'Bulk Lead',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);

        SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $oldOwner->id,
            'assigned_to' => $oldOwner->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $service = Mockery::mock(LeadAssignmentService::class);
        $service->shouldReceive('bulkAssignLeads')
            ->once()
            ->with([$lead->id], $newOwner->id, $crm->id, true)
            ->andReturnUsing(function () use ($lead, $oldOwner, $newOwner, $crm) {
                LeadAssignment::where('lead_id', $lead->id)
                    ->where('assigned_to', $oldOwner->id)
                    ->update([
                        'is_active' => false,
                        'unassigned_at' => now(),
                    ]);

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $newOwner->id,
                    'assigned_by' => $crm->id,
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);

                return [
                    'success' => 1,
                    'failed' => 0,
                    'errors' => [],
                ];
            });

        $this->app->instance(LeadAssignmentService::class, $service);

        $response = $this->actingAs($crm)->post(route('leads.bulk-change-owner'), [
            'ids' => [$lead->id],
            'assigned_to' => $newOwner->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $newOwner->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'status' => 'cancelled',
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'status' => 'completed',
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $newOwner->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'queue_hidden_at' => null,
        ]);

        $this->assertDatabaseHas('follow_ups', [
            'lead_id' => $lead->id,
            'created_by' => $oldOwner->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('meetings', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $this->assertDatabaseHas('site_visits', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'queue_hidden_reason' => 'lead_transferred',
        ]);

        $dashboard = app(TelecallerDashboardService::class);
        $urgent = $dashboard->getUrgentTasks($oldOwner->id);
        $todaySchedule = $dashboard->getTodaySchedule($oldOwner->id);

        $this->assertSame(0, $urgent['overdue_followups_count']);
        $this->assertSame(0, $urgent['today_site_visits_count']);
        $this->assertEmpty(array_filter($todaySchedule, fn (array $entry) => (int) $entry['lead_id'] === (int) $lead->id));
    }

    public function test_assigning_to_hr_manager_marks_lead_as_hiring_candidate(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $hrRole = $this->createRole(Role::HR_MANAGER);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole, 'CRM User');
        $oldOwner = $this->createUser($salesRole, 'Old Owner');
        $hrManager = $this->createUser($hrRole, 'HR Manager');
        $lead = $this->createLead('HR Normal Assign Lead');

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'assigned_by' => $crm->id,
            'assigned_at' => now()->subHour(),
            'is_active' => true,
        ]);

        $service = $this->makeLeadAssignmentService();

        $result = $service->transferAssignedLeads([$lead->id], $hrManager->id, $crm->id);

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $hrManager->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'new',
        ]);
    }

    public function test_assigning_to_junior_hr_marks_lead_as_hiring_candidate(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $juniorHrRole = $this->createRole(Role::JUNIOR_HR);

        $crm = $this->createUser($crmRole, 'CRM User');
        $juniorHr = $this->createUser($juniorHrRole, 'Junior HR');
        $lead = $this->createLead('Junior HR Assign Lead');

        $service = $this->makeLeadAssignmentService();

        $result = $service->transferAssignedLeads([$lead->id], $juniorHr->id, $crm->id);

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'new',
        ]);
    }

    public function test_assigning_to_sales_user_does_not_mark_lead_as_hiring_candidate(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole, 'CRM User');
        $salesUser = $this->createUser($salesRole, 'Sales User');
        $lead = $this->createLead('Sales Assign Lead');

        $service = $this->makeLeadAssignmentService();

        $result = $service->transferAssignedLeads([$lead->id], $salesUser->id, $crm->id);

        $this->assertSame(1, $result['success']);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'is_hiring_candidate' => false,
            'hiring_status' => null,
        ]);
    }

    public function test_hr_assigned_backfill_marks_only_non_hiring_hr_leads(): void
    {
        $hrRole = $this->createRole(Role::HR_MANAGER);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);

        $hrManager = $this->createUser($hrRole, 'HR Manager');
        $salesUser = $this->createUser($salesRole, 'Sales User');

        $hrLead = $this->createLead('Backfill HR Lead');
        $salesLead = $this->createLead('Backfill Sales Lead');
        $alreadyHiringLead = $this->createLead('Already Hiring Lead');
        $alreadyHiringLead->forceFill([
            'is_hiring_candidate' => true,
            'hiring_status' => 'connected',
        ])->save();

        LeadAssignment::create([
            'lead_id' => $hrLead->id,
            'assigned_to' => $hrManager->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);
        LeadAssignment::create([
            'lead_id' => $salesLead->id,
            'assigned_to' => $salesUser->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);
        LeadAssignment::create([
            'lead_id' => $alreadyHiringLead->id,
            'assigned_to' => $hrManager->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        Artisan::call('hiring:backfill-hr-assigned-leads');

        $this->assertDatabaseHas('leads', [
            'id' => $hrLead->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'new',
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $salesLead->id,
            'is_hiring_candidate' => false,
            'hiring_status' => null,
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $alreadyHiringLead->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'connected',
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
            $table->rememberToken();
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->boolean('is_hiring_candidate')->default(false);
            $table->string('hiring_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->string('assignment_method')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sheet_config_id')->nullable();
            $table->unsignedBigInteger('sheet_assignment_config_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->unsignedBigInteger('filled_by_user_id')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('moved_to_pending_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->nullable();
            $table->string('outcome')->nullable();
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
            $table->string('customer_name')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->boolean('reminder_enabled')->default(true);
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
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, string $name): User
    {
        static $counter = 1;

        return User::create([
            'name' => $name,
            'email' => 'bulk-owner-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(string $name): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => '9999999999',
            'source' => 'meta',
            'status' => 'connected',
        ]);
    }

    private function makeLeadAssignmentService(): LeadAssignmentService
    {
        $this->app->instance(NewLeadSlaAutomationService::class, Mockery::mock(NewLeadSlaAutomationService::class)
            ->shouldReceive('syncForAssignment')
            ->andReturnNull()
            ->getMock());

        $userStatusService = Mockery::mock(UserStatusService::class);
        $userStatusService->shouldReceive('isUserAbsent')->andReturnFalse();

        $limitService = Mockery::mock(TelecallerLimitService::class)->shouldIgnoreMissing();
        $limitService->shouldReceive('checkDailyLimits')->andReturn(['is_allowed' => true]);
        $limitService->shouldReceive('incrementAssignedCount')->andReturnNull();

        $statusService = Mockery::mock(TelecallerStatusService::class)->shouldIgnoreMissing();
        $statusService->shouldReceive('canReceiveAssignment')->andReturn(['can_receive' => true]);

        return new LeadAssignmentService(
            $limitService,
            $statusService,
            $userStatusService,
            Mockery::mock(NotificationService::class)->shouldIgnoreMissing(),
            Mockery::mock(LeadTaskCleanupService::class)->shouldReceive('deleteTasksForLeadAndOwner')->andReturn([])->getMock(),
        );
    }
}
