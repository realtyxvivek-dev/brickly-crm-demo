<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\NinetyNineAcresRequestLog;
use App\Models\NinetyNineAcresSetting;
use App\Models\SourceAutomationRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NinetyNineAcresIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
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

    public function test_valid_webhook_creates_99acres_lead_and_log(): void
    {
        $settings = NinetyNineAcresSetting::create([
            'is_enabled' => true,
            'api_key' => 'nna_test_key',
            'default_status' => 'new',
            'fallback_type' => NinetyNineAcresSetting::FALLBACK_UNASSIGNED_CRM_QUEUE,
        ]);

        $response = $this->withHeader('X-API-Key', $settings->api_key)
            ->postJson('/api/webhooks/99acres/leads', $this->payload());

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'duplicate' => false,
            ]);

        $this->assertDatabaseHas('leads', [
            'name' => 'Customer Name',
            'phone' => '919876543210',
            'source' => '99acres',
            'status' => 'new',
            'budget' => '80 Lac - 1 Cr',
        ]);

        $this->assertDatabaseHas('ninety_nine_acres_request_logs', [
            'status' => 'success',
            'external_lead_id' => 'nna-1',
            'phone' => '919876543210',
            'duplicate' => false,
        ]);
    }

    public function test_invalid_api_key_returns_401_and_logs_auth_failure(): void
    {
        NinetyNineAcresSetting::create([
            'is_enabled' => true,
            'api_key' => 'nna_test_key',
            'fallback_type' => NinetyNineAcresSetting::FALLBACK_UNASSIGNED_CRM_QUEUE,
        ]);

        $response = $this->withHeader('X-API-Key', 'wrong')
            ->postJson('/api/webhooks/99acres/leads', $this->payload());

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid API key.',
            ]);

        $this->assertDatabaseHas('ninety_nine_acres_request_logs', [
            'status' => 'auth_failed',
        ]);
        $this->assertSame(0, Lead::count());
    }

    public function test_missing_phone_returns_422_and_logs_validation_failure(): void
    {
        $settings = NinetyNineAcresSetting::create([
            'is_enabled' => true,
            'api_key' => 'nna_test_key',
            'fallback_type' => NinetyNineAcresSetting::FALLBACK_UNASSIGNED_CRM_QUEUE,
        ]);

        $payload = $this->payload();
        unset($payload['phone']);

        $response = $this->withHeader('X-API-Key', $settings->api_key)
            ->postJson('/api/webhooks/99acres/leads', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validation failed.',
            ]);

        $this->assertDatabaseHas('ninety_nine_acres_request_logs', [
            'status' => 'validation_failed',
        ]);
        $this->assertSame(0, Lead::count());
    }

    public function test_duplicate_phone_marks_reenquiry_without_creating_second_lead(): void
    {
        $settings = NinetyNineAcresSetting::create([
            'is_enabled' => true,
            'api_key' => 'nna_test_key',
            'fallback_type' => NinetyNineAcresSetting::FALLBACK_UNASSIGNED_CRM_QUEUE,
        ]);

        $existing = Lead::create([
            'name' => 'Existing Lead',
            'phone' => '919876543210',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => 1,
        ]);

        $response = $this->withHeader('X-API-Key', $settings->api_key)
            ->postJson('/api/webhooks/99acres/leads', $this->payload());

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'duplicate' => true,
                'lead_id' => $existing->id,
            ]);

        $this->assertSame(1, Lead::count());
        $this->assertTrue((bool) $existing->fresh()->is_reenquiry);
        $this->assertSame('99acres', $existing->fresh()->last_reenquiry_source);
        $this->assertDatabaseHas('ninety_nine_acres_request_logs', [
            'status' => 'duplicate',
            'lead_id' => $existing->id,
            'duplicate' => true,
        ]);
    }

    public function test_valid_webhook_uses_99acres_source_automation_rule(): void
    {
        $settings = NinetyNineAcresSetting::create([
            'is_enabled' => true,
            'api_key' => 'nna_test_key',
            'fallback_type' => NinetyNineAcresSetting::FALLBACK_UNASSIGNED_CRM_QUEUE,
        ]);

        DB::table('users')->insert([
            'id' => 2,
            'name' => 'Sales User',
            'email' => 'sales@example.test',
            'is_active' => true,
        ]);

        SourceAutomationRule::create([
            'name' => '99acres Lead Distribution',
            'source' => '99acres',
            'assignment_method' => 'single_user',
            'single_user_id' => 2,
            'is_active' => true,
            'auto_create_task' => false,
            'created_by' => 1,
        ]);

        $response = $this->withHeader('X-API-Key', $settings->api_key)
            ->postJson('/api/webhooks/99acres/leads', $this->payload());

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'duplicate' => false,
            ]);

        $leadId = $response->json('lead_id');

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $leadId,
            'assigned_to' => 2,
            'assignment_method' => 'single_user',
            'is_active' => true,
        ]);

        $log = NinetyNineAcresRequestLog::where('lead_id', $leadId)->firstOrFail();
        $this->assertTrue((bool) data_get($log->assignment_result, 'matched'));
        $this->assertSame(2, data_get($log->assignment_result, 'assigned_to.id'));
    }

    private function payload(): array
    {
        return [
            'lead_id' => 'nna-1',
            'name' => 'Customer Name',
            'phone' => '9876543210',
            'email' => 'customer@example.com',
            'project_name' => 'Project Name',
            'location' => 'Noida',
            'budget' => '80 Lac - 1 Cr',
            'property_type' => '3 BHK',
            'message' => 'Customer requirement',
            'created_at' => '2026-08-05 12:30:00',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'is_active' => true]);

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->boolean('is_absent')->default(false);
            $table->text('absent_reason')->nullable();
            $table->timestamp('absent_until')->nullable();
            $table->timestamp('lead_off_start_at')->nullable();
            $table->timestamp('lead_off_end_at')->nullable();
            $table->string('lead_off_source')->nullable();
            $table->foreignId('lead_off_set_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->string('property_type')->nullable();
            $table->string('budget')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->string('preferred_location')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->boolean('is_reenquiry')->default(false);
            $table->unsignedInteger('reenquiry_count')->default(0);
            $table->timestamp('last_reenquiry_at')->nullable();
            $table->string('last_reenquiry_source')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->foreignId('other_lead_marked_by')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->text('other_lead_reason')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->text('dead_reason')->nullable();
            $table->string('dead_at_stage')->nullable();
            $table->timestamp('marked_dead_at')->nullable();
            $table->foreignId('marked_dead_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('assigned_by')->nullable();
            $table->string('assignment_type')->default('primary');
            $table->string('assignment_method')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('source_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('source');
            $table->foreignId('fb_form_id')->nullable();
            $table->foreignId('google_sheet_config_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('assignment_method')->default('single_user');
            $table->foreignId('single_user_id')->nullable();
            $table->foreignId('fallback_user_id')->nullable();
            $table->foreignId('created_by')->default(1);
            $table->boolean('auto_create_task')->default(false);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('source_automation_rule_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id');
            $table->foreignId('user_id');
            $table->unsignedInteger('assigned_count_today')->default(0);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->date('last_assigned_date')->nullable();
            $table->date('last_reset_date')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        Schema::create('ninety_nine_acres_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('api_key')->unique();
            $table->string('default_status')->default('new');
            $table->string('fallback_type')->default('unassigned_crm_queue');
            $table->foreignId('fallback_user_id')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ninety_nine_acres_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ninety_nine_acres_setting_id')->nullable();
            $table->string('request_id')->index();
            $table->string('request_ip')->nullable();
            $table->string('external_lead_id')->nullable();
            $table->string('phone')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('mapped_payload')->nullable();
            $table->json('validation_result')->nullable();
            $table->json('assignment_result')->nullable();
            $table->json('fallback_result')->nullable();
            $table->string('status')->default('received');
            $table->foreignId('lead_id')->nullable();
            $table->boolean('duplicate')->default(false);
            $table->boolean('is_test')->default(false);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }
}
