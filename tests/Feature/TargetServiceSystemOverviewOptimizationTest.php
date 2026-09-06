<?php

namespace Tests\Feature;

use App\Services\TargetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TargetServiceSystemOverviewOptimizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
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

    public function test_system_overview_uses_bounded_bulk_queries_for_many_users(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Assistant Sales Manager', 'slug' => 'assistant_sales_manager'],
        ]);

        $now = now();
        for ($id = 1; $id <= 25; $id++) {
            DB::table('users')->insert([
                'id' => $id,
                'name' => 'Sales User ' . $id,
                'email' => 'sales-' . $id . '@example.test',
                'role_id' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('targets')->insert([
                'user_id' => $id,
                'target_month' => $now->copy()->startOfMonth(),
                'target_prospects_extract' => 10,
                'target_prospects_verified' => 5,
                'target_calls' => 20,
                'target_meetings' => 4,
                'target_visits' => 3,
                'target_closers' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('prospects')->insert([
                'telecaller_id' => $id,
                'verification_status' => 'verified',
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('telecaller_tasks')->insert([
                'assigned_to' => $id,
                'task_type' => 'calling',
                'status' => 'completed',
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $overview = app(TargetService::class)->getSystemOverview($now->format('Y-m'));

        $this->assertSame(25, $overview['total_users']);
        $this->assertCount(25, $overview['details']);
        $this->assertCount(25, $overview['achievement_breakdown']);
        $this->assertSame(25, $overview['actuals']['prospects_extract']);
        $this->assertSame(25, $overview['actuals']['prospects_verified']);
        $this->assertSame(25, $overview['actuals']['calls']);
        $this->assertLessThanOrEqual(20, $queryCount);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('target_month');
            $table->unsignedInteger('target_prospects_extract')->default(0);
            $table->unsignedInteger('target_prospects_verified')->default(0);
            $table->unsignedInteger('target_calls')->default(0);
            $table->unsignedInteger('target_meetings')->default(0);
            $table->unsignedInteger('target_visits')->default(0);
            $table->unsignedInteger('target_closers')->default(0);
            $table->string('manager_target_calculation_logic')->nullable();
            $table->string('manager_junior_scope')->nullable();
            $table->timestamps();
        });
        Schema::create('prospects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->string('verification_status')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('telecaller_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_converted')->default(false);
            $table->string('verification_status')->nullable();
            $table->boolean('is_rescheduled')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('site_visits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('closer_status')->nullable();
            $table->boolean('is_rescheduled')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->date('actual_closer_date')->nullable();
            $table->timestamp('closer_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('lead_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->boolean('is_active')->default(true);
        });
    }
}
