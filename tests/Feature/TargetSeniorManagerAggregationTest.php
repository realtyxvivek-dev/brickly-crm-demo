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

class TargetSeniorManagerAggregationTest extends TestCase
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

    public function test_senior_manager_final_target_is_self_plus_live_team_sum(): void
    {
        $seniorManagerRole = $this->createRole(Role::SENIOR_MANAGER);
        $assistantManagerRole = $this->createRole(Role::ASSISTANT_SALES_MANAGER);
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);

        $seniorManager = $this->createUser($seniorManagerRole, [
            'name' => 'Senior Manager',
            'email' => 'senior@example.com',
        ]);
        $assistantManager = $this->createUser($assistantManagerRole, [
            'name' => 'ASM',
            'email' => 'asm@example.com',
            'manager_id' => $seniorManager->id,
        ]);
        $salesExecutive = $this->createUser($salesExecutiveRole, [
            'name' => 'Exec',
            'email' => 'exec@example.com',
            'manager_id' => $seniorManager->id,
        ]);

        $seniorTarget = Target::create([
            'user_id' => $seniorManager->id,
            'target_month' => '2026-05-01',
            'target_visits' => 1,
            'target_meetings' => 1,
            'target_closers' => 1,
            'manager_target_calculation_logic' => 'individual_plus_team',
            'manager_junior_scope' => 'executives_and_telecallers',
        ]);

        Target::create([
            'user_id' => $assistantManager->id,
            'target_month' => '2026-05-01',
            'target_visits' => 2,
            'target_meetings' => 2,
            'target_closers' => 2,
        ]);

        Target::create([
            'user_id' => $salesExecutive->id,
            'target_month' => '2026-05-01',
            'target_visits' => 2,
            'target_meetings' => 2,
            'target_closers' => 2,
        ]);

        $closers = $seniorTarget->getTargetBreakdown('closers');

        $this->assertTrue($seniorTarget->supportsTeamTargetAggregation());
        $this->assertTrue($seniorTarget->usesTeamCalculatedTargets());
        $this->assertSame(1, $closers['self']);
        $this->assertSame(4, $closers['team']);
        $this->assertSame(5, $closers['final']);

        $salesExecutiveTarget = Target::where('user_id', $salesExecutive->id)
            ->where('target_month', '2026-05-01 00:00:00')
            ->firstOrFail();
        $salesExecutiveTarget->update(['target_closers' => 3]);

        $updatedClosers = $seniorTarget->fresh()->getTargetBreakdown('closers');
        $this->assertSame(5, $updatedClosers['team']);
        $this->assertSame(6, $updatedClosers['final']);
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
