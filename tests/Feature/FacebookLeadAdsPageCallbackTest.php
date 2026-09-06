<?php

namespace Tests\Feature;

use App\Jobs\FetchFacebookLeadDetailsJob;
use App\Jobs\ScanMetaBulkRecoveryLeadsJob;
use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\FbLeadAdsSettings;
use App\Models\FbPage;
use App\Models\Lead;
use App\Models\MetaBulkRecoveryScan;
use App\Models\MetaBulkRecoveryScanLead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FacebookLeadAdsPageCallbackTest extends TestCase
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
        Config::set('session.driver', 'array');
        Config::set('logging.default', 'errorlog');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_page_detail_returns_page_forms_leads_and_webhooks(): void
    {
        $admin = $this->createUser();
        [$page, $form] = $this->createMetaPageAndForm();
        $lead = Lead::create([
            'name' => 'Existing Buyer',
            'phone' => '919999999999',
            'source' => 'facebook_lead_ads',
            'status' => 'new',
            'created_by' => $admin->id,
        ]);
        FbLead::create([
            'leadgen_id' => 'lead-existing',
            'fb_form_id' => $form->id,
            'crm_lead_id' => $lead->id,
            'field_data_json' => ['full_name' => 'Existing Buyer', 'phone_number' => '9999999999'],
            'raw_response_json' => ['id' => 'lead-existing'],
        ]);
        DB::table('fb_webhook_events')->insert([
            'leadgen_id' => 'lead-existing',
            'status' => 'processed',
            'raw_payload' => json_encode(['entry' => [['changes' => [['value' => ['form_id' => $form->form_id]]]]]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'graph.facebook.com/*/page-1/leadgen_forms*' => Http::response(['data' => [['id' => 'form-1', 'name' => 'Fest Form']]], 200),
        ]);

        $response = $this->actingAs($admin)->getJson(route('integrations.facebook-lead-ads.pages.detail', $page));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.page.page_id', 'page-1')
            ->assertJsonPath('data.stats.forms', 1)
            ->assertJsonPath('data.stats.active', 1)
            ->assertJsonPath('data.recent_leads.0.leadgen_id', 'lead-existing')
            ->assertJsonPath('data.webhook_events.0.status', 'processed');
    }

    public function test_page_callback_imports_missing_leads_and_skips_existing_leadgen_ids(): void
    {
        $admin = $this->createUser();
        [$page, $form] = $this->createMetaPageAndForm();
        Queue::fake();

        FbLead::create([
            'leadgen_id' => 'lead-existing',
            'fb_form_id' => $form->id,
            'field_data_json' => ['phone_number' => '9999999999'],
            'raw_response_json' => ['id' => 'lead-existing'],
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/form-1/leads')) {
                return Http::response([
                    'data' => [
                        ['id' => 'lead-existing', 'created_time' => '2026-08-10T08:00:00+0000'],
                        ['id' => 'lead-new', 'created_time' => '2026-08-10T09:00:00+0000'],
                    ],
                ], 200);
            }

            if (str_contains($url, '/lead-new')) {
                return Http::response([
                    'id' => 'lead-new',
                    'created_time' => '2026-08-10T09:00:00+0000',
                    'form_id' => 'form-1',
                    'field_data' => [
                        ['name' => 'full_name', 'values' => ['Fresh Buyer']],
                        ['name' => 'phone_number', 'values' => ['9876543210']],
                        ['name' => 'email', 'values' => ['fresh@example.test']],
                    ],
                ], 200);
            }

            if (str_contains($url, '/page-1/leadgen_forms')) {
                return Http::response(['data' => [['id' => 'form-1', 'name' => 'Fest Form']]], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($admin)->postJson(route('integrations.facebook-lead-ads.pages.callback', $page), [
            'limit' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.fetched', 2)
            ->assertJsonPath('summary.already_present', 1)
            ->assertJsonPath('summary.queued', 1)
            ->assertJsonPath('summary.imported', 0)
            ->assertJsonPath('summary.failed', 0);

        Queue::assertPushed(FetchFacebookLeadDetailsJob::class, function (FetchFacebookLeadDetailsJob $job) use ($form) {
            return $job->leadgenId === 'lead-new' && $job->fbFormId === $form->id && $job->autoAssign === false;
        });
        $this->assertDatabaseMissing('fb_leads', ['leadgen_id' => 'lead-new']);
    }

    public function test_form_callback_preview_lists_importable_leads_without_queueing_jobs(): void
    {
        $admin = $this->createUser();
        [, $form] = $this->createMetaPageAndForm();
        Queue::fake();

        FbLead::create([
            'leadgen_id' => 'lead-existing',
            'fb_form_id' => $form->id,
            'field_data_json' => ['phone_number' => '9999999999'],
            'raw_response_json' => ['id' => 'lead-existing'],
        ]);

        Http::fake([
            'graph.facebook.com/*/form-1/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'lead-existing',
                        'created_time' => '2026-08-10T08:00:00+0000',
                        'field_data' => [['name' => 'full_name', 'values' => ['Existing Buyer']]],
                    ],
                    [
                        'id' => 'lead-new',
                        'created_time' => '2026-08-10T09:00:00+0000',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Fresh Buyer']],
                            ['name' => 'phone_number', 'values' => ['9876543210']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('integrations.facebook-lead-ads.forms.callback', $form), [
            'action' => 'preview',
            'limit' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('mode', 'preview')
            ->assertJsonPath('summary.fetched', 2)
            ->assertJsonPath('summary.importable', 1)
            ->assertJsonPath('summary.already_present', 1)
            ->assertJsonPath('leads.1.name', 'Fresh Buyer')
            ->assertJsonPath('leads.1.phone', '9876543210');

        Queue::assertNothingPushed();
    }

    public function test_form_callback_confirm_import_queues_jobs_without_auto_assignment(): void
    {
        $admin = $this->createUser();
        [, $form] = $this->createMetaPageAndForm();
        Queue::fake();

        Http::fake([
            'graph.facebook.com/*/form-1/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'lead-new',
                        'created_time' => '2026-08-10T09:00:00+0000',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Fresh Buyer']],
                            ['name' => 'phone_number', 'values' => ['9876543210']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('integrations.facebook-lead-ads.forms.callback', $form), [
            'action' => 'import',
            'limit' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('mode', 'import')
            ->assertJsonPath('summary.fetched', 1)
            ->assertJsonPath('summary.queued', 1);

        Queue::assertPushed(FetchFacebookLeadDetailsJob::class, function (FetchFacebookLeadDetailsJob $job) use ($form) {
            return $job->leadgenId === 'lead-new' && $job->fbFormId === $form->id && $job->autoAssign === false;
        });
    }

    public function test_bulk_recovery_scan_stores_preview_rows_without_queueing_import_jobs(): void
    {
        $admin = $this->createUser();
        [, $form] = $this->createMetaPageAndForm();

        FbLead::create([
            'leadgen_id' => 'lead-existing',
            'fb_form_id' => $form->id,
            'field_data_json' => ['phone_number' => '9999999999'],
            'raw_response_json' => ['id' => 'lead-existing'],
        ]);

        Http::fake([
            'graph.facebook.com/*/form-1/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'lead-existing',
                        'created_time' => '2026-08-10T08:00:00+0000',
                        'field_data' => [['name' => 'full_name', 'values' => ['Existing Buyer']]],
                    ],
                    [
                        'id' => 'lead-new',
                        'created_time' => '2026-08-10T09:00:00+0000',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Fresh Buyer']],
                            ['name' => 'phone_number', 'values' => ['9876543210']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Queue::fake([FetchFacebookLeadDetailsJob::class]);

        $scan = MetaBulkRecoveryScan::create([
            'status' => 'queued',
            'scope_type' => 'all',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-18',
            'per_form_limit' => 50,
            'total_limit' => 500,
            'started_by' => $admin->id,
        ]);

        (new ScanMetaBulkRecoveryLeadsJob($scan->id))->handle();

        $this->assertDatabaseHas('meta_bulk_recovery_scans', [
            'id' => $scan->id,
            'status' => 'completed',
            'fetched_count' => 2,
            'importable_count' => 1,
            'already_present_count' => 1,
        ]);
        $this->assertDatabaseHas('meta_bulk_recovery_scan_leads', [
            'scan_id' => $scan->id,
            'leadgen_id' => 'lead-new',
            'name' => 'Fresh Buyer',
            'phone' => '9876543210',
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('meta_bulk_recovery_scan_leads', [
            'scan_id' => $scan->id,
            'leadgen_id' => 'lead-existing',
            'status' => 'already_present',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_bulk_recovery_import_queues_selected_ready_leads_without_auto_assignment(): void
    {
        $admin = $this->createUser();
        [, $form] = $this->createMetaPageAndForm();
        Queue::fake();

        $scan = MetaBulkRecoveryScan::create([
            'status' => 'completed',
            'scope_type' => 'all',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-18',
            'per_form_limit' => 50,
            'total_limit' => 500,
            'started_by' => $admin->id,
        ]);
        $scanFormId = DB::table('meta_bulk_recovery_scan_forms')->insertGetId([
            'scan_id' => $scan->id,
            'fb_page_id' => $form->fb_page_id,
            'fb_form_id' => $form->id,
            'status' => 'completed',
            'fetched_count' => 1,
            'importable_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = MetaBulkRecoveryScanLead::create([
            'scan_id' => $scan->id,
            'scan_form_id' => $scanFormId,
            'fb_form_id' => $form->id,
            'leadgen_id' => 'lead-new',
            'name' => 'Fresh Buyer',
            'phone' => '9876543210',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($admin)->post(route('integrations.facebook-lead-ads.bulk-recovery.import', $scan), [
            'import_mode' => 'selected',
            'lead_ids' => [$row->id],
        ]);

        $response->assertRedirect(route('integrations.facebook-lead-ads.bulk-recovery.show', $scan));
        $this->assertDatabaseHas('meta_bulk_recovery_scan_leads', [
            'id' => $row->id,
            'status' => 'queued',
        ]);
        Queue::assertPushed(FetchFacebookLeadDetailsJob::class, function (FetchFacebookLeadDetailsJob $job) use ($form, $row) {
            return $job->leadgenId === 'lead-new'
                && $job->fbFormId === $form->id
                && $job->autoAssign === false
                && $job->bulkRecoveryScanLeadId === $row->id;
        });
    }

    public function test_page_callback_returns_error_when_meta_api_fails(): void
    {
        $admin = $this->createUser();
        [$page] = $this->createMetaPageAndForm();

        Http::fake([
            'graph.facebook.com/*/form-1/leads*' => Http::response(['error' => ['message' => 'Token expired']], 400),
            'graph.facebook.com/*/page-1/leadgen_forms*' => Http::response(['data' => [['id' => 'form-1']]], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('integrations.facebook-lead-ads.pages.callback', $page));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.failed', 1)
            ->assertJsonPath('forms.0.status', 'failed');
    }

    private function createMetaPageAndForm(): array
    {
        FbLeadAdsSettings::create([
            'page_access_token' => 'page-token',
            'graph_version' => 'v18.0',
        ]);

        $page = FbPage::create([
            'page_id' => 'page-1',
            'page_name' => 'Big Billion Property Fest',
            'page_access_token' => 'page-token',
        ]);

        $form = FbForm::create([
            'fb_page_id' => $page->id,
            'form_id' => 'form-1',
            'form_name' => 'Fest Form',
            'is_enabled' => true,
        ]);

        DB::table('fb_form_mappings')->insert([
            'fb_form_id' => $form->id,
            'mapping_json' => json_encode([
                'full_name' => 'name',
                'phone_number' => 'phone',
                'email' => 'email',
            ]),
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$page, $form];
    }

    private function createUser(): User
    {
        $role = Role::create([
            'name' => 'Admin',
            'slug' => Role::ADMIN,
            'is_active' => true,
        ]);

        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
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
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fb_lead_ads_settings', function (Blueprint $table) {
            $table->id();
            $table->text('page_access_token')->nullable();
            $table->text('marketing_access_token')->nullable();
            $table->string('ad_account_id')->nullable();
            $table->boolean('cpl_sync_enabled')->default(false);
            $table->timestamp('last_cpl_synced_at')->nullable();
            $table->string('page_id')->nullable();
            $table->string('graph_version')->default('v18.0');
            $table->string('webhook_verify_token')->nullable();
            $table->string('app_secret')->nullable();
            $table->boolean('signature_verification_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('fb_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_id')->unique();
            $table->string('page_name')->nullable();
            $table->text('page_access_token')->nullable();
            $table->string('token_reference')->nullable();
            $table->unsignedBigInteger('facebook_portfolio_id')->nullable();
            $table->timestamps();
        });

        Schema::create('facebook_portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fb_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_page_id')->nullable();
            $table->string('form_id')->unique();
            $table->string('form_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('fb_form_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_form_id');
            $table->text('mapping_json');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_leads', function (Blueprint $table) {
            $table->id();
            $table->string('leadgen_id')->unique();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->string('ad_id')->nullable();
            $table->string('ad_name')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('adset_name')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('platform')->nullable();
            $table->timestamp('meta_created_time')->nullable();
            $table->text('field_data_json')->nullable();
            $table->text('raw_response_json')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->text('raw_payload')->nullable();
            $table->string('leadgen_id')->nullable();
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->boolean('is_dead')->default(false);
            $table->boolean('is_reenquiry')->default(false);
            $table->unsignedInteger('reenquiry_count')->default(0);
            $table->timestamp('last_reenquiry_at')->nullable();
            $table->string('last_reenquiry_source')->nullable();
            $table->unsignedBigInteger('last_reenquiry_fb_form_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('lead_form_field_id')->nullable();
            $table->text('value')->nullable();
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

        Schema::create('source_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('source_automation_rule_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('meta_bulk_recovery_scans', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('queued');
            $table->string('scope_type')->default('all');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('per_form_limit')->default(50);
            $table->unsignedInteger('total_limit')->default(500);
            $table->unsignedInteger('forms_total')->default(0);
            $table->unsignedInteger('forms_scanned')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('importable_count')->default(0);
            $table->unsignedInteger('already_present_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_bulk_recovery_scan_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scan_id');
            $table->unsignedBigInteger('fb_page_id')->nullable();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('importable_count')->default(0);
            $table->unsignedInteger('already_present_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_bulk_recovery_scan_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scan_id');
            $table->unsignedBigInteger('scan_form_id');
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->string('leadgen_id');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('meta_created_time')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('ad_name')->nullable();
            $table->text('raw_meta_json')->nullable();
            $table->string('status')->default('ready');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
}
