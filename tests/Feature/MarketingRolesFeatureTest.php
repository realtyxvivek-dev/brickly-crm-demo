<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketingRolesFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('execution_saved_views');
        Schema::dropIfExists('execution_task_checklists');
        Schema::dropIfExists('execution_task_attachments');
        Schema::dropIfExists('execution_task_activities');
        Schema::dropIfExists('execution_tasks');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('company_files');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        parent::tearDown();
    }

    public function test_role_seeder_creates_marketing_roles(): void
    {
        (new RoleSeeder())->run();

        $this->assertDatabaseHas('roles', ['slug' => Role::MARKETING_MANAGER]);
        $this->assertDatabaseHas('roles', ['slug' => Role::MARKETING_EXECUTIVE]);
    }

    public function test_marketing_manager_nav_and_dashboard_render_correct_links(): void
    {
        $managerRole = $this->createRole(Role::MARKETING_MANAGER, 'Marketing Manager');
        $manager = $this->createUser($managerRole, 'manager@example.test');

        $response = $this->actingAs($manager)->get('/marketing/dashboard');

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Execution Desk')
            ->assertSee('Profile')
            ->assertDontSee('Support')
            ->assertSee('Marketing Manager');
    }

    public function test_marketing_executive_nav_and_profile_render_correct_links(): void
    {
        $role = $this->createRole(Role::MARKETING_EXECUTIVE, 'Marketing Executive');
        $user = $this->createUser($role, 'exec@example.test');

        $response = $this->actingAs($user)->get('/marketing/profile');

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Execution Desk')
            ->assertSee('Profile')
            ->assertDontSee('Support')
            ->assertSee('Marketing Executive');
    }

    public function test_marketing_manager_can_access_team_all_but_executive_cannot(): void
    {
        $managerRole = $this->createRole(Role::MARKETING_MANAGER, 'Marketing Manager');
        $executiveRole = $this->createRole(Role::MARKETING_EXECUTIVE, 'Marketing Executive');

        $manager = $this->createUser($managerRole, 'manager2@example.test');
        $executive = $this->createUser($executiveRole, 'exec2@example.test');

        $managerResponse = $this->actingAs($manager)->get('/execution-desk?tab=team_all');
        $managerResponse->assertOk()->assertSee('Team / All');

        $executiveResponse = $this->actingAs($executive)->get('/execution-desk?tab=team_all');
        $executiveResponse->assertOk()->assertDontSee('Team / All');
    }

    public function test_execution_desk_exposes_marketing_context(): void
    {
        $managerRole = $this->createRole(Role::MARKETING_MANAGER, 'Marketing Manager');
        $manager = $this->createUser($managerRole, 'manager3@example.test');

        $response = $this->actingAs($manager)->get('/execution-desk/create');

        $response->assertOk()->assertSee('Marketing');
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
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
            $table->foreignId('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('two_factor_mode')->nullable();
            $table->boolean('two_factor_enforced_by_admin')->default(false);
            $table->boolean('otp_recovery_allowed')->default(false);
            $table->rememberToken()->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('execution_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('assigned_by');
            $table->unsignedBigInteger('assigned_to');
            $table->string('priority');
            $table->string('status');
            $table->dateTime('due_at')->nullable();
            $table->unsignedInteger('estimated_time_minutes')->nullable();
            $table->unsignedInteger('actual_time_minutes')->nullable();
            $table->text('waiting_reason')->nullable();
            $table->unsignedBigInteger('waiting_on_user')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->string('context_label');
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('trigger_type')->default('manual');
            $table->boolean('is_private')->default(false);
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('execution_task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('execution_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
        });

        Schema::create('execution_task_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('execution_saved_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('scope_tab')->default('my_queue');
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->nullable();
            $table->string('category')->nullable();
            $table->string('group')->nullable();
            $table->string('display_label')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->text('help_text')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('company_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_type')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function createRole(string $slug, string $name): Role
    {
        return Role::create([
            'slug' => $slug,
            'name' => $name,
        ]);
    }

    private function createUser(Role $role, string $email): User
    {
        return User::create([
            'name' => strtok($email, '@'),
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ])->load('role');
    }
}
