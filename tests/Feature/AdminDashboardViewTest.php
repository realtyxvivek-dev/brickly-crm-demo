<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseDashboardSummaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class AdminDashboardViewTest extends TestCase
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

    public function test_admin_dashboard_route_renders_mobile_safe_dashboard_view(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin User',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewIs('admin.dashboard');
        $response->assertSee('admin-dashboard-root', false);
        $response->assertSee('admin-hero-grid', false);
        $response->assertSee('dashboard-mode-finance', false);
        $response->assertSee('dashboard-mode-hr', false);
        $response->assertSee('finance-dashboard-panel', false);
        $response->assertSee('hr-dashboard-panel', false);
        $response->assertSee('Open Approval Queue');
        $response->assertSee('@media (max-width: 768px)', false);
    }

    public function test_admin_can_save_and_restore_sales_score_columns(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN));

        $response = $this->actingAs($admin)->postJson(route('admin.dashboard.score-columns.update'), [
            'columns' => ['role', 'leads', 'visits', 'ps', 'vp'],
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'columns' => ['role', 'leads', 'visits', 'ps', 'vp'],
        ]);
        $this->assertSame(
            ['role', 'leads', 'visits', 'ps', 'vp'],
            $admin->fresh()->ui_preferences['admin_sales_score_columns']
        );

        $this->actingAs($admin->fresh())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('salesScoreColumns', ['role', 'leads', 'visits', 'ps', 'vp']);
    }

    public function test_sales_score_columns_reject_empty_and_unknown_values(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN));

        $this->actingAs($admin)
            ->postJson(route('admin.dashboard.score-columns.update'), ['columns' => []])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson(route('admin.dashboard.score-columns.update'), ['columns' => ['ps', 'unknown_metric']])
            ->assertUnprocessable();

        $this->assertNull($admin->fresh()->ui_preferences);
    }

    public function test_pipeline_funnel_counts_distinct_lead_journey_and_loss_buckets(): void
    {
        $this->createPipelineLead(1, 'new', false, '2026-06-01 10:00:00');
        $this->createPipelineLead(2, 'new', false, '2026-06-02 10:00:00');
        $this->createPipelineLead(3, 'new', false, '2026-06-03 10:00:00');
        $this->createPipelineLead(4, 'closed', false, '2026-06-04 10:00:00');
        $this->createPipelineLead(5, 'junk', false, '2026-06-05 10:00:00');
        $this->createPipelineLead(6, 'not_interested', false, '2026-06-06 10:00:00');
        $this->createPipelineLead(7, 'dead', false, '2026-06-07 10:00:00');
        $this->createPipelineLead(8, 'new', true, '2026-06-08 10:00:00');
        $this->createPipelineLead(9, 'new', false, '2026-06-09 10:00:00', true);
        $this->createPipelineLead(10, 'new', false, '2026-06-10 10:00:00');
        $this->createPipelineLead(12, 'new', false, '2026-06-12 10:00:00');
        $this->createPipelineLead(13, 'new', false, '2026-06-13 10:00:00');
        $this->createPipelineLead(11, 'new', false, '2025-12-31 10:00:00');

        DB::table('leads')->where('id', 12)->update(['cnp_count' => 1]);
        DB::table('leads')->where('id', 13)->update(['next_followup_at' => '2026-06-20 10:00:00']);

        DB::table('prospects')->insert([
            ['lead_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 11, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('meetings')->insert([
            ['lead_id' => 2, 'status' => 'completed', 'completed_at' => '2026-06-10 10:00:00', 'scheduled_at' => '2026-06-10 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 2, 'status' => 'completed', 'completed_at' => '2026-06-11 10:00:00', 'scheduled_at' => '2026-06-11 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 3, 'status' => 'completed', 'completed_at' => '2026-06-12 10:00:00', 'scheduled_at' => '2026-06-12 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 5, 'status' => 'completed', 'completed_at' => '2026-06-13 10:00:00', 'scheduled_at' => '2026-06-13 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 9, 'status' => 'completed', 'completed_at' => '2026-06-14 10:00:00', 'scheduled_at' => '2026-06-14 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 11, 'status' => 'completed', 'completed_at' => '2026-06-15 10:00:00', 'scheduled_at' => '2026-06-15 10:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('site_visits')->insert([
            ['lead_id' => 3, 'status' => 'completed', 'completed_at' => '2026-06-12 10:00:00', 'closer_status' => null, 'scheduled_at' => '2026-06-12 10:00:00', 'closer_verified_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 3, 'status' => 'completed', 'completed_at' => '2026-06-13 10:00:00', 'closer_status' => null, 'scheduled_at' => '2026-06-13 10:00:00', 'closer_verified_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 4, 'status' => 'completed', 'completed_at' => '2026-06-14 10:00:00', 'closer_status' => 'approved', 'scheduled_at' => '2026-06-14 10:00:00', 'closer_verified_at' => '2026-06-15 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 9, 'status' => 'completed', 'completed_at' => '2026-06-16 10:00:00', 'closer_status' => 'approved', 'scheduled_at' => '2026-06-16 10:00:00', 'closer_verified_at' => '2026-06-17 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['lead_id' => 11, 'status' => 'completed', 'completed_at' => '2026-06-18 10:00:00', 'closer_status' => 'approved', 'scheduled_at' => '2026-06-18 10:00:00', 'closer_verified_at' => '2026-06-19 10:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $controller = (new ReflectionClass(AdminDashboardController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionClass($controller);
        $funnelMethod = $method->getMethod('getPipelineFunnel');
        $funnelMethod->setAccessible(true);

        $items = collect($funnelMethod->invoke($controller, [
            'start_date' => '2026-01-01 00:00:00',
            'end_date' => '2026-12-31 23:59:59',
        ]))->pluck('value', 'label')->all();

        $this->assertSame(12, $items['Total Leads']);
        $this->assertSame(1, $items['HR Leads']);
        $this->assertSame(1, $items['New Leads']);
        $this->assertSame(1, $items['CNP']);
        $this->assertSame(1, $items['Follow Up']);
        $this->assertSame(1, $items['Active Prospects']);
        $this->assertSame(1, $items['Meeting Done']);
        $this->assertSame(1, $items['Site Visit Done']);
        $this->assertSame(1, $items['Closures']);
        $this->assertSame(1, $items['Junk']);
        $this->assertSame(1, $items['Not Interested']);
        $this->assertSame(2, $items['Dead']);
        $this->assertSame($items['Total Leads'], collect($items)->except('Total Leads')->sum());
    }

    public function test_hr_dashboard_summary_returns_requested_sections_without_remote_work_bucket(): void
    {
        DB::table('leads')->insert([
            ['id' => 101, 'status' => 'new', 'is_dead' => false, 'is_hiring_candidate' => true, 'hiring_status' => 'new', 'next_followup_at' => '2026-06-26 11:00:00', 'created_at' => '2026-06-26 09:00:00', 'updated_at' => '2026-06-26 09:00:00'],
            ['id' => 102, 'status' => 'new', 'is_dead' => false, 'is_hiring_candidate' => true, 'hiring_status' => 'interview_scheduled', 'next_followup_at' => '2026-06-26 15:00:00', 'created_at' => '2026-06-26 10:00:00', 'updated_at' => '2026-06-26 10:00:00'],
            ['id' => 103, 'status' => 'new', 'is_dead' => false, 'is_hiring_candidate' => true, 'hiring_status' => 'not_interested', 'next_followup_at' => null, 'created_at' => '2026-06-26 10:30:00', 'updated_at' => '2026-06-26 10:30:00'],
        ]);

        DB::table('attendance_records')->insert([
            ['user_id' => 1, 'attendance_date' => now()->toDateString(), 'status' => 'present', 'first_punch_in_at' => now(), 'last_punch_out_at' => now(), 'is_suspicious' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 2, 'attendance_date' => now()->toDateString(), 'status' => 'absent', 'first_punch_in_at' => null, 'last_punch_out_at' => null, 'is_suspicious' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 3, 'attendance_date' => now()->toDateString(), 'status' => 'late', 'first_punch_in_at' => now(), 'last_punch_out_at' => null, 'is_suspicious' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('employee_profiles')->insert([
            ['id' => 1, 'user_id' => 1, 'joining_date' => now()->startOfMonth()->toDateString(), 'employment_status' => 'active', 'probation_end_date' => now()->addDays(3)->toDateString(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 2, 'joining_date' => now()->subMonth()->toDateString(), 'employment_status' => 'on_notice', 'probation_end_date' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('employee_documents')->insert([
            ['employee_profile_id' => 1, 'document_type' => 'pan_card', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'User 1', 'email' => 'hr-leave-user-1@example.test', 'password' => bcrypt('secret'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'User 2', 'email' => 'hr-leave-user-2@example.test', 'password' => bcrypt('secret'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leave_requests')->insert([
            ['user_id' => 1, 'from_date' => now()->toDateString(), 'to_date' => now()->toDateString(), 'days_requested' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 2, 'from_date' => now()->toDateString(), 'to_date' => now()->toDateString(), 'days_requested' => 1, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $controller = (new ReflectionClass(AdminDashboardController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionClass($controller);
        $hrMethod = $method->getMethod('getHrDashboardSummary');
        $hrMethod->setAccessible(true);

        $data = $hrMethod->invoke($controller, [
            'start_date' => now()->startOfDay(),
            'end_date' => now()->endOfDay(),
        ]);

        $this->assertSame([
            'hr_summary',
            'hr_funnel',
            'hr_attendance',
            'hr_employee_status',
            'hr_tasks',
            'hr_leave_requests',
            'hr_alerts',
        ], array_keys($data));
        $this->assertSame(1, $data['hr_summary']['present_today']);
        $this->assertSame(1, $data['hr_summary']['absent_today']);
        $this->assertSame(1, $data['hr_summary']['late_today']);
        $this->assertSame(3, $data['hr_summary']['hr_leads']);
        $this->assertSame(1, $data['hr_summary']['interviews_today']);
        $this->assertSame(1, $data['hr_summary']['pending_leave_requests']);
        $this->assertSame(1, collect($data['hr_funnel'])->firstWhere('label', 'Rejected / Not Interested')['count']);
        $this->assertSame('User 1', $data['hr_leave_requests']['pending_items'][0]['user_name']);
        $this->assertSame('Leave', $data['hr_leave_requests']['pending_items'][0]['leave_type']);
        $this->assertArrayNotHasKey(implode('', ['w', 'f', 'm']), $data['hr_attendance']);
    }

    public function test_finance_dashboard_summary_returns_polish_metrics(): void
    {
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'Main Company', 'code' => 'main', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('expense_categories')->insert([
            ['id' => 1, 'name' => 'Marketing', 'code' => 'marketing', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Travel', 'code' => 'travel', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('expense_subcategories')->insert([
            ['id' => 1, 'expense_category_id' => 1, 'name' => 'Ads', 'code' => 'ads', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'expense_category_id' => 2, 'name' => 'Cab', 'code' => 'cab', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('expense_entries')->insert([
            ['company_id' => 1, 'expense_category_id' => 1, 'expense_subcategory_id' => 1, 'expense_date' => '2026-06-01', 'amount' => 30000, 'status' => 'draft', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 2, 'expense_subcategory_id' => 2, 'expense_date' => '2026-06-05', 'amount' => 5000, 'status' => 'draft', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 1, 'expense_subcategory_id' => 1, 'expense_date' => '2026-06-08', 'amount' => 10000, 'status' => 'approved', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 2, 'expense_subcategory_id' => 2, 'expense_date' => '2026-06-12', 'amount' => 2000, 'status' => 'rejected', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 1, 'expense_subcategory_id' => 1, 'expense_date' => '2026-05-15', 'amount' => 20000, 'status' => 'approved', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $snapshot = (new ExpenseDashboardSummaryService())->getRangeSnapshot(
            \Carbon\Carbon::parse('2026-06-01'),
            \Carbon\Carbon::parse('2026-06-30')
        );

        $this->assertSame(47000.0, $snapshot['summary']['total_amount']);
        $this->assertSame(35000.0, $snapshot['pending_amount']);
        $this->assertSame(10000.0, $snapshot['approved_amount']);
        $this->assertSame(2000.0, $snapshot['rejected_amount']);
        $this->assertSame(1, $snapshot['high_value_pending']['count']);
        $this->assertSame(30000.0, $snapshot['high_value_pending']['amount']);
        $this->assertSame(30000.0, $snapshot['oldest_pending']['amount']);
        $this->assertSame('Marketing', $snapshot['category_totals']->first()->category->name);
        $this->assertSame(4, $snapshot['latest_entries']->count());
        $this->assertSame(27000.0, $snapshot['month_comparison']['difference_amount']);
        $this->assertSame(135.0, $snapshot['month_comparison']['percentage_change']);
    }

    public function test_marketing_ad_spend_summary_maps_platforms_and_cpl(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Finance Manager', 'email' => 'finance-manager@example.test', 'password' => bcrypt('secret'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'Main Company', 'code' => 'main', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('expense_categories')->insert([
            ['id' => 1, 'name' => 'Marketing', 'code' => 'marketing', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Operations', 'code' => 'operations', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Travel', 'code' => 'travel', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('expense_subcategories')->insert([
            ['id' => 1, 'expense_category_id' => 1, 'name' => 'Meta Ads', 'code' => 'meta_ads', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'expense_category_id' => 1, 'name' => 'Google Ads', 'code' => 'google_ads', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'expense_category_id' => 1, 'name' => '99acres Portal', 'code' => 'portal', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'expense_category_id' => 2, 'name' => 'Brand Campaign', 'code' => 'brand_campaign', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'expense_category_id' => 3, 'name' => 'Cab', 'code' => 'cab', 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('expense_entries')->insert([
            ['company_id' => 1, 'expense_category_id' => 1, 'expense_subcategory_id' => 1, 'expense_date' => '2026-06-05', 'amount' => 10000, 'status' => 'approved', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 1, 'expense_subcategory_id' => 2, 'expense_date' => '2026-06-06', 'amount' => 5000, 'status' => 'draft', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 1, 'expense_subcategory_id' => 3, 'expense_date' => '2026-06-07', 'amount' => 3000, 'status' => 'rejected', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 2, 'expense_subcategory_id' => 4, 'expense_date' => '2026-06-08', 'amount' => 2000, 'status' => 'approved', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'expense_category_id' => 3, 'expense_subcategory_id' => 5, 'expense_date' => '2026-06-09', 'amount' => 9000, 'status' => 'approved', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('leads')->insert([
            ['id' => 201, 'source' => 'meta', 'status' => 'new', 'is_dead' => false, 'created_at' => '2026-06-05 10:00:00', 'updated_at' => '2026-06-05 10:00:00'],
            ['id' => 202, 'source' => 'meta_awareness', 'status' => 'new', 'is_dead' => false, 'created_at' => '2026-06-06 10:00:00', 'updated_at' => '2026-06-06 10:00:00'],
            ['id' => 203, 'source' => 'google', 'status' => 'new', 'is_dead' => false, 'created_at' => '2026-06-07 10:00:00', 'updated_at' => '2026-06-07 10:00:00'],
            ['id' => 204, 'source' => 'housing', 'status' => 'new', 'is_dead' => false, 'created_at' => '2026-06-08 10:00:00', 'updated_at' => '2026-06-08 10:00:00'],
        ]);

        $controller = (new ReflectionClass(AdminDashboardController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionClass($controller);
        $adSpendMethod = $method->getMethod('getAdSpendSummary');
        $adSpendMethod->setAccessible(true);

        $summary = $adSpendMethod->invoke($controller, [
            'start_date' => '2026-06-01 00:00:00',
            'end_date' => '2026-06-30 23:59:59',
        ]);
        $platforms = collect($summary['platforms'])->keyBy('key');

        $this->assertSame(20000.0, $summary['total_amount']);
        $this->assertSame(12000.0, $summary['approved_amount']);
        $this->assertSame(5000.0, $summary['pending_amount']);
        $this->assertSame(3000.0, $summary['rejected_amount']);
        $this->assertSame(10000.0, $platforms['meta']['amount']);
        $this->assertSame(2, $platforms['meta']['lead_count']);
        $this->assertSame(5000.0, $platforms['meta']['cost_per_lead']);
        $this->assertSame(5000.0, $platforms['google']['amount']);
        $this->assertSame(3000.0, $platforms['portal']['amount']);
        $this->assertSame(2000.0, $platforms['other']['amount']);
        $this->assertCount(4, $summary['latest_entries']);
        $this->assertFalse(collect($summary['latest_entries'])->contains('subcategory_name', 'Cab'));
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
            $table->json('ui_preferences')->nullable();
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ticket_number')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('priority')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('desktop_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->timestamp('next_followup_at')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->boolean('is_hiring_candidate')->default(false);
            $table->string('hiring_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable();
            $table->string('type')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('status')->nullable();
            $table->string('closer_status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('closer_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('attendance_date');
            $table->timestamp('first_punch_in_at')->nullable();
            $table->timestamp('last_punch_out_at')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->string('fraud_review_status')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('employment_status')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_profile_id');
            $table->string('document_type')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->decimal('days_requested', 8, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_subcategories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_category_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('expense_category_id')->nullable();
            $table->unsignedBigInteger('expense_subcategory_id')->nullable();
            $table->date('expense_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            'name' => 'User ' . $counter,
            'email' => 'admin-dashboard-view-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createPipelineLead(int $id, string $status, bool $isDead, string $createdAt, bool $isHiringCandidate = false): void
    {
        DB::table('leads')->insert([
            'id' => $id,
            'status' => $status,
            'is_dead' => $isDead,
            'is_hiring_candidate' => $isHiringCandidate,
            'hiring_status' => $isHiringCandidate ? 'new' : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
