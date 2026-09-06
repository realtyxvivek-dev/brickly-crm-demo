<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppIncomingWebhookTest extends TestCase
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

    public function test_text_payload_creates_conversation_and_received_message(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'role_id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->insert([
            'id' => 10,
            'name' => 'Sheena Nelson',
            'phone' => '+1 6505551234',
            'source' => 'whatsapp',
            'status' => 'new',
            'status_auto_update_enabled' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '102290129340398',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [[
                            'profile' => ['name' => 'Sheena Nelson'],
                            'wa_id' => '16505551234',
                        ]],
                        'messages' => [[
                            'from' => '16505551234',
                            'id' => 'wamid.test-text-1',
                            'timestamp' => '1749416383',
                            'type' => 'text',
                            'text' => ['body' => 'Does it come in another color?'],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $response = $this->postJson('/api/webhooks/whatsapp/incoming', $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'phone_number' => '16505551234',
            'contact_name' => 'Sheena Nelson',
            'lead_id' => 10,
        ]);

        $conversationId = DB::table('whatsapp_conversations')->where('phone_number', '16505551234')->value('id');

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conversationId,
            'message_id' => 'wamid.test-text-1',
            'direction' => 'received',
            'message' => 'Does it come in another color?',
            'status' => 'delivered',
        ]);
    }

    public function test_document_payload_stores_placeholder_and_is_idempotent(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'role_id' => 1,
            'name' => 'Admin',
            'email' => 'admin2@example.test',
            'password' => bcrypt('secret'),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1355585469617453',
                'changes' => [[
                    'value' => [
                        'contacts' => [[
                            'profile' => ['name' => 'Muklesh'],
                            'wa_id' => '917903554671',
                        ]],
                        'messages' => [[
                            'from' => '917903554671',
                            'id' => 'wamid.test-doc-1',
                            'timestamp' => '1764153022',
                            'type' => 'document',
                            'document' => [
                                'filename' => 'sample.pdf',
                                'mime_type' => 'application/pdf',
                                'id' => '1518827202781328',
                                'url' => 'https://example.test/file.pdf',
                            ],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $this->postJson('/api/webhooks/whatsapp/incoming', $payload)->assertOk();
        $this->postJson('/api/webhooks/whatsapp/incoming', $payload)->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'message_id' => 'wamid.test-doc-1',
            'message' => 'sample.pdf',
            'direction' => 'received',
        ]);

        $this->assertSame(1, DB::table('whatsapp_messages')->where('message_id', 'wamid.test-doc-1')->count());
    }

    public function test_invalid_or_non_message_payload_is_ignored_safely(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp/incoming', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [],
                    'field' => 'statuses',
                ]],
            ]],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_click2api_webhook_verification_returns_challange_query(): void
    {
        $this->get('/api/webhooks/whatsapp/incoming?challange=click2api-abc123')
            ->assertOk()
            ->assertSee('click2api-abc123');

        $this->get('/api/webhooks/whatsapp/incoming?challenge=click2api-def456')
            ->assertOk()
            ->assertSee('click2api-def456');
    }

    public function test_meta_waba_webhook_verification_returns_challenge(): void
    {
        DB::table('meta_waba_settings')->insert([
            'is_active' => 1,
            'is_verified' => 0,
            'graph_version' => 'v20.0',
            'webhook_verify_token' => 'verify-me',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/api/webhooks/meta-waba?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
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

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Admin',
            'slug' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone_number');
            $table->string('contact_name')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('direction');
            $table->text('message');
            $table->string('message_id')->nullable();
            $table->string('template_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('external_message_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->json('api_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_api_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_endpoint')->default('https://engage-api-eta.vercel.app/');
            $table->text('api_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_waba_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('graph_version')->default('v20.0');
            $table->string('webhook_verify_token')->nullable();
            $table->timestamps();
        });
    }
}
