<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FbForm;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\LeadDuplicateGuardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadDuplicateGuardAndMetaReenquiryTest extends TestCase
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
        Config::set('logging.default', 'errorlog');
        Config::set('session.driver', 'array');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_manual_lead_create_with_unique_phone_succeeds_and_normalizes_phone(): void
    {
        $admin = $this->createUser($this->createRole(Role::ADMIN), 'admin-unique@example.test');

        $response = $this->actingAs($admin)->post(route('leads.store'), [
            'name' => 'Unique Lead',
            'phone' => '98765-43210',
            'source' => 'meta',
        ]);

        $lead = Lead::query()->first();

        $response->assertRedirect(route('leads.index'));
        $response->assertSessionHas('success');
        $this->assertNotNull($lead);
        $this->assertSame('9876543210', $lead->phone);
        $this->assertSame('919876543210', $lead->normalized_phone);
    }

    public function test_lead_model_populates_normalized_phone_and_keeps_visible_phone_consistent(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-normalized@example.test');

        $lead = Lead::create([
            'name' => 'Formatted Phone',
            'phone' => '+91 (98765) 43210',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $crm->id,
        ]);

        $this->assertSame('9876543210', $lead->phone);
        $this->assertSame('919876543210', $lead->normalized_phone);
    }

    public function test_duplicate_lookup_uses_normalized_phone_across_common_formats(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-normalized-duplicate@example.test');
        $existingLead = Lead::create([
            'name' => 'Existing Normalized Lead',
            'phone' => '+91 (98765) 43210',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $crm->id,
        ]);

        $service = app(DuplicateDetectionService::class);

        $this->assertSame($existingLead->id, $service->findExistingLeadByPhone('9876543210')?->id);
        $this->assertSame($existingLead->id, $service->findExistingLeadByPhone('91-98765-43210')?->id);
        $this->assertSame($existingLead->id, $service->findExistingLeadByPhone('+91 98765 43210')?->id);
    }

    public function test_backfill_normalized_phone_command_updates_old_rows_and_is_rerunnable(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-backfill@example.test');

        DB::table('leads')->insert([
            'name' => 'Old Lead',
            'phone' => '+91 91234-56789',
            'normalized_phone' => null,
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $crm->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('leads')->insert([
            'name' => 'Invalid Phone Lead',
            'phone' => '12345',
            'normalized_phone' => null,
            'source' => 'other',
            'status' => 'new',
            'created_by' => $crm->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('leads:backfill-normalized-phone --chunk=1')
            ->expectsOutput('Processed 2 lead(s); updated 1 normalized phone value(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('leads', [
            'phone' => '9123456789',
            'normalized_phone' => '919123456789',
        ]);

        $this->artisan('leads:backfill-normalized-phone --chunk=1')
            ->expectsOutput('Processed 1 lead(s); updated 0 normalized phone value(s).')
            ->assertExitCode(0);
    }

    public function test_duplicate_detection_service_does_not_load_all_phone_leads_for_lookup(): void
    {
        $source = file_get_contents(app_path('Services/DuplicateDetectionService.php'));

        $this->assertStringNotContainsString("whereNotNull('phone')->get()", $source);
        $this->assertStringNotContainsString('whereNotNull("phone")->get()', $source);
        $this->assertStringNotContainsString('Lead::all()', $source);
    }

    public function test_manual_lead_create_with_duplicate_phone_is_blocked_with_existing_lead_shortcut(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-duplicate@example.test');
        $existingLead = Lead::create([
            'name' => 'Existing Lead',
            'phone' => '919876543210',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $crm->id,
        ]);

        $response = $this->actingAs($crm)->from(route('leads.create'))->post(route('leads.store'), [
            'name' => 'Duplicate Lead',
            'phone' => '9876543210',
            'source' => 'meta',
        ]);

        $response->assertRedirect(route('leads.create'));
        $response->assertSessionHasErrors('phone');
        $response->assertSessionHas('duplicate_lead', function (array $payload) use ($existingLead) {
            return ($payload['duplicate'] ?? false) === true
                && (int) ($payload['existing_lead_id'] ?? 0) === $existingLead->id
                && ($payload['existing_lead_url'] ?? null) === route('leads.show', $existingLead);
        });
        $this->assertSame(1, Lead::count());
    }

    public function test_meta_duplicate_on_active_lead_marks_reenquiry_without_creating_second_lead_or_duplicate_task(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-meta-active@example.test');
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), 'manager-meta-active@example.test');
        $existingLead = Lead::create([
            'name' => 'Active Existing Lead',
            'phone' => '919876543210',
            'source' => 'meta',
            'status' => 'new',
            'created_by' => $crm->id,
        ]);
        $form = $this->createFbForm('Plot Form');

        LeadAssignment::create([
            'lead_id' => $existingLead->id,
            'assigned_to' => $manager->id,
            'assigned_by' => $crm->id,
            'assigned_at' => now(),
            'assignment_type' => 'primary',
            'assignment_method' => 'manual',
            'is_active' => true,
        ]);

        Task::create([
            'lead_id' => $existingLead->id,
            'assigned_to' => $manager->id,
            'created_by' => $crm->id,
            'type' => 'phone_call',
            'title' => 'Call active lead',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(10),
        ]);

        $result = app(LeadDuplicateGuardService::class)->createOrAttachMetaLead($form, [
            'name' => 'Same Lead Again',
            'phone' => '98765 43210',
            'email' => 'same@example.test',
        ], $crm->id);

        $this->assertNotNull($result);
        $this->assertFalse($result['was_created']);
        $this->assertTrue($result['was_duplicate']);
        $this->assertFalse($result['was_reopened']);
        $this->assertSame($existingLead->id, $result['lead']->id);

        $existingLead->refresh();

        $this->assertSame(1, Lead::count());
        $this->assertTrue((bool) $existingLead->is_reenquiry);
        $this->assertSame(1, (int) $existingLead->reenquiry_count);
        $this->assertSame('meta', $existingLead->last_reenquiry_source);
        $this->assertSame($form->id, (int) $existingLead->last_reenquiry_fb_form_id);
        $this->assertSame(1, Task::count());
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => 'Lead',
            'model_id' => $existingLead->id,
            'action' => 'lead_reenquiry',
        ]);
    }

    public function test_meta_duplicate_on_terminal_lead_reopens_and_creates_single_fresh_task_for_owner(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'crm-meta-reopen@example.test');
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), 'manager-meta-reopen@example.test');
        $existingLead = Lead::create([
            'name' => 'Terminal Lead',
            'phone' => '919123456789',
            'source' => 'meta',
            'status' => 'junk',
            'created_by' => $crm->id,
            'status_auto_update_enabled' => false,
            'other_lead_marked_by' => $crm->id,
            'other_lead_marked_at' => now()->subDay(),
            'other_lead_reason' => 'Old junk',
        ]);
        $form = $this->createFbForm('Apartment Form');

        LeadAssignment::create([
            'lead_id' => $existingLead->id,
            'assigned_to' => $manager->id,
            'assigned_by' => $crm->id,
            'assigned_at' => now()->subDays(2),
            'assignment_type' => 'primary',
            'assignment_method' => 'manual',
            'is_active' => true,
        ]);

        $result = app(LeadDuplicateGuardService::class)->createOrAttachMetaLead($form, [
            'name' => 'Terminal Lead',
            'phone' => '9123456789',
        ], $crm->id);

        $this->assertNotNull($result);
        $this->assertFalse($result['was_created']);
        $this->assertTrue($result['was_duplicate']);
        $this->assertTrue($result['was_reopened']);

        $existingLead->refresh();
        $freshTask = Task::query()->where('lead_id', $existingLead->id)->latest('id')->first();

        $this->assertSame(1, Lead::count());
        $this->assertSame('new', $existingLead->status);
        $this->assertTrue((bool) $existingLead->status_auto_update_enabled);
        $this->assertTrue((bool) $existingLead->is_reenquiry);
        $this->assertSame(1, (int) $existingLead->reenquiry_count);
        $this->assertNull($existingLead->other_lead_marked_by);
        $this->assertNull($existingLead->other_lead_marked_at);
        $this->assertNull($existingLead->other_lead_reason);
        $this->assertNotNull($existingLead->last_reenquiry_at);
        $this->assertNotNull($freshTask);
        $this->assertSame($manager->id, (int) $freshTask->assigned_to);
        $this->assertSame('pending', $freshTask->status);
        $this->assertSame(1, Task::count());
        $this->assertSame(1, ActivityLog::where('model_id', $existingLead->id)->where('action', 'lead_reenquiry')->count());
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

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('normalized_phone')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->default('new');
            $table->string('budget')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->unsignedBigInteger('other_lead_marked_by')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->string('other_lead_reason')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->string('dead_reason')->nullable();
            $table->string('dead_at_stage')->nullable();
            $table->timestamp('marked_dead_at')->nullable();
            $table->unsignedBigInteger('marked_dead_by')->nullable();
            $table->boolean('is_reenquiry')->default(false);
            $table->unsignedInteger('reenquiry_count')->default(0);
            $table->timestamp('last_reenquiry_at')->nullable();
            $table->string('last_reenquiry_source')->nullable();
            $table->unsignedBigInteger('last_reenquiry_fb_form_id')->nullable();
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

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('field_key');
            $table->text('field_value')->nullable();
            $table->unsignedBigInteger('filled_by_user_id')->nullable();
            $table->timestamp('filled_at')->nullable();
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
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
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

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('call_status')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('called_at')->nullable();
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
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('action_type')->nullable();
            $table->text('action_url')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('fcm_token');
            $table->string('device_type')->nullable();
            $table->timestamps();
        });

        Schema::create('source_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('fb_form_id')->nullable();
            $table->unsignedBigInteger('google_sheet_config_id')->nullable();
            $table->string('assignment_method')->nullable();
            $table->unsignedBigInteger('single_user_id')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->boolean('auto_create_task')->default(true);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('source_automation_rule_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('percentage')->nullable();
            $table->unsignedInteger('assigned_count_today')->default(0);
            $table->date('last_assignment_date')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
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

    private function createUser(Role $role, string $email): User
    {
        return User::create([
            'name' => strtok($email, '@'),
            'email' => $email,
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createFbForm(string $name): FbForm
    {
        static $counter = 1;

        return FbForm::create([
            'form_id' => 'form-' . $counter,
            'form_name' => $name,
            'is_enabled' => true,
        ]);
    }
}
