<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Http\Controllers\Api\SiteVisitController;
use App\Services\AsmCnpAutomationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SiteVisitRescheduleTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-04-19 10:30:00'));

        $notificationService = Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $notificationService);

        $asmService = Mockery::mock(AsmCnpAutomationService::class);
        $asmService->shouldIgnoreMissing();
        $this->app->instance(AsmCnpAutomationService::class, $asmService);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_senior_manager_reschedule_cancels_old_visit_and_creates_new_visit_and_task(): void
    {
        $role = $this->createRole(Role::SENIOR_MANAGER);
        $manager = $this->createUser($role, ['email' => 'srm@example.test']);
        $lead = $this->createLead(['created_by' => $manager->id]);

        $oldVisit = SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'customer_name' => 'Shivansh Singh',
            'phone' => '919801662',
            'project' => 'Jashn elevate',
            'property_type' => 'Flat',
            'scheduled_at' => Carbon::parse('2026-04-20 12:00:00'),
            'status' => 'scheduled',
            'verification_status' => 'pending',
        ]);

        $oldTask = TelecallerTask::create([
            'lead_id' => $lead->id,
            'site_visit_id' => $oldVisit->id,
            'assigned_to' => $manager->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-04-20 11:50:00'),
            'notes' => 'Old site visit reminder',
            'created_by' => $manager->id,
        ]);

        $this->assertTrue($manager->isSeniorManager());
        $this->assertTrue((bool) in_array($manager->id, $manager->teamMembers()->pluck('id')->push($manager->id)->all(), true));

        $response = $this->callReschedule($manager, $oldVisit, [
            'scheduled_at' => '2026-04-21 15:30:00',
            'reason' => 'Customer asked to move the visit',
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);

        $oldVisit->refresh();
        $this->assertSame('cancelled', $oldVisit->status);
        $this->assertTrue((bool) $oldVisit->is_rescheduled);
        $this->assertSame('Customer asked to move the visit', $oldVisit->reschedule_reason);

        $newVisit = SiteVisit::where('lead_id', $lead->id)
            ->where('id', '!=', $oldVisit->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newVisit);
        $this->assertSame('scheduled', $newVisit->status);
        $this->assertSame('Shivansh Singh', $newVisit->customer_name);
        $this->assertSame('Jashn elevate', $newVisit->project);
        $this->assertSame('2026-04-21 15:30:00', $newVisit->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($oldVisit->id, (int) $newVisit->rescheduled_from_visit_id);

        $oldTask->refresh();
        $this->assertSame('completed', $oldTask->status);
        $this->assertSame('rescheduled', $oldTask->outcome);
        $this->assertNotNull($oldTask->completed_at);

        $newTask = TelecallerTask::where('lead_id', $lead->id)
            ->where('site_visit_id', $newVisit->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($newTask);
        $this->assertSame('2026-04-21 15:20:00', $newTask->scheduled_at->format('Y-m-d H:i:s'));

        $newManagerReminder = Task::where('lead_id', $lead->id)
            ->where('site_visit_id', $newVisit->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($newManagerReminder);
        $this->assertSame('site_visit', $newManagerReminder->type);
        $this->assertSame('2026-04-21 14:30:00', $newManagerReminder->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_reschedule_cancels_old_manager_reminder_and_creates_new_manager_reminder(): void
    {
        $role = $this->createRole(Role::SENIOR_MANAGER);
        $manager = $this->createUser($role, ['email' => 'srm-reminder@example.test']);
        $lead = $this->createLead(['created_by' => $manager->id]);

        $oldVisit = SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'customer_name' => 'Reminder Lead',
            'phone' => '9876543210',
            'project' => 'Oaks Sukoon',
            'scheduled_at' => Carbon::parse('2026-04-20 14:00:00'),
            'status' => 'scheduled',
            'verification_status' => 'pending',
            'reminder_enabled' => true,
            'reminder_minutes' => 15,
        ]);

        $oldReminder = Task::create([
            'lead_id' => $lead->id,
            'site_visit_id' => $oldVisit->id,
            'assigned_to' => $manager->id,
            'type' => 'phone_call',
            'title' => 'Reminder',
            'description' => 'Old reminder',
            'status' => 'pending',
            'scheduled_at' => Carbon::parse('2026-04-20 13:45:00'),
            'created_by' => $manager->id,
            'notes' => 'Site Visit Reminder - Site Visit ID: ' . $oldVisit->id,
        ]);

        $oldVisit->update(['reminder_task_id' => $oldReminder->id]);

        $this->assertTrue($manager->isSeniorManager());

        $response = $this->callReschedule($manager, $oldVisit, [
            'scheduled_at' => '2026-04-21 18:00:00',
            'reason' => 'Family requested evening slot',
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $oldReminder->refresh();
        $this->assertSame('cancelled', $oldReminder->status);
        $this->assertNotNull($oldReminder->completed_at);

        $newVisit = SiteVisit::where('lead_id', $lead->id)
            ->where('id', '!=', $oldVisit->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($newVisit->reminder_task_id);

        $newReminder = Task::find($newVisit->reminder_task_id);
        $this->assertNotNull($newReminder);
        $this->assertSame('pending', $newReminder->status);
        $this->assertSame('site_visit', $newReminder->type);
        $this->assertSame('2026-04-21 17:00:00', $newReminder->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_non_scheduled_visit_cannot_be_rescheduled(): void
    {
        $role = $this->createRole(Role::SENIOR_MANAGER);
        $manager = $this->createUser($role, ['email' => 'srm-invalid@example.test']);
        $lead = $this->createLead(['created_by' => $manager->id]);

        $visit = SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'customer_name' => 'Closed Visit',
            'scheduled_at' => Carbon::parse('2026-04-20 10:00:00'),
            'status' => 'completed',
            'verification_status' => 'pending',
        ]);

        $this->assertTrue($manager->isSeniorManager());

        $response = $this->callReschedule($manager, $visit, [
            'scheduled_at' => '2026-04-22 10:00:00',
            'reason' => 'Move date',
        ]);

        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
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
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('outcome')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('first_reminder_sent_at')->nullable();
            $table->dateTime('final_reminder_sent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('project')->nullable();
            $table->string('property_type')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->text('visit_notes')->nullable();
            $table->string('verification_status')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('closer_status')->nullable();
            $table->dateTime('converted_to_closer_at')->nullable();
            $table->unsignedBigInteger('closer_verified_by')->nullable();
            $table->dateTime('closer_verified_at')->nullable();
            $table->text('closer_rejection_reason')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->unsignedBigInteger('closing_verified_by')->nullable();
            $table->dateTime('closing_verified_at')->nullable();
            $table->text('closing_rejection_reason')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->text('dead_reason')->nullable();
            $table->dateTime('marked_dead_at')->nullable();
            $table->unsignedBigInteger('marked_dead_by')->nullable();
            $table->dateTime('rescheduled_at')->nullable();
            $table->unsignedBigInteger('rescheduled_by')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->integer('reminder_minutes')->nullable();
            $table->dateTime('first_reminder_sent_at')->nullable();
            $table->dateTime('final_reminder_sent_at')->nullable();
            $table->unsignedBigInteger('reminder_task_id')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->boolean('is_rescheduled')->default(false);
            $table->string('lead_type')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->unsignedBigInteger('rescheduled_from_visit_id')->nullable();
            $table->unsignedBigInteger('rescheduled_to_visit_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->enum('task_type', ['calling', 'follow_up', 'cnp_retry', 'pre_meeting_reminder'])->default('calling');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'rescheduled'])->default('pending');
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('outcome', ['interested', 'not_interested', 'cnp', 'block', 'rescheduled'])->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->dateTime('notification_sent_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->dateTime('moved_to_pending_at')->nullable();
            $table->dateTime('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_api_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_endpoint')->nullable();
            $table->text('api_token')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->string('base_url')->nullable();
            $table->string('send_message_endpoint')->nullable();
            $table->string('send_template_endpoint')->nullable();
            $table->string('get_conversations_endpoint')->nullable();
            $table->string('get_messages_endpoint')->nullable();
            $table->string('get_templates_endpoint')->nullable();
            $table->string('get_template_endpoint')->nullable();
            $table->string('create_template_endpoint')->nullable();
            $table->string('delete_template_endpoint')->nullable();
            $table->string('get_groups_endpoint')->nullable();
            $table->string('make_group_endpoint')->nullable();
            $table->string('update_group_endpoint')->nullable();
            $table->string('remove_group_endpoint')->nullable();
            $table->string('import_contact_endpoint')->nullable();
            $table->string('update_contact_endpoint')->nullable();
            $table->string('remove_contact_endpoint')->nullable();
            $table->string('add_contacts_endpoint')->nullable();
            $table->string('get_media_endpoint')->nullable();
            $table->string('get_campaigns_endpoint')->nullable();
            $table->string('send_campaign_endpoint')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => $role->name . ' User',
            'email' => uniqid($role->slug . '-', true) . '@example.test',
            'password' => 'secret',
            'role_id' => $role->id,
            'is_active' => true,
        ], $overrides));
    }

    private function createLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Reschedule Lead',
            'phone' => '9999999999',
            'status' => 'new',
            'source' => 'manual',
        ], $overrides));
    }

    private function callReschedule(User $actor, SiteVisit $siteVisit, array $payload)
    {
        $request = Request::create(
            "/api/site-visits/{$siteVisit->id}/reschedule",
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        $request->replace($payload);
        $request->setUserResolver(fn () => $actor);

        return app(SiteVisitController::class)->reschedule($request, $siteVisit);
    }
}
