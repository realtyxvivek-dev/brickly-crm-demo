<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Hr\EmployeeIncentiveController;
use App\Models\Incentive;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrIncentiveOverviewTest extends TestCase
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

    public function test_hr_overview_groups_incentives_and_loads_booking_details(): void
    {
        $role = Role::create(['name' => 'Sales Executive', 'slug' => Role::SALES_EXECUTIVE, 'is_active' => true]);
        $user = User::create([
            'name' => 'Mohit Yadav',
            'email' => 'mohit@example.com',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $visit = SiteVisit::create([
            'customer_name' => 'Kashvi Kashyap',
            'phone' => '919999999999',
            'project' => 'BASE Residential',
            'unit_details' => ['booking_date' => '2026-08-12'],
        ]);

        Incentive::create([
            'site_visit_id' => $visit->id,
            'user_id' => $user->id,
            'type' => 'closer',
            'amount' => 10000,
            'status' => 'verified',
            'created_at' => '2026-08-15 10:00:00',
            'updated_at' => '2026-08-15 10:00:00',
        ]);
        Incentive::create([
            'site_visit_id' => $visit->id,
            'user_id' => $user->id,
            'type' => 'site_visit',
            'amount' => 2500,
            'status' => 'pending_finance_manager',
            'created_at' => '2026-08-16 10:00:00',
            'updated_at' => '2026-08-16 10:00:00',
        ]);

        $request = Request::create('/hr-manager/settings/hr/incentives', 'GET', ['month' => '2026-08']);
        $view = app(EmployeeIncentiveController::class)->index($request);
        $row = $view->getData()['rows']->first();

        $this->assertSame(12500.0, $row['earned']);
        $this->assertSame(10000.0, $row['approved']);
        $this->assertSame(2500.0, $row['pending']);
        $this->assertSame(1, $row['booking_count']);
        $this->assertSame('Kashvi Kashyap', $row['incentives']->first()->siteVisit->customer_name);
        $this->assertSame('BASE Residential', $row['incentives']->first()->siteVisit->project);
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
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('employee_code')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('project')->nullable();
            $table->string('property_name')->nullable();
            $table->date('actual_closer_date')->nullable();
            $table->text('unit_details')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_visit_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->unsignedBigInteger('finance_manager_verified_by')->nullable();
            $table->timestamp('finance_manager_verified_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }
}
