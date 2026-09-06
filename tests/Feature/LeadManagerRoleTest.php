<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadManagerRoleTest extends TestCase
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

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken()->nullable();
            $table->json('ui_preferences')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_lead_manager_has_lead_bank_queue_capability_and_email_otp_default(): void
    {
        $leadManagerRole = Role::create([
            'name' => 'Lead Manager',
            'slug' => Role::LEAD_MANAGER,
            'permissions' => [
                'lead_bank.view',
                'lead_bank.import',
                'lead_bank.manage_tags',
                'lead_bank.approve_requests',
                'execution_desk.view',
                'execution_desk.team_all',
            ],
        ]);

        $user = User::create([
            'name' => 'Lead Manager',
            'email' => 'lead-manager@example.test',
            'password' => bcrypt('password'),
            'role_id' => $leadManagerRole->id,
        ])->load('role');

        $this->assertTrue($user->isLeadManager());
        $this->assertTrue($user->canManageLeadBankQueue());
        $this->assertTrue($user->hasRolePermission('lead_bank.approve_requests'));
        $this->assertSame(User::TWO_FACTOR_EMAIL, User::defaultTwoFactorModeForRoleSlug(Role::LEAD_MANAGER));
    }

    public function test_sales_manager_remains_request_only_for_lead_bank_queue(): void
    {
        $salesManagerRole = Role::create([
            'name' => 'Sales Manager',
            'slug' => Role::SALES_MANAGER,
        ]);

        $user = User::create([
            'name' => 'Sales Manager',
            'email' => 'sales-manager@example.test',
            'password' => bcrypt('password'),
            'role_id' => $salesManagerRole->id,
        ])->load('role');

        $this->assertFalse($user->canManageLeadBankQueue());
    }
}
