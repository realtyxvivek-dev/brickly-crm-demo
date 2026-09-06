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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualTaskSchedulingGuardTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-03-31 11:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manual_schedule_is_blocked_when_same_lead_has_open_manager_task(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-manual-task@example.test']);
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), ['email' => 'manager-manual-task@example.test']);
        $lead = $this->createLead(['name' => 'Manager Lead']);
        $this->assignLead($lead, $manager, $crm);

        $existingTask = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Existing call task',
            'status' => 'pending',
            'scheduled_at' => Carbon::now()->addMinutes(15),
            'created_by' => $crm->id,
        ]);

        Sanctum::actingAs($crm);

        $this->postJson('/api/sales-manager/tasks/schedule-call', [
            'lead_id' => $lead->id,
            'scheduled_at' => Carbon::now()->addHour()->toIso8601String(),
            'notes' => 'Need a new manual task',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete old task first.',
                'existing_task' => [
                    'id' => $existingTask->id,
                    'status' => 'pending',
                    'model_type' => 'task',
                ],
            ]);

        $this->assertSame(1, Task::count());
    }

    public function test_manual_schedule_is_blocked_when_same_lead_has_open_task_for_another_user(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-cross-user-guard@example.test']);
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), ['email' => 'manager-cross-user-guard@example.test']);
        $otherManager = $this->createUser($this->createRole(Role::SALES_MANAGER), ['email' => 'other-manager-cross-user-guard@example.test']);
        $lead = $this->createLead(['name' => 'Cross User Lead']);
        $this->assignLead($lead, $manager, $crm);

        $existingTask = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $otherManager->id,
            'type' => 'phone_call',
            'title' => 'Another user task',
            'status' => 'pending',
            'scheduled_at' => Carbon::now()->addMinutes(15),
            'created_by' => $crm->id,
        ]);

        Sanctum::actingAs($crm);

        $this->postJson('/api/sales-manager/tasks/schedule-call', [
            'lead_id' => $lead->id,
            'scheduled_at' => Carbon::now()->addHour()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete old task first.',
                'existing_task' => [
                    'id' => $existingTask->id,
                    'status' => 'pending',
                    'model_type' => 'task',
                    'assigned_to' => $otherManager->id,
                ],
            ]);
    }

    public function test_manual_schedule_is_blocked_when_same_lead_has_open_telecaller_task(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-telecaller-guard@example.test']);
        $telecaller = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'telecaller-guard@example.test']);
        $lead = $this->createLead(['name' => 'Telecaller Lead']);
        $this->assignLead($lead, $telecaller, $crm);

        $existingTask = TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $telecaller->id,
            'task_type' => 'calling',
            'status' => 'rescheduled',
            'scheduled_at' => Carbon::now()->addMinutes(20),
            'created_by' => $crm->id,
        ]);

        Sanctum::actingAs($crm);

        $this->postJson('/api/sales-manager/tasks/schedule-call', [
            'lead_id' => $lead->id,
            'scheduled_at' => Carbon::now()->addHour()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete old task first.',
                'existing_task' => [
                    'id' => $existingTask->id,
                    'status' => 'rescheduled',
                    'model_type' => 'telecaller_task',
                ],
            ]);

        $this->assertSame(1, TelecallerTask::count());
    }

    public function test_manual_schedule_is_allowed_when_previous_task_is_completed(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-completed-task@example.test']);
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), ['email' => 'manager-completed-task@example.test']);
        $lead = $this->createLead(['name' => 'Completed Task Lead']);
        $this->assignLead($lead, $manager, $crm);

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Completed call task',
            'status' => 'completed',
            'scheduled_at' => Carbon::now()->subHour(),
            'completed_at' => Carbon::now()->subMinutes(5),
            'created_by' => $crm->id,
        ]);

        Sanctum::actingAs($crm);

        $this->postJson('/api/sales-manager/tasks/schedule-call', [
            'lead_id' => $lead->id,
            'scheduled_at' => Carbon::now()->addHour()->toIso8601String(),
            'notes' => 'This one should be created',
        ])
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Call task scheduled successfully',
            ]);

        $this->assertSame(2, Task::count());
        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'status' => 'pending',
            'notes' => 'This one should be created',
        ]);
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

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
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
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->text('recurrence_pattern')->nullable();
            $table->timestamp('recurrence_end_date')->nullable();
            $table->timestamp('rescheduled_from')->nullable();
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
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('moved_to_pending_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->string('verification_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate([
            'slug' => $slug,
        ], [
            'name' => ucwords(str_replace('_', ' ', $slug)),
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $attributes['name'] ?? $role->name . ' User',
            'email' => $attributes['email'] ?? uniqid($role->slug . '-', true) . '@example.test',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Guard Test Lead',
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'manual',
        ], $attributes));
    }

    private function assignLead(Lead $lead, User $assignedTo, User $assignedBy): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo->id,
            'assigned_by' => $assignedBy->id,
            'assigned_at' => Carbon::now(),
            'is_active' => true,
        ]);
    }
}
