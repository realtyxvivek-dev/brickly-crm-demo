<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use App\Models\VerificationRoutingMapping;
use App\Services\VerificationRoutingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerificationRoutingServiceTest extends TestCase
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

    public function test_default_meeting_routing_allows_admin_crm_and_reporting_senior(): void
    {
        $admin = $this->user($this->role(Role::ADMIN));
        $crm = $this->user($this->role(Role::CRM));
        $manager = $this->user($this->role(Role::SALES_MANAGER));
        $creator = $this->user($this->role(Role::SALES_EXECUTIVE), ['manager_id' => $manager->id]);
        $unrelated = $this->user($this->role(Role::SENIOR_MANAGER));
        $meeting = $this->meeting($creator);

        $routing = app(VerificationRoutingService::class);

        $this->assertTrue($routing->canVerify($admin, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
        $this->assertTrue($routing->canVerify($crm, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
        $this->assertTrue($routing->canVerify($manager, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
        $this->assertFalse($routing->canVerify($unrelated, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
    }

    public function test_fixed_user_setting_only_allows_selected_user_when_user_exists(): void
    {
        $selected = $this->user($this->role(Role::HR_MANAGER));
        $admin = $this->user($this->role(Role::ADMIN));
        $creator = $this->user($this->role(Role::SALES_EXECUTIVE));
        $meeting = $this->meeting($creator);

        $routing = app(VerificationRoutingService::class);
        $setting = $routing->settingFor(VerificationRoutingService::WORKFLOW_MEETING);
        $setting->update([
            'mode' => VerificationRoutingService::MODE_FIXED_USER,
            'fixed_user_id' => $selected->id,
        ]);

        $this->assertTrue($routing->canVerify($selected, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
        $this->assertFalse($routing->canVerify($admin, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
    }

    public function test_user_mapping_has_priority_over_role_mapping(): void
    {
        $roleVerifier = $this->user($this->role(Role::CRM));
        $userVerifier = $this->user($this->role(Role::HR_MANAGER));
        $salesRole = $this->role(Role::SALES_EXECUTIVE);
        $creator = $this->user($salesRole);
        $meeting = $this->meeting($creator);

        VerificationRoutingMapping::create([
            'workflow_type' => VerificationRoutingService::WORKFLOW_MEETING,
            'source_type' => VerificationRoutingService::SOURCE_ROLE,
            'source_role_id' => $salesRole->id,
            'verifier_type' => VerificationRoutingService::VERIFIER_USER,
            'verifier_user_id' => $roleVerifier->id,
            'is_active' => true,
            'priority' => 1,
        ]);

        VerificationRoutingMapping::create([
            'workflow_type' => VerificationRoutingService::WORKFLOW_MEETING,
            'source_type' => VerificationRoutingService::SOURCE_USER,
            'source_user_id' => $creator->id,
            'verifier_type' => VerificationRoutingService::VERIFIER_USER,
            'verifier_user_id' => $userVerifier->id,
            'is_active' => true,
            'priority' => 999,
        ]);

        $routing = app(VerificationRoutingService::class);

        $this->assertTrue($routing->canVerify($userVerifier, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
        $this->assertFalse($routing->canVerify($roleVerifier, $meeting, VerificationRoutingService::WORKFLOW_MEETING));
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

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status')->default('completed');
            $table->string('verification_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('verification_routing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type')->unique();
            $table->string('mode')->default('reporting_senior');
            $table->json('fixed_role_ids')->nullable();
            $table->unsignedBigInteger('fixed_user_id')->nullable();
            $table->json('fallback_role_ids')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_routing_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type');
            $table->string('source_type');
            $table->unsignedBigInteger('source_user_id')->nullable();
            $table->unsignedBigInteger('source_role_id')->nullable();
            $table->unsignedBigInteger('source_team_user_id')->nullable();
            $table->string('verifier_type');
            $table->unsignedBigInteger('verifier_user_id')->nullable();
            $table->json('verifier_role_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    private function role(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst(str_replace('_', ' ', $slug)), 'is_active' => true]
        );
    }

    private function user(Role $role, array $attributes = []): User
    {
        static $i = 1;

        return User::create(array_merge([
            'name' => 'Routing User ' . $i,
            'email' => 'routing-' . $i++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function meeting(User $creator): Meeting
    {
        $id = DB::table('meetings')->insertGetId([
            'created_by' => $creator->id,
            'status' => 'completed',
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Meeting::findOrFail($id);
    }
}
