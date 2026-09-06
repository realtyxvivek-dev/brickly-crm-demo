<?php

namespace Tests\Feature;

use App\Services\LeadAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleSheetsSecureWebhookTest extends TestCase
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

        $assignmentService = \Mockery::mock(LeadAssignmentService::class);
        $assignmentService->shouldReceive('assignLead')->andReturn(1);
        $this->app->instance(LeadAssignmentService::class, $assignmentService);

        Event::fake();
    }

    public function test_webhook_accepts_valid_slug_and_api_key(): void
    {
        $this->seedAdminUser();
        $configId = $this->createGoogleSheetsConfig([
            'inbound_slug' => 'gsi_valid_slug',
            'inbound_api_key' => 'gsk_valid_key',
            'is_active' => 1,
        ]);

        $response = $this->postJson('/api/webhooks/google-sheets/gsi_valid_slug', [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 2,
            'name' => 'Riya Sharma',
            'phone' => '9876543210',
            'email' => 'riya@example.test',
        ], [
            'X-API-Key' => 'gsk_valid_key',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'message' => 'Lead created successfully',
                'duplicate' => false,
            ]);

        $this->assertDatabaseHas('leads', [
            'name' => 'Riya Sharma',
            'phone' => '9876543210',
            'source' => 'sheet',
        ]);

        $this->assertDatabaseHas('google_sheets_request_logs', [
            'google_sheets_config_id' => $configId,
            'status' => 'success',
        ]);
    }

    public function test_webhook_rejects_missing_or_invalid_api_key(): void
    {
        $configId = $this->createGoogleSheetsConfig([
            'inbound_slug' => 'gsi_auth_slug',
            'inbound_api_key' => 'gsk_secret_key',
            'is_active' => 1,
        ]);

        $this->postJson('/api/webhooks/google-sheets/gsi_auth_slug', [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 2,
            'name' => 'No Auth',
            'phone' => '9999999999',
        ])->assertStatus(401);

        $this->postJson('/api/webhooks/google-sheets/gsi_auth_slug', [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 2,
            'name' => 'Bad Auth',
            'phone' => '9999999999',
        ], [
            'X-API-Key' => 'wrong-key',
        ])->assertStatus(401);

        $this->assertSame(2, DB::table('google_sheets_request_logs')
            ->where('google_sheets_config_id', $configId)
            ->where('status', 'auth_failed')
            ->count());
    }

    public function test_webhook_returns_not_found_or_inactive_responses(): void
    {
        $this->postJson('/api/webhooks/google-sheets/missing-slug', [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 1,
            'name' => 'Missing',
            'phone' => '9999999999',
        ], [
            'X-API-Key' => 'anything',
        ])->assertStatus(404);

        $this->createGoogleSheetsConfig([
            'inbound_slug' => 'gsi_inactive_slug',
            'inbound_api_key' => 'gsk_inactive_key',
            'is_active' => 0,
        ]);

        $this->postJson('/api/webhooks/google-sheets/gsi_inactive_slug', [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 3,
            'name' => 'Inactive',
            'phone' => '9999999998',
        ], [
            'X-API-Key' => 'gsk_inactive_key',
        ])->assertStatus(410);
    }

    public function test_webhook_returns_validation_error_without_raw_exception_text(): void
    {
        $this->createGoogleSheetsConfig([
            'inbound_slug' => 'gsi_validation_slug',
            'inbound_api_key' => 'gsk_validation_key',
            'is_active' => 1,
        ]);

        $response = $this->postJson('/api/webhooks/google-sheets/gsi_validation_slug', [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 4,
            'name' => 'Bad Phone',
            'phone' => 'abc',
        ], [
            'X-API-Key' => 'gsk_validation_key',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validation failed',
            ]);
    }

    public function test_generate_script_contains_secure_webhook_and_api_key_header(): void
    {
        $admin = $this->seedAdminUser();
        $configId = $this->createGoogleSheetsConfig([
            'sheet_type' => 'custom',
            'inbound_slug' => 'gsi_script_slug',
            'inbound_api_key' => 'gsk_script_key',
            'created_by' => 1,
        ]);

        DB::table('google_sheets_column_mappings')->insert([
            'google_sheets_config_id' => $configId,
            'sheet_column' => 'A',
            'lead_field_key' => 'name',
            'field_label' => 'Full Name',
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('integrations.form-integration.generate-script', $configId));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $script = $response->json('script');

        $this->assertStringContainsString('/api/webhooks/google-sheets/gsi_script_slug', $script);
        $this->assertStringContainsString('X-API-Key', $script);
        $this->assertStringContainsString('gsk_script_key', $script);
    }

    public function test_rotating_secret_invalidates_old_key_and_allows_new_key(): void
    {
        $admin = $this->seedAdminUser();
        $this->createGoogleSheetsConfig([
            'inbound_slug' => 'gsi_rotate_slug',
            'inbound_api_key' => 'gsk_old_key',
            'is_active' => 1,
            'created_by' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('integrations.form-integration.rotate-secret', 1))
            ->assertRedirect();

        $newKey = DB::table('google_sheets_config')->where('id', 1)->value('inbound_api_key');
        $this->assertNotSame('gsk_old_key', $newKey);

        $payload = [
            'sheet_id' => 'sheet_123',
            'sheet_row_number' => 5,
            'name' => 'Rotate Test',
            'phone' => '9999999997',
        ];

        $this->postJson('/api/webhooks/google-sheets/gsi_rotate_slug', $payload, [
            'X-API-Key' => 'gsk_old_key',
        ])->assertStatus(401);

        $this->postJson('/api/webhooks/google-sheets/gsi_rotate_slug', $payload, [
            'X-API-Key' => $newKey,
        ])->assertStatus(200);
    }

    private function seedAdminUser()
    {
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Admin',
            'slug' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'role_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return \App\Models\User::find(1);
    }

    private function createGoogleSheetsConfig(array $overrides = []): int
    {
        $id = (int) ($overrides['id'] ?? (DB::table('google_sheets_config')->max('id') + 1));

        DB::table('google_sheets_config')->insert(array_merge([
            'id' => $id,
            'sheet_id' => 'sheet_123',
            'sheet_name' => 'Sheet1',
            'sheet_type' => 'custom',
            'api_key' => null,
            'inbound_slug' => 'gsi_' . $id,
            'inbound_api_key' => 'gsk_' . $id,
            'service_account_json_path' => null,
            'api_endpoint_url' => null,
            'linked_telecaller_id' => null,
            'is_active' => 1,
            'is_draft' => 0,
            'completion_notification_sent' => 0,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('google_sheets_config', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_id');
            $table->string('sheet_name')->default('Sheet1');
            $table->string('sheet_type')->nullable();
            $table->json('selected_columns_json')->nullable();
            $table->json('crm_status_columns_json')->nullable();
            $table->string('api_endpoint_url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('inbound_slug')->nullable();
            $table->string('inbound_api_key')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('service_account_json_path')->nullable();
            $table->string('range')->nullable();
            $table->string('name_column')->nullable();
            $table->string('phone_column')->nullable();
            $table->string('notes_column')->nullable();
            $table->string('status_column')->nullable();
            $table->string('notes_column_sync')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->integer('last_synced_row')->default(0);
            $table->boolean('auto_sync_enabled')->default(true);
            $table->integer('sync_interval_minutes')->default(5);
            $table->unsignedBigInteger('assignment_rule_id')->nullable();
            $table->unsignedBigInteger('automation_id')->nullable();
            $table->unsignedBigInteger('linked_telecaller_id')->nullable();
            $table->integer('per_sheet_daily_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_draft')->default(false);
            $table->dateTime('setup_completed_at')->nullable();
            $table->boolean('completion_notification_sent')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('google_sheets_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('google_sheets_config_id');
            $table->string('sheet_column')->nullable();
            $table->string('lead_field_key');
            $table->string('field_label')->nullable();
            $table->integer('display_order')->default(1);
            $table->timestamps();
        });

        Schema::create('google_sheets_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('google_sheets_config_id');
            $table->string('request_id');
            $table->string('request_ip')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status')->default('success');
            $table->boolean('is_test')->default(false);
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('property_type')->nullable();
            $table->string('budget')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->default('other');
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('sheet_config_id')->nullable();
            $table->integer('sheet_row_number')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->string('assignment_method')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
