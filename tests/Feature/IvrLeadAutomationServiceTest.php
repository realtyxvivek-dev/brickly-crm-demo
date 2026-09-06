<?php

namespace Tests\Feature;

use App\Models\IvrLeadAutomationConfig;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\IvrLeadAutomationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IvrLeadAutomationServiceTest extends TestCase
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

    public function test_route_lead_defaults_to_receiver_when_no_config_exists(): void
    {
        $receiver = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Receiver Admin']);
        $lead = Lead::create(['name' => 'IVR Lead', 'phone' => '9999999999', 'source' => 'ivr', 'status' => 'new']);

        $result = app(IvrLeadAutomationService::class)->routeLead($lead, $receiver);

        $this->assertSame($receiver->id, $result['assigned_user']?->id);
        $this->assertSame('hard_default', $result['rule_source']);
        $this->assertFalse($result['fallback_used']);
        $this->assertDatabaseHas('ivr_lead_automation_audits', [
            'lead_id' => $lead->id,
            'receiver_user_id' => $receiver->id,
            'assigned_user_id' => $receiver->id,
            'rule_source' => 'hard_default',
        ]);
    }

    public function test_receiver_override_can_route_to_shared_pool_by_round_robin(): void
    {
        $receiver = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Admin Receiver']);
        $managerA = $this->createUser($this->createRole(Role::SALES_MANAGER), ['name' => 'Pool A']);
        $managerB = $this->createUser($this->createRole(Role::SENIOR_MANAGER), ['name' => 'Pool B']);
        $leadOne = Lead::create(['name' => 'Lead 1', 'phone' => '9999991111', 'source' => 'ivr', 'status' => 'new']);
        $leadTwo = Lead::create(['name' => 'Lead 2', 'phone' => '9999992222', 'source' => 'ivr', 'status' => 'new']);

        $config = IvrLeadAutomationConfig::create([
            'is_enabled' => true,
            'default_mode' => IvrLeadAutomationConfig::MODE_RECEIVER,
            'distribution_method' => IvrLeadAutomationConfig::METHOD_RECEIVER,
            'fallback_mode' => IvrLeadAutomationConfig::FALLBACK_RECEIVER,
            'receiver_assignment_allowed' => true,
        ]);

        $config->poolUsers()->createMany([
            ['user_id' => $managerA->id, 'allocation_percentage' => 50, 'sort_order' => 0, 'is_active' => true],
            ['user_id' => $managerB->id, 'allocation_percentage' => 50, 'sort_order' => 1, 'is_active' => true],
        ]);

        $config->receiverOverrides()->create([
            'user_id' => $receiver->id,
            'is_enabled' => true,
            'can_assign_to_self' => false,
            'target_type' => 'pool',
            'distribution_method' => IvrLeadAutomationConfig::METHOD_ROUND_ROBIN,
        ]);

        $service = app(IvrLeadAutomationService::class);

        $first = $service->routeLead($leadOne, $receiver);
        $second = $service->routeLead($leadTwo, $receiver);

        $this->assertSame($managerA->id, $first['assigned_user']?->id);
        $this->assertSame($managerB->id, $second['assigned_user']?->id);
        $this->assertSame('receiver_override', $first['rule_source']);
        $this->assertSame('receiver_override', $second['rule_source']);
    }

    public function test_fallback_to_backup_user_when_pool_has_no_eligible_user(): void
    {
        $receiver = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Receiver']);
        $backup = $this->createUser($this->createRole(Role::SALES_MANAGER), ['name' => 'Backup User']);
        $inactivePoolUser = $this->createUser($this->createRole(Role::SALES_MANAGER), [
            'name' => 'Inactive Pool User',
            'is_active' => false,
        ]);
        $lead = Lead::create(['name' => 'Fallback Lead', 'phone' => '9999993333', 'source' => 'ivr', 'status' => 'new']);

        $config = IvrLeadAutomationConfig::create([
            'is_enabled' => true,
            'default_mode' => IvrLeadAutomationConfig::MODE_CUSTOM_POOL,
            'distribution_method' => IvrLeadAutomationConfig::METHOD_ROUND_ROBIN,
            'fallback_mode' => IvrLeadAutomationConfig::FALLBACK_BACKUP_USER,
            'fallback_user_id' => $backup->id,
            'receiver_assignment_allowed' => false,
        ]);

        $config->poolUsers()->create([
            'user_id' => $inactivePoolUser->id,
            'allocation_percentage' => 100,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $result = app(IvrLeadAutomationService::class)->routeLead($lead, $receiver);

        $this->assertSame($backup->id, $result['assigned_user']?->id);
        $this->assertTrue($result['fallback_used']);
        $this->assertSame(IvrLeadAutomationConfig::FALLBACK_BACKUP_USER, $result['fallback_mode']);
        $this->assertDatabaseHas('ivr_lead_automation_audits', [
            'lead_id' => $lead->id,
            'assigned_user_id' => $backup->id,
            'fallback_used' => 1,
            'fallback_mode' => IvrLeadAutomationConfig::FALLBACK_BACKUP_USER,
        ]);
    }

    public function test_receiver_lead_off_keeps_ivr_lead_unassigned(): void
    {
        $receiver = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['name' => 'Lead Off Receiver']);
        $receiver->userProfile()->create([
            'is_absent' => true,
            'lead_off_start_at' => now()->subMinute(),
            'lead_off_end_at' => null,
            'lead_off_source' => 'crm',
        ]);
        $lead = Lead::create(['name' => 'Lead Off IVR Lead', 'phone' => '9999994444', 'source' => 'ivr', 'status' => 'new']);

        IvrLeadAutomationConfig::create([
            'is_enabled' => true,
            'default_mode' => IvrLeadAutomationConfig::MODE_RECEIVER,
            'distribution_method' => IvrLeadAutomationConfig::METHOD_RECEIVER,
            'fallback_mode' => IvrLeadAutomationConfig::FALLBACK_RECEIVER,
            'receiver_assignment_allowed' => true,
        ]);

        $result = app(IvrLeadAutomationService::class)->routeLead($lead, $receiver);

        $this->assertNull($result['assigned_user']);
        $this->assertTrue($result['fallback_used']);
        $this->assertSame('Receiver is lead-off', $result['reason']);
        $this->assertDatabaseHas('ivr_lead_automation_audits', [
            'lead_id' => $lead->id,
            'receiver_user_id' => $receiver->id,
            'assigned_user_id' => null,
            'reason' => 'Receiver is lead-off',
        ]);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

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
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('is_absent')->default(false);
            $table->timestamp('absent_until')->nullable();
            $table->timestamp('lead_off_start_at')->nullable();
            $table->timestamp('lead_off_end_at')->nullable();
            $table->string('lead_off_source')->nullable();
            $table->unsignedBigInteger('lead_off_set_by')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('max_pending_leads')->default(50);
            $table->boolean('is_absent')->default(false);
            $table->timestamp('absent_until')->nullable();
            $table->timestamp('lead_off_start_at')->nullable();
            $table->timestamp('lead_off_end_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_daily_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('overall_daily_limit')->default(0);
            $table->unsignedInteger('assigned_count_today')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->string('assignment_method')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_lead_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('default_mode')->default('assign_to_receiver');
            $table->string('distribution_method')->default('receiver');
            $table->string('fallback_mode')->default('receiver');
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->unsignedBigInteger('fixed_user_id')->nullable();
            $table->unsignedBigInteger('default_team_manager_user_id')->nullable();
            $table->boolean('receiver_assignment_allowed')->default(true);
            $table->unsignedBigInteger('last_round_robin_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_lead_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('allocation_percentage', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ivr_lead_automation_receiver_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('can_assign_to_self')->default(true);
            $table->string('target_type')->default('self');
            $table->string('distribution_method')->default('receiver');
            $table->unsignedBigInteger('team_manager_user_id')->nullable();
            $table->unsignedBigInteger('fixed_user_id')->nullable();
            $table->string('fallback_mode')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->unsignedBigInteger('last_round_robin_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_lead_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('receiver_override_id')->nullable();
            $table->unsignedBigInteger('webhook_log_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('receiver_user_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('rule_source')->default('hard_default');
            $table->string('target_type')->nullable();
            $table->string('strategy_used')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->string('fallback_mode')->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'is_active' => true,
            ]
        );
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'ivr-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }
}
