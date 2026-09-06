<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetaOauthWebhookTest extends TestCase
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
        config()->set('meta_oauth.webhook_verify_token', 'verify-secret');
        config()->set('meta_oauth.webhook_signature_enabled', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_oauth_webhook_accepts_valid_verify_token(): void
    {
        $this->get('/api/webhooks/facebook/oauth-leads?hub.mode=subscribe&hub.verify_token=verify-secret&hub.challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_oauth_webhook_rejects_invalid_verify_token(): void
    {
        $this->get('/api/webhooks/facebook/oauth-leads?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
            ->assertStatus(403);
    }

    public function test_oauth_webhook_stores_leadgen_payload_without_touching_old_event_table(): void
    {
        $this->postJson('/api/webhooks/facebook/oauth-leads', [
            'object' => 'page',
            'entry' => [[
                'id' => 'page_123',
                'changes' => [[
                    'field' => 'leadgen',
                    'value' => [
                        'page_id' => 'page_123',
                        'form_id' => 'form_456',
                        'leadgen_id' => 'lead_789',
                    ],
                ]],
            ]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('meta_oauth_events', [
            'page_id' => 'page_123',
            'form_id' => 'form_456',
            'leadgen_id' => 'lead_789',
            'status' => 'received',
        ]);
        $this->assertSame(0, DB::table('fb_webhook_events')->count());
    }

    private function createSchema(): void
    {
        Schema::create('meta_oauth_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_oauth_connection_id')->nullable();
            $table->string('page_id')->nullable();
            $table->string('page_name')->nullable();
            $table->text('page_access_token')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('leadgen_subscribed')->default(false);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_oauth_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_oauth_page_id')->nullable();
            $table->string('page_id')->nullable();
            $table->string('form_id')->nullable();
            $table->string('leadgen_id')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->json('raw_payload')->nullable();
            $table->string('leadgen_id')->nullable();
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
}
