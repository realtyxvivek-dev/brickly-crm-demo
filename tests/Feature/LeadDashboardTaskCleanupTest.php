<?php

namespace Tests\Feature;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadDashboardTaskCleanupTest extends TestCase
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
    }

    public function test_asm_dashboard_counts_drop_after_same_day_owner_transfer(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $asmRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER, 'Assistant Sales Manager');
        $oldOwner = $this->createUser($asmRole, ['name' => 'ASM Old Owner']);
        $newOwner = $this->createUser($asmRole, ['name' => 'ASM New Owner']);

        $firstLead = $this->createLead(['name' => 'First ASM Lead']);
        $secondLead = $this->createLead(['name' => 'Second ASM Lead']);

        $this->createAssignment($firstLead->id, $oldOwner->id, $admin->id);
        $this->createAssignment($secondLead->id, $oldOwner->id, $admin->id);

        Sanctum::actingAs($oldOwner);
        $this->getJson('/api/sales-manager/profile?date_filter=today')
            ->assertOk()
            ->assertJsonPath('team_stats.assigned_leads', 2)
            ->assertJsonPath('team_stats.fresh_leads_today', 2);

        Event::fake([LeadAssigned::class]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/leads/{$secondLead->id}/assign", [
            'assigned_to' => $newOwner->id,
            'create_calling_task' => false,
            'transfer_existing_tasks' => true,
        ])->assertOk();

        Sanctum::actingAs($oldOwner);
        $this->getJson('/api/sales-manager/profile?date_filter=today')
            ->assertOk()
            ->assertJsonPath('team_stats.assigned_leads', 1)
            ->assertJsonPath('team_stats.fresh_leads_today', 1);

        Sanctum::actingAs($newOwner);
        $this->getJson('/api/sales-manager/profile?date_filter=today')
            ->assertOk()
            ->assertJsonPath('team_stats.assigned_leads', 1)
            ->assertJsonPath('team_stats.fresh_leads_today', 1);
    }

    public function test_deleted_lead_tasks_are_hidden_from_manager_task_api(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER, 'Assistant Sales Manager'), [
            'name' => 'Task Owner ASM',
        ]);
        $lead = $this->createLead(['name' => 'Deleted Task Lead']);

        $this->createAssignment($lead->id, $asm->id, $crm->id);

        DB::table('tasks')->insert([
            'lead_id' => $lead->id,
            'assigned_to' => $asm->id,
            'type' => 'phone_call',
            'title' => 'Call deleted lead',
            'description' => 'Pending manager call',
            'status' => 'pending',
            'notes' => 'Open task',
            'created_by' => $crm->id,
            'scheduled_at' => now()->subMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('telecaller_tasks')->insert([
            'lead_id' => $lead->id,
            'assigned_to' => $asm->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($asm);
        $this->getJson('/api/sales-manager/tasks')
            ->assertOk()
            ->assertJsonFragment(['lead_id' => $lead->id]);

        $this->actingAs($crm)
            ->delete(route('leads.destroy', $lead))
            ->assertRedirect(route('leads.index'));

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
        $this->assertSoftDeleted('tasks', ['lead_id' => $lead->id, 'assigned_to' => $asm->id]);
        $this->assertSoftDeleted('telecaller_tasks', ['lead_id' => $lead->id, 'assigned_to' => $asm->id]);

        Sanctum::actingAs($asm);
        $this->getJson('/api/sales-manager/tasks')
            ->assertOk()
            ->assertJsonMissing(['lead_id' => $lead->id]);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamps();
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
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->unsignedBigInteger('other_lead_marked_by')->nullable();
            $table->string('other_lead_reason')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamp('last_contacted_at')->nullable();
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

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lead_id');
            $table->timestamps();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('assigned_manager')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('manager_remark')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->decimal('lead_score', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('is_absent')->default(false);
            $table->string('absent_reason')->nullable();
            $table->timestamp('lead_off_start_at')->nullable();
            $table->timestamp('lead_off_end_at')->nullable();
            $table->string('lead_off_source')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamps();
        });

        Schema::create('sales_manager_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('team_size')->nullable();
            $table->text('preferences')->nullable();
            $table->timestamps();
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
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->nullable();
            $table->string('closer_status')->nullable();
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
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createRole(string $slug, ?string $name = null): Role
    {
        return Role::create([
            'name' => $name ?? ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $attributes['name'] ?? ucfirst($role->slug) . ' User',
            'email' => $attributes['email'] ?? uniqid($role->slug . '-', true) . '@example.com',
            'password' => bcrypt('Password123!'),
            'phone' => $attributes['phone'] ?? '9876543210',
            'role_id' => $role->id,
            'manager_id' => $attributes['manager_id'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ], $attributes));
    }

    private function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'phone' => '9999999999',
            'source' => 'other',
            'status' => 'new',
        ], $attributes));
    }

    private function createAssignment(int $leadId, int $assignedTo, ?int $assignedBy = null): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $leadId,
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
        ]);
    }
}
