<?php

namespace Tests\Feature;

use App\Models\AsmCnpAutomationConfig;
use App\Models\Role;
use App\Models\SourceAutomationRule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmAutomationIndexTest extends TestCase
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

    public function test_crm_automation_index_shows_new_lead_sla_card(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['name' => 'CRM User']);

        $response = $this->actingAs($crm)->get(route('crm.automation.index'));

        $response->assertOk();
        $response->assertSeeText('New Lead Response SLA');
        $response->assertSee(route('crm.automation.sla.index'), false);
        $response->assertSeeText('IVR Lead Distribution');
        $response->assertSee(route('crm.automation.ivr.index'), false);
    }

    public function test_admin_automation_index_shows_new_lead_sla_card(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Admin User']);

        $response = $this->actingAs($admin)->get(route('admin.automation.index'));

        $response->assertOk();
        $response->assertSeeText('New Lead Response SLA');
        $response->assertSee(route('crm.automation.sla.index'), false);
        $response->assertSeeText('Junk / Not Interested Cleanup');
        $response->assertSeeText('Always Active');
        $response->assertSee(route('admin.other-leads.index'), false);
        $response->assertSeeText('IVR Receiver Assignment');
        $response->assertSee(route('crm.automation.ivr.index'), false);
    }

    public function test_admin_can_turn_cnp_automation_off_and_back_on_from_card(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Admin User']);
        $config = AsmCnpAutomationConfig::create([
            'name' => 'ASM CNP Automation',
            'is_enabled' => true,
            'is_active' => true,
            'create_retry_tasks' => true,
            'transfer_rule_mode' => 'count_only',
            'retry_delay_minutes' => 5,
            'max_cnp_attempts' => 4,
            'fallback_routing' => 'round_robin',
        ]);

        $this->actingAs($admin)->get(route('admin.automation.index'))
            ->assertOk()
            ->assertSeeText('Turn Off Transfer')
            ->assertSee(route('admin.automation.cnp.toggle'), false);

        $this->actingAs($admin)->post(route('admin.automation.cnp.toggle'), ['enabled' => 0])
            ->assertRedirect(route('admin.automation.index'))
            ->assertSessionHas('success', 'ASM CNP auto transfer turned off. CNP marking and retry remain active.');

        $config->refresh();
        $this->assertTrue($config->is_enabled);
        $this->assertFalse($config->is_active);
        $this->assertSame($admin->id, $config->updated_by);

        $this->actingAs($admin)->get(route('admin.automation.index'))
            ->assertOk()
            ->assertSeeText('Turn On Transfer');
    }

    public function test_admin_automation_index_shows_99acres_distribution_card(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Admin User']);

        $response = $this->actingAs($admin)->get(route('admin.automation.index'));

        $response->assertOk();
        $response->assertSeeText('99acres Lead Distribution');
        $response->assertSee(route('admin.automation.99acres.edit'), false);
    }

    public function test_admin_can_save_99acres_percentage_distribution_rule(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);
        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $firstUser = $this->createUser($salesRole, ['name' => 'Sales One']);
        $secondUser = $this->createUser($salesRole, ['name' => 'Sales Two']);

        $response = $this->actingAs($admin)->post(route('admin.automation.99acres.update'), [
            'is_active' => '1',
            'auto_create_task' => '1',
            'assignment_method' => 'percentage',
            'daily_limit' => 50,
            'users' => [
                ['user_id' => $firstUser->id, 'percentage' => 60, 'daily_limit' => 30],
                ['user_id' => $secondUser->id, 'percentage' => 40, 'daily_limit' => 20],
            ],
        ]);

        $response->assertRedirect(route('admin.automation.99acres.edit'));

        $this->assertDatabaseHas('source_automation_rules', [
            'source' => '99acres',
            'assignment_method' => 'percentage',
            'daily_limit' => 50,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('source_automation_rule_users', [
            'user_id' => $firstUser->id,
            'percentage' => 60,
            'daily_limit' => 30,
        ]);
        $this->assertDatabaseHas('source_automation_rule_users', [
            'user_id' => $secondUser->id,
            'percentage' => 40,
            'daily_limit' => 20,
        ]);
    }

    public function test_admin_can_create_source_linked_wizard_rule_with_defaults(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);
        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $salesUser = $this->createUser($salesRole, ['name' => 'Sales One']);

        $response = $this->actingAs($admin)->post(route('admin.automation.store'), [
            'source_type' => 'website',
            'source_id' => '17',
            'source_label' => 'Landing Page A',
            'distribution_method' => 'round_robin',
            'task_enabled' => '1',
            'notification_enabled' => '1',
            'skip_lead_off_users' => '1',
            'duplicate_handling' => 'keep_existing_owner_mark_reenquiry',
            'users' => [
                ['user_id' => $salesUser->id, 'daily_limit' => 25],
            ],
        ]);

        $response->assertRedirect(route('admin.automation.index'));

        $this->assertDatabaseHas('source_automation_rules', [
            'name' => 'Landing Page A Automation',
            'source' => 'website',
            'source_type' => 'website',
            'source_id' => '17',
            'source_label' => 'Landing Page A',
            'assignment_method' => 'round_robin',
            'distribution_method' => 'round_robin',
            'auto_create_task' => true,
            'task_enabled' => true,
            'notification_enabled' => true,
            'skip_lead_off_users' => true,
            'duplicate_handling' => 'keep_existing_owner_mark_reenquiry',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_single_user_rule_from_wizard_payload(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);
        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $salesUser = $this->createUser($salesRole, ['name' => 'Sales One']);

        $response = $this->actingAs($admin)->post(route('admin.automation.store'), [
            'source_type' => 'website',
            'source_label' => 'Residential Leads',
            'distribution_method' => 'single_user',
            'single_user_id' => $salesUser->id,
            'users' => [['user_id' => '']],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.automation.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('source_automation_rules', [
            'name' => 'Residential Leads Automation',
            'assignment_method' => 'single_user',
            'single_user_id' => $salesUser->id,
        ]);
    }

    public function test_ad_manager_cannot_attach_form_owned_by_another_active_rule(): void
    {
        $adManager = $this->createUser($this->createRole(Role::AD_MANAGER), ['name' => 'Ad Manager']);
        $salesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['name' => 'Sales User']);
        $form = $this->createFacebookForm('BASE | Apartments');

        SourceAutomationRule::create([
            'name' => 'Apartments (1)',
            'source' => 'facebook_lead_ads',
            'source_type' => 'facebook_lead_ads',
            'source_id' => (string) $form->id,
            'fb_form_id' => $form->id,
            'assignment_method' => 'round_robin',
            'distribution_method' => 'round_robin',
            'is_active' => true,
            'created_by' => $adManager->id,
        ]);

        $response = $this->actingAs($adManager)->post(route('ad-manager.automation.store'), [
            'name' => 'Residential Lead Distribution',
            'source_type' => 'facebook_lead_ads',
            'fb_form_ids' => [$form->id],
            'assignment_method' => 'round_robin',
            'is_active' => '1',
            'users' => [
                ['user_id' => $salesUser->id],
            ],
        ]);

        $response->assertSessionHasErrors('fb_form_ids');
        $this->assertDatabaseMissing('source_automation_rules', [
            'name' => 'Residential Lead Distribution',
        ]);
    }

    public function test_ad_manager_can_attach_form_from_disabled_old_rule_to_new_multi_form_rule(): void
    {
        $adManager = $this->createUser($this->createRole(Role::AD_MANAGER), ['name' => 'Ad Manager']);
        $salesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['name' => 'Sales User']);
        $firstForm = $this->createFacebookForm('BASE | Apartments');
        $secondForm = $this->createFacebookForm('BASE | RESIDENTIAL');

        SourceAutomationRule::create([
            'name' => 'Apartments (1)',
            'source' => 'facebook_lead_ads',
            'source_type' => 'facebook_lead_ads',
            'source_id' => (string) $firstForm->id,
            'fb_form_id' => $firstForm->id,
            'assignment_method' => 'round_robin',
            'distribution_method' => 'round_robin',
            'is_active' => false,
            'created_by' => $adManager->id,
        ]);

        $response = $this->actingAs($adManager)->post(route('ad-manager.automation.store'), [
            'name' => 'Residential Lead Distribution',
            'source_type' => 'facebook_lead_ads',
            'fb_form_ids' => [$firstForm->id, $secondForm->id],
            'assignment_method' => 'round_robin',
            'is_active' => '1',
            'users' => [
                ['user_id' => $salesUser->id],
            ],
        ]);

        $response->assertRedirect(route('ad-manager.automation.index'));
        $rule = SourceAutomationRule::where('name', 'Residential Lead Distribution')->firstOrFail();

        $this->assertSame($firstForm->id, (int) $rule->fb_form_id);
        $this->assertDatabaseHas('source_automation_rule_forms', [
            'rule_id' => $rule->id,
            'fb_form_id' => $firstForm->id,
        ]);
        $this->assertDatabaseHas('source_automation_rule_forms', [
            'rule_id' => $rule->id,
            'fb_form_id' => $secondForm->id,
        ]);
    }

    public function test_admin_can_move_integrated_form_into_existing_grouped_automation(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Admin User']);
        $oldForm = $this->createFacebookForm('Old Residential Form');
        $groupForm = $this->createFacebookForm('Grouped Residential Form');

        $oldRule = SourceAutomationRule::create([
            'name' => 'Old One Form Rule',
            'source' => 'facebook_lead_ads',
            'source_type' => 'facebook_lead_ads',
            'source_id' => (string) $oldForm->id,
            'fb_form_id' => $oldForm->id,
            'assignment_method' => 'round_robin',
            'distribution_method' => 'round_robin',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $targetRule = SourceAutomationRule::create([
            'name' => 'Residential Group',
            'source' => 'facebook_lead_ads',
            'source_type' => 'facebook_lead_ads',
            'source_id' => (string) $groupForm->id,
            'fb_form_id' => $groupForm->id,
            'assignment_method' => 'round_robin',
            'distribution_method' => 'round_robin',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $targetRule->fbForms()->attach($groupForm->id);

        $response = $this->actingAs($admin)
            ->from(route('integrations.facebook-lead-ads.forms'))
            ->post(route('integrations.facebook-lead-ads.forms.automation', $oldForm), [
                'automation_rule_id' => $targetRule->id,
            ]);

        $response->assertRedirect(route('integrations.facebook-lead-ads.forms'));
        $this->assertDatabaseHas('source_automation_rule_forms', [
            'rule_id' => $targetRule->id,
            'fb_form_id' => $oldForm->id,
        ]);
        $this->assertDatabaseHas('source_automation_rules', [
            'id' => $oldRule->id,
            'is_active' => false,
        ]);
    }

    public function test_99acres_percentage_distribution_requires_total_100(): void
    {
        $adminRole = $this->createRole(Role::ADMIN);
        $salesRole = $this->createRole(Role::SALES_EXECUTIVE);
        $admin = $this->createUser($adminRole, ['name' => 'Admin User']);
        $firstUser = $this->createUser($salesRole, ['name' => 'Sales One']);

        $response = $this->actingAs($admin)->from(route('admin.automation.99acres.edit'))->post(route('admin.automation.99acres.update'), [
            'is_active' => '1',
            'assignment_method' => 'percentage',
            'users' => [
                ['user_id' => $firstUser->id, 'percentage' => 50],
            ],
        ]);

        $response->assertRedirect(route('admin.automation.99acres.edit'));
        $response->assertSessionHasErrors('percentage_total');
        $this->assertSame(0, SourceAutomationRule::where('source', '99acres')->count());
    }

    public function test_crm_layout_automation_link_points_to_crm_automation_index(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['name' => 'CRM User']);

        $response = $this->actingAs($crm)->get(route('crm.automation.index'));

        $response->assertOk();
        $response->assertSee(route('crm.automation.index'), false);
    }

    public function test_paused_automation_shows_resume_and_can_be_resumed(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), ['name' => 'Admin User']);
        $rule = SourceAutomationRule::create([
            'name' => 'Paused Website Rule',
            'source' => 'website',
            'source_type' => 'website',
            'assignment_method' => 'round_robin',
            'distribution_method' => 'round_robin',
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        $html = view('admin.automation.partials.rule-card', [
            'rule' => $rule->load(['users.user', 'fbForm', 'singleUser']),
        ])->render();

        $this->assertStringContainsString('Paused Website Rule', $html);
        $this->assertStringContainsString('Resume', $html);

        $this->actingAs($admin)
            ->postJson(route('admin.automation.toggle', $rule))
            ->assertOk()
            ->assertJson(['is_active' => true]);

        $this->assertTrue($rule->fresh()->is_active);
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('login_security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
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
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('new_lead_sla_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('source')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sla_minutes')->default(240);
            $table->time('business_start_time');
            $table->time('business_end_time');
            $table->boolean('weekends_off')->default(true);
            $table->unsignedTinyInteger('max_transfer_attempts')->default(3);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('dashboard_alert_enabled')->default(true);
            $table->boolean('skip_inactive_users')->default(true);
            $table->boolean('skip_absent_users')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('create_retry_tasks')->default(true);
            $table->string('transfer_rule_mode')->default('count_only');
            $table->unsignedInteger('retry_delay_minutes')->default(5);
            $table->unsignedInteger('transfer_window_hours')->nullable();
            $table->unsignedInteger('max_cnp_attempts')->default(4);
            $table->string('fallback_routing')->default('round_robin');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_user_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('current_assignee_id')->nullable();
            $table->unsignedBigInteger('current_assignment_id')->nullable();
            $table->unsignedBigInteger('original_assignee_id')->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('sla_started_at')->nullable();
            $table->timestamp('sla_deadline_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('event_type');
            $table->json('meta')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_name')->nullable();
            $table->string('name')->nullable();
            $table->string('page_id')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_page_id')->nullable();
            $table->string('form_name')->nullable();
            $table->string('name')->nullable();
            $table->string('form_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('source_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_label')->nullable();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->unsignedBigInteger('google_sheet_config_id')->nullable();
            $table->string('assignment_method')->nullable();
            $table->string('distribution_method')->nullable();
            $table->unsignedBigInteger('single_user_id')->nullable();
            $table->boolean('auto_create_task')->default(true);
            $table->boolean('task_enabled')->default(true);
            $table->boolean('notification_enabled')->default(true);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->boolean('skip_lead_off_users')->default(true);
            $table->string('duplicate_handling')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('source_automation_rule_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('percentage', 8, 2)->nullable();
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

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->default('primary');
            $table->string('assignment_method')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
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
            'email' => 'crm-automation-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createFacebookForm(string $name)
    {
        $pageId = DB::table('fb_pages')->insertGetId([
            'page_name' => 'Base Infra Solutions',
            'name' => 'Base Infra Solutions',
            'page_id' => 'page-' . str()->random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $formId = DB::table('fb_forms')->insertGetId([
            'fb_page_id' => $pageId,
            'form_name' => $name,
            'name' => $name,
            'form_id' => 'form-' . str()->random(6),
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return \App\Models\FbForm::findOrFail($formId);
    }
}
