<?php

namespace Tests\Feature;

use App\Models\LeadFavorite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportWorkspaceExcludeFavoritesTest extends TestCase
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

    public function test_all_leads_preview_excludes_favorites_by_default(): void
    {
        [$admin] = $this->seedExportLeads();

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'all-leads',
            'date_range' => 'all_time',
        ]));

        $response->assertOk();
        $response->assertSee('Favourite Leads Excluded');
        $response->assertSee('Yes');
        $response->assertDontSee('Favourite Customer');
        $response->assertSee('Regular Customer');
    }

    public function test_all_leads_preview_includes_favorites_when_unchecked(): void
    {
        [$admin] = $this->seedExportLeads();

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'all-leads',
            'date_range' => 'all_time',
            'exclude_favorites' => '0',
        ]));

        $response->assertOk();
        $response->assertSee('Favourite Leads Excluded');
        $response->assertSee('No');
        $response->assertSee('Favourite Customer');
        $response->assertSee('Regular Customer');
    }

    public function test_all_leads_preview_preserves_default_checked_export_filter(): void
    {
        [$admin] = $this->seedExportLeads();

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'all-leads',
            'date_range' => 'all_time',
        ]));

        $response->assertOk();
        $response->assertSee('name="exclude_favorites"', false);
        $response->assertSee('value="1"', false);
    }

    public function test_all_leads_csv_download_excludes_favorites_when_checked(): void
    {
        [$admin] = $this->seedExportLeads();

        $response = $this->actingAs($admin)->post(route('export.reports.download', [
            'reportKey' => 'all-leads',
        ]), [
            'date_range' => 'all_time',
            'exclude_favorites' => '1',
            'format' => 'csv',
        ]);

        $response->assertOk();
        $csv = $response->getContent();

        $this->assertStringNotContainsString('Favourite Customer', $csv);
        $this->assertStringContainsString('Regular Customer', $csv);
        $this->assertDatabaseHas('report_exports', [
            'report_key' => 'all-leads',
            'record_count' => 1,
        ]);
    }

    public function test_all_leads_preview_excludes_unassigned_lead_bank_leads(): void
    {
        [$admin, $asm] = $this->seedExportUsers('preview-bank');
        [$normalLeadName, $unassignedBankLeadName, $assignedBankLeadName] = $this->seedLeadBankExportVisibilityLeads($admin, $asm);

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'all-leads',
            'date_range' => 'all_time',
        ]));

        $response->assertOk();
        $response->assertSee($normalLeadName);
        $response->assertDontSee($unassignedBankLeadName);
        $response->assertSee($assignedBankLeadName);
    }

    public function test_all_leads_csv_download_excludes_unassigned_lead_bank_leads(): void
    {
        [$admin, $asm] = $this->seedExportUsers('csv-bank');
        [$normalLeadName, $unassignedBankLeadName, $assignedBankLeadName] = $this->seedLeadBankExportVisibilityLeads($admin, $asm);

        $response = $this->actingAs($admin)->post(route('export.reports.download', [
            'reportKey' => 'all-leads',
        ]), [
            'date_range' => 'all_time',
            'format' => 'csv',
        ]);

        $response->assertOk();
        $csv = $response->getContent();

        $this->assertStringContainsString($normalLeadName, $csv);
        $this->assertStringNotContainsString($unassignedBankLeadName, $csv);
        $this->assertStringContainsString($assignedBankLeadName, $csv);
        $this->assertDatabaseHas('report_exports', [
            'report_key' => 'all-leads',
            'record_count' => 2,
        ]);
    }

    public function test_all_leads_preview_excludes_site_visit_and_closed_leads_by_default(): void
    {
        [$admin, $asm] = $this->seedExportLeads();
        $siteVisitLeadId = $this->createAssignedLead($admin, $asm, 'Site Visit Customer');
        $closedLeadId = $this->createAssignedLead($admin, $asm, 'Closed Customer', 'closed');
        $openLeadId = $this->createAssignedLead($admin, $asm, 'Open Customer');

        DB::table('site_visits')->insert([
            'lead_id' => $siteVisitLeadId,
            'created_by' => $admin->id,
            'assigned_to' => $asm->id,
            'customer_name' => 'Site Visit Customer',
            'phone' => '9000000003',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'all-leads',
            'date_range' => 'all_time',
        ]));

        $response->assertOk();
        $response->assertSee('Site Visit Leads');
        $response->assertSee('Closed Leads');
        $response->assertSee('Site Visit Leads Excluded');
        $response->assertSee('Closed Leads Excluded');
        $response->assertDontSee('Site Visit Customer');
        $response->assertDontSee('Closed Customer');
        $response->assertSee('Open Customer');
        $this->assertNotNull($closedLeadId);
        $this->assertNotNull($openLeadId);
    }

    public function test_all_leads_preview_includes_site_visit_and_closed_leads_when_unchecked(): void
    {
        [$admin, $asm] = $this->seedExportLeads();
        $siteVisitLeadId = $this->createAssignedLead($admin, $asm, 'Site Visit Customer');
        $this->createAssignedLead($admin, $asm, 'Closed Customer', 'closed');

        DB::table('site_visits')->insert([
            'lead_id' => $siteVisitLeadId,
            'created_by' => $admin->id,
            'assigned_to' => $asm->id,
            'customer_name' => 'Site Visit Customer',
            'phone' => '9000000003',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'all-leads',
            'date_range' => 'all_time',
            'exclude_favorites' => '0',
            'exclude_site_visits' => '0',
            'exclude_closed_leads' => '0',
        ]));

        $response->assertOk();
        $response->assertSee('Site Visit Customer');
        $response->assertSee('Closed Customer');
    }

    public function test_closed_leads_preview_excludes_favorites_by_default(): void
    {
        [$admin, $asm, $favouriteLeadId, $regularLeadId] = $this->seedExportLeads();

        DB::table('leads')->whereIn('id', [$favouriteLeadId, $regularLeadId])->update([
            'status' => 'closed',
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'closed-leads',
            'date_range' => 'all_time',
        ]));

        $response->assertOk();
        $response->assertSee('Favourite Leads Excluded');
        $response->assertSee('Yes');
        $response->assertDontSee('Favourite Customer');
        $response->assertSee('Regular Customer');
    }

    public function test_site_visits_preview_excludes_favorite_leads_and_cancelled_by_default(): void
    {
        [$admin, $asm, $favouriteLeadId, $regularLeadId] = $this->seedExportLeads();

        DB::table('site_visits')->insert([
            [
                'lead_id' => $favouriteLeadId,
                'created_by' => $admin->id,
                'assigned_to' => $asm->id,
                'customer_name' => 'Favourite Visit',
                'phone' => '9000000001',
                'status' => 'scheduled',
                'scheduled_at' => now(),
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $regularLeadId,
                'created_by' => $admin->id,
                'assigned_to' => $asm->id,
                'customer_name' => 'Regular Scheduled Visit',
                'phone' => '9000000002',
                'status' => 'scheduled',
                'scheduled_at' => now(),
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $regularLeadId,
                'created_by' => $admin->id,
                'assigned_to' => $asm->id,
                'customer_name' => 'Regular Rescheduled Visit',
                'phone' => '9000000002',
                'status' => 'rescheduled',
                'scheduled_at' => now(),
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $regularLeadId,
                'created_by' => $admin->id,
                'assigned_to' => $asm->id,
                'customer_name' => 'Regular Completed Visit',
                'phone' => '9000000002',
                'status' => 'completed',
                'scheduled_at' => now(),
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lead_id' => $regularLeadId,
                'created_by' => $admin->id,
                'assigned_to' => $asm->id,
                'customer_name' => 'Regular Cancelled Visit',
                'phone' => '9000000002',
                'status' => 'cancelled',
                'scheduled_at' => now(),
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'site-visits',
            'date_range' => 'all_time',
        ]));

        $response->assertOk();
        $response->assertSee('Favourite Leads Excluded');
        $response->assertSee('Yes');
        $response->assertDontSee('Favourite Visit');
        $response->assertSee('3 rows showing out of 3 total records.');
        $response->assertSee('Regular Customer');
        $response->assertDontSee('Regular Cancelled Visit');
    }

    public function test_site_visit_report_includes_every_visit_record_and_shows_current_lead_state(): void
    {
        [$admin, $asm, , $leadId] = $this->seedExportLeads();
        DB::table('leads')->where('id', $leadId)->update([
            'status' => 'not_interested',
            'next_followup_at' => '2026-09-08 12:30:00',
        ]);
        DB::table('tasks')->insert([
            'lead_id' => $leadId,
            'assigned_to' => $asm->id,
            'created_by' => $admin->id,
            'type' => 'phone_call',
            'status' => 'completed',
            'outcome' => 'follow_up',
            'next_action_at' => '2026-09-08 12:30:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('site_visits')->insert([
            [
                'lead_id' => $leadId, 'created_by' => $admin->id, 'assigned_to' => $asm->id,
                'customer_name' => 'Actual Site Visit', 'phone' => '9000000002', 'lead_type' => 'New Visit', 'visit_sequence' => 'fresh_visit',
                'status' => 'completed', 'scheduled_at' => '2026-09-05 10:00:00', 'completed_at' => '2026-09-05 11:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'lead_id' => $leadId, 'created_by' => $admin->id, 'assigned_to' => $asm->id,
                'customer_name' => 'Meeting Stored As Visit', 'phone' => '9000000002', 'lead_type' => 'Meeting', 'visit_sequence' => '2nd_visit',
                'status' => 'completed', 'scheduled_at' => '2026-09-05 09:00:00', 'completed_at' => '2026-09-05 09:30:00',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'site-visits',
            'date_range' => 'all_time',
            'status' => ['completed'],
            'exclude_favorites' => '0',
        ]));

        $response->assertOk()
            ->assertSee('Current Lead Status')
            ->assertSee('Visit Sequence')
            ->assertSee('Lead Type / Entry Stage')
            ->assertSee('Fresh Visit')
            ->assertSee('2nd Visit')
            ->assertSee('Latest Call Outcome')
            ->assertSee('Next Follow-up')
            ->assertSee('2 rows showing out of 2 total records.')
            ->assertSee('New Visit')
            ->assertSee('Not Interested')
            ->assertSee('Follow Up')
            ->assertSee('2026-09-08 12:30')
            ->assertSee('>Meeting<', false);

        $csv = $this->actingAs($admin)->post(route('export.reports.download', ['reportKey' => 'site-visits']), [
            'date_range' => 'all_time',
            'status' => ['completed'],
            'exclude_favorites' => '0',
            'format' => 'csv',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('Visit Date', $csv);
        $this->assertStringContainsString('Visit Sequence', $csv);
        $this->assertStringContainsString('Lead Type / Entry Stage', $csv);
        $this->assertStringContainsString('Fresh Visit', $csv);
        $this->assertStringContainsString('2nd Visit', $csv);
        $this->assertStringContainsString('Current Lead Status', $csv);
        $this->assertStringContainsString('Latest Call Outcome', $csv);
        $this->assertStringContainsString('Next Follow-up', $csv);
        $this->assertStringContainsString('Not Interested', $csv);
        $this->assertStringContainsString(',Meeting,', $csv);
    }

    public function test_site_visit_preview_shows_all_matching_rows_beyond_200(): void
    {
        [$admin, $asm, , $leadId] = $this->seedExportLeads();
        $visits = [];

        for ($index = 1; $index <= 201; $index++) {
            $visits[] = [
                'lead_id' => $leadId,
                'created_by' => $admin->id,
                'assigned_to' => $asm->id,
                'customer_name' => 'Site Visit ' . $index,
                'phone' => '9000000002',
                'lead_type' => 'New Visit',
                'status' => 'completed',
                'scheduled_at' => '2026-09-05 10:00:00',
                'completed_at' => '2026-09-05 11:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('site_visits')->insert($visits);

        $this->actingAs($admin)->get(route('export.reports.preview', [
            'reportKey' => 'site-visits',
            'date_range' => 'all_time',
            'status' => ['completed'],
            'exclude_favorites' => '0',
        ]))->assertOk()
            ->assertSee('Preview: all matching rows')
            ->assertSee('201 rows showing out of 201 total records.');
    }

    private function seedExportLeads(): array
    {
        [$admin, $asm] = $this->seedExportUsers('favorites');

        $favouriteLeadId = DB::table('leads')->insertGetId([
            'name' => 'Favourite Customer',
            'email' => 'favorite@example.test',
            'phone' => '9000000001',
            'source' => 'other',
            'status' => 'new',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $regularLeadId = DB::table('leads')->insertGetId([
            'name' => 'Regular Customer',
            'email' => 'regular@example.test',
            'phone' => '9000000002',
            'source' => 'other',
            'status' => 'new',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$favouriteLeadId, $regularLeadId] as $leadId) {
            DB::table('lead_assignments')->insert([
                'lead_id' => $leadId,
                'assigned_to' => $asm->id,
                'assigned_by' => $admin->id,
                'assignment_type' => 'primary',
                'assigned_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        LeadFavorite::create([
            'user_id' => $asm->id,
            'lead_id' => $favouriteLeadId,
        ]);

        return [$admin, $asm, $favouriteLeadId, $regularLeadId];
    }

    private function seedExportUsers(string $emailPrefix): array
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => Role::ADMIN, 'is_active' => true]);
        $asmRole = Role::create(['name' => 'Assistant Sales Manager', 'slug' => Role::ASSISTANT_SALES_MANAGER, 'is_active' => true]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => $emailPrefix . '-admin@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $asm = User::create([
            'name' => 'ASM User',
            'email' => $emailPrefix . '-asm@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $asmRole->id,
            'is_active' => true,
        ]);

        return [$admin, $asm];
    }

    private function seedLeadBankExportVisibilityLeads(User $admin, User $asm): array
    {
        $normalLeadName = 'Normal Unassigned Export Customer';
        $unassignedBankLeadName = 'Unassigned Lead Bank Export Customer';
        $assignedBankLeadName = 'Assigned Lead Bank Export Customer';

        $this->createLead($admin, $normalLeadName, '9100000001');
        $unassignedBankLeadId = $this->createLead($admin, $unassignedBankLeadName, '9100000002');
        $assignedBankLeadId = $this->createLead($admin, $assignedBankLeadName, '9100000003');

        $this->createLeadBankImport($admin, $unassignedBankLeadId);
        $this->createLeadBankImport($admin, $assignedBankLeadId);

        DB::table('lead_assignments')->insert([
            'lead_id' => $assignedBankLeadId,
            'assigned_to' => $asm->id,
            'assigned_by' => $admin->id,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$normalLeadName, $unassignedBankLeadName, $assignedBankLeadName];
    }

    private function createLead(User $admin, string $name, string $phone): int
    {
        return DB::table('leads')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '@example.test',
            'phone' => $phone,
            'source' => 'other',
            'status' => 'new',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLeadBankImport(User $admin, int $leadId): void
    {
        $batchId = DB::table('import_batches')->insertGetId([
            'user_id' => $admin->id,
            'source_type' => 'csv',
            'import_kind' => 'lead_bank',
            'file_name' => 'lead-bank-export-test.csv',
            'total_leads' => 1,
            'imported_leads' => 1,
            'failed_leads' => 0,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('imported_leads')->insert([
            'import_batch_id' => $batchId,
            'lead_id' => $leadId,
            'assigned_to' => null,
            'assigned_at' => null,
            'import_data' => json_encode(['action' => 'create']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAssignedLead(User $admin, User $asm, string $name, string $status = 'new'): int
    {
        $leadId = DB::table('leads')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '@example.test',
            'phone' => '9000000099',
            'source' => 'other',
            'status' => $status,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_assignments')->insert([
            'lead_id' => $leadId,
            'assigned_to' => $asm->id,
            'assigned_by' => $admin->id,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $leadId;
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

        Schema::create('login_security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('status');
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
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->nullable();
            $table->text('description')->nullable();
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
            $table->timestamp('next_followup_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by');
            $table->string('assignment_type')->default('primary');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lead_id');
            $table->timestamps();
            $table->unique(['user_id', 'lead_id']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('import_kind')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('total_leads')->default(0);
            $table->unsignedInteger('imported_leads')->default(0);
            $table->unsignedInteger('failed_leads')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_batch_id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->json('import_data')->nullable();
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('lead_type')->nullable();
            $table->string('visit_sequence')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('closer_status')->nullable();
            $table->string('property_name')->nullable();
            $table->text('property_address')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->unsignedBigInteger('assigned_manager')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('verification_status')->nullable();
            $table->text('notes')->nullable();
            $table->text('remark')->nullable();
            $table->text('employee_remark')->nullable();
            $table->text('manager_remark')->nullable();
            $table->timestamps();
        });

        Schema::create('interested_project_names', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('prospect_project', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospect_id');
            $table->unsignedBigInteger('project_id');
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->default('phone_call');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('task_type')->default('calling');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 80);
            $table->string('report_name', 160);
            $table->json('filters_json')->nullable();
            $table->string('format', 20);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->timestamps();
        });
    }
}
