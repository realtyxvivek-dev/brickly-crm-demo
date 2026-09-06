<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemSettings;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserManagerSelectionTest extends TestCase
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

        SystemSettings::set('send_welcome_email_to_new_user', '0');
        SystemSettings::set('notify_admin_on_new_user', '0');
    }

    public function test_create_form_shows_all_higher_manager_options_for_asm(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $crmRole = $this->createRole(Role::CRM, 'CRM');
        $salesManagerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $seniorManagerRole = $this->createRole(Role::SENIOR_MANAGER, 'Senior Manager');
        $assistantRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER, 'Assistant Sales Manager');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $crm = $this->createUser($crmRole, ['name' => 'CRM User']);
        $salesHead = $this->createUser($salesManagerRole, ['name' => 'Sales Head User', 'manager_id' => null]);
        $salesManager = $this->createUser($salesManagerRole, ['name' => 'Sales Manager User', 'manager_id' => $admin->id]);
        $seniorManager = $this->createUser($seniorManagerRole, ['name' => 'Senior Manager User', 'manager_id' => $salesManager->id]);
        $assistantManager = $this->createUser($assistantRole, ['name' => 'Assistant Manager User', 'manager_id' => $seniorManager->id]);

        $response = $this->actingAs($admin)->get(route('users.create'));

        $response->assertOk();
        $response->assertSee('Admin User');
        $response->assertSee('CRM User');
        $response->assertSee('Sales Head User');
        $response->assertSee('Associate Director');
        $response->assertSee('Sales Manager User');
        $response->assertSee('Senior Manager User');
        $response->assertSee('Assistant Manager User');
        $response->assertSee('Select any higher manager from Senior Manager up to Admin.');
    }

    public function test_store_rejects_assistant_sales_manager_with_lower_role_manager(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $assistantRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER, 'Assistant Sales Manager');
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $invalidManager = $this->createUser($salesExecutiveRole, ['name' => 'Executive Manager']);

        $response = $this->actingAs($admin)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'New ASM',
            'email' => 'new-asm@example.com',
            'password' => 'Password123!',
            'phone' => '9999999999',
            'role_id' => $assistantRole->id,
            'manager_id' => $invalidManager->id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('users.create'));
        $response->assertSessionHasErrors([
            'manager_id' => 'Assistant Sales Manager can only report to Senior Manager, Sales Manager, Sales Head, CRM, or Admin.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'new-asm@example.com']);
    }

    public function test_store_and_update_accept_higher_roles_for_assistant_sales_manager(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $crmRole = $this->createRole(Role::CRM, 'CRM');
        $salesManagerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $seniorManagerRole = $this->createRole(Role::SENIOR_MANAGER, 'Senior Manager');
        $assistantRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER, 'Assistant Sales Manager');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $crm = $this->createUser($crmRole, ['name' => 'CRM User']);
        $salesHead = $this->createUser($salesManagerRole, ['name' => 'Sales Head User', 'manager_id' => null]);
        $salesManager = $this->createUser($salesManagerRole, ['name' => 'Sales Manager User', 'manager_id' => $admin->id]);
        $seniorManager = $this->createUser($seniorManagerRole, ['name' => 'Senior Manager User', 'manager_id' => $salesManager->id]);

        $storeResponse = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Valid ASM',
            'email' => 'valid-asm@example.com',
            'password' => 'Password123!',
            'phone' => '8888888888',
            'role_id' => $assistantRole->id,
            'manager_id' => $crm->id,
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect(route('users.index'));

        $createdAsm = User::where('email', 'valid-asm@example.com')->firstOrFail();
        $this->assertSame($crm->id, $createdAsm->manager_id);

        $updateResponse = $this->actingAs($admin)->put(route('users.update', $createdAsm), [
            'name' => 'Valid ASM',
            'email' => 'valid-asm@example.com',
            'phone' => '8888888888',
            'role_id' => $assistantRole->id,
            'manager_id' => $salesHead->id,
            'is_active' => '1',
        ]);

        $updateResponse->assertRedirect(route('users.show', $createdAsm));
        $this->assertDatabaseHas('users', [
            'id' => $createdAsm->id,
            'manager_id' => $salesHead->id,
        ]);

        $createdAsm->refresh();
        $this->assertTrue($createdAsm->manager->isSalesHead());
    }

    public function test_index_search_ignores_empty_role_filter(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $aman = $this->createUser($salesExecutiveRole, [
            'name' => 'Aman Sharma',
            'email' => 'aman@example.com',
            'phone' => '9998887777',
        ]);

        $response = $this->actingAs($admin)->get(route('users.index', [
            'search' => 'aman',
            'role' => '',
            'login_method' => '',
        ]));

        $response->assertOk();
        $response->assertSee($aman->name);
        $response->assertSee($aman->email);
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
            $table->string('two_factor_mode')->nullable();
            $table->boolean('two_factor_enforced_by_admin')->default(false);
            $table->boolean('otp_recovery_allowed')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('employee_code')->nullable();
            $table->string('employment_status')->default('active');
            $table->timestamps();
        });

        Schema::create('employee_exit_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_profile_id');
            $table->string('status')->default('on_notice');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
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

        Schema::create('desktop_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
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
}
