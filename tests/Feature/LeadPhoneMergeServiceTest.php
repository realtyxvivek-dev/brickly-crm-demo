<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\DuplicateDetectionService;
use App\Services\LeadMergeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadPhoneMergeServiceTest extends TestCase
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
        Config::set('logging.default', 'errorlog');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_legacy_duplicate_phone_is_not_backfilled_by_unrelated_save(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unique('normalized_phone');
        });
        DB::table('leads')->insert([
            ['id' => 1, 'name' => 'Canonical', 'phone' => '+918789808969', 'normalized_phone' => '918789808969'],
            ['id' => 2, 'name' => 'Legacy', 'phone' => '+918789808969', 'normalized_phone' => null],
        ]);
        $lead = Lead::findOrFail(2);
        $lead->notes = 'Outcome stage update';
        $lead->save();
        $this->assertNull($lead->fresh()->normalized_phone);
        $this->assertSame('Outcome stage update', $lead->fresh()->notes);
        $this->assertSame('918789808969', Lead::findOrFail(1)->normalized_phone);
        $lead->phone = '9876543210';
        $lead->save();
        $this->assertSame('919876543210', $lead->fresh()->normalized_phone);
    }

    public function test_country_change_recomputes_phone_identity(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('phone_country_iso')->nullable();
        });
        DB::table('leads')->insert(['id' => 1, 'name' => 'Legacy', 'phone' => '9876543210', 'normalized_phone' => null]);
        $lead = Lead::findOrFail(1);
        $lead->phone_country_iso = 'IN';
        $lead->save();
        $this->assertSame('919876543210', $lead->fresh()->normalized_phone);
    }

    public function test_indian_phone_formats_share_one_canonical_identity_and_dummy_numbers_are_rejected(): void
    {
        $service = app(DuplicateDetectionService::class);

        $this->assertSame('919876543210', $service->normalizeLeadPhone('9876543210'));
        $this->assertSame('919876543210', $service->normalizeLeadPhone('919876543210'));
        $this->assertSame('919876543210', $service->normalizeLeadPhone('+91 98765-43210'));
        $this->assertSame('919876543210', $service->normalizeLeadPhone('09876543210'));
        $this->assertSame('', $service->normalizeLeadPhone('1111111111'));
        $this->assertSame('', $service->normalizeLeadPhone('12345'));
    }

    public function test_merge_keeps_newest_master_moves_links_dedupes_pivots_and_soft_archives_old_leads(): void
    {
        $old = Lead::create([
            'name' => 'Old Customer',
            'phone' => '+91 98765-43210',
            'email' => 'old@example.test',
            'source' => 'meta',
            'status' => 'closed',
            'created_by' => 1,
        ]);
        $master = Lead::create([
            'name' => 'Newest Customer',
            'phone' => '9876543210',
            'source' => 'organic',
            'status' => 'new',
            'created_by' => 2,
        ]);

        DB::table('lead_assignments')->insert([
            ['lead_id' => $old->id, 'assigned_to' => 20, 'is_active' => 1],
            ['lead_id' => $master->id, 'assigned_to' => 30, 'is_active' => 1],
        ]);
        DB::table('site_visits')->insert(['lead_id' => $old->id, 'status' => 'completed']);
        DB::table('tasks')->insert([
            ['lead_id' => $old->id, 'assigned_to' => 20, 'status' => 'pending'],
            ['lead_id' => $master->id, 'assigned_to' => 30, 'status' => 'pending'],
        ]);
        DB::table('lead_favorites')->insert([
            ['user_id' => 5, 'lead_id' => $old->id],
            ['user_id' => 5, 'lead_id' => $master->id],
        ]);

        $result = app(LeadMergeService::class)->merge('919876543210', 1);

        $this->assertSame($master->id, $result['master_lead_id']);
        $this->assertSame([$old->id], $result['merged_lead_ids']);
        $this->assertDatabaseHas('site_visits', ['lead_id' => $master->id, 'status' => 'completed']);
        $this->assertSame(1, DB::table('lead_favorites')->where('lead_id', $master->id)->count());
        $this->assertSame(0, DB::table('tasks')->where('lead_id', $master->id)->where('status', 'pending')->count());
        $this->assertSame(2, DB::table('tasks')->where('lead_id', $master->id)->where('status', 'cancelled')->count());
        $this->assertSame(1, DB::table('lead_assignments')->where('lead_id', $master->id)->where('is_active', 1)->where('assigned_to', 30)->count());

        $master->refresh();
        $this->assertSame('closed', $master->status);
        $this->assertSame('old@example.test', $master->email);
        $this->assertStringContainsString("Lead #{$old->id} merged into Lead #{$master->id}", (string) $master->notes);

        $archived = Lead::withTrashed()->findOrFail($old->id);
        $this->assertSame($master->id, (int) $archived->merged_into_lead_id);
        $this->assertNull($archived->normalized_phone);
        $this->assertNotNull($archived->deleted_at);
        $this->assertDatabaseHas('lead_merge_audits', [
            'master_lead_id' => $master->id,
            'duplicate_lead_id' => $old->id,
            'status' => 'merged',
        ]);
    }

    public function test_unique_index_command_refuses_to_run_while_duplicates_exist(): void
    {
        Lead::create(['name' => 'One', 'phone' => '9876543210', 'source' => 'meta', 'status' => 'new']);
        Lead::create(['name' => 'Two', 'phone' => '919876543210', 'source' => 'organic', 'status' => 'new']);

        $this->artisan('leads:enforce-unique-phone --force')
            ->expectsOutput('Cannot add unique index: 1 duplicate group(s) remain.')
            ->assertExitCode(1);
    }

    public function test_merge_ignores_external_identifiers_and_dedupes_duplicate_only_unique_values(): void
    {
        $older = Lead::create(['name' => 'Older', 'phone' => '9876543210', 'source' => 'meta', 'status' => 'new']);
        $newerDuplicate = Lead::create(['name' => 'Newer duplicate', 'phone' => '+91 98765-43210', 'source' => 'organic', 'status' => 'new']);
        $master = Lead::create(['name' => 'Master', 'phone' => '919876543210', 'source' => 'website', 'status' => 'new']);

        DB::table('lead_form_field_values')->insert([
            ['lead_id' => $older->id, 'field_key' => 'budget', 'field_value' => 'older'],
            ['lead_id' => $newerDuplicate->id, 'field_key' => 'budget', 'field_value' => 'newer'],
        ]);
        DB::table('external_events')->insert(['external_lead_id' => (string) $older->id]);

        app(LeadMergeService::class)->merge('919876543210', 1);

        $this->assertDatabaseHas('lead_form_field_values', [
            'lead_id' => $master->id,
            'field_key' => 'budget',
            'field_value' => 'newer',
        ]);
        $this->assertSame(1, DB::table('lead_form_field_values')->where('lead_id', $master->id)->where('field_key', 'budget')->count());
        $this->assertDatabaseHas('external_events', ['external_lead_id' => (string) $older->id]);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('normalized_phone')->nullable()->index();
            $table->unsignedBigInteger('merged_into_lead_id')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->string('merge_reason')->nullable();
            foreach (['address', 'city', 'state', 'pincode', 'property_type', 'budget', 'requirements', 'preferred_location', 'preferred_size', 'preferred_projects', 'use_end_use', 'possession_status', 'notes'] as $column) {
                $table->text($column)->nullable();
            }
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_merge_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('master_lead_id');
            $table->unsignedBigInteger('duplicate_lead_id')->unique();
            $table->string('normalized_phone')->nullable();
            $table->string('status');
            $table->json('master_snapshot')->nullable();
            $table->json('duplicate_snapshot')->nullable();
            $table->json('moved_record_counts')->nullable();
            $table->text('remark')->nullable();
            $table->text('failure_details')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->boolean('is_active')->default(true);
            $table->timestamp('unassigned_at')->nullable();
        });
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('status')->nullable();
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
        });
        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
        });
        Schema::create('lead_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lead_id');
            $table->unique(['user_id', 'lead_id']);
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
        });
        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->unique(['lead_id', 'field_key']);
        });
        Schema::create('external_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_lead_id');
        });
    }
}
