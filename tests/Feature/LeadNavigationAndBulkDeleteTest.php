<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadNavigationAndBulkDeleteTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_index_links_to_lead_detail_with_plain_leads_back_url(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $lead = $this->createLead(['name' => 'Back Link Lead']);

        $response = $this->actingAs($admin)->get(route('leads.index'));

        $response->assertOk();
        $response->assertSee(
            route('leads.show', ['lead' => $lead->id, 'back' => route('leads.index')]),
            false
        );
    }

    public function test_show_back_button_uses_plain_leads_index(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $lead = $this->createLead(['name' => 'Show Back Lead']);

        $response = $this->actingAs($admin)->get(
            route('leads.show', ['lead' => $lead->id, 'back' => route('leads.index')])
        );

        $response->assertOk();
        $response->assertSee('href="' . route('leads.index') . '"', false);
    }

    public function test_admin_can_bulk_delete_selected_leads(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));
        $leadA = $this->createLead(['name' => 'Delete A']);
        $leadB = $this->createLead(['name' => 'Delete B']);

        $response = $this->actingAs($admin)->delete(route('leads.bulk-delete'), [
            'ids' => [$leadA->id, $leadB->id],
        ]);

        $response->assertRedirect(route('leads.index'));
        $this->assertSoftDeleted('leads', ['id' => $leadA->id]);
        $this->assertSoftDeleted('leads', ['id' => $leadB->id]);
    }

    public function test_non_admin_cannot_bulk_delete_leads(): void
    {
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive'));
        $lead = $this->createLead(['name' => 'Protected Lead']);

        $response = $this->actingAs($executive)->delete(route('leads.bulk-delete'), [
            'ids' => [$lead->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'deleted_at' => null,
        ]);
    }

    public function test_bulk_delete_requires_at_least_one_lead_id(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));

        $response = $this->actingAs($admin)
            ->from(route('leads.index'))
            ->delete(route('leads.bulk-delete'), [
                'ids' => [],
            ]);

        $response->assertRedirect(route('leads.index'));
        $response->assertSessionHasErrors('ids');
    }

    public function test_crm_can_filter_leads_by_today_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 10:00:00'));

        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $todayLead = $this->createLead(['name' => 'Today Lead']);
        $yesterdayLead = $this->createLead(['name' => 'Yesterday Lead']);

        DB::table('leads')->where('id', $todayLead->id)->update([
            'created_at' => now()->copy()->subHour(),
            'updated_at' => now()->copy()->subHour(),
        ]);

        DB::table('leads')->where('id', $yesterdayLead->id)->update([
            'created_at' => now()->copy()->subDay(),
            'updated_at' => now()->copy()->subDay(),
        ]);

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'date_range' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Today Lead');
        $response->assertDontSee('Yesterday Lead');
    }

    public function test_crm_new_status_filter_excludes_cnp_leads_even_for_today_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 10:00:00'));

        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $newLead = $this->createLead([
            'name' => 'Today New Lead',
            'status' => 'new',
            'cnp_count' => 0,
        ]);
        $cnpLead = $this->createLead([
            'name' => 'Today CNP Lead',
            'status' => 'new',
            'cnp_count' => 1,
        ]);

        DB::table('leads')->whereIn('id', [$newLead->id, $cnpLead->id])->update([
            'created_at' => now()->copy()->subHour(),
            'updated_at' => now()->copy()->subHour(),
        ]);

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'date_range' => 'today',
            'status' => 'new',
        ]));

        $response->assertOk();
        $response->assertSee('Today New Lead');
        $response->assertDontSee('Today CNP Lead');
    }

    public function test_crm_can_filter_leads_by_custom_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 10:00:00'));

        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $inRangeLead = $this->createLead(['name' => 'In Range Lead']);
        $outOfRangeLead = $this->createLead(['name' => 'Out Of Range Lead']);

        DB::table('leads')->where('id', $inRangeLead->id)->update([
            'created_at' => Carbon::parse('2026-03-12 09:00:00'),
            'updated_at' => Carbon::parse('2026-03-12 09:00:00'),
        ]);

        DB::table('leads')->where('id', $outOfRangeLead->id)->update([
            'created_at' => Carbon::parse('2026-03-25 09:00:00'),
            'updated_at' => Carbon::parse('2026-03-25 09:00:00'),
        ]);

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'date_range' => 'custom',
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-15',
        ]));

        $response->assertOk();
        $response->assertSee('In Range Lead');
        $response->assertDontSee('Out Of Range Lead');
    }

    public function test_crm_can_filter_leads_by_this_year_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 10:00:00'));

        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $thisYearLead = $this->createLead(['name' => 'This Year Lead']);
        $lastYearLead = $this->createLead(['name' => 'Last Year Lead']);

        DB::table('leads')->where('id', $thisYearLead->id)->update([
            'created_at' => Carbon::parse('2026-01-15 09:00:00'),
            'updated_at' => Carbon::parse('2026-01-15 09:00:00'),
        ]);

        DB::table('leads')->where('id', $lastYearLead->id)->update([
            'created_at' => Carbon::parse('2025-12-31 09:00:00'),
            'updated_at' => Carbon::parse('2025-12-31 09:00:00'),
        ]);

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'date_range' => 'this_year',
        ]));

        $response->assertOk();
        $response->assertSee('This Year Lead');
        $response->assertDontSee('Last Year Lead');
    }

    public function test_crm_activity_status_date_range_uses_meeting_and_visit_dates_not_lead_created_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00'));

        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $inRangeVisitLead = $this->createLead([
            'name' => 'Old Followup Lead With August Visit',
            'status' => 'follow_up',
            'is_hiring_candidate' => false,
            'created_at' => Carbon::parse('2026-07-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-08-15 09:00:00'),
        ]);
        $outOfRangeVisitLead = $this->createLead([
            'name' => 'Old Lead With July Visit',
            'status' => 'follow_up',
            'is_hiring_candidate' => false,
            'created_at' => Carbon::parse('2026-07-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-20 09:00:00'),
        ]);

        DB::table('site_visits')->insert([
            [
                'lead_id' => $inRangeVisitLead->id,
                'status' => 'completed',
                'completed_at' => Carbon::parse('2026-08-12 12:00:00'),
                'created_at' => Carbon::parse('2026-08-12 11:00:00'),
                'updated_at' => Carbon::parse('2026-08-12 12:00:00'),
            ],
            [
                'lead_id' => $outOfRangeVisitLead->id,
                'status' => 'completed',
                'completed_at' => Carbon::parse('2026-07-20 12:00:00'),
                'created_at' => Carbon::parse('2026-07-20 11:00:00'),
                'updated_at' => Carbon::parse('2026-07-20 12:00:00'),
            ],
        ]);

        DB::table('lead_assignments')->insert([
            [
                'lead_id' => $inRangeVisitLead->id,
                'assigned_to' => $crm->id,
                'assigned_at' => Carbon::parse('2026-08-01 09:00:00'),
                'is_active' => true,
                'created_at' => Carbon::parse('2026-08-01 09:00:00'),
                'updated_at' => Carbon::parse('2026-08-01 09:00:00'),
            ],
            [
                'lead_id' => $outOfRangeVisitLead->id,
                'assigned_to' => $crm->id,
                'assigned_at' => Carbon::parse('2026-08-01 09:00:00'),
                'is_active' => true,
                'created_at' => Carbon::parse('2026-08-01 09:00:00'),
                'updated_at' => Carbon::parse('2026-08-01 09:00:00'),
            ],
        ]);

        $this->assertSame(1, Lead::whereHas('siteVisits', function ($visitQuery) {
            $visitQuery->where('status', 'completed')
                ->whereBetween('completed_at', [
                    Carbon::parse('2026-08-01')->startOfDay(),
                    Carbon::parse('2026-08-31')->endOfDay(),
                ]);
        })->count());
        $this->assertSame(1, Lead::visibleInAllLeadsInventory()
            ->where(function ($leadQuery) {
                $leadQuery->where('is_hiring_candidate', false)
                    ->orWhereNull('is_hiring_candidate');
            })
            ->whereHas('siteVisits', function ($visitQuery) {
                $visitQuery->where('status', 'completed')
                    ->whereBetween('completed_at', [
                        Carbon::parse('2026-08-01')->startOfDay(),
                        Carbon::parse('2026-08-31')->endOfDay(),
                    ]);
            })
            ->count());

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'date_range' => 'this_month',
            'status' => ['meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done'],
            'lead_group' => 'sales',
        ]));

        $response->assertOk();
        $leadNames = $response->viewData('leads')->getCollection()->pluck('name')->all();

        $this->assertContains('Old Followup Lead With August Visit', $leadNames);
        $this->assertNotContains('Old Lead With July Visit', $leadNames);
    }

    public function test_crm_create_page_shows_whatsapp_source_option_and_accepts_it_on_store(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));

        $createResponse = $this->actingAs($crm)->get(route('leads.create'));

        $createResponse->assertOk();
        $createResponse->assertSee('<option value="whatsapp"', false);
        $createResponse->assertSee('WhatsApp');

        $storeResponse = $this->actingAs($crm)->post(route('leads.store'), [
            'name' => 'WhatsApp Lead',
            'phone' => '9999999999',
            'source' => 'whatsapp',
        ]);

        $lead = Lead::query()->where('name', 'WhatsApp Lead')->first();

        $storeResponse->assertRedirect(route('leads.show', $lead?->id));
        $this->assertNotNull($lead);
        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame('WhatsApp', $lead->source_label);
    }

    public function test_admin_forms_index_shows_add_lead_form_name(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));

        $response = $this->actingAs($admin)->get(route('admin.forms.index'));

        $response->assertOk();
        $response->assertSee('Add Lead Form');
        $response->assertDontSee('Lead Form (Standard)');
    }

    public function test_admin_can_preview_closer_kyc_form_schema(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));

        $response = $this->actingAs($admin)->get(route('admin.forms.existing-preview', [
            'formPath' => 'closer.kyc',
        ]));

        $response->assertOk();
        $response->assertSee('Closer KYC Form Builder');
        $response->assertSee('Unit Type');
    }

    public function test_create_page_uses_published_dynamic_form_labels_for_add_lead(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN, 'Admin'));

        $form = DynamicForm::create([
            'name' => 'Add Lead Form',
            'slug' => 'add-lead-form',
            'location_path' => 'leads.create',
            'form_type' => 'lead',
            'settings' => [],
            'is_active' => true,
            'status' => 'published',
            'created_by' => $admin->id,
        ]);

        DynamicFormField::create([
            'form_id' => $form->id,
            'field_key' => 'name',
            'field_type' => 'text',
            'label' => 'Lead Full Name',
            'placeholder' => 'Enter custom lead name',
            'required' => true,
            'order' => 0,
            'section' => 'Basic Information',
        ]);

        DynamicFormField::create([
            'form_id' => $form->id,
            'field_key' => 'source',
            'field_type' => 'select',
            'label' => 'Acquisition Source',
            'required' => true,
            'order' => 1,
            'section' => 'Basic Information',
        ]);

        $response = $this->actingAs($admin)->get(route('leads.create'));

        $response->assertOk();
        $response->assertSee('Lead Full Name');
        $response->assertSee('Acquisition Source');
        $response->assertSee('placeholder="Enter custom lead name"', false);
    }

    public function test_crm_date_filter_preserves_query_values_in_toolbar_and_page_size_form(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 10:00:00'));

        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $agent = $this->createUser($this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive'), [
            'name' => 'Agent Filter',
        ]);
        $lead = $this->createLead([
            'name' => 'Preserved Lead',
            'status' => 'new',
            'source' => 'meta',
        ]);

        DB::table('leads')->where('id', $lead->id)->update([
            'created_at' => Carbon::parse('2026-03-12 11:00:00'),
            'updated_at' => Carbon::parse('2026-03-12 11:00:00'),
        ]);

        DB::table('lead_assignments')->insert([
            'lead_id' => $lead->id,
            'assigned_to' => $agent->id,
            'assigned_by' => $crm->id,
            'assigned_at' => Carbon::parse('2026-03-12 11:05:00'),
            'is_active' => true,
            'created_at' => Carbon::parse('2026-03-12 11:05:00'),
            'updated_at' => Carbon::parse('2026-03-12 11:05:00'),
        ]);

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'date_range' => 'custom',
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-15',
            'status' => 'new',
            'source' => 'meta',
            'assigned_to' => $agent->id,
            'search' => 'Preserved',
            'view' => 'list',
        ]));

        $response->assertOk();
        $response->assertSee('name="date_range" value="custom"', false);
        $response->assertSee('name="start_date" value="2026-03-10"', false);
        $response->assertSee('name="end_date" value="2026-03-15"', false);
        $response->assertSee('name="assigned_to" value="' . $agent->id . '"', false);
        $response->assertSee(route('leads.index', ['view' => 'list']), false);
    }

    public function test_assigned_to_filter_shows_current_owner_name_instead_of_historical_owner(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM, 'CRM'));
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $omkar = $this->createUser($salesExecutiveRole, [
            'name' => 'Omkar Yadav',
        ]);
        $naveen = $this->createUser($salesExecutiveRole, [
            'name' => 'Naveen Singh',
        ]);
        $mohit = $this->createUser($salesExecutiveRole, [
            'name' => 'Mohit Yadav',
        ]);

        $lead = $this->createLead([
            'name' => 'Filter Owner Lead',
            'status' => 'new',
            'source' => 'meta',
        ]);

        DB::table('lead_assignments')->insert([
            [
                'lead_id' => $lead->id,
                'assigned_to' => $mohit->id,
                'assigned_by' => $crm->id,
                'assigned_at' => Carbon::parse('2026-03-28 09:00:00'),
                'unassigned_at' => Carbon::parse('2026-03-29 09:00:00'),
                'is_active' => false,
                'created_at' => Carbon::parse('2026-03-28 09:00:00'),
                'updated_at' => Carbon::parse('2026-03-29 09:00:00'),
            ],
            [
                'lead_id' => $lead->id,
                'assigned_to' => $naveen->id,
                'assigned_by' => $crm->id,
                'assigned_at' => Carbon::parse('2026-03-29 10:00:00'),
                'unassigned_at' => Carbon::parse('2026-03-30 10:00:00'),
                'is_active' => false,
                'created_at' => Carbon::parse('2026-03-29 10:00:00'),
                'updated_at' => Carbon::parse('2026-03-30 10:00:00'),
            ],
            [
                'lead_id' => $lead->id,
                'assigned_to' => $omkar->id,
                'assigned_by' => $crm->id,
                'assigned_at' => Carbon::parse('2026-03-31 11:00:00'),
                'unassigned_at' => null,
                'is_active' => true,
                'created_at' => Carbon::parse('2026-03-31 11:00:00'),
                'updated_at' => Carbon::parse('2026-03-31 11:00:00'),
            ],
        ]);

        $response = $this->actingAs($crm)->get(route('leads.index', [
            'status' => 'new',
            'assigned_to' => $omkar->id,
            'view' => 'list',
        ]));

        $response->assertOk();
        $response->assertSee('Filter Owner Lead');
        $response->assertSee('<td style="font-size:12px;color:#374151;">Omkar Yadav</td>', false);
        $response->assertDontSee('<td style="font-size:12px;color:#374151;">Naveen Singh</td>', false);
        $response->assertDontSee('<td style="font-size:12px;color:#374151;">Mohit Yadav</td>', false);
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamps();
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

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->boolean('is_hiring_candidate')->default(false);
            $table->unsignedInteger('cnp_count')->default(0);
            $table->timestamp('next_followup_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->unsignedBigInteger('filled_by_user_id')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->date('date_of_visit')->nullable();
            $table->string('lead_type')->nullable();
            $table->string('visit_sequence')->nullable();
            $table->string('closer_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->date('date_of_visit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->string('verification_status')->nullable();
            $table->decimal('lead_score', 8, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospect_interested_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospect_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('location_path')->nullable();
            $table->string('form_type')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status')->nullable();
            $table->unsignedBigInteger('replaces_form_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('field_key')->nullable();
            $table->string('field_type')->nullable();
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->text('default_value')->nullable();
            $table->integer('order')->default(0);
            $table->string('section')->nullable();
            $table->json('styles')->nullable();
            $table->timestamps();
        });
    }

    protected function createRole(string $slug, ?string $name = null): Role
    {
        return Role::create([
            'name' => $name ?? ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    protected function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $attributes['name'] ?? ucfirst($role->slug) . ' User',
            'email' => $attributes['email'] ?? uniqid($role->slug . '-', true) . '@example.com',
            'password' => bcrypt('Password123!'),
            'phone' => $attributes['phone'] ?? '9876543210',
            'role_id' => $role->id,
            'manager_id' => $attributes['manager_id'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ], $attributes));
    }

    protected function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'phone' => '9999999999',
            'source' => 'other',
            'status' => 'new',
        ], $attributes));
    }
}
