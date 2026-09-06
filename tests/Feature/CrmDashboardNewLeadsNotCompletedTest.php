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

class CrmDashboardNewLeadsNotCompletedTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-03-30 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_returns_only_new_leads_with_incomplete_telecaller_tasks(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM));
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Exec User');

        $pendingLead = $this->createLead('new', 'Pending New Lead');
        $completedLead = $this->createLead('new', 'Completed New Lead');
        $notNewLead = $this->createLead('verified_prospect', 'Verified Lead');

        $this->assignLead($pendingLead, $executive->id, Carbon::parse('2026-03-30 09:00:00'));
        $this->assignLead($completedLead, $executive->id, Carbon::parse('2026-03-30 10:00:00'));
        $this->assignLead($notNewLead, $executive->id, Carbon::parse('2026-03-30 11:00:00'));

        TelecallerTask::create([
            'lead_id' => $pendingLead->id,
            'assigned_to' => $executive->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-30 09:10:00'),
        ]);

        TelecallerTask::create([
            'lead_id' => $completedLead->id,
            'assigned_to' => $executive->id,
            'task_type' => 'calling',
            'status' => 'completed',
            'scheduled_at' => Carbon::parse('2026-03-30 10:10:00'),
            'completed_at' => Carbon::parse('2026-03-30 10:30:00'),
        ]);

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/new-leads-not-completed?date_range=all_time');

        $response->assertOk();
        $response->assertJsonPath('data.0.user_name', 'Exec User');
        $response->assertJsonPath('data.0.pending_new_count', 1);
        $response->assertJsonPath('data.0.leads.0.name', 'Pending New Lead');
    }

    public function test_it_uses_manager_task_completion_for_manager_roles(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM));
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), 'Manager User', 99);

        $pendingLead = $this->createLead('new', 'Manager Pending Lead');
        $completedLead = $this->createLead('new', 'Manager Completed Lead');

        $this->assignLead($pendingLead, $manager->id, Carbon::parse('2026-03-30 09:00:00'));
        $this->assignLead($completedLead, $manager->id, Carbon::parse('2026-03-30 10:00:00'));

        Task::create([
            'lead_id' => $pendingLead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Call pending lead',
            'status' => 'rescheduled',
            'scheduled_at' => Carbon::parse('2026-03-30 09:10:00'),
        ]);

        Task::create([
            'lead_id' => $completedLead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Call completed lead',
            'status' => 'completed',
            'scheduled_at' => Carbon::parse('2026-03-30 10:10:00'),
            'completed_at' => Carbon::parse('2026-03-30 10:25:00'),
        ]);

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/new-leads-not-completed?date_range=all_time');

        $response->assertOk();
        $response->assertJsonPath('data.0.user_name', 'Manager User');
        $response->assertJsonPath('data.0.pending_new_count', 1);
        $response->assertJsonPath('data.0.leads.0.name', 'Manager Pending Lead');
    }

    public function test_it_respects_assignment_date_filter_and_excludes_inactive_assignments(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM));
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Filtered Exec');

        $todayLead = $this->createLead('new', 'Today Lead');
        $oldLead = $this->createLead('new', 'Old Lead');
        $inactiveLead = $this->createLead('new', 'Inactive Lead');

        $this->assignLead($todayLead, $executive->id, Carbon::parse('2026-03-30 09:00:00'));
        $this->assignLead($oldLead, $executive->id, Carbon::parse('2026-03-28 09:00:00'));

        LeadAssignment::create([
            'lead_id' => $inactiveLead->id,
            'assigned_to' => $executive->id,
            'assigned_at' => Carbon::parse('2026-03-30 11:00:00'),
            'is_active' => false,
        ]);

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/new-leads-not-completed?date_range=today');

        $response->assertOk();
        $response->assertJsonPath('data.0.pending_new_count', 1);
        $response->assertJsonPath('data.0.leads.0.name', 'Today Lead');
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
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
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

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('moved_to_pending_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
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
            $table->json('recurrence_pattern')->nullable();
            $table->timestamp('recurrence_end_date')->nullable();
            $table->timestamp('rescheduled_from')->nullable();
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
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, string $name = 'User', ?int $managerId = null): User
    {
        static $counter = 1;

        return User::create([
            'name' => $name,
            'email' => 'crm-pending-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'manager_id' => $managerId,
            'is_active' => true,
        ]);
    }

    private function createLead(string $status, string $name): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => '9999999999',
            'source' => 'meta',
            'status' => $status,
        ]);
    }

    private function assignLead(Lead $lead, int $userId, Carbon $assignedAt): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $userId,
            'assigned_at' => $assignedAt,
            'is_active' => true,
        ]);
    }
}
