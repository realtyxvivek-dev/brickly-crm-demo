<?php

namespace Tests\Feature;

use App\Events\LeadAssigned;
use App\Mail\NewLeadSlaEscalationMail;
use App\Models\AppNotification;
use App\Models\FbForm;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\NewLeadSlaAutomationAudit;
use App\Models\NewLeadSlaAutomationConfig;
use App\Models\NewLeadSlaAutomationState;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\NewLeadSlaAutomationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NewLeadSlaAutomationServiceTest extends TestCase
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
        Config::set('mail.default', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        Carbon::setTestNow(Carbon::parse('2026-04-01 11:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sync_for_assignment_creates_active_state_for_configured_new_lead(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-sla-sync@example.test']);
        $salesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'sales-sla-sync@example.test']);
        $lead = $this->createLead(['source' => 'meta', 'status' => 'new']);
        $form = $this->createFbForm();
        $this->linkLeadToFbForm($lead, $form);
        $assignment = $this->createAssignment($lead, $salesUser, $crm, 'manual');
        $config = $this->createConfig($crm, 'meta', [$salesUser->id], [$crm->id], [], $form->id);

        $state = app(NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        $this->assertNotNull($state);
        $this->assertSame($lead->id, $state->lead_id);
        $this->assertSame($config->id, $state->config_id);
        $this->assertSame($salesUser->id, $state->current_assigned_to);
        $this->assertSame('active', $state->status);
        $this->assertNotNull($state->sla_deadline_at);
        $this->assertDatabaseHas('new_lead_sla_automation_audits', [
            'state_id' => $state->id,
            'action' => 'assigned',
            'to_user_id' => $salesUser->id,
        ]);
    }

    public function test_process_cycle_reassigns_missed_lead_and_counts_lost_lead_for_old_user(): void
    {
        Event::fake([LeadAssigned::class]);

        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-sla-transfer@example.test']);
        $userA = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'sales-a-transfer@example.test']);
        $userB = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'sales-b-transfer@example.test']);
        $lead = $this->createLead(['source' => 'meta', 'status' => 'new']);
        $form = $this->createFbForm();
        $this->linkLeadToFbForm($lead, $form);
        $config = $this->createConfig($crm, 'meta', [$userA->id, $userB->id], [$crm->id], [], $form->id);
        $assignment = $this->createAssignment($lead, $userA, $crm, 'new_lead_sla_round_robin');

        $state = app(NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $userA->id,
            'created_by' => $crm->id,
            'type' => 'phone_call',
            'title' => 'Call lead',
            'status' => 'pending',
            'scheduled_at' => Carbon::now()->subHour(),
        ]);

        $state->update([
            'sla_started_at' => Carbon::now()->subHours(5),
            'sla_deadline_at' => Carbon::now()->subMinute(),
        ]);

        $result = app(NewLeadSlaAutomationService::class)->processAutomationCycle();

        $state->refresh();
        $oldAssignment = $assignment->fresh();
        $newAssignment = LeadAssignment::query()->where('lead_id', $lead->id)->where('assigned_to', $userB->id)->latest('id')->first();
        $archivedTask = Task::withQueueHidden()->where('lead_id', $lead->id)->where('assigned_to', $userA->id)->first();

        $this->assertSame(1, $result['transferred']);
        $this->assertSame('active', $state->status);
        $this->assertSame($userB->id, $state->current_assigned_to);
        $this->assertSame(2, $state->attempt_number);
        $this->assertFalse($oldAssignment->is_active);
        $this->assertNotNull($newAssignment);
        $this->assertTrue($newAssignment->is_active);
        $this->assertSame('lead_transferred', $archivedTask?->queue_hidden_reason);
        $this->assertSame(1, app(NewLeadSlaAutomationService::class)->countLostLeadsForUserThisMonth($userA->id));
        $this->assertDatabaseHas('new_lead_sla_automation_audits', [
            'state_id' => $state->id,
            'action' => 'sla_missed',
            'from_user_id' => $userA->id,
        ]);
        $this->assertDatabaseHas('new_lead_sla_automation_audits', [
            'state_id' => $state->id,
            'action' => 'reassigned',
            'from_user_id' => $userA->id,
            'to_user_id' => $userB->id,
        ]);
        Event::assertDispatched(LeadAssigned::class);
        $this->assertSame($userB->id, (int) $config->fresh()->last_round_robin_user_id);
    }

    public function test_process_cycle_marks_state_responded_when_any_outcome_exists_before_deadline(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-sla-response@example.test']);
        $salesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'sales-sla-response@example.test']);
        $lead = $this->createLead(['source' => 'meta', 'status' => 'new']);
        $form = $this->createFbForm();
        $this->linkLeadToFbForm($lead, $form);
        $this->createConfig($crm, 'meta', [$salesUser->id], [$crm->id], [], $form->id);
        $assignment = $this->createAssignment($lead, $salesUser, $crm, 'new_lead_sla_round_robin');
        $state = app(NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $salesUser->id,
            'created_by' => $crm->id,
            'type' => 'phone_call',
            'title' => 'Outcome call',
            'status' => 'completed',
            'outcome' => 'follow_up',
            'scheduled_at' => Carbon::now()->subHours(2),
            'completed_at' => Carbon::now()->subMinutes(10),
            'outcome_recorded_at' => Carbon::now()->subMinutes(10),
        ]);

        $state->update([
            'sla_started_at' => Carbon::now()->subHours(4),
            'sla_deadline_at' => Carbon::now()->subMinute(),
        ]);

        $result = app(NewLeadSlaAutomationService::class)->processAutomationCycle();

        $state->refresh();

        $this->assertSame(1, $result['responded']);
        $this->assertSame('responded', $state->status);
        $this->assertSame('follow_up', $state->response_outcome);
        $this->assertNotNull($state->responded_at);
        $this->assertDatabaseHas('new_lead_sla_automation_audits', [
            'state_id' => $state->id,
            'action' => 'responded',
            'from_user_id' => $salesUser->id,
        ]);
    }

    public function test_process_cycle_escalates_when_initial_assignment_has_no_eligible_user_and_notifies_recipients(): void
    {
        Mail::fake();

        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-sla-escalation@example.test']);
        $inactiveSalesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), [
            'email' => 'sales-sla-inactive@example.test',
            'is_active' => false,
        ]);
        $lead = $this->createLead(['source' => 'meta', 'status' => 'new']);
        $form = $this->createFbForm();
        $this->linkLeadToFbForm($lead, $form);
        $config = $this->createConfig($crm, 'meta', [$inactiveSalesUser->id], [$crm->id], ['skip_inactive_users' => true], $form->id);

        $result = app(NewLeadSlaAutomationService::class)->processAutomationCycle();

        $state = NewLeadSlaAutomationState::query()->where('lead_id', $lead->id)->where('config_id', $config->id)->first();

        $this->assertSame(0, $result['assigned']);
        $this->assertNotNull($state);
        $this->assertSame('escalated', $state->status);
        $this->assertSame(0, $state->attempt_number);
        $this->assertDatabaseHas('new_lead_sla_automation_audits', [
            'state_id' => $state->id,
            'action' => 'escalated',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $crm->id,
            'title' => 'New Lead SLA Escalation',
        ]);
        Mail::assertSent(NewLeadSlaEscalationMail::class, 1);
    }

    public function test_meta_lead_matches_only_its_exact_fb_form_config(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-sla-form-match@example.test']);
        $salesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'sales-sla-form-match@example.test']);
        $lead = $this->createLead(['source' => 'meta', 'status' => 'new']);
        $matchingForm = $this->createFbForm('Plot Form');
        $otherForm = $this->createFbForm('Apartment Form');
        $this->linkLeadToFbForm($lead, $matchingForm);
        $this->createConfig($crm, 'meta', [$salesUser->id], [$crm->id], ['name' => 'Other Form'], $otherForm->id);
        $matchingConfig = $this->createConfig($crm, 'meta', [$salesUser->id], [$crm->id], ['name' => 'Matching Form'], $matchingForm->id);
        $assignment = $this->createAssignment($lead, $salesUser, $crm, 'manual');

        $state = app(NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        $this->assertNotNull($state);
        $this->assertSame($matchingConfig->id, $state->config_id);
    }

    public function test_meta_lead_without_matching_fb_form_config_is_skipped(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-sla-form-skip@example.test']);
        $salesUser = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'sales-sla-form-skip@example.test']);
        $lead = $this->createLead(['source' => 'meta', 'status' => 'new']);
        $this->createConfig($crm, 'meta', [$salesUser->id], [$crm->id], [], $this->createFbForm('Other Form')->id);
        $assignment = $this->createAssignment($lead, $salesUser, $crm, 'manual');

        $state = app(NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        $this->assertNull($state);
        $this->assertDatabaseCount('new_lead_sla_automation_states', 0);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
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

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('is_absent')->default(false);
            $table->timestamp('absent_until')->nullable();
            $table->timestamp('lead_off_start_at')->nullable();
            $table->timestamp('lead_off_end_at')->nullable();
            $table->string('lead_off_source')->nullable();
            $table->unsignedBigInteger('lead_off_set_by')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('max_pending_leads')->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('absent_reason')->nullable();
            $table->timestamp('absent_until')->nullable();
            $table->timestamp('lead_off_start_at')->nullable();
            $table->timestamp('lead_off_end_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->unsignedBigInteger('other_lead_marked_by')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->string('other_lead_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fb_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fb_page_id')->nullable();
            $table->string('form_id')->nullable();
            $table->string('form_name')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('fb_leads', function (Blueprint $table) {
            $table->id();
            $table->string('leadgen_id')->nullable();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->text('field_data_json')->nullable();
            $table->text('raw_response_json')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->string('assignment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->string('priority')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->text('recurrence_pattern')->nullable();
            $table->timestamp('recurrence_end_date')->nullable();
            $table->timestamp('rescheduled_from')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('moved_to_pending_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->text('data')->nullable();
            $table->string('action_type')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('source');
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sla_minutes')->default(240);
            $table->time('business_start_time')->default('10:00:00');
            $table->time('business_end_time')->default('19:00:00');
            $table->boolean('weekends_off')->default(true);
            $table->unsignedTinyInteger('max_transfer_attempts')->default(3);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('dashboard_alert_enabled')->default(true);
            $table->boolean('skip_inactive_users')->default(true);
            $table->boolean('skip_absent_users')->default(true);
            $table->unsignedBigInteger('last_round_robin_user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['source', 'fb_form_id'], 'new_lead_sla_source_form_unique');
        });

        Schema::create('new_lead_sla_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('current_assignment_id')->nullable();
            $table->unsignedBigInteger('original_assignment_id')->nullable();
            $table->unsignedBigInteger('current_assigned_to')->nullable();
            $table->unsignedBigInteger('original_assigned_to')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->timestamp('sla_started_at')->nullable();
            $table->timestamp('sla_deadline_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('response_outcome')->nullable();
            $table->string('response_task_model')->nullable();
            $table->unsignedBigInteger('response_task_id')->nullable();
            $table->timestamp('last_transferred_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('new_lead_sla_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->string('task_model')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('action');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'is_active' => true,
            ]
        );
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $role->slug . ' User',
            'email' => $role->slug . '-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'SLA Lead',
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'meta',
        ], $attributes));
    }

    private function createAssignment(Lead $lead, User $assignedTo, User $assignedBy, string $method): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo->id,
            'assigned_by' => $assignedBy->id,
            'assignment_type' => 'primary',
            'assignment_method' => $method,
            'assigned_at' => Carbon::now(),
            'is_active' => true,
        ]);
    }

    private function createConfig(User $crm, string $source, array $poolUserIds, array $recipientIds, array $overrides = [], ?int $fbFormId = null): NewLeadSlaAutomationConfig
    {
        $config = NewLeadSlaAutomationConfig::create(array_merge([
            'name' => 'Meta SLA',
            'source' => Lead::normalizeSource($source),
            'fb_form_id' => $fbFormId,
            'is_active' => true,
            'sla_minutes' => 240,
            'business_start_time' => '10:00:00',
            'business_end_time' => '19:00:00',
            'weekends_off' => true,
            'max_transfer_attempts' => 3,
            'email_enabled' => true,
            'in_app_enabled' => true,
            'dashboard_alert_enabled' => true,
            'skip_inactive_users' => true,
            'skip_absent_users' => false,
            'created_by' => $crm->id,
            'updated_by' => $crm->id,
        ], $overrides));

        foreach (array_values($poolUserIds) as $index => $userId) {
            $config->poolUsers()->create([
                'user_id' => $userId,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        foreach ($recipientIds as $userId) {
            $config->recipients()->create([
                'user_id' => $userId,
                'is_active' => true,
            ]);
        }

        return $config->fresh(['fbForm', 'poolUsers.user.role', 'recipients.user.role']);
    }

    private function createFbForm(string $name = 'Plot Form'): FbForm
    {
        static $counter = 1;

        return FbForm::create([
            'form_id' => 'fb-form-' . $counter,
            'form_name' => $name . ' ' . $counter++,
            'is_enabled' => true,
        ]);
    }

    private function linkLeadToFbForm(Lead $lead, FbForm $form): void
    {
        DB::table('fb_leads')->insert([
            'leadgen_id' => 'leadgen-' . $lead->id . '-' . $form->id,
            'fb_form_id' => $form->id,
            'crm_lead_id' => $lead->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
