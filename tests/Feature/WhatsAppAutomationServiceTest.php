<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAutomationJourney;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppApiService;
use App\Services\WhatsAppAutomationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class WhatsAppAutomationServiceTest extends TestCase
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
        Mockery::close();
        parent::tearDown();
    }

    public function test_source_specific_welcome_rule_wins_over_fallback(): void
    {
        $template = WhatsAppTemplate::create([
            'template_id' => 'welcome_template',
            'name' => 'welcome_template',
            'content' => 'Hi {{1}}',
            'language' => 'en_US',
            'is_active' => true,
        ]);

        $journey = WhatsAppAutomationJourney::create([
            'key' => 'new-lead-welcome',
            'name' => 'New Lead Welcome',
            'slug' => 'new-lead-welcome',
            'status' => 'active',
            'is_active' => true,
        ]);

        $fallbackRule = WhatsAppAutomationRule::create([
            'journey_id' => $journey->id,
            'name' => 'Default Welcome',
            'trigger' => 'lead_created',
            'status' => 'active',
            'is_active' => true,
            'priority' => 300,
            'template_id' => $template->id,
            'conditions' => [],
            'variable_map' => ['1' => 'lead_name'],
            'send_timing' => ['mode' => 'immediate'],
            'quiet_hour_policy' => ['enabled' => false, 'start' => '09:00', 'end' => '19:00'],
            'once_per_lead' => true,
            'resend_cap' => 1,
            'stop_statuses' => ['dead', 'closed'],
        ]);

        $sourceRule = WhatsAppAutomationRule::create([
            'journey_id' => $journey->id,
            'name' => 'Meta Welcome',
            'trigger' => 'lead_created',
            'status' => 'active',
            'is_active' => true,
            'priority' => 100,
            'template_id' => $template->id,
            'conditions' => ['source_mode' => 'exact', 'source_values' => ['meta']],
            'variable_map' => ['1' => 'lead_name'],
            'send_timing' => ['mode' => 'immediate'],
            'quiet_hour_policy' => ['enabled' => false, 'start' => '09:00', 'end' => '19:00'],
            'once_per_lead' => true,
            'resend_cap' => 1,
            'stop_statuses' => ['dead', 'closed'],
        ]);

        $lead = Lead::withoutEvents(fn () => Lead::create([
            'name' => 'Riya',
            'phone' => '9876543210',
            'source' => 'facebook_lead_ads',
            'status' => 'new',
            'created_by' => 1,
        ]));

        $mock = Mockery::mock(WhatsAppApiService::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->once()
            ->withArgs(function (string $phone, string $templateName, array $parameters) {
                return $phone === '9876543210'
                    && $templateName === 'welcome_template'
                    && ($parameters[0]['text'] ?? null) === 'Riya';
            })
            ->andReturn(['success' => true, 'data' => ['message_id' => 'wa-1']]);
        $this->app->instance(WhatsAppApiService::class, $mock);

        $service = $this->app->make(WhatsAppAutomationService::class);
        $service->handleTrigger('lead_created', ['lead' => $lead]);

        $this->assertDatabaseHas('whatsapp_automation_logs', [
            'rule_id' => $sourceRule->id,
            'status' => WhatsAppAutomationLog::STATUS_SENT,
        ]);
        $this->assertDatabaseMissing('whatsapp_automation_logs', [
            'rule_id' => $fallbackRule->id,
            'status' => WhatsAppAutomationLog::STATUS_SENT,
        ]);
    }

    public function test_missing_advisor_variable_fails_safely_without_sending(): void
    {
        $template = WhatsAppTemplate::create([
            'template_id' => 'advisor_intro',
            'name' => 'advisor_intro',
            'content' => 'Hi {{1}}, advisor {{2}}',
            'language' => 'en_US',
            'is_active' => true,
        ]);

        $journey = WhatsAppAutomationJourney::create([
            'key' => 'assigned-advisor-introduction',
            'name' => 'Assigned Advisor Introduction',
            'slug' => 'assigned-advisor-introduction',
            'status' => 'active',
            'is_active' => true,
        ]);

        $rule = WhatsAppAutomationRule::create([
            'journey_id' => $journey->id,
            'name' => 'Advisor Introduction',
            'trigger' => 'lead_assigned',
            'status' => 'active',
            'is_active' => true,
            'priority' => 100,
            'template_id' => $template->id,
            'conditions' => [],
            'variable_map' => ['1' => 'lead_name', '2' => 'assigned_advisor_name'],
            'send_timing' => ['mode' => 'immediate'],
            'quiet_hour_policy' => ['enabled' => false, 'start' => '09:00', 'end' => '19:00'],
            'once_per_lead' => true,
            'resend_cap' => 1,
            'stop_statuses' => ['dead', 'closed'],
        ]);

        $lead = Lead::withoutEvents(fn () => Lead::create([
            'name' => 'Aman',
            'phone' => '9988776655',
            'source' => 'website',
            'status' => 'new',
            'created_by' => 1,
        ]));

        $mock = Mockery::mock(WhatsAppApiService::class);
        $mock->shouldNotReceive('sendTemplateMessage');
        $this->app->instance(WhatsAppApiService::class, $mock);

        $service = $this->app->make(WhatsAppAutomationService::class);
        $service->handleTrigger('lead_assigned', ['lead' => $lead]);

        $this->assertDatabaseHas('whatsapp_automation_logs', [
            'rule_id' => $rule->id,
            'status' => WhatsAppAutomationLog::STATUS_FAILED,
        ]);
    }

    public function test_duplicate_trigger_is_blocked_by_once_per_lead_guard(): void
    {
        $template = WhatsAppTemplate::create([
            'template_id' => 'welcome_once',
            'name' => 'welcome_once',
            'content' => 'Hi {{1}}',
            'language' => 'en_US',
            'is_active' => true,
        ]);

        $journey = WhatsAppAutomationJourney::create([
            'key' => 'new-lead-welcome-once',
            'name' => 'New Lead Welcome Once',
            'slug' => 'new-lead-welcome-once',
            'status' => 'active',
            'is_active' => true,
        ]);

        $rule = WhatsAppAutomationRule::create([
            'journey_id' => $journey->id,
            'name' => 'Welcome Once',
            'trigger' => 'lead_created',
            'status' => 'active',
            'is_active' => true,
            'priority' => 100,
            'template_id' => $template->id,
            'conditions' => [],
            'variable_map' => ['1' => 'lead_name'],
            'send_timing' => ['mode' => 'immediate'],
            'quiet_hour_policy' => ['enabled' => false, 'start' => '09:00', 'end' => '19:00'],
            'once_per_lead' => true,
            'resend_cap' => 1,
            'stop_statuses' => ['dead', 'closed'],
        ]);

        $lead = Lead::withoutEvents(fn () => Lead::create([
            'name' => 'Neha',
            'phone' => '9000011111',
            'source' => 'website',
            'status' => 'new',
            'created_by' => 1,
        ]));

        $mock = Mockery::mock(WhatsAppApiService::class);
        $mock->shouldReceive('sendTemplateMessage')->once()->andReturn(['success' => true, 'data' => ['message_id' => 'once-1']]);
        $this->app->instance(WhatsAppApiService::class, $mock);

        $service = $this->app->make(WhatsAppAutomationService::class);
        $service->handleTrigger('lead_created', ['lead' => $lead]);
        $service->handleTrigger('lead_created', ['lead' => $lead]);

        $this->assertSame(1, WhatsAppAutomationLog::query()->where('rule_id', $rule->id)->where('status', WhatsAppAutomationLog::STATUS_SENT)->count());
        $this->assertSame(1, WhatsAppAutomationLog::query()->where('rule_id', $rule->id)->where('status', WhatsAppAutomationLog::STATUS_SKIPPED)->count());
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('password')->nullable();
            $table->rememberToken();
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
            $table->string('city')->nullable();
            $table->string('preferred_projects')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->boolean('is_dead')->default(false);
            $table->boolean('is_reenquiry')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_id')->nullable();
            $table->string('name');
            $table->text('content')->nullable();
            $table->string('category')->nullable();
            $table->string('language')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_automation_journeys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_preset')->default(false);
            $table->boolean('test_mode')->default(false);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->text('description')->nullable();
            $table->json('default_filters')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id');
            $table->string('name');
            $table->string('trigger');
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->boolean('test_mode')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->json('conditions')->nullable();
            $table->json('variable_map')->nullable();
            $table->json('send_timing')->nullable();
            $table->json('quiet_hour_policy')->nullable();
            $table->boolean('once_per_lead')->default(true);
            $table->unsignedInteger('resend_cap')->default(1);
            $table->json('stop_statuses')->nullable();
            $table->timestamp('last_executed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journey_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('trigger');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('execution_key')->unique();
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->json('resolved_variables')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();
        });

        Role::create(['name' => 'Sales Manager', 'slug' => Role::SALES_MANAGER]);
        User::create([
            'name' => 'Advisor',
            'email' => 'advisor@example.test',
            'phone' => '9999999999',
            'role_id' => 1,
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
