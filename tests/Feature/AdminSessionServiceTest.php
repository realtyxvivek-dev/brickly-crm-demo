<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AdminSessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSessionServiceTest extends TestCase
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
        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_it_counts_user_sessions_and_revokes_non_admin_sessions(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);

        $admin = $this->createUser($adminRole, 'admin@example.test');
        $sales = $this->createUser($salesRole, 'sales@example.test');

        DB::table('sessions')->insert([
            [
                'id' => 'admin-current',
                'user_id' => $admin->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Chrome Windows',
                'payload' => base64_encode(serialize(['foo' => 'bar'])),
                'last_activity' => now()->getTimestamp(),
            ],
            [
                'id' => 'sales-active',
                'user_id' => $sales->id,
                'ip_address' => '10.0.0.2',
                'user_agent' => 'Chrome Android',
                'payload' => base64_encode(serialize(['foo' => 'bar'])),
                'last_activity' => now()->getTimestamp(),
            ],
        ]);

        DB::table('personal_access_tokens')->insert([
            $this->tokenRow($admin->id, 'web-session-token', 'admin-token'),
            $this->tokenRow($sales->id, 'web-session-token', 'sales-token'),
        ]);

        $service = app(AdminSessionService::class);

        $counts = $service->getUserSessionCountMap([$admin->id, $sales->id]);

        $this->assertSame(1, $counts[$admin->id]);
        $this->assertSame(1, $counts[$sales->id]);

        $deleted = $service->revokeAllNonAdminSessions($admin, 'admin-current');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-current']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sales-active']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $admin->id, 'name' => 'web-session-token']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $sales->id, 'name' => 'web-session-token']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin_non_admin_sessions_revoked',
            'model_type' => User::class,
        ]);
        $this->assertNotSame('remember-sales@example.test', $sales->fresh()->remember_token);
    }

    public function test_it_preserves_current_admin_token_when_revoking_own_other_sessions(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), 'admin-self@example.test');

        DB::table('sessions')->insert([
            [
                'id' => 'admin-current',
                'user_id' => $admin->id,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Chrome Windows',
                'payload' => base64_encode(serialize(['foo' => 'bar'])),
                'last_activity' => now()->getTimestamp(),
            ],
            [
                'id' => 'admin-old',
                'user_id' => $admin->id,
                'ip_address' => '10.0.0.9',
                'user_agent' => 'Safari iPhone',
                'payload' => base64_encode(serialize(['foo' => 'bar'])),
                'last_activity' => now()->getTimestamp(),
            ],
        ]);

        DB::table('personal_access_tokens')->insert([
            $this->tokenRow($admin->id, 'web-session-token', 'admin-token-1'),
            $this->tokenRow($admin->id, 'web-session-token', 'admin-token-2'),
        ]);

        $deleted = app(AdminSessionService::class)->revokeUserSessions($admin, $admin, 'admin-current');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-current']);
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-old']);
        $this->assertSame(2, DB::table('personal_access_tokens')->where('tokenable_id', $admin->id)->count());
        $this->assertSame('remember-admin-self@example.test', $admin->fresh()->remember_token);
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
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
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

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
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
        $user = User::create([
            'name' => strtok($email, '@'),
            'email' => $email,
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $user->forceFill([
            'remember_token' => 'remember-' . $email,
        ])->save();

        return $user->fresh();
    }

    private function tokenRow(int $userId, string $name, string $tokenSeed): array
    {
        return [
            'tokenable_type' => User::class,
            'tokenable_id' => $userId,
            'name' => $name,
            'token' => hash('sha256', $tokenSeed),
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
