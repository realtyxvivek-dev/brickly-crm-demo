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

class CrmDashboardPreviousOverPodTest extends TestCase
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

    public function test_it_returns_only_previous_open_telecaller_tasks_before_today(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'CRM User');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Exec User');

        $previousLead = $this->createLead('new', 'Previous Pending Lead');
        $todayLead = $this->createLead('new', 'Today Pending Lead');
        $completedLead = $this->createLead('new', 'Completed Lead');

        $this->assignLead($previousLead, $executive->id, Carbon::parse('2026-03-29 09:00:00'));
        $this->assignLead($todayLead, $executive->id, Carbon::parse('2026-03-30 09:00:00'));
        $this->assignLead($completedLead, $executive->id, Carbon::parse('2026-03-28 09:00:00'));

        TelecallerTask::create([
            'lead_id' => $previousLead->id,
            'assigned_to' => $executive->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-29 09:15:00'),
        ]);

        TelecallerTask::create([
            'lead_id' => $todayLead->id,
            'assigned_to' => $executive->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-30 09:15:00'),
        ]);

        TelecallerTask::create([
            'lead_id' => $completedLead->id,
            'assigned_to' => $executive->id,
            'task_type' => 'calling',
            'status' => 'completed',
            'scheduled_at' => Carbon::parse('2026-03-28 09:15:00'),
            'completed_at' => Carbon::parse('2026-03-28 09:35:00'),
        ]);

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/previous-over-pod');

        $response->assertOk();
        $response->assertJsonPath('data.0.user_name', 'Exec User');
        $response->assertJsonPath('data.0.previous_over_pod_count', 1);
        $response->assertJsonPath('data.0.items.0.name', 'Previous Pending Lead');
        $response->assertJsonPath('data.0.oldest_pending_at', '2026-03-29T09:15:00+05:30');
    }

    public function test_it_uses_manager_phone_call_tasks_for_manager_roles(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'CRM User');
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), 'Manager User', 99);

        $pendingLead = $this->createLead('new', 'Manager Previous Pending');
        $completedLead = $this->createLead('new', 'Manager Completed');
        $todayLead = $this->createLead('new', 'Manager Today Pending');

        $this->assignLead($pendingLead, $manager->id, Carbon::parse('2026-03-29 09:00:00'));
        $this->assignLead($completedLead, $manager->id, Carbon::parse('2026-03-28 09:00:00'));
        $this->assignLead($todayLead, $manager->id, Carbon::parse('2026-03-30 09:00:00'));

        Task::create([
            'lead_id' => $pendingLead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Pending manager call',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-29 10:00:00'),
        ]);

        Task::create([
            'lead_id' => $todayLead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Today manager call',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-30 10:00:00'),
        ]);

        Task::create([
            'lead_id' => $completedLead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Completed manager call',
            'status' => 'completed',
            'scheduled_at' => Carbon::parse('2026-03-28 10:00:00'),
            'completed_at' => Carbon::parse('2026-03-28 10:25:00'),
        ]);

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/previous-over-pod');

        $response->assertOk();
        $response->assertJsonPath('data.0.user_name', 'Manager User');
        $response->assertJsonPath('data.0.previous_over_pod_count', 1);
        $response->assertJsonPath('data.0.items.0.name', 'Manager Previous Pending');
    }

    public function test_it_sorts_by_count_then_oldest_pending_then_name(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'CRM User');
        $alpha = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Alpha User');
        $beta = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Beta User');

        $alphaLeadOne = $this->createLead('new', 'Alpha Lead One');
        $alphaLeadTwo = $this->createLead('new', 'Alpha Lead Two');
        $betaLeadOne = $this->createLead('new', 'Beta Lead One');
        $betaLeadTwo = $this->createLead('new', 'Beta Lead Two');

        $this->assignLead($alphaLeadOne, $alpha->id, Carbon::parse('2026-03-29 09:00:00'));
        $this->assignLead($alphaLeadTwo, $alpha->id, Carbon::parse('2026-03-29 10:00:00'));
        $this->assignLead($betaLeadOne, $beta->id, Carbon::parse('2026-03-27 09:00:00'));
        $this->assignLead($betaLeadTwo, $beta->id, Carbon::parse('2026-03-27 10:00:00'));

        TelecallerTask::create([
            'lead_id' => $alphaLeadOne->id,
            'assigned_to' => $alpha->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-29 09:30:00'),
        ]);
        TelecallerTask::create([
            'lead_id' => $alphaLeadTwo->id,
            'assigned_to' => $alpha->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-29 10:30:00'),
        ]);
        TelecallerTask::create([
            'lead_id' => $betaLeadOne->id,
            'assigned_to' => $beta->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-27 09:30:00'),
        ]);
        TelecallerTask::create([
            'lead_id' => $betaLeadTwo->id,
            'assigned_to' => $beta->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-03-27 10:30:00'),
        ]);

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/previous-over-pod');

        $response->assertOk();
        $response->assertJsonPath('data.0.user_name', 'Beta User');
        $response->assertJsonPath('data.1.user_name', 'Alpha User');
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

        Schema::create('crm_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->string('call_status')->default('pending');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_metric_resets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('metric_key');
            $table->timestamp('reset_at');
            $table->unsignedBigInteger('reset_by')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], [
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, string $name, ?int $managerId = null): User
    {
        static $counter = 1;

        return User::create([
            'name' => $name,
            'email' => 'crm-pod-' . $counter++ . '@example.test',
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
            'status' => $status,
            'phone' => '9999999999',
            'email' => strtolower(str_replace(' ', '-', $name)) . '@example.test',
        ]);
    }

    private function assignLead(Lead $lead, int $assignedTo, Carbon $assignedAt): void
    {
        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo,
            'assigned_at' => $assignedAt,
            'is_active' => true,
        ]);
    }
}
