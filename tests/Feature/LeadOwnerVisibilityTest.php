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

class LeadOwnerVisibilityTest extends TestCase
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

    public function test_reassigned_lead_is_hidden_from_old_sales_executive_and_visible_to_new_owner(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $executiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $oldOwner = $this->createUser($executiveRole, ['name' => 'Old Owner']);
        $newOwner = $this->createUser($executiveRole, ['name' => 'New Owner']);
        $lead = $this->createLead(['name' => 'Transferred Lead']);

        $this->createAssignment($lead->id, $oldOwner->id, $admin->id);
        $this->createProspect($lead->id, $oldOwner->id, $admin->id);

        $this->actingAs($oldOwner)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Transferred Lead');

        Event::fake([LeadAssigned::class]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/leads/{$lead->id}/assign", [
            'assigned_to' => $newOwner->id,
            'create_calling_task' => false,
            'transfer_existing_tasks' => true,
        ])->assertOk();

        $this->actingAs($oldOwner)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertDontSee('Transferred Lead');

        $this->actingAs($oldOwner)
            ->get(route('leads.show', $lead))
            ->assertForbidden();

        $this->actingAs($newOwner)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Transferred Lead');
    }

    public function test_manager_api_list_does_not_leak_transferred_lead_via_old_team_prospect_fallback(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $executiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $oldManager = $this->createUser($managerRole, ['name' => 'Old Manager', 'manager_id' => 999]);
        $teamExecutive = $this->createUser($executiveRole, ['name' => 'Team Executive', 'manager_id' => $oldManager->id]);
        $newManager = $this->createUser($managerRole, ['name' => 'New Manager', 'manager_id' => 998]);
        $lead = $this->createLead(['name' => 'Manager Transfer Lead']);

        $this->createAssignment($lead->id, $oldManager->id, $admin->id);
        $this->createProspect($lead->id, $teamExecutive->id, $oldManager->id);

        Event::fake([LeadAssigned::class]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/leads/{$lead->id}/assign", [
            'assigned_to' => $newManager->id,
            'create_calling_task' => false,
            'transfer_existing_tasks' => true,
        ])->assertOk();

        Sanctum::actingAs($oldManager);
        $this->getJson('/api/leads')
            ->assertOk()
            ->assertJsonMissing(['id' => $lead->id]);

        Sanctum::actingAs($newManager);
        $this->getJson('/api/leads')
            ->assertOk()
            ->assertJsonFragment(['id' => $lead->id]);
    }

    public function test_owner_transfer_moves_only_open_tasks_to_new_owner(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $executiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $oldOwner = $this->createUser($executiveRole, ['name' => 'Task Old Owner']);
        $newOwner = $this->createUser($executiveRole, ['name' => 'Task New Owner']);
        $lead = $this->createLead(['name' => 'Task Transfer Lead']);

        $this->createAssignment($lead->id, $oldOwner->id, $admin->id);
        $this->createProspect($lead->id, $oldOwner->id, $admin->id);

        DB::table('telecaller_tasks')->insert([
            [
                'lead_id' => $lead->id,
                'assigned_to' => $oldOwner->id,
                'task_type' => 'calling',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $lead->id,
                'assigned_to' => $oldOwner->id,
                'task_type' => 'calling',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('tasks')->insert([
            [
                'lead_id' => $lead->id,
                'assigned_to' => $oldOwner->id,
                'type' => 'phone_call',
                'title' => 'Call lead',
                'description' => 'Pending manager call',
                'status' => 'pending',
                'created_by' => $admin->id,
                'scheduled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $lead->id,
                'assigned_to' => $oldOwner->id,
                'type' => 'phone_call',
                'title' => 'Completed lead call',
                'description' => 'Completed manager call',
                'status' => 'completed',
                'created_by' => $admin->id,
                'scheduled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Event::fake([LeadAssigned::class]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/leads/{$lead->id}/assign", [
            'assigned_to' => $newOwner->id,
            'create_calling_task' => false,
            'transfer_existing_tasks' => true,
        ])->assertOk();

        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $newOwner->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $newOwner->id,
            'status' => 'pending',
            'type' => 'phone_call',
        ]);

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'status' => 'completed',
            'type' => 'phone_call',
        ]);

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $newOwner->id,
            'is_active' => 1,
        ]);
    }

    public function test_verified_prospect_fallback_still_works_when_lead_has_no_active_assignment(): void
    {
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $executiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $manager = $this->createUser($managerRole, ['name' => 'Fallback Manager', 'manager_id' => 999]);
        $teamExecutive = $this->createUser($executiveRole, ['name' => 'Fallback Executive', 'manager_id' => $manager->id]);
        $lead = $this->createLead(['name' => 'Unassigned Verified Prospect']);

        $this->createProspect($lead->id, $teamExecutive->id, $manager->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/leads')
            ->assertOk()
            ->assertJsonFragment(['id' => $lead->id]);
    }

    public function test_junk_marked_lead_is_hidden_from_manager_prospects_api(): void
    {
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $executiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $manager = $this->createUser($managerRole, ['name' => 'Prospect Manager', 'manager_id' => 999]);
        $teamExecutive = $this->createUser($executiveRole, ['name' => 'Prospect Executive', 'manager_id' => $manager->id]);
        $lead = $this->createLead([
            'name' => 'Junk Prospect Lead',
            'status' => 'junk',
        ]);

        $this->createProspect($lead->id, $teamExecutive->id, $manager->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/sales-manager/prospects')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Junk Prospect Lead'])
            ->assertJsonMissing(['customer_name' => 'Prospect Customer']);
    }

    protected function createSchema(): void
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
            $table->decimal('lead_score', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
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
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createRole(string $slug, ?string $name = null): Role
    {
        return Role::create([
            'name' => $name ?? ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    protected function createUser(Role $role, array $attributes = []): User
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

    protected function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'phone' => '9999999999',
            'source' => 'other',
            'status' => 'new',
        ], $attributes));
    }

    protected function createAssignment(int $leadId, int $assignedTo, ?int $assignedBy = null): LeadAssignment
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

    protected function createProspect(int $leadId, int $telecallerId, ?int $verifiedBy = null): void
    {
        DB::table('prospects')->insert([
            'lead_id' => $leadId,
            'telecaller_id' => $telecallerId,
            'created_by' => $telecallerId,
            'verified_by' => $verifiedBy,
            'verification_status' => 'verified',
            'customer_name' => 'Prospect Customer',
            'phone' => '8888888888',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
