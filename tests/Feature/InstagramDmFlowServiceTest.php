<?php

namespace Tests\Feature;

use App\Models\IgAutomationRule;
use App\Models\IgConversation;
use App\Models\IgConversationState;
use App\Models\IgDmFlow;
use App\Models\IgDmFlowStep;
use App\Models\IgWebhookEvent;
use App\Models\InstagramAccount;
use App\Services\SourceAutomationService;
use App\Services\WhatsAppAutomationTriggerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class InstagramDmFlowServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('instagram.base_url', 'https://graph.instagram.com');
        Config::set('instagram.graph_version', 'v21.0');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->app->instance(SourceAutomationService::class, Mockery::mock(SourceAutomationService::class, function ($mock) {
            $mock->shouldReceive('assignFromSource')->andReturnFalse();
        }));
        $this->app->instance(WhatsAppAutomationTriggerService::class, Mockery::mock(WhatsAppAutomationTriggerService::class, function ($mock) {
            $mock->shouldReceive('leadCreated')->andReturnNull();
        }));

        $this->createSchema();
    }

    public function test_city_reply_is_saved_and_phone_step_is_sent(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'mid-phone-question'], 200)]);
        [$account, $conversation, $event] = $this->seedConversation($this->messagePayload('Delhi'));

        app(\App\Services\Instagram\InstagramDmFlowService::class)->handleMessageEvent($event, $account);

        $conversation->refresh();
        $this->assertSame('Delhi', $conversation->collected_fields['city'] ?? null);
        $this->assertDatabaseHas('ig_conversation_states', [
            'conversation_id' => $conversation->id,
            'current_step' => 2,
            'current_field' => 'phone',
            'completed' => 0,
        ]);
        $this->assertDatabaseHas('ig_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'sent',
            'message_text' => 'Please share mobile number.',
            'status' => 'sent',
        ]);
    }

    public function test_invalid_phone_sends_retry_and_does_not_create_lead(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'mid-retry'], 200)]);
        [$account, $conversation, $event] = $this->seedConversation($this->messagePayload('not-phone'), currentStep: 2, currentField: 'phone', fields: ['city' => 'Delhi']);

        app(\App\Services\Instagram\InstagramDmFlowService::class)->handleMessageEvent($event, $account);

        $this->assertSame(0, DB::table('leads')->count());
        $this->assertDatabaseHas('ig_conversation_states', [
            'conversation_id' => $conversation->id,
            'current_step' => 2,
            'current_field' => 'phone',
            'retries' => 1,
        ]);
    }

    public function test_valid_phone_creates_meta_lead_and_completes_conversation(): void
    {
        Http::fake(['*' => Http::response(['message_id' => 'mid-thanks'], 200)]);
        [$account, $conversation, $event] = $this->seedConversation($this->messagePayload('9876543210'), currentStep: 2, currentField: 'phone', fields: ['city' => 'Delhi']);

        app(\App\Services\Instagram\InstagramDmFlowService::class)->handleMessageEvent($event, $account);

        $conversation->refresh();
        $lead = $conversation->lead;

        $this->assertNotNull($lead);
        $this->assertSame('meta', $lead->source);
        $this->assertSame('919876543210', $lead->phone);
        $this->assertSame('Delhi', $lead->city);
        $this->assertSame('completed', $conversation->status);
        $this->assertDatabaseHas('ig_conversation_states', [
            'conversation_id' => $conversation->id,
            'completed' => 1,
        ]);
    }

    private function seedConversation(array $payload, int $currentStep = 1, string $currentField = 'city', array $fields = []): array
    {
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = InstagramAccount::query()->create([
            'ig_user_id' => 'ig-business-1',
            'ig_username' => 'business',
            'access_token' => 'token',
            'status' => 'connected',
            'connected_by' => 1,
        ]);

        $flow = IgDmFlow::query()->create(['name' => 'Lead Flow', 'status' => 'active', 'is_active' => true]);
        IgDmFlowStep::query()->create(['flow_id' => $flow->id, 'step_order' => 1, 'message_text' => 'Which city?', 'save_reply_as' => 'city']);
        IgDmFlowStep::query()->create(['flow_id' => $flow->id, 'step_order' => 2, 'message_text' => 'Please share mobile number.', 'save_reply_as' => 'phone']);
        IgDmFlowStep::query()->create(['flow_id' => $flow->id, 'step_order' => 3, 'message_text' => 'Thanks, team will contact you.', 'save_reply_as' => null]);

        $rule = IgAutomationRule::query()->create([
            'instagram_account_id' => $account->id,
            'name' => 'Price Rule',
            'keywords' => ['price'],
            'public_reply_message' => 'Check DM',
            'dm_flow_id' => $flow->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $conversation = IgConversation::query()->create([
            'instagram_account_id' => $account->id,
            'instagram_user_id' => 'ig-user-1',
            'instagram_username' => 'customer',
            'original_comment' => 'price',
            'comment_id' => 'comment-1',
            'automation_rule_id' => $rule->id,
            'collected_fields' => $fields,
            'status' => 'open',
        ]);

        IgConversationState::query()->create([
            'conversation_id' => $conversation->id,
            'flow_id' => $flow->id,
            'current_step' => $currentStep,
            'current_field' => $currentField,
            'completed' => false,
            'retries' => 0,
        ]);

        $event = IgWebhookEvent::query()->create([
            'instagram_account_id' => $account->id,
            'event_type' => 'messages',
            'field' => 'messages',
            'object_type' => 'instagram',
            'payload' => $payload,
            'status' => 'queued',
        ]);

        return [$account, $conversation, $event];
    }

    private function messagePayload(string $text): array
    {
        return [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'ig-business-1',
                'messaging' => [[
                    'sender' => ['id' => 'ig-user-1'],
                    'recipient' => ['id' => 'ig-business-1'],
                    'timestamp' => 1764153022000,
                    'message' => [
                        'mid' => 'mid-' . md5($text),
                        'text' => $text,
                    ],
                ]],
            ]],
        ];
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('text');
            $table->string('category')->nullable();
            $table->string('group')->nullable();
            $table->string('display_label')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->text('help_text')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('ig_user_id')->unique();
            $table->string('ig_username')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expiry')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('connected_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ig_dm_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ig_dm_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flow_id');
            $table->unsignedInteger('step_order');
            $table->text('message_text')->nullable();
            $table->string('save_reply_as')->nullable();
            $table->string('message_type')->default('text');
            $table->text('media_url')->nullable();
            $table->string('attachment_type')->nullable();
            $table->timestamps();
        });

        Schema::create('ig_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_account_id');
            $table->string('name');
            $table->json('keywords')->nullable();
            $table->text('public_reply_message')->nullable();
            $table->unsignedBigInteger('dm_flow_id')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ig_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_account_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('duplicate_lead_id')->nullable();
            $table->timestamp('duplicate_checked_at')->nullable();
            $table->string('instagram_user_id')->nullable();
            $table->string('instagram_username')->nullable();
            $table->text('original_comment')->nullable();
            $table->string('comment_id')->nullable();
            $table->string('media_id')->nullable();
            $table->unsignedBigInteger('automation_rule_id')->nullable();
            $table->json('collected_fields')->nullable();
            $table->boolean('is_human_taken_over')->default(false);
            $table->unsignedBigInteger('human_taken_over_by')->nullable();
            $table->timestamp('human_taken_over_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ig_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedInteger('current_step')->default(1);
            $table->string('current_field')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('last_reply_at')->nullable();
            $table->unsignedInteger('retries')->default(0);
            $table->unsignedBigInteger('flow_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ig_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction');
            $table->text('message_text')->nullable();
            $table->string('message_type')->default('text');
            $table->text('media_url')->nullable();
            $table->string('attachment_type')->nullable();
            $table->string('meta_message_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ig_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_account_id')->nullable();
            $table->string('external_event_id')->nullable();
            $table->string('event_type')->nullable();
            $table->string('object_type')->nullable();
            $table->string('field')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ig_api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_account_id')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->unsignedInteger('status_code')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }
}
