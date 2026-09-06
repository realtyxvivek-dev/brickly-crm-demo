<?php

namespace Tests\Feature;

use App\Models\AsmCnpAutomationAudit;
use App\Models\AsmCnpAutomationConfig;
use App\Models\AsmCnpAutomationPoolUser;
use App\Models\AsmCnpAutomationState;
use App\Models\AsmCnpAutomationUserOverride;
use App\Models\LeadAssignment;
use App\Models\Lead;
use App\Models\Prospect;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Services\AsmCnpAutomationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsmTaskLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('broadcasting.default', 'log');
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
        $this->createAsmCnpConfig();
        Carbon::setTestNow(Carbon::parse('2026-03-30 15:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_follow_up_hides_old_task_and_respects_ten_minute_overdue_grace(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Follow Up Lead');
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(5),
        ]);

        Sanctum::actingAs($asm);

        $response = $this->postJson("/api/sales-manager/tasks/{$task->id}/outcome", [
            'outcome' => 'follow_up',
            'next_datetime' => '2026-03-30 15:10:00',
            'remark' => 'Customer asked to call later',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'outcome' => 'follow_up',
            ]);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('follow_up', $task->outcome);
        $this->assertSame('2026-03-30 15:10:00', optional($task->next_action_at)->format('Y-m-d H:i:s'));

        $newTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('id', '!=', $task->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newTask);
        $this->assertSame('pending', $newTask->status);
        $this->assertSame('2026-03-30 15:10:00', optional($newTask->scheduled_at)->format('Y-m-d H:i:s'));

        $this->getJson('/api/sales-manager/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $pendingResponse = $this->getJson('/api/sales-manager/tasks?status=pending')
            ->assertOk();

        $this->assertSame([$newTask->id], $this->taskIdsFromResponse($pendingResponse));
        $this->assertSame(
            'fresh_lead',
            collect($pendingResponse->json('data', []))->firstWhere('id', $newTask->id)['category'] ?? null
        );

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:19:00'));
        $overdueResponseBeforeGrace = $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk();

        $this->assertSame([], $this->taskIdsFromResponse($overdueResponseBeforeGrace));

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:21:00'));
        $overdueResponseAfterGrace = $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk();

        $this->assertSame([$newTask->id], $this->taskIdsFromResponse($overdueResponseAfterGrace));
    }

    public function test_customer_remark_with_site_visit_and_bhk_is_not_treated_as_a_site_visit_task(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Site Visit Remark Lead');
        $this->createLeadAssignment($lead, $asm, $asm);
        $task = $this->createTask($lead, $asm, [
            'title' => 'Follow-up call: Site Visit Remark Lead',
            'description' => 'Follow-up call task scheduled.',
            'notes' => 'Customer will visit office then site visit 3 bhk required',
        ]);

        Sanctum::actingAs($asm);

        $payload = collect($this->getJson('/api/sales-manager/tasks?status=pending')
            ->assertOk()
            ->json('data', []))
            ->firstWhere('id', $task->id);

        $this->assertNotNull($payload);
        $this->assertSame('fresh_lead', $payload['category']);
        $this->assertNull($payload['site_visit_id']);
    }

    public function test_cnp_hides_old_task_from_all_and_completed_and_shows_only_retry_task(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('CNP Lead');
        $this->createProspect($lead, [
            'telecaller_id' => $asm->id,
            'verification_status' => 'pending_verification',
        ]);
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(5),
        ]);

        Sanctum::actingAs($asm);

        $response = $this->postJson("/api/sales-manager/tasks/{$task->id}/outcome", [
            'outcome' => 'cnp',
            'next_datetime' => '2026-03-30 15:10:00',
            'remark' => 'No answer',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'outcome' => 'cnp',
            ]);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('cnp', $task->outcome);
        $this->assertSame('2026-03-30 15:10:00', optional($task->next_action_at)->format('Y-m-d H:i:s'));

        $retryTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('id', '!=', $task->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($retryTask);
        $this->assertSame('pending', $retryTask->status);
        $this->assertSame('2026-03-30 15:10:00', optional($retryTask->scheduled_at)->format('Y-m-d H:i:s'));

        $allTasksResponse = $this->getJson('/api/sales-manager/tasks')
            ->assertOk();

        $this->assertSame([$retryTask->id], $this->taskIdsFromResponse($allTasksResponse));

        $this->getJson('/api/sales-manager/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $pendingResponse = $this->getJson('/api/sales-manager/tasks?status=pending')
            ->assertOk();

        $this->assertSame([$retryTask->id], $this->taskIdsFromResponse($pendingResponse));

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:19:00'));
        $overdueResponseBeforeGrace = $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk();

        $this->assertSame([], $this->taskIdsFromResponse($overdueResponseBeforeGrace));

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:21:00'));
        $overdueResponseAfterGrace = $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk();

        $this->assertSame([$retryTask->id], $this->taskIdsFromResponse($overdueResponseAfterGrace));
    }

    public function test_cnp_requires_next_datetime_for_asm_and_stores_remark_on_retry_flow(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('ASM Scheduled CNP Lead');
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(3),
        ]);

        Sanctum::actingAs($asm);

        $this->postJson("/api/sales-manager/tasks/{$task->id}/outcome", [
            'outcome' => 'cnp',
            'remark' => 'No answer',
        ])->assertStatus(422)
            ->assertJsonPath('errors.next_datetime.0', 'Please select the next call date and time.');

        $response = $this->postJson("/api/sales-manager/tasks/{$task->id}/outcome", [
            'outcome' => 'cnp',
            'next_datetime' => '2026-03-30 15:25:00',
            'remark' => 'Asked to call later in the evening',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'outcome' => 'cnp',
            ]);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('cnp', $task->outcome);
        $this->assertSame('Asked to call later in the evening', $task->outcome_remark);
        $this->assertSame('2026-03-30 15:25:00', optional($task->next_action_at)->format('Y-m-d H:i:s'));

        $retryTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('id', '!=', $task->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($retryTask);
        $this->assertStringContainsString('Remark: Asked to call later in the evening', (string) $retryTask->notes);
        $this->assertSame('2026-03-30 15:25:00', optional($retryTask->scheduled_at)->format('Y-m-d H:i:s'));
    }

    public function test_fresh_lead_cnp_immediately_transfers_on_max_attempt_count(): void
    {
        $asmA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM A',
            'email' => 'asm-a@example.test',
        ]);
        $asmB = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM B',
            'email' => 'asm-b@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin User',
            'email' => 'admin@example.test',
        ]);

        $lead = $this->createLead('Immediate Transfer Lead');
        $assignment = $this->createLeadAssignment($lead, $asmA, $admin);
        $config = $this->createAsmCnpConfig([
            'max_cnp_attempts' => 2,
            'retry_delay_minutes' => 5,
            'create_retry_tasks' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->addPoolUser($config, $asmB);

        $firstTask = $this->createTask($lead, $asmA, [
            'scheduled_at' => Carbon::now()->subMinute(),
        ]);

        $service = app(AsmCnpAutomationService::class);

        $firstAttempt = $service->handleFreshLeadCnp(
            $firstTask,
            $asmA,
            Carbon::parse('2026-03-30 15:10:00')
        );

        $this->assertSame('Call Not Picked marked. Retry task has been auto-created.', $firstAttempt['message']);
        $retryTask = $firstAttempt['retry_task'];
        $this->assertNotNull($retryTask);
        $this->assertSame($asmA->id, $retryTask->assigned_to);

        $secondAttempt = $service->handleFreshLeadCnp(
            $retryTask->fresh(),
            $asmA,
            Carbon::parse('2026-03-30 15:20:00')
        );

        $this->assertSame('Call Not Picked limit reached. Lead auto-transferred to the next ASM.', $secondAttempt['message']);

        $lead->refresh();
        $assignment->refresh();

        $this->assertSame(Lead::STATUS_FRESH_TRANSFER, $lead->status);
        $this->assertSame($asmA->id, $lead->transferred_from_user_id);
        $this->assertSame($asmB->id, $lead->transferred_to_user_id);
        $this->assertFalse($assignment->is_active);

        $newAssignment = LeadAssignment::query()
            ->where('lead_id', $lead->id)
            ->where('assigned_to', $asmB->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($newAssignment);
        $this->assertSame('cnp_auto_transfer', $newAssignment->assignment_method);

        $freshTransferTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('assigned_to', $asmB->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($freshTransferTask);
        $this->assertSame('Fresh transfer fresh lead call: Immediate Transfer Lead', $freshTransferTask->title);
        $this->assertStringContainsString('Fresh transfer fresh lead handoff', (string) $freshTransferTask->description);
        $this->assertStringNotContainsString('CNP #', (string) $freshTransferTask->description);
        $this->assertStringNotContainsString('ASM A', (string) $freshTransferTask->notes);

        $state = AsmCnpAutomationState::query()->firstOrFail();
        $this->assertSame('transferred', $state->status);
        $this->assertSame('fresh_lead', $state->stage);
        $this->assertSame(2, $state->cnp_count);
        $this->assertSame($asmB->id, $state->current_assigned_to);
        $this->assertNotNull($state->transferred_at);

        $transferAudit = AsmCnpAutomationAudit::query()
            ->where('state_id', $state->id)
            ->where('action', 'transferred')
            ->first();

        $this->assertNotNull($transferAudit);
        $this->assertSame($asmA->id, $transferAudit->from_user_id);
        $this->assertSame($asmB->id, $transferAudit->to_user_id);
    }

    public function test_cnp_retry_continues_with_same_owner_when_auto_transfer_is_off(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Retained Owner',
            'email' => 'asm-retained@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin User',
            'email' => 'retained-admin@example.test',
        ]);

        $lead = $this->createLead('Transfer Disabled Lead');
        $assignment = $this->createLeadAssignment($lead, $asm, $admin);
        $this->createAsmCnpConfig([
            'is_enabled' => true,
            'is_active' => false,
            'max_cnp_attempts' => 2,
            'retry_delay_minutes' => 5,
            'create_retry_tasks' => true,
        ]);

        $service = app(AsmCnpAutomationService::class);
        $task = $this->createTask($lead, $asm);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $result = $service->handleFreshLeadCnp($task->fresh(), $asm, now()->addMinutes($attempt * 5));
            $this->assertNotNull($result['retry_task']);
            $this->assertSame($asm->id, $result['retry_task']->assigned_to);
            $task = $result['retry_task'];
        }

        $lead->refresh();
        $assignment->refresh();
        $state = AsmCnpAutomationState::query()->firstOrFail();

        $this->assertSame('new', $lead->status);
        $this->assertTrue($assignment->is_active);
        $this->assertSame($asm->id, $assignment->assigned_to);
        $this->assertSame('active', $state->status);
        $this->assertSame(3, $state->cnp_count);
        $this->assertFalse($state->transfer_eligible);
    }

    public function test_positive_follow_up_resets_fresh_cnp_chain_before_follow_up_cnp_counting(): void
    {
        $asmA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Stage Source',
            'email' => 'asm-stage-source@example.test',
        ]);
        $asmB = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Stage Target',
            'email' => 'asm-stage-target@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Stage Admin',
            'email' => 'stage-admin@example.test',
        ]);

        $lead = $this->createLead('Stage Reset Lead');
        $this->createLeadAssignment($lead, $asmA, $admin);
        $config = $this->createAsmCnpConfig([
            'max_cnp_attempts' => 4,
            'retry_delay_minutes' => 5,
            'create_retry_tasks' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->addPoolUser($config, $asmB);

        $service = app(AsmCnpAutomationService::class);
        $firstTask = $this->createTask($lead, $asmA);
        $firstAttempt = $service->handleStageCnp($firstTask, $asmA, now()->addMinutes(5));
        $secondAttempt = $service->handleStageCnp($firstAttempt['retry_task']->fresh(), $asmA, now()->addMinutes(10));

        $service->cancelTaskStageAutomation($secondAttempt['retry_task']->fresh(), 'Lead moved to follow-up flow.');

        $followUpId = DB::table('follow_ups')->insertGetId([
            'lead_id' => $lead->id,
            'created_by' => $asmA->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $followUpTask = $this->createTask($lead, $asmA, [
            'title' => 'Follow-up call: ' . $lead->name,
            'description' => 'Follow-up call task scheduled.',
            'follow_up_id' => $followUpId,
        ]);
        $thirdLogicalCnp = $service->handleStageCnp($followUpTask, $asmA, now()->addMinutes(15));
        $service->handleStageCnp($thirdLogicalCnp['retry_task']->fresh(), $asmA, now()->addMinutes(20));

        $lead->refresh();
        $this->assertSame('new', $lead->status);
        $this->assertNull($lead->transferred_to_user_id);

        $freshState = AsmCnpAutomationState::query()->where('stage', 'fresh_lead')->firstOrFail();
        $followUpState = AsmCnpAutomationState::query()->where('stage', 'follow_up')->firstOrFail();

        $this->assertSame('cancelled', $freshState->status);
        $this->assertSame(2, $freshState->cnp_count);
        $this->assertSame('active', $followUpState->status);
        $this->assertSame(2, $followUpState->cnp_count);
        $this->assertSame($followUpId, $followUpState->stage_record_id);

        $this->assertDatabaseHas('asm_cnp_automation_audits', [
            'state_id' => $freshState->id,
            'action' => 'stage_reset',
            'stage' => 'fresh_lead',
        ]);
    }

    public function test_follow_up_stage_transfers_after_four_consecutive_stage_cnps(): void
    {
        $asmA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Follow Source',
            'email' => 'asm-follow-source@example.test',
        ]);
        $asmB = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Follow Target',
            'email' => 'asm-follow-target@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Follow Admin',
            'email' => 'follow-admin@example.test',
        ]);

        $lead = $this->createLead('Follow Stage Transfer Lead');
        $assignment = $this->createLeadAssignment($lead, $asmA, $admin);
        $config = $this->createAsmCnpConfig([
            'max_cnp_attempts' => 4,
            'retry_delay_minutes' => 5,
            'create_retry_tasks' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->addPoolUser($config, $asmB);

        $followUpId = DB::table('follow_ups')->insertGetId([
            'lead_id' => $lead->id,
            'created_by' => $asmA->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(AsmCnpAutomationService::class);
        $task = $this->createTask($lead, $asmA, [
            'title' => 'Follow-up call: ' . $lead->name,
            'description' => 'Follow-up call task scheduled.',
            'follow_up_id' => $followUpId,
        ]);
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $result = $service->handleStageCnp($task->fresh(), $asmA, now()->addMinutes($attempt * 5));
            $task = $result['retry_task'] ?: $task;
        }

        $lead->refresh();
        $assignment->refresh();

        $this->assertSame(Lead::STATUS_FRESH_TRANSFER, $lead->status);
        $this->assertSame($asmA->id, $lead->transferred_from_user_id);
        $this->assertSame($asmB->id, $lead->transferred_to_user_id);
        $this->assertFalse($assignment->is_active);

        $state = AsmCnpAutomationState::query()->where('stage', 'follow_up')->firstOrFail();
        $this->assertSame('transferred', $state->status);
        $this->assertSame(4, $state->cnp_count);
        $this->assertSame($followUpId, $state->stage_record_id);

        $handoffTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('assigned_to', $asmB->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($handoffTask);
        $this->assertSame($followUpId, $handoffTask->follow_up_id);
        $this->assertStringContainsString('follow up', strtolower((string) $handoffTask->title));
    }

    public function test_process_due_transfers_cancels_progressed_fresh_lead_states(): void
    {
        $asmA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Source',
            'email' => 'asm-source@example.test',
        ]);
        $asmB = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Target',
            'email' => 'asm-target@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Admin User 2',
            'email' => 'admin-two@example.test',
        ]);

        $lead = $this->createLead('Progressed Fresh Lead');
        $assignment = $this->createLeadAssignment($lead, $asmA, $admin);
        $config = $this->createAsmCnpConfig([
            'max_cnp_attempts' => 1,
            'create_retry_tasks' => false,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->addPoolUser($config, $asmB);

        $state = AsmCnpAutomationState::create([
            'lead_id' => $lead->id,
            'lead_assignment_id' => $assignment->id,
            'config_id' => $config->id,
            'original_assigned_to' => $asmA->id,
            'current_assigned_to' => $asmA->id,
            'cnp_count' => 1,
            'assignment_started_at' => now()->subHour(),
            'first_cnp_at' => now()->subMinutes(30),
            'last_cnp_at' => now()->subMinutes(5),
            'eligible_for_transfer_at' => now()->subMinute(),
            'transfer_eligible' => true,
            'status' => 'active',
        ]);

        DB::table('follow_ups')->insert([
            'lead_id' => $lead->id,
            'created_by' => $asmA->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(AsmCnpAutomationService::class)->processDueTransfers();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['cancelled']);
        $this->assertSame(0, $result['transferred']);

        $state->refresh();
        $assignment->refresh();

        $this->assertSame('cancelled', $state->status);
        $this->assertSame('Lead moved out of fresh lead CNP flow.', $state->cancel_reason);
        $this->assertTrue($assignment->is_active);
        $this->assertDatabaseMissing('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $asmB->id,
            'is_active' => true,
        ]);
    }

    public function test_window_restart_mode_starts_fresh_chain_after_expiry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 18:00:00'));

        $asmA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Restart Source',
            'email' => 'asm-restart-source@example.test',
        ]);
        $asmB = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Restart Target',
            'email' => 'asm-restart-target@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Restart Admin',
            'email' => 'restart-admin@example.test',
        ]);

        $lead = $this->createLead('Window Restart Lead');
        $assignment = $this->createLeadAssignment($lead, $asmA, $admin);
        $config = $this->createAsmCnpConfig([
            'max_cnp_attempts' => 2,
            'create_retry_tasks' => false,
            'transfer_rule_mode' => 'count_window_restart',
            'transfer_window_hours' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->addPoolUser($config, $asmB);

        AsmCnpAutomationState::create([
            'lead_id' => $lead->id,
            'lead_assignment_id' => $assignment->id,
            'config_id' => $config->id,
            'original_assigned_to' => $asmA->id,
            'current_assigned_to' => $asmA->id,
            'cnp_count' => 1,
            'assignment_started_at' => now()->subHours(2),
            'first_cnp_at' => now()->subHours(2),
            'last_cnp_at' => now()->subHours(2),
            'status' => 'active',
            'transfer_eligible' => true,
        ]);

        $task = $this->createTask($lead, $asmA, [
            'scheduled_at' => now()->subMinutes(1),
        ]);

        $result = app(AsmCnpAutomationService::class)->handleFreshLeadCnp($task, $asmA);

        $this->assertSame('Call Not Picked marked. Retry saved without creating a new task.', $result['message']);

        $state = AsmCnpAutomationState::query()->firstOrFail();
        $state->refresh();

        $this->assertSame('active', $state->status);
        $this->assertSame(1, $state->cnp_count);
        $this->assertNull($state->transferred_at);

        $windowAudit = AsmCnpAutomationAudit::query()
            ->where('state_id', $state->id)
            ->where('action', 'window_restart')
            ->first();

        $this->assertNotNull($windowAudit);
    }

    public function test_window_reset_mode_clears_old_chain_before_recounting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-30 18:00:00'));

        $asmA = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Reset Source',
            'email' => 'asm-reset-source@example.test',
        ]);
        $asmB = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), [
            'name' => 'ASM Reset Target',
            'email' => 'asm-reset-target@example.test',
        ]);
        $admin = $this->createUser($this->createRole(Role::ADMIN), [
            'name' => 'Reset Admin',
            'email' => 'reset-admin@example.test',
        ]);

        $lead = $this->createLead('Window Reset Lead');
        $assignment = $this->createLeadAssignment($lead, $asmA, $admin);
        $config = $this->createAsmCnpConfig([
            'max_cnp_attempts' => 2,
            'create_retry_tasks' => false,
            'transfer_rule_mode' => 'count_window_reset',
            'transfer_window_hours' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->addPoolUser($config, $asmB);

        AsmCnpAutomationState::create([
            'lead_id' => $lead->id,
            'lead_assignment_id' => $assignment->id,
            'config_id' => $config->id,
            'original_assigned_to' => $asmA->id,
            'current_assigned_to' => $asmA->id,
            'cnp_count' => 1,
            'assignment_started_at' => now()->subHours(2),
            'first_cnp_at' => now()->subHours(2),
            'last_cnp_at' => now()->subHours(2),
            'status' => 'active',
            'transfer_eligible' => true,
        ]);

        $task = $this->createTask($lead, $asmA, [
            'scheduled_at' => now()->subMinutes(1),
        ]);

        $result = app(AsmCnpAutomationService::class)->handleFreshLeadCnp($task, $asmA);

        $this->assertSame('Call Not Picked marked. Retry saved without creating a new task.', $result['message']);

        $state = AsmCnpAutomationState::query()->firstOrFail();
        $state->refresh();

        $this->assertSame('active', $state->status);
        $this->assertSame(1, $state->cnp_count);
        $this->assertNull($state->transferred_at);

        $windowAudit = AsmCnpAutomationAudit::query()
            ->where('state_id', $state->id)
            ->where('action', 'window_reset')
            ->first();

        $this->assertNotNull($windowAudit);
    }

    public function test_task_created_after_scheduled_time_respects_ten_minute_overdue_grace(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Late Created Retry Lead');

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:21:00'));
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::parse('2026-03-30 15:20:00'),
            'created_at' => Carbon::parse('2026-03-30 15:21:00'),
            'updated_at' => Carbon::parse('2026-03-30 15:21:00'),
        ]);

        Sanctum::actingAs($asm);

        $this->assertFalse($task->fresh()->isOverdue());

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:29:00'));
        $this->assertFalse($task->fresh()->isOverdue());
        $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Carbon::setTestNow(Carbon::parse('2026-03-30 15:31:00'));
        $this->assertTrue($task->fresh()->isOverdue());
        $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk()
            ->assertJsonPath('data.0.id', $task->id);
    }

    public function test_remove_all_overdue_only_completes_tasks_past_grace_cutoff(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Overdue Cleanup Lead');

        $overdueTask = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(11),
        ]);

        $withinGraceTask = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(9),
            'title' => 'Call lead: Within Grace',
        ]);

        Sanctum::actingAs($asm);

        $this->postJson('/api/sales-manager/tasks/remove-all-overdue')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertSame('completed', $overdueTask->fresh()->status);
        $this->assertSame('pending', $withinGraceTask->fresh()->status);
    }

    public function test_cnp_retry_iso_datetime_is_normalized_to_app_timezone_and_not_marked_overdue(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('sonu test');
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(2),
        ]);

        Sanctum::actingAs($asm);

        $selectedLocalTime = Carbon::create(2026, 3, 30, 17, 0, 0, 'Asia/Kolkata');

        $this->postJson("/api/sales-manager/tasks/{$task->id}/outcome", [
            'outcome' => 'cnp',
            'next_datetime' => $selectedLocalTime->toIso8601String(),
            'remark' => 'Call later',
        ])->assertOk();

        $retryTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('id', '!=', $task->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($retryTask);
        $this->assertSame('2026-03-30 17:00:00', optional($retryTask->scheduled_at)->format('Y-m-d H:i:s'));
        $this->assertSame(
            'Retry call: sonu test (CNP rescheduled - 30 Mar 2026, 05:00 PM)',
            $retryTask->title
        );
        $this->assertFalse($retryTask->isOverdue());

        $tasksResponse = $this->getJson('/api/sales-manager/tasks?status=rescheduled')
            ->assertOk();

        $taskPayload = collect($tasksResponse->json('data', []))
            ->firstWhere('id', $retryTask->id);

        $this->assertNotNull($taskPayload);
        $this->assertSame('2026-03-30 17:00:00', $taskPayload['scheduled_at']);
        $this->assertFalse($taskPayload['is_overdue']);
    }

    public function test_follow_up_iso_datetime_is_normalized_to_app_timezone(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Follow Up Timezone Lead');
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(2),
        ]);

        Sanctum::actingAs($asm);

        $selectedLocalTime = Carbon::create(2026, 3, 30, 18, 15, 0, 'Asia/Kolkata');

        $this->postJson("/api/sales-manager/tasks/{$task->id}/outcome", [
            'outcome' => 'follow_up',
            'next_datetime' => $selectedLocalTime->toIso8601String(),
            'remark' => 'Call after office hours',
        ])->assertOk();

        $followUpTask = Task::query()
            ->where('lead_id', $lead->id)
            ->where('id', '!=', $task->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($followUpTask);
        $this->assertSame('2026-03-30 18:15:00', optional($followUpTask->scheduled_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-30 18:15:00', optional($task->fresh()->next_action_at)->format('Y-m-d H:i:s'));
        $this->assertFalse($followUpTask->isOverdue());
    }

    public function test_latest_open_task_for_same_lead_is_used_in_task_list_payload(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Duplicate Lead');

        $olderTask = $this->createTask($lead, $asm, [
            'title' => 'Retry call: Duplicate Lead (CNP rescheduled - 30 Mar 2026, 11:04 AM)',
            'scheduled_at' => Carbon::parse('2026-03-30 11:04:00'),
        ]);

        $newerTask = $this->createTask($lead, $asm, [
            'title' => 'Retry call: Duplicate Lead (CNP rescheduled - 30 Mar 2026, 02:34 PM)',
            'scheduled_at' => Carbon::parse('2026-03-30 14:34:00'),
        ]);

        Sanctum::actingAs($asm);

        $response = $this->getJson('/api/sales-manager/tasks')
            ->assertOk();

        $payload = collect($response->json('data', []))
            ->firstWhere('lead_id', $lead->id);

        $this->assertNotNull($payload);
        $this->assertSame($newerTask->id, $payload['id']);
        $this->assertSame('2026-03-30 14:34:00', $payload['scheduled_at']);
        $this->assertNotSame($olderTask->id, $payload['id']);
    }

    public function test_completed_interested_task_is_hidden_from_completed_filter(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Interested Lead');

        $task = $this->createTask($lead, $asm, [
            'status' => 'completed',
            'outcome' => 'interested',
            'completed_at' => Carbon::now()->subMinute(),
            'outcome_recorded_at' => Carbon::now()->subMinute(),
        ]);

        Sanctum::actingAs($asm);

        $this->getJson('/api/sales-manager/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_direct_complete_hides_task_from_all_filters_and_keeps_activity_history(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead('Direct Complete Lead');
        $task = $this->createTask($lead, $asm, [
            'scheduled_at' => Carbon::now()->subMinutes(2),
        ]);

        Sanctum::actingAs($asm);

        $response = $this->postJson("/api/sales-manager/tasks/{$task->id}/complete");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Task completed successfully',
            ]);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);

        $this->getJson('/api/sales-manager/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/sales-manager/tasks?status=pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/sales-manager/tasks?status=overdue')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/sales-manager/tasks?status=rescheduled')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/sales-manager/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'activity_type' => 'status_changed',
            'new_value' => 'completed',
        ]);

        $this->assertGreaterThanOrEqual(2, TaskActivity::query()->where('task_id', $task->id)->count());
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
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('location_path')->nullable();
            $table->string('form_type')->nullable();
            $table->text('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('replaces_form_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('field_key')->nullable();
            $table->string('field_type')->nullable();
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->text('options')->nullable();
            $table->text('validation')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0);
            $table->string('section')->nullable();
            $table->text('styles')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('system_binding')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('fb_forms', function (Blueprint $table) {
            $table->id();
            $table->string('form_id')->nullable();
            $table->string('name')->nullable();
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

        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->text('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('field_key')->nullable();
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
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
            $table->timestamp('next_followup_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->unsignedBigInteger('other_lead_marked_by')->nullable();
            $table->string('other_lead_reason')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->boolean('is_dead')->default(false);
            $table->string('pre_transfer_status')->nullable();
            $table->unsignedBigInteger('transferred_from_user_id')->nullable();
            $table->unsignedBigInteger('transferred_to_user_id')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->text('transfer_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('assigned_manager')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('manager_remark')->nullable();
            $table->string('lead_status')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('lead_assignment_id')->nullable();
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('original_assigned_to')->nullable();
            $table->unsignedBigInteger('current_assigned_to')->nullable();
            $table->unsignedBigInteger('last_retry_task_id')->nullable();
            $table->string('stage')->default('fresh_lead');
            $table->unsignedBigInteger('stage_record_id')->nullable();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->dateTime('assignment_started_at')->nullable();
            $table->dateTime('stage_started_at')->nullable();
            $table->dateTime('first_cnp_at')->nullable();
            $table->dateTime('last_cnp_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->dateTime('eligible_for_transfer_at')->nullable();
            $table->dateTime('last_processed_at')->nullable();
            $table->boolean('transfer_eligible')->default(false);
            $table->string('status')->default('active');
            $table->text('cancel_reason')->nullable();
            $table->text('reset_reason')->nullable();
            $table->dateTime('reset_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('transferred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('stage')->nullable();
            $table->unsignedBigInteger('stage_record_id')->nullable();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->string('action');
            $table->text('message')->nullable();
            $table->text('meta')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_lead_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('lead_assignment_id')->nullable();
            $table->unsignedInteger('completed_cnp_count')->default(0);
            $table->dateTime('max_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default ASM CNP Config');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('create_retry_tasks')->default(true);
            $table->string('transfer_rule_mode')->default('count_only');
            $table->unsignedInteger('retry_delay_minutes')->default(5);
            $table->unsignedInteger('transfer_window_hours')->nullable();
            $table->unsignedInteger('transfer_threshold_hours')->default(1);
            $table->unsignedInteger('max_cnp_attempts')->default(4);
            $table->string('fallback_routing')->default('round_robin');
            $table->unsignedBigInteger('last_round_robin_user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_pool_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_user_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->default('primary');
            $table->string('assignment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('sheet_config_id')->nullable();
            $table->unsignedBigInteger('sheet_row_number')->nullable();
            $table->unsignedBigInteger('sheet_assignment_config_id')->nullable();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->default('phone_call');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->string('priority')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('outcome_recorded_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->text('recurrence_pattern')->nullable();
            $table->dateTime('recurrence_end_date')->nullable();
            $table->dateTime('rescheduled_from')->nullable();
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('task_type')->default('calling');
            $table->string('status')->default('pending');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->dateTime('notification_sent_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->dateTime('moved_to_pending_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'is_active' => true,
            ]
        );
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'ASM User ' . $counter,
            'email' => 'asm' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(string $name, array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => $name,
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'meta',
        ], $attributes));
    }

    private function createTask(Lead $lead, User $assignedTo, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo->id,
            'type' => 'phone_call',
            'title' => 'Call lead: ' . $lead->name,
            'description' => 'Phone call task',
            'status' => 'pending',
            'scheduled_at' => Carbon::now(),
            'created_by' => $assignedTo->id,
        ], $attributes));
    }

    private function createProspect(Lead $lead, array $attributes = []): Prospect
    {
        return Prospect::create(array_merge([
            'lead_id' => $lead->id,
            'customer_name' => $lead->name,
            'phone' => $lead->phone,
            'verification_status' => 'pending_verification',
            'created_by' => 1,
        ], $attributes));
    }

    private function createLeadAssignment(Lead $lead, User $assignedTo, User $assignedBy, array $attributes = []): LeadAssignment
    {
        return LeadAssignment::create(array_merge([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo->id,
            'assigned_by' => $assignedBy->id,
            'assignment_type' => 'primary',
            'assignment_method' => 'manual',
            'assigned_at' => now(),
            'is_active' => true,
        ], $attributes));
    }

    private function createAsmCnpConfig(array $attributes = []): AsmCnpAutomationConfig
    {
        $payload = array_merge([
            'name' => 'ASM Fresh Lead CNP',
            'is_enabled' => true,
            'is_active' => true,
            'create_retry_tasks' => true,
            'transfer_rule_mode' => 'count_only',
            'retry_delay_minutes' => 5,
            'transfer_window_hours' => null,
            'transfer_threshold_hours' => 1,
            'max_cnp_attempts' => 4,
            'fallback_routing' => 'round_robin',
        ], $attributes);

        $config = AsmCnpAutomationConfig::query()->first();

        if ($config) {
            $config->update($payload);

            return $config->fresh();
        }

        return AsmCnpAutomationConfig::create($payload);
    }

    private function addPoolUser(AsmCnpAutomationConfig $config, User $user, int $sortOrder = 1): AsmCnpAutomationPoolUser
    {
        return AsmCnpAutomationPoolUser::create([
            'config_id' => $config->id,
            'user_id' => $user->id,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function taskIdsFromResponse($response): array
    {
        return collect($response->json('data', []))
            ->pluck('id')
            ->values()
            ->all();
    }
}
