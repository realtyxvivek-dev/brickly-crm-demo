<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesManagerDashboardClearCacheTest extends TestCase
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

    public function test_assistant_sales_manager_can_clear_dashboard_cache(): void
    {
        $user = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), 'ASM User');

        Sanctum::actingAs($user);

        Artisan::shouldReceive('call')
            ->once()
            ->with('optimize:clear');

        $response = $this->postJson('/api/sales-manager/dashboard/clear-cache');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Dashboard cache cleared successfully.',
        ]);
    }

    public function test_senior_manager_can_clear_dashboard_cache(): void
    {
        $user = $this->createUser($this->createRole(Role::SENIOR_MANAGER), 'Senior Manager');

        Sanctum::actingAs($user);

        Artisan::shouldReceive('call')
            ->once()
            ->with('optimize:clear');

        $response = $this->postJson('/api/sales-manager/dashboard/clear-cache');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Dashboard cache cleared successfully.',
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
            'email' => 'sm-cache-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'manager_id' => null,
            'is_active' => true,
        ]);
    }
}
