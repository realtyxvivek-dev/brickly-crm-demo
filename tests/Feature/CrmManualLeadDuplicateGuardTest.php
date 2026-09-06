<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmManualLeadDuplicateGuardTest extends TestCase
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

    public function test_crm_manual_create_blocks_duplicate_phone_and_flashes_existing_lead_link(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-duplicate@example.test',
        ]);

        $existingLead = Lead::create([
            'name' => 'Existing Lead',
            'phone' => '919876543210',
            'status' => 'new',
            'source' => 'other',
            'created_by' => $crm->id,
        ]);

        $response = $this->actingAs($crm)->from(route('crm.automation.leads.create'))->post(route('crm.automation.leads.store'), [
            'name' => 'Duplicate Lead',
            'phone' => '+91 98765 43210',
            'source' => 'other',
        ]);

        $response->assertRedirect(route('crm.automation.leads.create'));
        $response->assertSessionHasErrors(['phone']);
        $response->assertSessionHas('duplicate_lead', function (array $payload) use ($existingLead) {
            return ($payload['duplicate'] ?? false) === true
                && (int) ($payload['existing_lead_id'] ?? 0) === $existingLead->id
                && ($payload['existing_lead_url'] ?? null) === route('leads.show', $existingLead);
        });

        $this->assertDatabaseCount('leads', 1);
    }

    public function test_crm_manual_create_allows_fresh_phone(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-fresh@example.test',
        ]);

        $response = $this->actingAs($crm)->post(route('crm.automation.leads.store'), [
            'name' => 'Fresh Lead',
            'phone' => '9876543210',
            'source' => 'other',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'name' => 'Fresh Lead',
            'phone' => '919876543210',
            'source' => 'other',
        ]);
    }

    public function test_crm_duplicate_check_endpoint_returns_existing_lead_metadata(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-check@example.test',
        ]);

        $existingLead = Lead::create([
            'name' => 'Existing Lead',
            'phone' => '919876543210',
            'status' => 'new',
            'source' => 'other',
            'created_by' => $crm->id,
        ]);

        $this->actingAs($crm)
            ->get(route('crm.automation.leads.check-duplicate', ['phone' => '98765-43210']))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'duplicate' => true,
                'existing_lead_id' => $existingLead->id,
                'existing_lead_url' => route('leads.show', $existingLead),
                'normalized_phone' => '919876543210',
            ]);
    }

    public function test_crm_duplicate_check_endpoint_returns_clear_for_new_phone(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'email' => 'crm-check-clear@example.test',
        ]);

        $this->actingAs($crm)
            ->get(route('crm.automation.leads.check-duplicate', ['phone' => '9123456789']))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'duplicate' => false,
                'normalized_phone' => '919123456789',
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
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('normalized_phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('budget')->nullable();
            $table->string('property_type')->nullable();
            $table->string('use_end_use')->nullable();
            $table->string('possession_status')->nullable();
            $table->text('preferred_projects')->nullable();
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
            $table->unsignedBigInteger('assigned_by');
            $table->string('assignment_type')->default('primary');
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('unassigned_at')->nullable();
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

        Schema::create('blacklisted_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
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
            'role_id' => $role->id,
            'name' => 'Test User',
            'email' => uniqid('user_', true) . '@example.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ], $attributes));
    }
}
