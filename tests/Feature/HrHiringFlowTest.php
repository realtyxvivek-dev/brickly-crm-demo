<?php

namespace Tests\Feature;

use App\Events\NewLeadNotification;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SourceAutomationRule;
use App\Models\SourceAutomationRuleUser;
use App\Models\User;
use App\Services\SourceAutomationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrHiringFlowTest extends TestCase
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

    public function test_admin_automation_form_shows_hr_users_as_assignable_options(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'admin@example.com']);
        $this->createUser($hrRole, ['name' => 'HR User', 'email' => 'hr@example.com']);
        $this->createUser($salesExecutiveRole, ['name' => 'Sales User', 'email' => 'sales@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.automation.create'));

        $response->assertOk();
        $response->assertSee('HR User');
        $response->assertSee('HR Manager');
    }

    public function test_admin_automation_form_shows_custom_website_source_and_can_create_rule(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'admin@example.com']);
        $salesUser = $this->createUser($salesExecutiveRole, ['name' => 'Website Owner', 'email' => 'owner@example.com']);

        $formResponse = $this->actingAs($admin)->get(route('admin.automation.create'));

        $formResponse->assertOk();
        $formResponse->assertSee('Custom Website');
        $formResponse->assertSee('value="website"', false);

        $storeResponse = $this->actingAs($admin)->post(route('admin.automation.store'), [
            'name' => 'Custom Website Rule',
            'source' => 'website',
            'assignment_method' => 'single_user',
            'single_user_id' => $salesUser->id,
            'users' => [
                ['user_id' => '', 'percentage' => ''],
            ],
            'is_active' => '1',
        ]);

        $storeResponse->assertRedirect(route('admin.automation.index'));

        $this->assertDatabaseHas('source_automation_rules', [
            'name' => 'Custom Website Rule',
            'source' => 'website',
            'assignment_method' => 'single_user',
            'single_user_id' => $salesUser->id,
        ]);
    }

    public function test_admin_can_create_single_user_hr_hiring_rule_without_multi_user_payload(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'admin@example.com']);
        $hrUser = $this->createUser($hrRole, ['name' => 'HR User', 'email' => 'hr@example.com']);

        DB::table('fb_pages')->insert([
            'id' => 1,
            'name' => 'Hiring Page',
            'page_id' => 'page_1',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fb_forms')->insert([
            'id' => 1,
            'fb_page_id' => 1,
            'name' => 'Job Interview',
            'form_id' => 'form_1',
            'is_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.automation.store'), [
            'name' => 'Hiring Lead',
            'source' => 'facebook_lead_ads',
            'fb_form_id' => 1,
            'assignment_method' => 'single_user',
            'single_user_id' => $hrUser->id,
            'users' => [
                ['user_id' => '', 'percentage' => ''],
            ],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.automation.index'));

        $this->assertDatabaseHas('source_automation_rules', [
            'name' => 'Hiring Lead',
            'source' => 'facebook_lead_ads',
            'assignment_method' => 'single_user',
            'single_user_id' => $hrUser->id,
        ]);
    }

    public function test_facebook_hiring_rule_assigns_hr_and_marks_lead_as_hiring_candidate(): void
    {
        Event::fake([NewLeadNotification::class]);
        Queue::fake();

        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'admin@example.com']);
        $hrUser = $this->createUser($hrRole, ['name' => 'Hiring HR', 'email' => 'hr@example.com']);

        DB::table('fb_pages')->insert([
            'id' => 10,
            'name' => 'Hiring Page',
            'page_id' => 'page_10',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fb_forms')->insert([
            'id' => 50,
            'fb_page_id' => 10,
            'name' => 'Hiring Form',
            'form_id' => 'form_50',
            'is_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SourceAutomationRule::create([
            'name' => 'Hiring Form to HR',
            'source' => 'facebook_lead_ads',
            'fb_form_id' => 50,
            'assignment_method' => 'single_user',
            'single_user_id' => $hrUser->id,
            'auto_create_task' => false,
            'daily_limit' => null,
            'fallback_user_id' => null,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $lead = Lead::create([
            'name' => 'Candidate One',
            'phone' => '9876543210',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $admin->id,
        ]);

        $result = app(SourceAutomationService::class)->assignFromSource($lead, 'facebook_lead_ads', 50, null);

        $this->assertTrue($result);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'is_hiring_candidate' => 1,
            'hiring_status' => 'new',
        ]);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $hrUser->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $hrUser->id,
            'action_url' => route('hr-manager.hiring.show', $lead),
        ]);
    }

    public function test_multi_form_source_automation_rule_takes_priority_over_old_single_form_rule(): void
    {
        Event::fake([NewLeadNotification::class]);
        Queue::fake();

        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'multi-form-admin@example.com']);
        $oldOwner = $this->createUser($salesRole, ['name' => 'Old Owner', 'email' => 'old-owner@example.com']);
        $newOwner = $this->createUser($salesRole, ['name' => 'New Owner', 'email' => 'new-owner@example.com']);

        DB::table('fb_pages')->insert([
            'id' => 20,
            'name' => 'Residential Page',
            'page_id' => 'page_20',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fb_forms')->insert([
            'id' => 60,
            'fb_page_id' => 20,
            'name' => 'Residential Form',
            'form_id' => 'form_60',
            'is_enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SourceAutomationRule::create([
            'name' => 'Old Form Rule',
            'source' => 'facebook_lead_ads',
            'fb_form_id' => 60,
            'assignment_method' => 'single_user',
            'single_user_id' => $oldOwner->id,
            'auto_create_task' => false,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $multiRule = SourceAutomationRule::create([
            'name' => 'Combined Residential Rule',
            'source' => 'facebook_lead_ads',
            'assignment_method' => 'single_user',
            'single_user_id' => $newOwner->id,
            'auto_create_task' => false,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $multiRule->fbForms()->attach(60);

        $lead = Lead::create([
            'name' => 'Residential Buyer',
            'phone' => '9876500060',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $admin->id,
        ]);

        $result = app(SourceAutomationService::class)->assignFromSource($lead, 'facebook_lead_ads', 60, null);

        $this->assertTrue($result);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $newOwner->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $oldOwner->id,
            'is_active' => 1,
        ]);
    }

    public function test_source_automation_skips_lead_off_single_and_fallback_users(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'lead-off-admin@example.com']);
        $singleUser = $this->createUser($salesRole, ['name' => 'Lead Off Single', 'email' => 'lead-off-single@example.com']);
        $fallbackUser = $this->createUser($salesRole, ['name' => 'Lead Off Fallback', 'email' => 'lead-off-fallback@example.com']);

        foreach ([$singleUser, $fallbackUser] as $user) {
            $user->userProfile()->create([
                'is_absent' => true,
                'lead_off_start_at' => now()->subMinute(),
                'lead_off_end_at' => null,
                'lead_off_source' => 'crm',
            ]);
        }

        SourceAutomationRule::create([
            'name' => 'Website Single Lead Off',
            'source' => 'website',
            'assignment_method' => 'single_user',
            'single_user_id' => $singleUser->id,
            'auto_create_task' => false,
            'daily_limit' => null,
            'fallback_user_id' => $fallbackUser->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $lead = Lead::create([
            'name' => 'Website Lead Off Lead',
            'phone' => '9999991234',
            'source' => 'website',
            'status' => 'new',
            'created_by' => $admin->id,
        ]);

        $result = app(SourceAutomationService::class)->assignFromSource($lead, 'website');

        $this->assertFalse($result);
        $this->assertDatabaseMissing('lead_assignments', [
            'lead_id' => $lead->id,
        ]);
    }

    public function test_source_automation_round_robin_skips_lead_off_users(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, ['name' => 'Admin User', 'email' => 'rr-admin@example.com']);
        $leadOffUser = $this->createUser($salesRole, ['name' => 'Lead Off Sales', 'email' => 'rr-off@example.com']);
        $availableUser = $this->createUser($salesRole, ['name' => 'Available Sales', 'email' => 'rr-available@example.com']);

        $leadOffUser->userProfile()->create([
            'is_absent' => true,
            'lead_off_start_at' => now()->subMinute(),
            'lead_off_end_at' => null,
            'lead_off_source' => 'crm',
        ]);

        $rule = SourceAutomationRule::create([
            'name' => 'Website Round Robin Lead Off',
            'source' => 'website',
            'assignment_method' => 'round_robin',
            'single_user_id' => null,
            'auto_create_task' => false,
            'daily_limit' => null,
            'fallback_user_id' => null,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        SourceAutomationRuleUser::create([
            'rule_id' => $rule->id,
            'user_id' => $leadOffUser->id,
            'percentage' => null,
            'daily_limit' => null,
            'assigned_count_today' => 0,
            'last_reset_date' => now()->toDateString(),
            'is_active' => true,
            'sort_order' => 1,
        ]);
        SourceAutomationRuleUser::create([
            'rule_id' => $rule->id,
            'user_id' => $availableUser->id,
            'percentage' => null,
            'daily_limit' => null,
            'assigned_count_today' => 0,
            'last_reset_date' => now()->toDateString(),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $lead = Lead::create([
            'name' => 'Website Round Robin Lead',
            'phone' => '9999995678',
            'source' => 'website',
            'status' => 'new',
            'created_by' => $admin->id,
        ]);

        $result = app(SourceAutomationService::class)->assignFromSource($lead, 'website');

        $this->assertTrue($result);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $availableUser->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $leadOffUser->id,
        ]);
    }

    public function test_hr_hiring_queue_filters_assigned_candidates_and_updates_status_and_remark(): void
    {
        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');

        $admin = $this->createUser($adminRole, ['name' => 'Admin', 'email' => 'admin@example.com']);
        $hrOne = $this->createUser($hrRole, ['name' => 'HR One', 'email' => 'hr1@example.com']);
        $hrTwo = $this->createUser($hrRole, ['name' => 'HR Two', 'email' => 'hr2@example.com']);

        $candidateA = Lead::create([
            'name' => 'Candidate A',
            'phone' => '9999999999',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $admin->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'connected',
        ]);
        $candidateB = Lead::create([
            'name' => 'Candidate B',
            'phone' => '8888888888',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $admin->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'selected',
        ]);
        $candidateC = Lead::create([
            'name' => 'Candidate C',
            'phone' => '7777777777',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $admin->id,
            'is_hiring_candidate' => true,
            'hiring_status' => 'selected',
        ]);

        $this->assignLeadToUser($candidateA->id, $hrOne->id, $admin->id);
        $this->assignLeadToUser($candidateB->id, $hrOne->id, $admin->id);
        $this->assignLeadToUser($candidateC->id, $hrTwo->id, $admin->id);

        $indexResponse = $this->actingAs($hrOne)->get(route('hr-manager.hiring.index', ['status' => 'selected']));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Candidate B');
        $indexResponse->assertDontSee('Candidate A');
        $indexResponse->assertDontSee('Candidate C');

        $showResponse = $this->actingAs($hrOne)->get(route('hr-manager.hiring.show', $candidateA));
        $showResponse->assertOk();
        $showResponse->assertSee('Candidate A');
        $showResponse->assertSee('Update Hiring Progress');

        $updateResponse = $this->actingAs($hrOne)->put(route('hr-manager.hiring.update', $candidateA), [
            'hiring_status' => 'interview_pending',
            'hr_remark' => 'Candidate connected. Interview scheduled for Monday.',
        ]);

        $updateResponse->assertRedirect(route('hr-manager.hiring.show', $candidateA));
        $this->assertDatabaseHas('leads', [
            'id' => $candidateA->id,
            'hiring_status' => 'interview_pending',
            'hr_remark' => 'Candidate connected. Interview scheduled for Monday.',
        ]);

        $forbiddenResponse = $this->actingAs($hrOne)->get(route('hr-manager.hiring.show', $candidateC));
        $forbiddenResponse->assertNotFound();
    }

    protected function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('fb_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('page_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fb_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_page_id')->nullable();
            $table->string('name')->nullable();
            $table->string('form_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('google_sheets_config', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_draft')->default(false);
            $table->timestamps();
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

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('source')->default('other');
            $table->string('status')->default('new');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_blocked')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->boolean('form_filled_by_telecaller')->default(false);
            $table->boolean('form_filled_by_executive')->default(false);
            $table->boolean('form_filled_by_manager')->default(false);
            $table->boolean('is_hiring_candidate')->default(false);
            $table->string('hiring_status')->nullable();
            $table->text('hr_remark')->nullable();
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

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by');
            $table->string('assignment_type')->default('primary');
            $table->string('assignment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sheet_config_id')->nullable();
            $table->unsignedBigInteger('sheet_row_number')->nullable();
            $table->unsignedBigInteger('sheet_assignment_config_id')->nullable();
            $table->timestamps();
        });

        Schema::create('source_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source');
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->unsignedBigInteger('google_sheet_config_id')->nullable();
            $table->string('assignment_method');
            $table->unsignedBigInteger('single_user_id')->nullable();
            $table->boolean('auto_create_task')->default(true);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('source_automation_rule_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('assigned_count_today')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->timestamps();
        });

        Schema::create('source_automation_rule_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('fb_form_id');
            $table->timestamps();
        });

        Schema::create('fb_leads', function (Blueprint $table) {
            $table->id();
            $table->string('leadgen_id')->nullable();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->text('field_data_json')->nullable();
            $table->text('raw_response_json')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('action_type')->nullable();
            $table->text('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('desktop_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reported_by_user_id')->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->string('issue_type', 50)->default('other');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    protected function createRole(string $slug, string $name): Role
    {
        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'is_active' => true,
        ]);
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => $role->name . ' User',
            'email' => 'user' . $counter++ . '@example.com',
            'password' => 'Password123!',
            'phone' => '9999999999',
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    protected function assignLeadToUser(int $leadId, int $assignedTo, int $assignedBy): void
    {
        DB::table('lead_assignments')->insert([
            'lead_id' => $leadId,
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assignment_method' => 'manual',
            'assigned_at' => now(),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
