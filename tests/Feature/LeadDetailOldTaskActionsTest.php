<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\LeadActivityService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadDetailOldTaskActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('broadcasting.default', 'log');
        Config::set('logging.default', 'errorlog');
        Config::set('view.compiled', sys_get_temp_dir());
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
        Carbon::setTestNow(Carbon::parse('2026-03-31 15:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_crm_lead_detail_shows_old_tasks_card_with_both_open_task_models_only_for_crm_admin(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-old-task@example.test']);
        $salesExecutive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'owner-old-task@example.test']);
        $lead = $this->createLead(['name' => 'Old Task Lead']);
        $this->assignLead($lead, $salesExecutive);

        $this->createTask($lead, $salesExecutive, [
            'title' => 'Manager Old Call',
            'scheduled_at' => Carbon::now()->addHour(),
        ]);
        $this->createTelecallerTask($lead, $salesExecutive, [
            'task_type' => 'calling',
            'scheduled_at' => Carbon::now()->addMinutes(20),
            'notes' => 'Callback requested',
        ]);
        $this->createTask($lead, $salesExecutive, [
            'title' => 'Completed Hidden Call',
            'status' => 'completed',
            'completed_at' => Carbon::now()->subMinutes(30),
        ]);

        $crmResponse = $this->actingAs($crm)->get(route('leads.show', $lead));

        $crmResponse->assertOk();
        $crmResponse->assertSee('Old Tasks');
        $crmResponse->assertSee('Manager Old Call');
        $crmResponse->assertSee('Calling task');
        $crmResponse->assertSee('Mark Complete');
        $crmResponse->assertSee('Delete');
        $crmResponse->assertSee('2 Open');

        $ownerResponse = $this->actingAs($salesExecutive)->get(route('leads.show', $lead));
        $ownerResponse->assertOk();
        $ownerResponse->assertDontSee('Old Tasks');
    }

    public function test_crm_can_complete_old_tasks_from_lead_detail_api_for_both_models(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-complete@example.test']);
        $salesExecutive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'owner-complete@example.test']);
        $lead = $this->createLead(['name' => 'Completion Lead']);
        $this->assignLead($lead, $salesExecutive);

        $managerTask = $this->createTask($lead, $salesExecutive, [
            'title' => 'Manager Old Task',
        ]);
        $telecallerTask = $this->createTelecallerTask($lead, $salesExecutive, [
            'task_type' => 'calling',
        ]);

        Sanctum::actingAs($crm);

        $this->postJson("/api/leads/{$lead->id}/old-tasks/complete", [
            'model_type' => 'task',
            'task_id' => $managerTask->id,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->postJson("/api/leads/{$lead->id}/old-tasks/complete", [
            'model_type' => 'telecaller_task',
            'task_id' => $telecallerTask->id,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $managerTask->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('telecaller_tasks', [
            'id' => $telecallerTask->id,
            'status' => 'completed',
        ]);

        $timeline = app(LeadActivityService::class)->getTimeline($lead)->where('type', 'task_completed')->values();

        $this->assertTrue($timeline->contains(function ($entry) {
            return $entry['title'] === 'Task Completed'
                && str_contains($entry['description'], 'Manager Old Task');
        }));

        $this->assertTrue($timeline->contains(function ($entry) {
            return $entry['title'] === 'Task Completed'
                && str_contains($entry['description'], 'Calling task');
        }));
    }

    public function test_crm_can_delete_old_tasks_from_lead_detail_api_and_timeline_keeps_removed_entries(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), ['email' => 'crm-delete@example.test']);
        $salesExecutive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), ['email' => 'owner-delete@example.test']);
        $lead = $this->createLead(['name' => 'Delete Lead']);
        $this->assignLead($lead, $salesExecutive);

        $managerTask = $this->createTask($lead, $salesExecutive, [
            'title' => 'Delete Manager Task',
        ]);
        $telecallerTask = $this->createTelecallerTask($lead, $salesExecutive, [
            'task_type' => 'calling',
        ]);

        Sanctum::actingAs($crm);

        $this->deleteJson("/api/leads/{$lead->id}/old-tasks", [
            'model_type' => 'task',
            'task_id' => $managerTask->id,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->deleteJson("/api/leads/{$lead->id}/old-tasks", [
            'model_type' => 'telecaller_task',
            'task_id' => $telecallerTask->id,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('tasks', [
            'id' => $managerTask->id,
        ]);

        $this->assertSoftDeleted('telecaller_tasks', [
            'id' => $telecallerTask->id,
        ]);

        $timeline = app(LeadActivityService::class)->getTimeline($lead)->where('type', 'task_deleted')->values();

        $this->assertCount(2, $timeline);
        $this->assertTrue($timeline->contains(function ($entry) {
            return $entry['title'] === 'Task Removed'
                && str_contains($entry['description'], 'Delete Manager Task')
                && str_contains($entry['description'], 'removed manually from lead details');
        }));
        $this->assertTrue($timeline->contains(function ($entry) {
            return $entry['title'] === 'Task Removed'
                && str_contains($entry['description'], 'Calling task')
                && str_contains($entry['description'], 'removed manually from lead details');
        }));
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
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('converted_to_closer_at')->nullable();
            $table->timestamp('closing_verified_at')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->string('project')->nullable();
            $table->integer('rating')->nullable();
            $table->text('visit_notes')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->boolean('is_rescheduled')->default(false);
            $table->json('kyc_documents')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
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
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('moved_to_pending_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            $table->text('recurrence_pattern')->nullable();
            $table->timestamp('recurrence_end_date')->nullable();
            $table->timestamp('rescheduled_from')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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
            'name' => 'CRM Lead',
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

    private function createTelecallerTask(Lead $lead, User $assignedTo, array $attributes = []): TelecallerTask
    {
        return TelecallerTask::create(array_merge([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::now(),
            'created_by' => $assignedTo->id,
        ], $attributes));
    }
}
