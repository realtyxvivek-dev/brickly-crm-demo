<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Models\Lead;
use App\Models\LeadBankImportRow;
use App\Models\LeadBankImportSession;
use App\Models\LeadTag;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\LeadBankAvailabilityService;
use App\Services\LeadBankImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadBankSmartFolderTest extends TestCase
{
    private User $user;

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
        Config::set('logging.default', 'errorlog');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'CRM Admin',
            'email' => 'crm-admin@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user = User::findOrFail(1);
        $this->actingAs($this->user);
    }

    public function test_confirm_creates_colored_folder_and_assigns_successful_new_lead(): void
    {
        $session = $this->createSession('Noida Leads', '#2563EB');
        $this->createRow($session, [
            'name' => 'New Noida Lead',
            'phone' => '9876543210',
            'city' => 'Noida',
            'source' => 'meta',
        ]);

        $result = $this->service()->confirm($session, $this->user->id);

        $folder = LeadTag::where('slug', 'noida-leads')->firstOrFail();
        $lead = Lead::where('phone', '9876543210')->firstOrFail();
        $this->assertSame(1, $result['created']);
        $this->assertTrue($folder->is_folder);
        $this->assertSame('#2563EB', $folder->color);
        $this->assertTrue($lead->leadTags()->whereKey($folder->id)->exists());
        $this->assertSame($folder->id, $session->fresh()->folder_tag_id);
    }

    public function test_existing_folder_is_reused_without_overwriting_its_color(): void
    {
        $existing = LeadTag::create([
            'name' => 'Noida Leads',
            'slug' => 'noida-leads',
            'type' => 'custom',
            'color' => '#DC2626',
            'is_folder' => true,
            'created_by' => $this->user->id,
        ]);
        $session = $this->createSession('Noida Leads', '#2563EB');
        $this->createRow($session, ['name' => 'Second Lead', 'phone' => '9876543211']);

        $this->service()->confirm($session, $this->user->id);

        $this->assertSame(1, LeadTag::count());
        $this->assertSame('#DC2626', $existing->fresh()->color);
        $this->assertSame($existing->id, $session->fresh()->folder_tag_id);
    }

    public function test_existing_normal_tag_is_promoted_to_folder_with_selected_color(): void
    {
        $tag = LeadTag::create([
            'name' => 'Campaign A',
            'slug' => 'campaign-a',
            'type' => 'campaign',
            'color' => '#64748B',
            'is_folder' => false,
            'created_by' => $this->user->id,
        ]);
        $session = $this->createSession('Campaign A', '#7C3AED');
        $this->createRow($session, ['name' => 'Campaign Lead', 'phone' => '9876543212']);

        $this->service()->confirm($session, $this->user->id);

        $tag->refresh();
        $this->assertTrue($tag->is_folder);
        $this->assertSame('#7C3AED', $tag->color);
    }

    public function test_update_existing_and_scope_filters_use_folder_and_import_relations(): void
    {
        $existingLead = Lead::create([
            'name' => 'Existing Lead',
            'phone' => '9876543213',
            'source' => 'other',
            'status' => 'new',
            'created_by' => $this->user->id,
        ]);
        $session = $this->createSession('Existing Updates', '#D97706');
        $this->createRow($session, ['name' => 'Existing Lead', 'phone' => '9876543213'], [
            'validation_status' => 'duplicate',
            'import_action' => 'update_existing',
            'existing_lead_id' => $existingLead->id,
        ]);

        $result = $this->service()->confirm($session, $this->user->id);
        $folder = LeadTag::where('slug', 'existing-updates')->firstOrFail();

        $this->assertSame(1, $result['updated']);
        $this->assertTrue($existingLead->leadTags()->whereKey($folder->id)->exists());

        $manualLead = Lead::create([
            'name' => 'Manual Lead',
            'phone' => '9876543214',
            'source' => 'other',
            'status' => 'new',
            'created_by' => $this->user->id,
        ]);
        $service = app(LeadBankAvailabilityService::class);
        $importedQuery = Lead::query();
        $service->applyFilters($importedQuery, ['scope' => 'imported']);
        $existingQuery = Lead::query();
        $service->applyFilters($existingQuery, ['scope' => 'existing']);

        $this->assertSame([$existingLead->id], $importedQuery->pluck('id')->all());
        $this->assertContains($manualLead->id, $existingQuery->pluck('id')->all());
        $this->assertNotContains($existingLead->id, $existingQuery->pluck('id')->all());
    }

    public function test_skipped_import_does_not_create_empty_folder(): void
    {
        $session = $this->createSession('Empty Folder', '#205A44');
        $this->createRow($session, ['name' => 'Skipped Lead', 'phone' => '9876543215'], [
            'include' => false,
            'import_action' => 'skip',
        ]);

        $this->service()->confirm($session, $this->user->id);

        $this->assertDatabaseMissing('lead_tags', ['slug' => 'empty-folder']);
        $this->assertNull($session->fresh()->folder_tag_id);
    }

    private function service(): LeadBankImportService
    {
        return new LeadBankImportService(new DuplicateDetectionService());
    }

    private function createSession(?string $folderName, ?string $folderColor): LeadBankImportSession
    {
        return LeadBankImportSession::create([
            'user_id' => $this->user->id,
            'original_file_name' => 'sample.xlsx',
            'stored_path' => 'lead-bank-imports/sample.xlsx',
            'source_type' => 'sheet',
            'default_tags' => [],
            'folder_name' => $folderName,
            'folder_color' => $folderColor,
            'status' => 'draft',
        ]);
    }

    private function createRow(LeadBankImportSession $session, array $mapped, array $overrides = []): LeadBankImportRow
    {
        $row = LeadBankImportRow::create(array_merge([
            'lead_bank_import_session_id' => $session->id,
            'row_number' => 2,
            'mapped_data' => $mapped,
            'normalized_phone' => $mapped['phone'],
            'tags' => [],
            'validation_status' => 'valid',
            'include' => true,
            'import_action' => 'create',
        ], $overrides));
        $session->update([
            'total_rows' => $session->rows()->count(),
            'included_rows' => $session->rows()->where('include', true)->count(),
        ]);

        return $row;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('source')->nullable();
            $table->string('budget')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('is_dead')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('custom');
            $table->string('color')->nullable();
            $table->boolean('is_folder')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('lead_tag_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();
            $table->unique(['lead_id', 'lead_tag_id']);
        });
        Schema::create('lead_bank_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('original_file_name');
            $table->string('stored_path');
            $table->string('source_type')->default('csv');
            $table->text('headers')->nullable();
            $table->text('column_mapping')->nullable();
            $table->text('default_tags')->nullable();
            $table->string('folder_name')->nullable();
            $table->string('folder_color')->nullable();
            $table->unsignedBigInteger('folder_tag_id')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('included_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_bank_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_bank_import_session_id');
            $table->unsignedInteger('row_number');
            $table->text('raw_data')->nullable();
            $table->text('mapped_data')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->text('tags')->nullable();
            $table->string('validation_status')->default('valid');
            $table->text('errors')->nullable();
            $table->boolean('include')->default(true);
            $table->string('import_action')->default('create');
            $table->unsignedBigInteger('existing_lead_id')->nullable();
            $table->unsignedBigInteger('created_lead_id')->nullable();
            $table->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('source_type')->default('csv');
            $table->string('import_kind')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('total_leads')->default(0);
            $table->integer('imported_leads')->default(0);
            $table->integer('failed_leads')->default(0);
            $table->string('status')->default('pending');
            $table->text('error_log')->nullable();
            $table->timestamps();
        });
        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_batch_id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('import_data')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_bank_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
}
