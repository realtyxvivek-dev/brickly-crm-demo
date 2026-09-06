<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActiveLeadCountAfterDeleteTest extends TestCase
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

    public function test_user_listing_count_source_excludes_soft_deleted_leads_from_active_leads_count(): void
    {
        $this->createUser($this->createRole(Role::ADMIN), 'admin-active-count@example.test');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'exec-active-count@example.test');
        $lead = $this->createLead();

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $executive->id,
            'assigned_by' => 1,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $lead->delete();

        $listedUser = User::query()
            ->withCount(['activeAssignedLeads as active_assigned_leads_count'])
            ->find($executive->id);

        $this->assertNotNull($listedUser);
        $this->assertSame(0, (int) $listedUser->active_assigned_leads_count);
    }

    public function test_user_delete_is_allowed_when_only_stale_assignment_points_to_deleted_lead(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), 'admin-delete-guard@example.test');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'exec-delete-guard@example.test');
        $lead = $this->createLead();

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $executive->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $lead->delete();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $executive));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $executive->id]);
    }

    public function test_lead_delete_deactivates_active_assignments(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-lead-delete@example.test');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'exec-lead-delete@example.test');
        $lead = $this->createLead();

        $assignment = LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $executive->id,
            'assigned_by' => $crm->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($crm)->delete(route('leads.destroy', $lead));

        $response->assertRedirect(route('leads.index'));
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
        $this->assertDatabaseHas('lead_assignments', [
            'id' => $assignment->id,
            'is_active' => false,
        ]);
        $this->assertNotNull(LeadAssignment::find($assignment->id)?->unassigned_at);
    }

    public function test_user_deletion_transfer_service_ignores_deleted_leads_in_active_ids(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), 'admin-transfer-service@example.test');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'exec-transfer-service@example.test');
        $lead = $this->createLead();

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $executive->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $lead->delete();

        $activeLeadIds = app(\App\Services\UserDeletionTransferService::class)->getActiveLeadIds($executive);

        $this->assertSame([], $activeLeadIds);
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
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
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
            $table->string('assignment_type')->nullable();
            $table->string('assignment_method')->nullable();
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
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
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
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate([
            'slug' => $slug,
        ], [
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, string $email): User
    {
        return User::create([
            'name' => strtok($email, '@'),
            'email' => $email,
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(): Lead
    {
        return Lead::create([
            'name' => 'Deleted Lead',
            'phone' => '9999999999',
            'source' => 'meta',
            'status' => 'new',
        ]);
    }
}
