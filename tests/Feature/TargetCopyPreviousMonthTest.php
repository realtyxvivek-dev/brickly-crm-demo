<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TargetCopyPreviousMonthTest extends TestCase
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

    public function test_copy_previous_targets_skips_existing_records_when_requested(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
        $managerRole = $this->createRole(Role::SALES_MANAGER);
        $asmRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);

        $firstUser = $this->createUser($managerRole, [
            'name' => 'Manager One',
            'email' => 'manager1@example.com',
        ]);
        $secondUser = $this->createUser($asmRole, [
            'name' => 'ASM One',
            'email' => 'asm1@example.com',
        ]);

        Target::create([
            'user_id' => $firstUser->id,
            'target_month' => '2026-04-01',
            'target_visits' => 12,
            'target_meetings' => 4,
            'target_closers' => 2,
            'target_prospects_extract' => 0,
            'target_prospects_verified' => 0,
            'target_calls' => 0,
            'manager_target_calculation_logic' => 'juniors_sum',
            'manager_junior_scope' => 'executives_only',
            'incentive_per_closer' => 500,
            'incentive_per_visit' => 100,
        ]);
        Target::create([
            'user_id' => $secondUser->id,
            'target_month' => '2026-04-01',
            'target_visits' => 9,
            'target_meetings' => 3,
            'target_closers' => 1,
            'target_prospects_extract' => 0,
            'target_prospects_verified' => 0,
            'target_calls' => 0,
        ]);

        Target::create([
            'user_id' => $firstUser->id,
            'target_month' => '2026-05-01',
            'target_visits' => 99,
            'target_meetings' => 99,
            'target_closers' => 99,
            'target_prospects_extract' => 0,
            'target_prospects_verified' => 0,
            'target_calls' => 0,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.targets.copy-previous'), [
            'month' => '2026-05',
            'mode' => 'skip_existing',
        ]);

        $response->assertRedirect(route('admin.targets.index', ['month' => '2026-05']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('targets', [
            'user_id' => $firstUser->id,
            'target_month' => '2026-05-01 00:00:00',
            'target_visits' => 99,
            'target_closers' => 99,
        ]);

        $this->assertDatabaseHas('targets', [
            'user_id' => $secondUser->id,
            'target_month' => '2026-05-01 00:00:00',
            'target_visits' => 9,
            'target_meetings' => 3,
            'target_closers' => 1,
        ]);
    }

    public function test_copy_previous_targets_overwrites_existing_records_when_requested(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin',
            'email' => 'admin2@example.com',
        ]);
        $execRole = $this->createRole(Role::SALES_EXECUTIVE);
        $user = $this->createUser($execRole, [
            'name' => 'Executive',
            'email' => 'exec@example.com',
        ]);

        Target::create([
            'user_id' => $user->id,
            'target_month' => '2026-04-01',
            'target_visits' => 14,
            'target_meetings' => 6,
            'target_closers' => 4,
            'target_prospects_extract' => 22,
            'target_prospects_verified' => 18,
            'target_calls' => 80,
            'incentive_per_closer' => 700,
            'incentive_per_visit' => 90,
        ]);

        Target::create([
            'user_id' => $user->id,
            'target_month' => '2026-05-01',
            'target_visits' => 1,
            'target_meetings' => 1,
            'target_closers' => 1,
            'target_prospects_extract' => 1,
            'target_prospects_verified' => 1,
            'target_calls' => 1,
            'incentive_per_closer' => 1,
            'incentive_per_visit' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.targets.copy-previous'), [
            'month' => '2026-05',
            'mode' => 'overwrite_existing',
        ]);

        $response->assertRedirect(route('admin.targets.index', ['month' => '2026-05']));

        $this->assertDatabaseHas('targets', [
            'user_id' => $user->id,
            'target_month' => '2026-05-01 00:00:00',
            'target_visits' => 14,
            'target_meetings' => 6,
            'target_closers' => 4,
            'target_prospects_extract' => 22,
            'target_prospects_verified' => 18,
            'target_calls' => 80,
        ]);
    }

    public function test_copy_previous_targets_returns_error_when_source_month_has_no_data(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin',
            'email' => 'admin3@example.com',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.targets.copy-previous'), [
            'month' => '2026-05',
            'mode' => 'skip_existing',
        ]);

        $response->assertRedirect(route('admin.targets.index', ['month' => '2026-05']));
        $response->assertSessionHasErrors('error');
    }

    public function test_hr_manager_can_copy_telecaller_targets_from_hr_routes(): void
    {
        $hr = $this->createUser($this->createRole(Role::HR_MANAGER), [
            'name' => 'HR Manager',
            'email' => 'hr-targets@example.com',
        ]);
        $telecaller = $this->createUser($this->createRole(Role::TELECALLER), [
            'name' => 'Telecaller',
            'email' => 'telecaller-targets@example.com',
        ]);

        Target::create([
            'user_id' => $telecaller->id,
            'target_month' => '2026-04-01',
            'target_calls' => 100,
            'target_prospects_extract' => 20,
            'target_prospects_verified' => 10,
        ]);

        $response = $this->actingAs($hr)->post(route('hr-manager.targets.copy-previous'), [
            'month' => '2026-05',
            'mode' => 'skip_existing',
        ]);

        $response->assertRedirect(route('hr-manager.targets.index', ['month' => '2026-05']));
        $this->assertDatabaseHas('targets', [
            'user_id' => $telecaller->id,
            'target_month' => '2026-05-01 00:00:00',
            'target_calls' => 100,
        ]);
    }

    public function test_hr_target_index_lists_users_without_monthly_targets(): void
    {
        $hr = $this->createUser($this->createRole(Role::HR_MANAGER), [
            'name' => 'HR Manager',
            'email' => 'hr-target-list@example.com',
        ]);
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), [
            'name' => 'Missing Target Executive',
            'email' => 'missing-target@example.com',
        ]);

        $request = \Illuminate\Http\Request::create('/hr-manager/targets', 'GET', ['month' => '2026-07']);
        $request->setUserResolver(fn () => $hr);
        $view = app(\App\Http\Controllers\Admin\TargetController::class)->index($request);
        $targetRow = $view->getData()['targets']->firstWhere('user_id', $executive->id);

        $this->assertNotNull($targetRow);
        $this->assertFalse($targetRow->exists);
        $this->assertSame($executive->name, $targetRow->user->name);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->text('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('target_month');
            $table->integer('target_visits')->default(0);
            $table->integer('target_meetings')->default(0);
            $table->integer('target_closers')->default(0);
            $table->integer('target_prospects_extract')->default(0);
            $table->integer('target_prospects_verified')->default(0);
            $table->integer('target_calls')->default(0);
            $table->string('manager_target_calculation_logic')->nullable();
            $table->string('manager_junior_scope')->nullable();
            $table->decimal('incentive_per_closer', 10, 2)->nullable();
            $table->decimal('incentive_per_visit', 10, 2)->nullable();
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

    private function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'User',
            'email' => uniqid('user_', true) . '@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }
}
