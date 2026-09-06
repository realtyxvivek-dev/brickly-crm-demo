<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BulkCallingTaskTest extends TestCase
{
    use WithFaker;

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

    public function test_crm_can_view_bulk_calling_task_page_and_only_eligible_users_are_listed(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $salesManagerRole = $this->createRole(Role::SALES_MANAGER);
        $financeRole = $this->createRole(Role::FINANCE_MANAGER);

        $crm = $this->createUser($crmRole, ['name' => 'CRM User']);
        $eligibleExecutive = $this->createUser($salesExecutiveRole, ['name' => 'Eligible Executive']);
        $eligibleManager = $this->createUser($salesManagerRole, ['name' => 'Eligible Manager']);
        $ineligibleFinance = $this->createUser($financeRole, ['name' => 'Finance User']);

        $response = $this->actingAs($crm)->get(route('lead-assignment.calling-tasks.index'));

        $response->assertOk();
        $response->assertSee('Bulk Calling Tasks');
        $response->assertSee($eligibleExecutive->name);
        $response->assertSee($eligibleManager->name);
        $response->assertDontSee($ineligibleFinance->name);
    }

    public function test_non_crm_non_admin_user_cannot_access_bulk_calling_task_page(): void
    {
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $salesExecutive = $this->createUser($salesExecutiveRole);

        $response = $this->actingAs($salesExecutive)->get(route('lead-assignment.calling-tasks.index'));

        $response->assertForbidden();
    }

    public function test_selected_creation_uses_telecaller_tasks_and_skips_duplicate_or_ineligible_leads(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole);
        $assignee = $this->createUser($salesExecutiveRole, ['name' => 'Executive']);
        $otherAssignee = $this->createUser($salesExecutiveRole, ['name' => 'Other Executive']);

        $duplicateLead = $this->createLead('Duplicate Lead', ['source' => 'meta']);
        $readyLead = $this->createLead('Ready Lead', ['source' => 'meta']);
        $notEligibleLead = $this->createLead('Not Eligible Lead', ['source' => 'meta']);

        $this->assignLead($duplicateLead, $assignee, $crm);
        $this->assignLead($readyLead, $assignee, $crm);
        $this->assignLead($notEligibleLead, $otherAssignee, $crm);

        TelecallerTask::create([
            'lead_id' => $duplicateLead->id,
            'assigned_to' => $assignee->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(15),
            'created_by' => $crm->id,
        ]);

        $preview = $this->actingAs($crm)->getJson(route('lead-assignment.calling-tasks.leads', [
            'assigned_user_id' => $assignee->id,
        ]));

        $preview->assertOk();
        $preview->assertJsonCount(2, 'data');
        $previewIds = collect($preview->json('data'))->pluck('id')->all();
        $this->assertSame([$readyLead->id, $duplicateLead->id], $previewIds);
        $duplicatePreview = collect($preview->json('data'))->firstWhere('id', $duplicateLead->id);
        $this->assertTrue((bool) ($duplicatePreview['has_open_call_task'] ?? false));

        $response = $this->actingAs($crm)->postJson(route('lead-assignment.calling-tasks.store'), [
            'assigned_user_id' => $assignee->id,
            'start_date' => now()->addHour()->format('Y-m-d'),
            'start_time' => now()->addHour()->format('H:i'),
            'gap_minutes' => 5,
            'notes' => 'Bulk created',
            'lead_ids' => [$duplicateLead->id, $readyLead->id, $notEligibleLead->id],
        ]);

        $response->assertOk();
        $response->assertJson([
            'created' => 1,
            'skipped' => 2,
            'reason_counts' => [
                'duplicate_open_task' => 1,
                'lead_not_eligible' => 1,
            ],
        ]);

        $this->assertEquals(2, TelecallerTask::count());
        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $readyLead->id,
            'assigned_to' => $assignee->id,
            'task_type' => 'calling',
            'notes' => 'Bulk created',
        ]);
    }

    public function test_preview_returns_all_eligible_leads_for_selected_user_without_filters(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $assistantManagerRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);

        $crm = $this->createUser($crmRole);
        $assignee = $this->createUser($assistantManagerRole, ['name' => 'ASM']);
        $otherAssignee = $this->createUser($assistantManagerRole, ['name' => 'Other ASM']);

        $matchingLead = $this->createLead('Meta New Lead', ['status' => 'new', 'source' => 'meta']);
        $otherStatusLead = $this->createLead('Connected Lead', ['status' => 'connected', 'source' => 'meta']);
        $otherSourceLead = $this->createLead('Google Lead', ['status' => 'new', 'source' => 'google']);
        $otherUserLead = $this->createLead('Other User Lead', ['status' => 'new', 'source' => 'meta']);

        $this->assignLead($matchingLead, $assignee, $crm);
        $this->assignLead($otherStatusLead, $assignee, $crm);
        $this->assignLead($otherSourceLead, $assignee, $crm);
        $this->assignLead($otherUserLead, $otherAssignee, $crm);

        $preview = $this->actingAs($crm)->getJson(route('lead-assignment.calling-tasks.leads', [
            'assigned_user_id' => $assignee->id,
        ]));

        $preview->assertOk();
        $previewIds = collect($preview->json('data'))->pluck('id')->all();
        $this->assertSame([$otherSourceLead->id, $otherStatusLead->id, $matchingLead->id], $previewIds);
    }

    public function test_manager_assignee_still_creates_phone_call_task_for_selected_leads(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $assistantManagerRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);

        $crm = $this->createUser($crmRole);
        $assignee = $this->createUser($assistantManagerRole, ['name' => 'ASM']);

        $matchingLead = $this->createLead('Meta New Lead', ['status' => 'new', 'source' => 'meta']);
        $secondLead = $this->createLead('Second Lead', ['status' => 'connected', 'source' => 'meta']);

        $this->assignLead($matchingLead, $assignee, $crm);
        $this->assignLead($secondLead, $assignee, $crm);

        $response = $this->actingAs($crm)->postJson(route('lead-assignment.calling-tasks.store'), [
            'assigned_user_id' => $assignee->id,
            'start_date' => now()->addHours(2)->format('Y-m-d'),
            'start_time' => now()->addHours(2)->format('H:i'),
            'gap_minutes' => 5,
            'notes' => 'Manager bulk task',
            'lead_ids' => [$matchingLead->id, $secondLead->id],
        ]);

        $response->assertOk();
        $response->assertJson([
            'created' => 2,
            'skipped' => 0,
        ]);

        $this->assertEquals(2, Task::count());
        $this->assertDatabaseHas('tasks', [
            'lead_id' => $matchingLead->id,
            'assigned_to' => $assignee->id,
            'type' => 'phone_call',
            'notes' => 'Manager bulk task',
        ]);
    }

    public function test_staggered_schedule_uses_current_query_order_and_gap_minutes(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole);
        $assignee = $this->createUser($salesExecutiveRole);

        $firstLead = $this->createLead('First Lead');
        $secondLead = $this->createLead('Second Lead');
        $thirdLead = $this->createLead('Third Lead');

        $this->assignLead($firstLead, $assignee, $crm);
        $this->assignLead($secondLead, $assignee, $crm);
        $this->assignLead($thirdLead, $assignee, $crm);

        $response = $this->actingAs($crm)->postJson(route('lead-assignment.calling-tasks.store'), [
            'assigned_user_id' => $assignee->id,
            'start_date' => '2026-03-28',
            'start_time' => '10:05',
            'gap_minutes' => 5,
            'lead_ids' => [$firstLead->id, $secondLead->id, $thirdLead->id],
        ]);

        $response->assertOk();
        $response->assertJson([
            'created' => 3,
            'skipped' => 0,
            'first_scheduled_at' => '2026-03-28 10:05:00',
            'last_scheduled_at' => '2026-03-28 10:15:00',
        ]);

        $tasks = TelecallerTask::query()->orderBy('scheduled_at')->pluck('scheduled_at')->map(fn ($time) => Carbon::parse($time)->format('H:i'))->values()->all();
        $this->assertSame(['10:05', '10:10', '10:15'], $tasks);
    }

    public function test_gap_zero_creates_same_scheduled_time_for_all_tasks(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole);
        $assignee = $this->createUser($salesExecutiveRole);

        $firstLead = $this->createLead('Gap Zero One');
        $secondLead = $this->createLead('Gap Zero Two');

        $this->assignLead($firstLead, $assignee, $crm);
        $this->assignLead($secondLead, $assignee, $crm);

        $response = $this->actingAs($crm)->postJson(route('lead-assignment.calling-tasks.store'), [
            'assigned_user_id' => $assignee->id,
            'start_date' => '2026-03-28',
            'start_time' => '11:00',
            'gap_minutes' => 0,
            'lead_ids' => [$firstLead->id, $secondLead->id],
        ]);

        $response->assertOk();

        $tasks = TelecallerTask::query()->pluck('scheduled_at')->map(fn ($time) => Carbon::parse($time)->format('Y-m-d H:i:s'))->unique()->values()->all();
        $this->assertSame(['2026-03-28 11:00:00'], $tasks);
    }

    public function test_non_selected_listed_leads_do_not_get_tasks(): void
    {
        $crmRole = $this->createRole(Role::CRM);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $crm = $this->createUser($crmRole);
        $assignee = $this->createUser($salesExecutiveRole);

        $firstLead = $this->createLead('Selected Lead');
        $secondLead = $this->createLead('Unselected Lead');

        $this->assignLead($firstLead, $assignee, $crm);
        $this->assignLead($secondLead, $assignee, $crm);

        $response = $this->actingAs($crm)->postJson(route('lead-assignment.calling-tasks.store'), [
            'assigned_user_id' => $assignee->id,
            'start_date' => '2026-03-28',
            'start_time' => '11:30',
            'gap_minutes' => 5,
            'lead_ids' => [$firstLead->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $firstLead->id,
            'assigned_to' => $assignee->id,
        ]);
        $this->assertDatabaseMissing('telecaller_tasks', [
            'lead_id' => $secondLead->id,
            'assigned_to' => $assignee->id,
        ]);
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

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('text');
            $table->string('category')->nullable();
            $table->string('group')->nullable();
            $table->string('display_label')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->text('validation_rules')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('company_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_type');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('dimensions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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
            $table->boolean('is_active')->default(true);
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('unassigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('meeting_id')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->default('phone_call');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->string('priority')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('outcome_recorded_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->text('recurrence_pattern')->nullable();
            $table->dateTime('recurrence_end_date')->nullable();
            $table->dateTime('rescheduled_from')->nullable();
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
            'email' => 'bulkuser' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(string $name, array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => $name,
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'meta',
        ], $attributes));
    }

    private function assignLead(Lead $lead, User $assignee, User $assignedBy): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignee->id,
            'assigned_by' => $assignedBy->id,
            'assignment_type' => 'primary',
            'is_active' => true,
            'assigned_at' => now(),
        ]);
    }
}
