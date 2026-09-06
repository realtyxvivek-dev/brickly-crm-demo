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

class CrmDashboardRecentLeadsTest extends TestCase
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

    public function test_it_returns_recent_twenty_leads_with_current_owner_status_and_outcome(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'CRM User');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Exec User');
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), 'Manager User');

        $latestLead = $this->createLead('Latest Lead', 'new', Carbon::parse('2026-03-31 11:30:00'));
        $fallbackLead = $this->createLead('Fallback Lead', 'connected', Carbon::parse('2026-03-31 11:00:00'));
        $noOutcomeLead = $this->createLead('No Outcome Lead', 'new', Carbon::parse('2026-03-31 10:30:00'));

        $this->assignLead($latestLead, $executive->id, Carbon::parse('2026-03-31 11:35:00'));
        $this->assignLead($fallbackLead, $manager->id, Carbon::parse('2026-03-31 11:05:00'));

        TelecallerTask::create([
            'lead_id' => $latestLead->id,
            'assigned_to' => $executive->id,
            'task_type' => 'calling',
            'status' => 'completed',
            'outcome' => 'interested',
            'completed_at' => Carbon::parse('2026-03-31 11:50:00'),
        ]);

        Task::create([
            'lead_id' => $fallbackLead->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'status' => 'completed',
            'outcome' => 'follow_up',
            'outcome_recorded_at' => Carbon::parse('2026-03-31 11:40:00'),
        ]);

        for ($i = 0; $i < 19; $i++) {
            $this->createLead('Older Lead ' . $i, 'new', Carbon::parse('2026-03-30 09:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00'));
        }

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/recent-leads?date_range=all_time');

        $response->assertOk();
        $response->assertJsonCount(20, 'data');
        $response->assertJsonPath('data.0.lead_name', 'Latest Lead');
        $response->assertJsonPath('data.0.owner_name', 'Exec User');
        $response->assertJsonPath('data.0.status_label', 'New');
        $response->assertJsonPath('data.0.outcome', 'interested');
        $response->assertJsonPath('data.0.outcome_label', 'Interested');
        $response->assertJsonPath('data.1.lead_name', 'Fallback Lead');
        $response->assertJsonPath('data.1.owner_name', 'Manager User');
        $response->assertJsonPath('data.1.status_label', 'Connected');
        $response->assertJsonPath('data.1.outcome', 'follow_up');
        $response->assertJsonPath('data.1.outcome_label', 'Follow Up');
        $response->assertJsonPath('data.2.lead_name', 'No Outcome Lead');
        $response->assertJsonPath('data.2.owner_name', 'Unassigned');
        $response->assertJsonPath('data.2.outcome', 'No Outcome');
        $response->assertJsonPath('data.2.outcome_label', 'No Outcome');
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
            'email' => 'crm-recent-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'manager_id' => $role->slug === Role::SALES_MANAGER ? 99 : null,
            'is_active' => true,
        ]);
    }

    private function createLead(string $name, string $status, Carbon $createdAt): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => '9999999999',
            'source' => 'meta',
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
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
