<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsmLeadDetailTaskCompletionTest extends TestCase
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
        Event::fake();
        Carbon::setTestNow(Carbon::parse('2026-03-30 15:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_asm_lead_detail_shows_complete_selector_for_all_open_manager_tasks(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead();
        $this->assignLead($lead, $asm);

        $latestTask = $this->createTask($lead, $asm, [
            'title' => 'Latest ASM Call',
            'notes' => 'Call back after site visit',
            'scheduled_at' => Carbon::now()->addHour(),
        ]);
        $olderTask = $this->createTask($lead, $asm, [
            'title' => 'Older ASM Call',
            'status' => 'rescheduled',
            'scheduled_at' => Carbon::now()->subHour(),
        ]);
        $this->createTask($lead, $asm, [
            'title' => 'Completed ASM Call',
            'status' => 'completed',
            'completed_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->actingAs($asm)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Mark Task Complete');

        $content = $response->getContent();
        $this->assertStringContainsString('lead-detail-container manager-mobile-safe', $content);
        $this->assertMatchesRegularExpression(
            '/value="' . preg_quote((string) $latestTask->id, '/') . '".*checked/s',
            $content
        );
        $this->assertStringContainsString('id="completeAsmTaskModal"', $content);
        $this->assertStringContainsString('id="verifyRejectPromptModal"', $content);
        $this->assertStringContainsString('fresh_lead', $content);
        $this->assertStringNotContainsString('data-task-title="Completed ASM Call"', $content);
        $this->assertStringNotContainsString("/sales-manager/tasks/{$latestTask->id}/complete", $content);
        $this->assertStringNotContainsString('value="' . $olderTask->id . '" checked', $content);
    }

    public function test_asm_lead_detail_top_complete_selector_includes_open_meeting_tasks(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'asm-meeting-top@example.test']);
        $lead = $this->createLead(['name' => 'Meeting Top Selector Lead']);
        $this->assignLead($lead, $asm);

        $meetingTask = $this->createTask($lead, $asm, [
            'title' => 'Meeting follow-up call',
            'notes' => 'Pre-meeting reminder call for meeting scheduled at 2026-03-31 18:00 | Meeting ID: 91',
            'meeting_id' => 91,
            'scheduled_at' => Carbon::now()->addMinutes(20),
        ]);

        $response = $this->actingAs($asm)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Mark Task Complete');
        $this->assertStringContainsString('meeting', $response->getContent());
        $this->assertStringContainsString('91', $response->getContent());
        $this->assertMatchesRegularExpression(
            '/value="' . preg_quote((string) $meetingTask->id, '/') . '".*data-task-meeting-id="91"/s',
            $response->getContent()
        );
    }

    public function test_completed_task_disappears_from_selector_after_api_outcome_submission(): void
    {
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER));
        $lead = $this->createLead(['name' => 'Reload Lead']);
        $this->assignLead($lead, $asm);

        $taskToComplete = $this->createTask($lead, $asm, [
            'title' => 'Task To Complete',
            'scheduled_at' => Carbon::now()->addMinutes(30),
        ]);
        $remainingTask = $this->createTask($lead, $asm, [
            'title' => 'Remaining Task',
            'scheduled_at' => Carbon::now()->addMinutes(10),
        ]);

        Sanctum::actingAs($asm);

        $this->postJson("/api/sales-manager/tasks/{$taskToComplete->id}/outcome", [
            'outcome' => 'follow_up',
            'next_datetime' => Carbon::now()->addHour()->format('Y-m-d H:i:s'),
            'remark' => 'Call later today',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $taskToComplete->id,
            'status' => 'completed',
            'outcome' => 'follow_up',
        ]);

        $response = $this->actingAs($asm)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Remaining Task');
        $response->assertSee('Mark Task Complete');
        $this->assertStringNotContainsString('data-task-title="Task To Complete"', $response->getContent());
    }

    public function test_asm_cannot_complete_another_users_task(): void
    {
        $ownerAsm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'owner@example.test']);
        $otherAsm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'other@example.test']);
        $lead = $this->createLead(['name' => 'Protected Task Lead']);
        $this->assignLead($lead, $ownerAsm);

        $task = $this->createTask($lead, $ownerAsm, [
            'title' => 'Protected Task',
        ]);

        Sanctum::actingAs($otherAsm);

        $this->postJson("/api/sales-manager/tasks/{$task->id}/complete")
            ->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'pending',
        ]);
    }

    public function test_non_asm_roles_do_not_get_complete_selector_and_asm_without_tasks_sees_empty_state(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm@example.test']);
        $asm = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'asm-no-task@example.test']);
        $lead = $this->createLead(['name' => 'No Task Lead']);
        $this->assignLead($lead, $asm);

        $crmResponse = $this->actingAs($crm)->get(route('leads.show', $lead));
        $crmResponse->assertOk();
        $crmResponse->assertDontSee('Mark Task Complete');

        $asmResponse = $this->actingAs($asm)->get(route('leads.show', $lead));
        $asmResponse->assertOk();
    }

    public function test_manager_lead_detail_includes_follow_up_action_hub_markup(): void
    {
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'manager-follow-up@example.test']);
        $lead = $this->createLead(['name' => 'Follow Up Lead']);
        $this->assignLead($lead, $manager);

        $this->createTask($lead, $manager, [
            'title' => 'Manager Call',
            'scheduled_at' => Carbon::now()->addMinutes(45),
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Mark Task Complete');
        $response->assertSee('Follow-Up Actions');
        $response->assertSee('Reschedule Follow Up');
        $response->assertSee('Follow Up Complete');
        $response->assertSee('Edit Requirement Form');
        $response->assertSee('Schedule Meeting');
        $response->assertSee('Schedule Visit');
        $response->assertSee('More Outcomes');
        $response->assertSee('Not Interested');
        $response->assertSee('Junk');
        $response->assertSee("handleFollowUpHubAction('meeting')", false);
        $response->assertSee("handleFollowUpHubAction('visit')", false);
    }

    public function test_manager_lead_detail_includes_visit_action_hub_markup(): void
    {
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'manager-visit@example.test']);
        $lead = $this->createLead(['name' => 'Visit Flow Lead']);
        $this->assignLead($lead, $manager);

        $this->createTask($lead, $manager, [
            'title' => 'Visit Task',
            'scheduled_at' => Carbon::now()->addMinutes(30),
            'notes' => 'Prepare site visit workflow',
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/text-cyan-700[^>]*>\s*S\s*</', $response->getContent());
        $response->assertSee('Visit Actions');
        $response->assertSee('Visited');
        $response->assertSee('Reschedule Visit');
        $response->assertSee('Follow-up Needed');
        $response->assertSee('Customer Not Available');
        $response->assertSee('Cancelled');
        $response->assertSee('Complete Visit');
        $response->assertSee('Back');
        $response->assertSee('Add another project');
        $response->assertSee('addLeadDetailVisitedProject', false);
        $response->assertDontSee('Visit Outcome');
        $response->assertSee('leadDetailVisitOutcome', false);
        $response->assertSee('leadDetailVisitLockedOutcome', false);
        $response->assertSee('backToVisitActionHub', false);
        $response->assertDontSee("handleVisitHubAction('cnp')", false);
        $response->assertSee("handleVisitHubAction('follow_up')", false);
        $response->assertSee("currentTaskCategory === 'site_visit'", false);
    }

    public function test_visit_workflow_requires_proof_for_visited_outcome(): void
    {
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-proof-required@example.test');
        Sanctum::actingAs($manager);

        $this->postJson("/api/sales-manager/tasks/{$task->id}/workflow-action", [
            'workflow' => 'visit_complete',
            'site_visit_id' => $siteVisit->id,
            'outcome' => 'visited',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_photos']);

        $this->assertDatabaseHas('site_visits', [
            'id' => $siteVisit->id,
            'status' => 'scheduled',
            'completed_at' => null,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'pending',
        ]);
    }

    public function test_visit_workflow_completes_visited_outcome_with_proof(): void
    {
        Storage::fake('local');
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-proof-complete@example.test');
        Sanctum::actingAs($manager);

        $this->post("/api/sales-manager/tasks/{$task->id}/workflow-action", [
            'workflow' => 'visit_complete',
            'site_visit_id' => $siteVisit->id,
            'outcome' => 'visited',
            'notes' => 'Customer visited the project',
            'proof_photos' => [
                UploadedFile::fake()->image('visit-proof.jpg'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('site_visits', [
            'id' => $siteVisit->id,
            'status' => 'completed',
            'verification_status' => 'pending',
            'visit_notes' => 'Customer visited the project',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'outcome' => 'visited',
        ]);
    }

    public function test_visit_workflow_customer_not_available_keeps_task_open(): void
    {
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-cna@example.test');
        Sanctum::actingAs($manager);

        $this->postJson("/api/sales-manager/tasks/{$task->id}/workflow-action", [
            'workflow' => 'visit_complete',
            'site_visit_id' => $siteVisit->id,
            'outcome' => 'customer_not_available',
            'remark' => 'Customer did not reach the location',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'keep_task_open' => true,
            ]);

        $this->assertDatabaseHas('site_visits', [
            'id' => $siteVisit->id,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'pending',
            'outcome' => 'customer_not_available',
            'outcome_remark' => 'Customer did not reach the location',
        ]);
        $this->assertStringContainsString('Customer not available: Customer did not reach the location', DB::table('site_visits')->where('id', $siteVisit->id)->value('visit_notes'));
    }

    public function test_visit_workflow_cancelled_marks_visit_and_task_cancelled(): void
    {
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-cancel@example.test');
        Sanctum::actingAs($manager);

        $this->postJson("/api/sales-manager/tasks/{$task->id}/workflow-action", [
            'workflow' => 'visit_complete',
            'site_visit_id' => $siteVisit->id,
            'outcome' => 'cancelled',
            'remark' => 'Customer cancelled the visit',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('site_visits', [
            'id' => $siteVisit->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'cancelled',
            'outcome' => 'cancelled',
            'outcome_remark' => 'Customer cancelled the visit',
        ]);
    }

    public function test_visit_workflow_follow_up_creates_next_task_and_closes_current_task(): void
    {
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-follow-up@example.test');
        Sanctum::actingAs($manager);

        $this->postJson("/api/sales-manager/tasks/{$task->id}/workflow-action", [
            'workflow' => 'visit_complete',
            'site_visit_id' => $siteVisit->id,
            'outcome' => 'follow_up_needed',
            'scheduled_at' => Carbon::now()->addDay()->format('Y-m-d H:i:s'),
            'remark' => 'Call again tomorrow',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'outcome' => 'follow_up_needed',
            'outcome_remark' => 'Call again tomorrow',
        ]);
        $this->assertDatabaseHas('follow_ups', [
            'lead_id' => $lead->id,
            'notes' => 'Call again tomorrow',
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'title' => "Follow-up call: {$lead->name}",
            'status' => 'pending',
        ]);
    }

    public function test_visit_workflow_reschedule_creates_new_visit_and_cancels_current_visit(): void
    {
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-reschedule@example.test');
        Sanctum::actingAs($manager);
        $nextAt = Carbon::now()->addDay();

        $this->postJson("/api/sales-manager/tasks/{$task->id}/workflow-action", [
            'workflow' => 'visit_reschedule',
            'site_visit_id' => $siteVisit->id,
            'scheduled_at' => $nextAt->format('Y-m-d H:i:s'),
            'remark' => 'Customer requested a new time',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('site_visits', [
            'id' => $siteVisit->id,
            'status' => 'cancelled',
            'is_rescheduled' => true,
            'reschedule_reason' => 'Customer requested a new time',
        ]);
        $this->assertDatabaseHas('site_visits', [
            'lead_id' => $lead->id,
            'status' => 'scheduled',
            'rescheduled_from_visit_id' => $siteVisit->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_site_visit_task_sync_cancels_duplicate_telecaller_visit_reminder(): void
    {
        [$manager, $lead, $task, $siteVisit] = $this->seedSiteVisitWorkflow('visit-duplicate-reminder@example.test');

        $task->update([
            'status' => 'cancelled',
            'completed_at' => Carbon::now(),
        ]);

        $duplicateTelecallerTaskId = DB::table('telecaller_tasks')->insertGetId([
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'site_visit_id' => $siteVisit->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse($siteVisit->scheduled_at)->subMinutes(10),
            'notes' => 'Reminder call 10 min before site visit scheduled at ' . Carbon::parse($siteVisit->scheduled_at)->format('M d, Y h:i A'),
            'created_by' => $manager->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        app(\App\Services\SiteVisitTaskSyncService::class)->syncReminderTask($siteVisit->fresh(), $manager);

        $this->assertDatabaseHas('tasks', [
            'lead_id' => $lead->id,
            'site_visit_id' => $siteVisit->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('telecaller_tasks', [
            'id' => $duplicateTelecallerTaskId,
            'status' => 'cancelled',
        ]);
    }

    public function test_manager_lead_detail_includes_meeting_action_hub_markup(): void
    {
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'manager-meeting@example.test']);
        $lead = $this->createLead(['name' => 'Meeting Flow Lead']);
        $this->assignLead($lead, $manager);

        $this->createTask($lead, $manager, [
            'title' => 'Pre-meeting reminder call - Demo Lead',
            'notes' => 'Pre-meeting reminder call for meeting scheduled at 2026-03-31 18:00 | Meeting ID: 91',
            'scheduled_at' => Carbon::now()->addMinutes(25),
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/text-emerald-700[^>]*>\s*M\s*</', $response->getContent());
        $response->assertSee('Meeting Actions');
        $response->assertSee('Meeting Complete');
        $response->assertSee('Reschedule Meeting');
        $response->assertSee('Edit Requirement Form');
        $response->assertSee('Schedule Visit');
        $response->assertSee('Schedule Follow Up');
        $response->assertSee('Complete Meeting');
        $response->assertSee('Meeting Outcome');
        $response->assertSee('Schedule Visit');
        $response->assertSee('Schedule Follow Up');
        $response->assertSee('More Outcomes');
        $response->assertSee('Interested');
        $response->assertSee('Not Interested');
        $response->assertSee('Junk');
        $response->assertDontSee("handleMeetingHubAction('cnp')", false);
        $response->assertSee("currentTaskCategory === 'meeting'", false);
        $response->assertSee("handleMeetingHubAction('interested')", false);
        $this->assertStringContainsString('meeting', $response->getContent());
        $this->assertStringContainsString('Pre-meeting reminder call for meeting scheduled at 2026-03-31 18:00 | Meeting ID: 91', $response->getContent());
    }

    public function test_manager_lead_detail_normalizes_site_visit_task_category_from_legacy_text(): void
    {
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'manager-legacy-visit@example.test']);
        $lead = $this->createLead(['name' => 'Legacy Visit Flow Lead']);
        $this->assignLead($lead, $manager);

        $this->createTask($lead, $manager, [
            'title' => 'Prepare site visit follow-up',
            'notes' => 'Site visit scheduled for tomorrow morning',
            'scheduled_at' => Carbon::now()->addMinutes(40),
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $this->assertStringContainsString('site_visit', $response->getContent());
        $this->assertMatchesRegularExpression('/text-cyan-700[^>]*>\s*S\s*</', $response->getContent());
    }

    public function test_manager_lead_detail_shows_follow_up_badge_for_follow_up_tasks(): void
    {
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => 'manager-follow-up-badge@example.test']);
        $lead = $this->createLead(['name' => 'Follow Up Badge Lead']);
        $this->assignLead($lead, $manager);

        $this->createTask($lead, $manager, [
            'title' => 'Follow-up call task',
            'notes' => 'Follow-up call scheduled for tomorrow',
            'scheduled_at' => Carbon::now()->addMinutes(20),
        ]);

        $response = $this->actingAs($manager)->get(route('leads.show', $lead));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/text-blue-700[^>]*>\s*F\s*</', $response->getContent());
        $this->assertStringContainsString('follow_up', $response->getContent());
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

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->string('budget')->nullable();
            $table->string('property_type')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('marked_dead_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->unsignedBigInteger('other_lead_marked_by')->nullable();
            $table->string('other_lead_reason')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lead_id');
            $table->timestamps();
        });

        Schema::create('lead_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('project_share_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('imported_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('fb_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('waba_call_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('user_attendance_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('attendance_enabled')->default(false);
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('call_type')->nullable();
            $table->string('direction')->nullable();
            $table->integer('duration')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('closing_verified_by')->nullable();
            $table->unsignedBigInteger('rescheduled_by')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('converted_to_closer_at')->nullable();
            $table->timestamp('closing_verified_at')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->string('closer_status')->nullable();
            $table->unsignedBigInteger('closer_verified_by')->nullable();
            $table->timestamp('closer_verified_at')->nullable();
            $table->text('closer_rejection_reason')->nullable();
            $table->text('closing_rejection_reason')->nullable();
            $table->string('property_name')->nullable();
            $table->string('property_address')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('project')->nullable();
            $table->integer('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->text('visit_notes')->nullable();
            $table->json('completion_proof_photos')->nullable();
            $table->string('visited_projects')->nullable();
            $table->json('visited_property_types')->nullable();
            $table->string('tentative_closing_time')->nullable();
            $table->string('lead_type')->nullable();
            $table->timestamp('rescheduled_at')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->boolean('is_rescheduled')->default(false);
            $table->unsignedBigInteger('reminder_task_id')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->unsignedBigInteger('rescheduled_from_visit_id')->nullable();
            $table->unsignedBigInteger('rescheduled_to_visit_id')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->text('dead_reason')->nullable();
            $table->timestamp('marked_dead_at')->nullable();
            $table->unsignedBigInteger('marked_dead_by')->nullable();
            $table->json('kyc_documents')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('interested_project_names', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_public_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('assigned_manager')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('manager_remark')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prospect_project', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospect_id');
            $table->unsignedBigInteger('project_id');
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
            $table->unsignedInteger('cnp_count')->default(0);
            $table->dateTime('assignment_started_at')->nullable();
            $table->dateTime('first_cnp_at')->nullable();
            $table->dateTime('last_cnp_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->dateTime('eligible_for_transfer_at')->nullable();
            $table->dateTime('last_processed_at')->nullable();
            $table->boolean('transfer_eligible')->default(false);
            $table->string('status')->default('active');
            $table->text('cancel_reason')->nullable();
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
            $table->unsignedInteger('cnp_count')->default(0);
            $table->string('action');
            $table->text('message')->nullable();
            $table->text('meta')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->default('calling');
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

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('follow_up_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->default('phone_call');
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

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
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
            $table->unsignedBigInteger('form_id');
            $table->string('field_key');
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->text('options')->nullable();
            $table->string('default_value')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], [
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, array $attributes = []): User
    {
        static $counter = 1;

        return User::create(array_merge([
            'name' => 'User ' . $counter,
            'email' => 'user' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
    }

    private function createLead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'ASM Lead',
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'meta',
        ], $attributes));
    }

    private function assignLead(Lead $lead, User $user): void
    {
        DB::table('lead_assignments')->insert([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assignment_type' => 'primary',
            'assigned_at' => Carbon::now(),
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
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

    private function seedSiteVisitWorkflow(string $managerEmail): array
    {
        $manager = $this->createUser($this->createRole(Role::ASSISTANT_SALES_MANAGER), ['email' => $managerEmail]);
        $lead = $this->createLead(['name' => 'Visit Workflow Lead']);
        $this->assignLead($lead, $manager);

        $siteVisitId = DB::table('site_visits')->insertGetId([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'customer_name' => $lead->name,
            'phone' => $lead->phone,
            'scheduled_at' => Carbon::now()->addHour(),
            'status' => 'scheduled',
            'verification_status' => 'pending',
            'lead_type' => 'New Visit',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $task = $this->createTask($lead, $manager, [
            'title' => 'Site visit reminder',
            'notes' => 'Site visit workflow task',
            'site_visit_id' => $siteVisitId,
            'scheduled_at' => Carbon::now()->addMinutes(30),
        ]);

        return [$manager, $lead, $task, \App\Models\SiteVisit::find($siteVisitId)];
    }
}
