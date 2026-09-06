<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesManagerDashboardGreetingTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_shows_good_morning_for_asm_at_8_am(): void
    {
        $this->assertDashboardGreetingAt('2026-03-30 08:00:00', 'Good morning');
    }

    public function test_dashboard_shows_good_afternoon_for_asm_at_1_pm(): void
    {
        $this->assertDashboardGreetingAt('2026-03-30 13:00:00', 'Good afternoon');
    }

    public function test_dashboard_shows_good_evening_for_asm_at_6_pm(): void
    {
        $this->assertDashboardGreetingAt('2026-03-30 18:00:00', 'Good evening');
    }

    public function test_dashboard_shows_good_night_for_asm_at_10_pm(): void
    {
        $this->assertDashboardGreetingAt('2026-03-30 22:00:00', 'Good night');
    }

    private function assertDashboardGreetingAt(string $timestamp, string $expectedGreeting): void
    {
        Carbon::setTestNow(Carbon::parse($timestamp));

        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'Test ASM',
        ]);

        $response = $this->actingAs($asm)->get(route('sales-manager.dashboard'));

        $response->assertOk();
        $response->assertSeeText($expectedGreeting . ',');
        $response->assertSeeText('Test ASM');
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

        Schema::create('sales_manager_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('team_size')->nullable();
            $table->text('preferences')->nullable();
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
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'ASM User ' . $counter,
            'email' => 'asm-dashboard-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }
}
