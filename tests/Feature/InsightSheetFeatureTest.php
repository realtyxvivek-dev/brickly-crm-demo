<?php

namespace Tests\Feature;

use App\Models\InsightSheetCellAudit;
use App\Models\InsightSheetCellOverride;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadAssignmentWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsightSheetFeatureTest extends TestCase
{
    private Role $adminRole;
    private Role $viewerRole;
    private User $admin;
    private User $viewer;
    private User $blockedUser;
    private User $advisor;
    private Lead $lead;

    private function grantAuditor(User $auditor): void
    {
        $salesRole = Role::firstOrCreate(['slug' => 'sales_manager'], ['name' => 'Sales Manager']);
        $this->advisor->forceFill(['role_id' => $salesRole->id, 'is_active' => true])->save();
        if (!Schema::hasTable('lead_auditor_user_access')) {
            Schema::create('lead_auditor_user_access', function (Blueprint $table) {
                $table->integer('auditor_id'); $table->integer('sales_user_id'); $table->timestamps();
            });
        }
        DB::table('lead_auditor_user_access')->updateOrInsert(['auditor_id' => $auditor->id, 'sales_user_id' => $this->advisor->id]);
    }

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
        $this->seedUsersAndLead();
    }

    public function test_admin_can_load_insight_sheet_data_from_crm_source(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson(route('admin.insight-sheet.data'));

        $response->assertOk()
            ->assertJsonPath('rows.0.row_key', 'lead:' . $this->lead->id)
            ->assertJsonPath('rows.0.cells.customer.value', 'Gaurav Bagga')
            ->assertJsonPath('rows.0.cells.advisor.value', 'Naveen Singh')
            ->assertJsonPath('rows.0.cells.visit_status.value', 'COMPLETED');
    }

    public function test_mobile_number_search_is_visible_and_filters_leads(): void
    {
        $this->assertStringContainsString(
            'Search by mobile number',
            file_get_contents(resource_path('views/admin/insight-sheet/index.blade.php')),
        );

        $this->actingAs($this->admin);
        $this->getJson(route('admin.insight-sheet.data', ['search' => '570306']))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.lead_id', $this->lead->id);

        $this->getJson(route('admin.insight-sheet.data', ['search' => '000000']))
            ->assertOk()
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_auditor_can_transfer_an_accessible_lead_to_an_allowed_sales_user(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => Role::LEAD_QUALITY_AUDITOR,
            'permissions' => ['insight_sheet.view'],
        ]);
        $salesRole = Role::firstOrCreate(['slug' => Role::SALES_MANAGER], ['name' => 'Sales Manager']);
        $auditor = User::create([
            'name' => 'Transfer Auditor',
            'email' => 'transfer-auditor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);
        $newOwner = User::create([
            'name' => 'New Lead Owner',
            'email' => 'new-owner@example.test',
            'password' => bcrypt('password'),
            'role_id' => $salesRole->id,
            'is_active' => true,
        ]);

        $this->grantAuditor($auditor);
        DB::table('lead_auditor_user_access')->insert([
            'auditor_id' => $auditor->id,
            'sales_user_id' => $newOwner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workflow = \Mockery::mock(LeadAssignmentWorkflowService::class);
        $workflow->shouldReceive('assignLead')
            ->once()
            ->withArgs(fn (Lead $lead, int $assignedTo, int $assignedBy, string $notes) =>
                $lead->is($this->lead)
                && $assignedTo === $newOwner->id
                && $assignedBy === $auditor->id
                && $notes === 'Balance team workload'
            )
            ->andReturn(['old_owner_ids' => []]);
        $this->app->instance(LeadAssignmentWorkflowService::class, $workflow);

        $this->actingAs($auditor)
            ->postJson(route('admin.insight-sheet.transfer'), [
                'row_key' => 'lead:'.$this->lead->id,
                'assigned_to' => $newOwner->id,
                'notes' => 'Balance team workload',
            ])
            ->assertOk()
            ->assertJsonPath('new_owner.id', $newOwner->id)
            ->assertJsonPath('new_owner.name', 'New Lead Owner');
    }

    public function test_auditor_cannot_transfer_a_lead_to_a_user_outside_their_scope(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => Role::LEAD_QUALITY_AUDITOR,
        ]);
        $salesRole = Role::firstOrCreate(['slug' => Role::SALES_MANAGER], ['name' => 'Sales Manager']);
        $auditor = User::create([
            'name' => 'Scoped Auditor',
            'email' => 'scoped-auditor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);
        $outsideUser = User::create([
            'name' => 'Outside User',
            'email' => 'outside-owner@example.test',
            'password' => bcrypt('password'),
            'role_id' => $salesRole->id,
        ]);

        $this->grantAuditor($auditor);

        $this->actingAs($auditor)
            ->postJson(route('admin.insight-sheet.transfer'), [
                'row_key' => 'lead:'.$this->lead->id,
                'assigned_to' => $outsideUser->id,
                'notes' => 'Unauthorized transfer attempt',
            ])
            ->assertForbidden();
    }

    public function test_lead_quality_auditor_can_open_profile_page(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => 'lead_quality_auditor',
        ]);
        $auditor = User::create([
            'name' => 'Auditor User',
            'email' => 'auditor-profile@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);

        $this->grantAuditor($auditor);
        $this->actingAs($auditor)
            ->get(route('lead-quality-auditor.profile'))
            ->assertOk()
            ->assertSee('Auditor User')
            ->assertSee('Change Password')
            ->assertSee(route('lead-quality-auditor.profile'), false);
    }

    public function test_lead_quality_auditor_can_open_activity_calendar(): void
    {
        $auditorRole = Role::create(['name' => 'Lead Quality Auditor', 'slug' => 'lead_quality_auditor']);
        $auditor = User::create([
            'name' => 'Calendar Auditor', 'email' => 'calendar-auditor@example.test',
            'password' => bcrypt('password'), 'role_id' => $auditorRole->id,
        ]);

        $this->grantAuditor($auditor);
        $this->actingAs($auditor)
            ->get(route('lead-quality-auditor.activity-calendar.index'))
            ->assertOk()
            ->assertSee('Team Activity Calendar')
            ->assertSee('New Activity');
    }

    public function test_auditor_dashboard_loads_and_other_roles_cannot_read_its_endpoints(): void
    {
        $role = Role::create(['name' => 'Lead Quality Auditor', 'slug' => 'lead_quality_auditor']);
        $auditor = User::create(['name' => 'Dashboard Auditor', 'email' => 'dashboard-auditor@example.test', 'password' => bcrypt('password'), 'role_id' => $role->id]);
        $this->grantAuditor($auditor);
        $this->actingAs($auditor)->get(route('lead-quality-auditor.dashboard'))->assertOk()->assertSee('Untouched Leads')->assertSee('Initial Lead Tasks');
        $this->actingAs($this->viewer);
        foreach (['summary', 'untouched', 'tasks', 'details'] as $endpoint) {
            $this->getJson(route('lead-quality-auditor.dashboard.'.$endpoint))->assertForbidden();
        }
    }

    public function test_auditor_dashboard_details_obey_phone_privacy(): void
    {
        $role = Role::create(['name' => 'Lead Quality Auditor', 'slug' => 'lead_quality_auditor']);
        $auditor = User::create(['name' => 'Private Auditor', 'email' => 'private-auditor@example.test', 'password' => bcrypt('password'), 'role_id' => $role->id]);
        $privacy = \Mockery::mock(\App\Services\PhonePrivacyService::class)->makePartial();
        $privacy->shouldReceive('shouldMask')->andReturn(true);
        $this->app->instance(\App\Services\PhonePrivacyService::class, $privacy);
        $sheet = \Mockery::mock(\App\Services\InsightSheetService::class);
        $sheet->shouldReceive('leadDetails')->once()->with('lead:'.$this->lead->id)->andReturn([
            'phone' => '9876543210', 'contact' => ['Phone' => '9876543210'],
            'remark_history' => [['remark' => 'Call 9876543210']],
        ]);
        $this->app->instance(\App\Services\InsightSheetService::class, $sheet);
        $this->grantAuditor($auditor);
        $this->actingAs($auditor)->getJson(route('lead-quality-auditor.dashboard.details', ['lead_id' => $this->lead->id]))
            ->assertOk()->assertJsonPath('phone', '98XXXX3210')->assertJsonPath('contact.Phone', '98XXXX3210')
            ->assertDontSee('9876543210');
    }

    public function test_completed_activity_is_shown_on_planned_and_completed_dates(): void
    {
        $auditorRole = Role::create(['name' => 'Lead Quality Auditor', 'slug' => 'lead_quality_auditor']);
        $auditor = User::create([
            'name' => 'Calendar Auditor', 'email' => 'calendar-events@example.test',
            'password' => bcrypt('password'), 'role_id' => $auditorRole->id,
        ]);
        DB::table('follow_ups')->insert([
            'lead_id' => $this->lead->id,
            'created_by' => $this->advisor->id,
            'scheduled_at' => '2026-09-10 10:00:00',
            'completed_at' => '2026-09-11 12:00:00',
            'notes' => 'Customer callback',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->grantAuditor($auditor);
        $response = $this->actingAs($auditor)->getJson(route('lead-quality-auditor.activity-calendar.events', [
            'start' => '2026-09-01',
            'end' => '2026-10-01',
            'type' => 'follow_up',
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('summary.planned', 1)
            ->assertJsonPath('summary.completed', 1)
            ->assertJsonPath('summary.follow_up', 1);
        $this->assertEqualsCanonicalizing(['planned', 'completed'], array_column($response->json('events.*.extendedProps'), 'occurrence'));
    }

    public function test_stored_display_status_filter_is_paginated_by_the_database(): void
    {
        Lead::create([
            'name' => 'Different Status',
            'phone' => '9000000000',
            'status' => 'new',
        ]);

        $this->actingAs($this->admin);

        $this->getJson(route('admin.insight-sheet.data', [
            'column_filters' => ['crm_status' => ['visit_done']],
            'per_page' => 500,
        ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.lead_id', $this->lead->id);
    }

    public function test_advisor_column_filter_is_paginated_by_the_database(): void
    {
        $otherAdvisor = User::create([
            'name' => 'Other Advisor',
            'email' => 'other-advisor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $this->viewerRole->id,
        ]);
        $otherLead = Lead::create([
            'name' => 'Other Advisor Lead',
            'phone' => '9000000000',
            'status' => 'new',
        ]);
        DB::table('lead_assignments')->insert([
            'lead_id' => $otherLead->id,
            'assigned_to' => $otherAdvisor->id,
            'assigned_by' => $this->admin->id,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin);

        $this->getJson(route('admin.insight-sheet.data', [
            'column_filters' => ['advisor' => ['Naveen Singh']],
            'per_page' => 500,
        ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.lead_id', $this->lead->id)
            ->assertJsonMissing(['lead_id' => $otherLead->id]);
    }

    public function test_prospect_values_fill_blank_requirement_cells_in_the_sheet(): void
    {
        $prospectLead = Lead::create([
            'name' => 'Prospect Fallback',
            'phone' => '9000000001',
            'status' => 'verified_prospect',
        ]);
        DB::table('lead_assignments')->insert([
            'lead_id' => $prospectLead->id,
            'assigned_to' => $this->advisor->id,
            'assigned_by' => $this->admin->id,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('prospects')->insert([
            'lead_id' => $prospectLead->id,
            'budget' => '75 Lacs-1 Cr',
            'preferred_location' => 'Gomti Nagar',
            'purpose' => 'investment',
            'possession' => 'Ready to move',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('lead_form_field_values')->insert([
            'lead_id' => $prospectLead->id,
            'field_key' => 'customer_job',
            'field_value' => 'Business Owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson(route('admin.insight-sheet.data', ['per_page' => 500]));
        $response->assertOk();
        $row = collect($response->json('rows'))->firstWhere('lead_id', $prospectLead->id);

        $this->assertSame('75 Lacs-1 Cr', data_get($row, 'cells.budget.value'));
        $this->assertSame('Gomti Nagar', data_get($row, 'cells.location.value'));
        $this->assertSame('investment', data_get($row, 'cells.purpose.value'));
        $this->assertSame('Ready to move', data_get($row, 'cells.possession.value'));
        $this->assertSame('Job: Business Owner', data_get($row, 'cells.customer_profiling.value'));
    }

    public function test_date_range_filters_all_rows_before_pagination(): void
    {
        $oldLead = Lead::create([
            'name' => 'Old Lead',
            'phone' => '9111111111',
            'status' => 'new',
        ]);
        $oldLead->forceFill([
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ])->save();
        $this->lead->forceFill([
            'created_at' => now()->startOfMonth(),
            'updated_at' => now()->startOfMonth(),
        ])->save();

        $this->actingAs($this->admin);

        $this->getJson(route('admin.insight-sheet.data', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'per_page' => 500,
        ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.lead_id', $this->lead->id)
            ->assertJsonMissing(['lead_id' => $oldLead->id]);
    }

    public function test_admin_can_open_insight_sheet_page(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.insight-sheet.index'))
            ->assertOk()
            ->assertSee('Insight Sheet');
    }

    public function test_customer_details_include_all_saved_dynamic_form_values(): void
    {
        DB::table('lead_form_field_values')->insert([
            [
                'lead_id' => $this->lead->id,
                'field_key' => 'customer_job',
                'field_value' => 'Business Owner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $this->lead->id,
                'field_key' => 'custom_family_size',
                'field_value' => '5 members',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.insight-sheet.details', ['row_key' => 'lead:'.$this->lead->id]))
            ->assertOk()
            ->assertJsonPath('profile.Job / Profession', 'Business Owner')
            ->assertJsonPath('requirements.Type', 'Apartments')
            ->assertJsonPath('additional.Custom Family Size', '5 members');
    }

    public function test_customer_details_include_lead_journey_and_remark_history(): void
    {
        $this->lead->update(['notes' => "Customer asked for a callback.\nSynced automatically from Base CRM Android app device call log."]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.insight-sheet.details', ['row_key' => 'lead:'.$this->lead->id]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Site Visit Completed'])
            ->assertJsonFragment(['title' => 'Lead Received'])
            ->assertJsonFragment(['remark' => 'Customer asked for a callback.'])
            ->assertJsonMissing(['remark' => 'Synced automatically from Base CRM Android app device call log.'])
            ->assertJsonPath('profile.Job / Profession', 'Not saved')
            ->assertJsonPath('requirements.Purpose', 'Not saved');
    }

    public function test_lead_quality_auditor_can_save_audited_customer_detail_override(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => Role::LEAD_QUALITY_AUDITOR,
            'permissions' => ['insight_sheet.view'],
        ]);
        $auditor = User::create([
            'name' => 'Quality Auditor',
            'email' => 'detail-auditor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);

        $this->grantAuditor($auditor);
        $this->actingAs($auditor)
            ->postJson(route('admin.insight-sheet.details.override'), [
                'row_key' => 'lead:'.$this->lead->id,
                'values' => ['budget' => 'Above 1 Cr', 'purpose' => 'Long Term Investment'],
            ])
            ->assertOk()
            ->assertJsonPath('requirements.Budget', 'Above 1 Cr')
            ->assertJsonPath('requirements.Purpose', 'Long Term Investment')
            ->assertJsonPath('can_override', true);

        $this->assertSame('Above 2 Cr', $this->lead->fresh()->budget);
        $this->assertDatabaseHas('insight_sheet_cell_overrides', [
            'row_key' => 'lead:'.$this->lead->id,
            'column_key' => 'customer_details',
            'edited_by' => $auditor->id,
        ]);
        $this->assertDatabaseHas('insight_sheet_cell_audits', [
            'row_key' => 'lead:'.$this->lead->id,
            'column_key' => 'customer_details',
            'edited_by' => $auditor->id,
        ]);
    }

    public function test_lead_quality_auditor_cannot_save_random_controlled_values(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => Role::LEAD_QUALITY_AUDITOR,
            'permissions' => ['insight_sheet.view'],
        ]);
        $auditor = User::create([
            'name' => 'Quality Auditor',
            'email' => 'controlled-values-auditor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);

        $this->grantAuditor($auditor);
        $this->actingAs($auditor)
            ->postJson(route('admin.insight-sheet.details.override'), [
                'row_key' => 'lead:'.$this->lead->id,
                'values' => ['budget' => 'Random budget'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.budget');
    }

    public function test_completion_details_return_the_submitted_visit_form(): void
    {
        DB::table('site_visits')->where('lead_id', $this->lead->id)->update([
            'assigned_to' => $this->advisor->id,
            'visited_projects' => 'Eiffel Vivasa, Palm Residency',
            'visited_property_types' => json_encode(['apartment', 'villa']),
            'tentative_closing_time' => 'this_week',
            'feedback' => 'Customer liked the apartment layout.',
            'rating' => 4,
            'visit_notes' => 'Follow up with final price.',
            'verification_status' => 'pending',
            'completion_proof_photos' => json_encode(['site-visits/proof/test.jpg']),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.insight-sheet.completion-details', ['row_key' => 'lead:'.$this->lead->id]))
            ->assertOk()
            ->assertJsonPath('customer', 'Gaurav Bagga')
            ->assertJsonPath('activities.0.type', 'visit')
            ->assertJsonPath('activities.0.completed_by', 'Naveen Singh')
            ->assertJsonPath('activities.0.fields.Visited Projects', 'Eiffel Vivasa, Palm Residency')
            ->assertJsonPath('activities.0.fields.Property Types', 'Apartment, Villa')
            ->assertJsonPath('activities.0.fields.Rating', '4/5');
    }

    public function test_completion_details_return_the_submitted_meeting_form(): void
    {
        $meetingLead = Lead::create([
            'name' => 'Meeting Customer',
            'phone' => '9888888888',
            'status' => 'meeting_completed',
        ]);
        DB::table('meetings')->insert([
            'lead_id' => $meetingLead->id,
            'assigned_to' => $this->advisor->id,
            'project' => 'Palm Residency',
            'meeting_mode' => 'offline',
            'feedback' => 'Customer requested the final cost sheet.',
            'rating' => 5,
            'meeting_notes' => 'Schedule a site visit next.',
            'verification_status' => 'verified',
            'completion_proof_photos' => json_encode(['meetings/proof/test.jpg']),
            'status' => 'completed',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.insight-sheet.completion-details', ['row_key' => 'lead:'.$meetingLead->id]))
            ->assertOk()
            ->assertJsonPath('activities.0.type', 'meeting')
            ->assertJsonPath('activities.0.completed_by', 'Naveen Singh')
            ->assertJsonPath('activities.0.fields.Project', 'Palm Residency')
            ->assertJsonPath('activities.0.fields.Meeting Mode', 'Offline')
            ->assertJsonPath('activities.0.fields.Meeting Notes', 'Schedule a site visit next.')
            ->assertJsonPath('activities.0.fields.Rating', '5/5');
    }

    public function test_user_can_save_visible_insight_sheet_columns(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(route('admin.insight-sheet.layout.update'), [
            'visible_columns' => ['source', 'customer', 'advisor'],
        ])->assertOk()->assertExactJson([
            'visible_columns' => ['source', 'customer', 'advisor'],
        ]);

        $this->assertSame(
            ['source', 'customer', 'advisor'],
            data_get($this->admin->fresh()->ui_preferences, 'insight_sheet.visible_columns')
        );
    }

    public function test_user_without_permission_cannot_access_insight_sheet(): void
    {
        $this->actingAs($this->blockedUser);

        $this->getJson(route('admin.insight-sheet.data'))->assertForbidden();
    }

    public function test_edit_saves_override_and_does_not_mutate_lead(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(route('admin.insight-sheet.cells.save'), [
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
            'value' => 'Internal Name Override',
        ])->assertOk()->assertJsonPath('cell.is_overridden', true);

        $this->assertSame('Gaurav Bagga', $this->lead->fresh()->name);
        $this->assertDatabaseHas('insight_sheet_cell_overrides', [
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
            'value' => 'Internal Name Override',
        ]);
        $this->assertDatabaseHas('insight_sheet_cell_audits', [
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
            'source_value' => 'Gaurav Bagga',
            'new_value' => 'Internal Name Override',
        ]);

        $this->getJson(route('admin.insight-sheet.data'))
            ->assertJsonPath('rows.0.cells.customer.value', 'Internal Name Override')
            ->assertJsonPath('rows.0.cells.customer.source_value', 'Gaurav Bagga')
            ->assertJsonPath('rows.0.cells.customer.is_overridden', true);
    }

    public function test_internal_remark_audit_returns_the_complete_timeline(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => Role::LEAD_QUALITY_AUDITOR,
            'permissions' => ['insight_sheet.view', 'insight_sheet.edit'],
        ]);
        $auditor = User::create([
            'name' => 'Quality Auditor',
            'email' => 'quality-auditor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);

        foreach (range(1, 35) as $index) {
            InsightSheetCellAudit::create([
                'sheet_key' => 'master',
                'row_key' => 'lead:' . $this->lead->id,
                'column_key' => 'internal_remark',
                'new_value' => 'Internal remark ' . $index,
                'edited_by' => $auditor->id,
                'edited_at' => now()->addMinutes($index),
            ]);
        }

        $this->grantAuditor($auditor);
        $this->actingAs($auditor)
            ->getJson(route('admin.insight-sheet.audit', [
                'row_key' => 'lead:' . $this->lead->id,
                'column_key' => 'internal_remark',
            ]))
            ->assertOk()
            ->assertJsonCount(35, 'history')
            ->assertJsonPath('history.0.new_value', 'Internal remark 35');
    }

    public function test_lead_quality_auditor_can_only_edit_internal_columns(): void
    {
        $auditorRole = Role::create([
            'name' => 'Lead Quality Auditor',
            'slug' => Role::LEAD_QUALITY_AUDITOR,
            'permissions' => ['insight_sheet.view', 'insight_sheet.edit'],
        ]);
        $auditor = User::create([
            'name' => 'Quality Auditor',
            'email' => 'restricted-quality-auditor@example.test',
            'password' => bcrypt('password'),
            'role_id' => $auditorRole->id,
        ]);
        $payload = ['row_key' => 'lead:' . $this->lead->id];

        $this->grantAuditor($auditor);
        $this->actingAs($auditor)
            ->postJson(route('admin.insight-sheet.cells.save'), $payload + [
                'column_key' => 'customer',
                'value' => 'Must not change',
            ])->assertForbidden();

        $this->postJson(route('admin.insight-sheet.cells.save'), $payload + [
            'column_key' => 'internal_stage',
            'value' => 'Verified',
        ])->assertOk();

        $this->assertDatabaseHas('insight_sheet_cell_overrides', [
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'internal_stage',
            'value' => 'Verified',
        ]);
    }

    public function test_reset_removes_override_and_restores_source_display(): void
    {
        InsightSheetCellOverride::create([
            'sheet_key' => 'master',
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
            'value' => 'Internal Name Override',
            'edited_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        $this->deleteJson(route('admin.insight-sheet.cells.reset'), [
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
        ])->assertOk()->assertJsonPath('source_value', 'Gaurav Bagga');

        $this->assertDatabaseMissing('insight_sheet_cell_overrides', [
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
        ]);
        $this->assertDatabaseCount('insight_sheet_cell_audits', 1);
    }

    public function test_export_requires_permission_and_outputs_visible_values(): void
    {
        InsightSheetCellOverride::create([
            'sheet_key' => 'master',
            'row_key' => 'lead:' . $this->lead->id,
            'column_key' => 'customer',
            'value' => 'Export Override',
            'edited_by' => $this->admin->id,
        ]);

        $this->actingAs($this->viewer);
        $this->get(route('admin.insight-sheet.export'))->assertForbidden();

        $this->actingAs($this->admin);
        $response = $this->get(route('admin.insight-sheet.export', ['full' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('Export Override', $response->streamedContent());
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken()->nullable();
            $table->json('ui_preferences')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->string('budget')->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('property_type')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->foreignId('assigned_to');
            $table->foreignId('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('import_kind')->nullable();
            $table->timestamps();
        });

        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable();
            $table->foreignId('import_batch_id')->nullable();
            $table->json('import_data')->nullable();
            $table->timestamps();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->foreignId('telecaller_id')->nullable();
            $table->string('budget')->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('purpose')->nullable();
            $table->string('possession')->nullable();
            $table->text('manager_remark')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('verification_status')->nullable();
            $table->timestamps();
        });

        Schema::create('interested_project_names', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('prospect_project', function (Blueprint $table) {
            $table->foreignId('prospect_id');
            $table->foreignId('project_id');
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->string('property_name')->nullable();
            $table->string('project')->nullable();
            $table->string('lead_type')->nullable();
            $table->text('visited_projects')->nullable();
            $table->json('visited_property_types')->nullable();
            $table->string('property_type')->nullable();
            $table->string('tentative_closing_time')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('visit_notes')->nullable();
            $table->string('verification_status')->nullable();
            $table->json('completion_proof_photos')->nullable();
            $table->string('status')->nullable();
            $table->string('visit_sequence')->nullable();
            $table->date('date_of_visit')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->string('project')->nullable();
            $table->string('meeting_mode')->nullable();
            $table->string('location')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->string('verification_status')->nullable();
            $table->json('completion_proof_photos')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->foreignId('created_by')->nullable();
            $table->timestamp('follow_up_date')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('insight_sheet_cell_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_key', 40)->default('master');
            $table->string('row_key', 120);
            $table->string('column_key', 80);
            $table->text('value')->nullable();
            $table->foreignId('edited_by')->nullable();
            $table->timestamps();
            $table->unique(['sheet_key', 'row_key', 'column_key'], 'insight_sheet_override_unique');
        });

        Schema::create('insight_sheet_cell_audits', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_key', 40)->default('master');
            $table->string('row_key', 120);
            $table->string('column_key', 80);
            $table->text('source_value')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('edited_by')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('desktop_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
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
    }

    private function seedUsersAndLead(): void
    {
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => Role::ADMIN]);
        $this->viewerRole = Role::create([
            'name' => 'Insight Viewer',
            'slug' => 'insight_viewer',
            'permissions' => ['insight_sheet.view'],
        ]);
        $blockedRole = Role::create(['name' => 'Blocked', 'slug' => 'blocked']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('password'), 'role_id' => $this->adminRole->id]);
        $this->viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => bcrypt('password'), 'role_id' => $this->viewerRole->id]);
        $this->blockedUser = User::create(['name' => 'Blocked', 'email' => 'blocked@example.test', 'password' => bcrypt('password'), 'role_id' => $blockedRole->id]);
        $this->advisor = User::create(['name' => 'Naveen Singh', 'email' => 'naveen@example.test', 'password' => bcrypt('password'), 'role_id' => $blockedRole->id]);

        $this->lead = Lead::create([
            'name' => 'Gaurav Bagga',
            'email' => 'gaurav@example.test',
            'phone' => '9219570306',
            'source' => 'meta',
            'status' => 'visit_done',
            'budget' => 'Above 2 Cr',
            'preferred_location' => 'Sushant Golf City',
            'property_type' => 'Apartments',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('lead_assignments')->insert([
            'lead_id' => $this->lead->id,
            'assigned_to' => $this->advisor->id,
            'assigned_by' => $this->admin->id,
            'assignment_type' => 'primary',
            'assigned_at' => now()->subDay(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_visits')->insert([
            'lead_id' => $this->lead->id,
            'project' => 'Eiffel Vivasa',
            'status' => 'completed',
            'date_of_visit' => now()->toDateString(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
