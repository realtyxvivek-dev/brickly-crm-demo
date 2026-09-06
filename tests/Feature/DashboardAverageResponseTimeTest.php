<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserMetricReset;
use App\Services\DashboardResponseTimeService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAverageResponseTimeTest extends TestCase
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

    public function test_average_response_time_uses_assignment_to_first_completed_response_formula(): void
    {
        $salesExecutive = $this->createUser(Role::SALES_EXECUTIVE, 'Ayushi');
        $assistantManager = $this->createUser(Role::ASSISTANT_SALES_MANAGER, 'Nishant');

        $leadOne = $this->createLead('Lead One');
        $leadTwo = $this->createLead('Lead Two');
        $leadThree = $this->createLead('Lead Three');
        $leadFour = $this->createLead('Lead Four');

        DB::table('lead_assignments')->insert([
            [
                'lead_id' => $leadOne,
                'assigned_to' => $salesExecutive->id,
                'assigned_at' => '2026-03-30 10:05:00',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadTwo,
                'assigned_to' => $salesExecutive->id,
                'assigned_at' => '2026-03-30 11:20:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadThree,
                'assigned_to' => $assistantManager->id,
                'assigned_at' => '2026-03-30 09:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadFour,
                'assigned_to' => $assistantManager->id,
                'assigned_at' => '2026-03-30 12:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('telecaller_tasks')->insert([
            [
                'lead_id' => $leadOne,
                'assigned_to' => $salesExecutive->id,
                'task_type' => 'calling',
                'status' => 'completed',
                'completed_at' => '2026-03-30 10:15:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadTwo,
                'assigned_to' => $salesExecutive->id,
                'task_type' => 'calling',
                'status' => 'completed',
                'completed_at' => '2026-03-30 11:30:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('tasks')->insert([
            [
                'lead_id' => $leadThree,
                'assigned_to' => $assistantManager->id,
                'type' => 'phone_call',
                'status' => 'completed',
                'completed_at' => '2026-03-30 09:20:00',
                'outcome_recorded_at' => '2026-03-30 09:21:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadFour,
                'assigned_to' => $assistantManager->id,
                'type' => 'phone_call',
                'status' => 'pending',
                'completed_at' => null,
                'outcome_recorded_at' => '2026-03-30 12:25:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $rows = collect(app(DashboardResponseTimeService::class)->getAverageResponseTimeByUser(
            Carbon::parse('2026-03-30 00:00:00'),
            Carbon::parse('2026-03-30 23:59:59')
        ))->keyBy('user_id');

        $this->assertSame(10.0, $rows[$salesExecutive->id]['avg_response_minutes']);
        $this->assertSame(2, $rows[$salesExecutive->id]['responded_count']);
        $this->assertSame(22.5, $rows[$assistantManager->id]['avg_response_minutes']);
        $this->assertSame(2, $rows[$assistantManager->id]['responded_count']);
    }

    public function test_reset_ignores_old_completed_responses_and_starts_fresh(): void
    {
        $salesExecutive = $this->createUser(Role::SALES_EXECUTIVE, 'Mohit');
        $leadOne = $this->createLead('Lead Old');
        $leadTwo = $this->createLead('Lead New');

        DB::table('lead_assignments')->insert([
            [
                'lead_id' => $leadOne,
                'assigned_to' => $salesExecutive->id,
                'assigned_at' => '2026-03-30 10:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadTwo,
                'assigned_to' => $salesExecutive->id,
                'assigned_at' => '2026-03-30 13:00:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('telecaller_tasks')->insert([
            [
                'lead_id' => $leadOne,
                'assigned_to' => $salesExecutive->id,
                'task_type' => 'calling',
                'status' => 'completed',
                'completed_at' => '2026-03-30 10:10:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $leadTwo,
                'assigned_to' => $salesExecutive->id,
                'task_type' => 'calling',
                'status' => 'completed',
                'completed_at' => '2026-03-30 13:20:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(DashboardResponseTimeService::class)->resetForUser($salesExecutive->id, null);
        UserMetricReset::where('user_id', $salesExecutive->id)->update([
            'reset_at' => '2026-03-30 12:00:00',
        ]);

        $row = collect(app(DashboardResponseTimeService::class)->getAverageResponseTimeByUser(
            Carbon::parse('2026-03-30 00:00:00'),
            Carbon::parse('2026-03-30 23:59:59')
        ))->firstWhere('user_id', $salesExecutive->id);

        $this->assertSame(20.0, $row['avg_response_minutes']);
        $this->assertSame(1, $row['responded_count']);
    }

    public function test_admin_reset_route_persists_response_time_cutoff(): void
    {
        $admin = $this->createUser(Role::ADMIN, 'Admin User');
        $target = $this->createUser(Role::SALES_EXECUTIVE, 'Target User');

        $response = $this->actingAs($admin)->post(route('admin.dashboard.response-time.reset', $target));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_metric_resets', [
            'user_id' => $target->id,
            'metric_key' => UserMetricReset::METRIC_RESPONSE_TIME,
            'reset_by' => $admin->id,
        ]);
    }

    public function test_response_time_query_count_does_not_grow_with_assignments(): void
    {
        $user = $this->createUser(Role::SALES_EXECUTIVE, 'Bulk User');

        foreach (range(1, 120) as $index) {
            $leadId = $this->createLead('Bulk Lead ' . $index);
            $assignedAt = Carbon::parse('2026-03-30 08:00:00')->addMinutes($index);

            DB::table('lead_assignments')->insert([
                'lead_id' => $leadId,
                'assigned_to' => $user->id,
                'assigned_at' => $assignedAt,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('telecaller_tasks')->insert([
                'lead_id' => $leadId,
                'assigned_to' => $user->id,
                'task_type' => 'calling',
                'status' => 'completed',
                'completed_at' => $assignedAt->copy()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $row = collect(app(DashboardResponseTimeService::class)->getAverageResponseTimeByUser(
            Carbon::parse('2026-03-30 00:00:00'),
            Carbon::parse('2026-03-30 23:59:59')
        ))->firstWhere('user_id', $user->id);

        $this->assertSame(5.0, $row['avg_response_minutes']);
        $this->assertSame(120, $row['responded_count']);
        $this->assertLessThanOrEqual(8, $queryCount);
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
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_metric_resets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('metric_key');
            $table->timestamp('reset_at');
            $table->unsignedBigInteger('reset_by')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $roleSlug, string $name): User
    {
        static $counter = 1;

        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst(str_replace('_', ' ', $roleSlug)), 'is_active' => true]
        );

        return User::create([
            'name' => $name,
            'email' => 'response-time-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(string $name): int
    {
        return DB::table('leads')->insertGetId([
            'name' => $name,
            'phone' => '9999999999',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
