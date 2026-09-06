<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AdvisorPerformanceReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdvisorPerformanceReportServiceTest extends TestCase
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

    public function test_current_unique_leads_follow_current_active_owner_without_breaking_assigned_workload(): void
    {
        $role = $this->createRole(Role::SALES_EXECUTIVE);
        $ayushi = $this->createUser($role, 'Ayushi Gupta');
        $ayush = $this->createUser($role, 'Ayush Kumar');

        $leadTransferred = $this->createLead('Transferred Lead');
        $leadDuplicateSameAdvisor = $this->createLead('Duplicate Assignment Lead');

        $this->createAssignment($leadTransferred, $ayushi, '2026-04-10 10:00:00', false);
        $this->createAssignment($leadTransferred, $ayush, '2026-05-10 10:00:00', true);
        $this->createAssignment($leadDuplicateSameAdvisor, $ayush, '2026-04-12 10:00:00', false);
        $this->createAssignment($leadDuplicateSameAdvisor, $ayush, '2026-04-18 10:00:00', true);

        $service = app(AdvisorPerformanceReportService::class);
        $period = $service->resolvePeriod([
            'period_type' => 'quarter',
            'year' => 2026,
            'quarter' => 1,
        ]);

        $report = $service->build($period);
        $rows = collect($report['rows'])->keyBy('advisor');

        $this->assertSame(1, $rows['Ayushi Gupta']['leads']);
        $this->assertSame(0, $rows['Ayushi Gupta']['current_unique_leads']);
        $this->assertSame(2, $rows['Ayush Kumar']['leads']);
        $this->assertSame(2, $rows['Ayush Kumar']['current_unique_leads']);
        $this->assertSame(3, $report['totals']['leads']);
        $this->assertSame(2, $report['totals']['current_unique_leads']);
    }

    public function test_excel_export_uses_assigned_and_current_unique_lead_headers(): void
    {
        $role = $this->createRole(Role::SALES_EXECUTIVE);
        $advisor = $this->createUser($role, 'Advisor One');
        $lead = $this->createLead('Lead One');
        $this->createAssignment($lead, $advisor, '2026-04-10 10:00:00', true);

        $service = app(AdvisorPerformanceReportService::class);
        $report = $service->build($service->resolvePeriod([
            'period_type' => 'quarter',
            'year' => 2026,
            'quarter' => 1,
        ]));

        $response = $service->export($report);
        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'advisor-performance-') . '.xlsx';
        file_put_contents($path, $contents);

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('Assigned Leads', $sheet->getCell('B3')->getValue());
        $this->assertSame('Current Unique Leads', $sheet->getCell('C3')->getValue());

        @unlink($path);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
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
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('project')->nullable();
            $table->string('property_name')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('finance_handover_status')->nullable();
            $table->timestamp('finance_reviewed_at')->nullable();
            $table->decimal('revenue_value', 15, 2)->nullable();
            $table->text('revenue_note')->nullable();
            $table->unsignedBigInteger('revenue_updated_by')->nullable();
            $table->timestamp('revenue_updated_at')->nullable();
            $table->json('unit_details')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visit_revenue_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_visit_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->nullable();
            $table->timestamp('finance_manager_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_monthly_rollups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('estimated_salary', 12, 2)->default(0);
            $table->decimal('payable_days', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('user_salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('salary_structure_id')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_structure_id');
            $table->string('component_type');
            $table->string('code')->nullable();
            $table->string('label')->nullable();
            $table->string('calc_type')->default('fixed');
            $table->decimal('value', 12, 2)->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'permissions' => [],
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => str($name)->slug() . '@example.test',
            'password' => 'secret',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createLead(string $name): int
    {
        return (int) DB::table('leads')->insertGetId([
            'name' => $name,
            'phone' => (string) random_int(7000000000, 9999999999),
            'status' => 'new',
            'created_at' => '2026-04-01 10:00:00',
            'updated_at' => '2026-04-01 10:00:00',
        ]);
    }

    private function createAssignment(int $leadId, User $advisor, string $assignedAt, bool $isActive): void
    {
        DB::table('lead_assignments')->insert([
            'lead_id' => $leadId,
            'assigned_to' => $advisor->id,
            'assigned_by' => null,
            'assigned_at' => $assignedAt,
            'unassigned_at' => $isActive ? null : '2026-05-09 10:00:00',
            'is_active' => $isActive,
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ]);
    }
}
