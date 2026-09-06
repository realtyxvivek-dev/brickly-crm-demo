<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ExecutionDeskFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Storage::fake('public');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('execution_saved_views');
        Schema::dropIfExists('execution_task_checklists');
        Schema::dropIfExists('execution_task_attachments');
        Schema::dropIfExists('execution_task_activities');
        Schema::dropIfExists('execution_tasks');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('company_files');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        parent::tearDown();
    }

    public function test_task_can_be_created_without_checklist_and_seen_by_assignee(): void
    {
        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');
        $financeRole = $this->createRole(Role::FINANCE_MANAGER, 'Finance Manager');
        $creator = $this->createUser($hrRole, 'hr@example.test');
        $assignee = $this->createUser($financeRole, 'finance@example.test');

        $response = $this->actingAs($creator)->post('/execution-desk/tasks', [
            'title' => 'Settlement sheet',
            'description' => 'Prepare final settlement',
            'assigned_to' => $assignee->id,
            'priority' => 'high',
            'context_label' => 'finance',
            'is_private' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('execution_tasks', [
            'title' => 'Settlement sheet',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'is_private' => 1,
            'status' => 'open',
        ]);
        $this->assertDatabaseCount('execution_task_checklists', 0);

        $assigneeResponse = $this->actingAs($assignee)->get('/execution-desk');
        $assigneeResponse->assertOk()->assertSee('Settlement sheet');
    }

    public function test_task_can_be_created_with_multiple_files_and_links(): void
    {
        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');
        $financeRole = $this->createRole(Role::FINANCE_MANAGER, 'Finance Manager');
        $creator = $this->createUser($hrRole, 'hr2@example.test');
        $assignee = $this->createUser($financeRole, 'finance2@example.test');

        $response = $this->actingAs($creator)->post('/execution-desk/tasks', [
            'title' => 'Vendor pack',
            'description' => 'Collect all supporting docs',
            'assigned_to' => $assignee->id,
            'priority' => 'medium',
            'context_label' => 'finance',
            'attachments' => [
                UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('scope.xlsx', 80, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ],
            'attachment_links' => [
                ['title' => 'Drive folder', 'url' => 'https://drive.example.test/folder'],
                ['title' => '', 'url' => 'https://docs.example.test/spec'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('execution_tasks', [
            'title' => 'Vendor pack',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
        ]);

        $taskId = (int) DB::table('execution_tasks')->where('title', 'Vendor pack')->value('id');
        $this->assertDatabaseCount('execution_task_attachments', 4);
        $this->assertDatabaseHas('execution_task_attachments', [
            'task_id' => $taskId,
            'attachment_kind' => 'link',
            'link_url' => 'https://drive.example.test/folder',
            'link_title' => 'Drive folder',
        ]);
        $this->assertDatabaseHas('execution_task_attachments', [
            'task_id' => $taskId,
            'attachment_kind' => 'link',
            'link_url' => 'https://docs.example.test/spec',
        ]);
    }

    public function test_task_detail_accepts_link_attachment_and_renders_open_action(): void
    {
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $execRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $creator = $this->createUser($managerRole, 'creator2@example.test');
        $assignee = $this->createUser($execRole, 'assignee2@example.test');

        $taskId = DB::table('execution_tasks')->insertGetId([
            'title' => 'Linkable task',
            'description' => 'Pending resource',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'priority' => 'medium',
            'status' => 'open',
            'context_label' => 'operations',
            'is_private' => false,
            'task_code' => 'EX-100001',
            'created_at' => now(),
            'updated_at' => now(),
            'last_status_changed_at' => now(),
        ]);

        $saveResponse = $this->actingAs($creator)->post("/execution-desk/tasks/{$taskId}/attachments", [
            'link_title' => 'Resource deck',
            'link_url' => 'https://example.test/resource-deck',
        ]);

        $saveResponse->assertRedirect();
        $this->assertDatabaseHas('execution_task_attachments', [
            'task_id' => $taskId,
            'attachment_kind' => 'link',
            'link_title' => 'Resource deck',
            'link_url' => 'https://example.test/resource-deck',
        ]);

        $detail = $this->actingAs($assignee)->get("/execution-desk/tasks/{$taskId}");
        $detail->assertOk()->assertSee('Resource deck')->assertSee('Open')->assertSee('Link');
    }

    public function test_team_all_hides_private_tasks_and_close_requires_creator_or_admin(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $execRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');

        $admin = $this->createUser($adminRole, 'admin@example.test');
        $creator = $this->createUser($managerRole, 'manager@example.test');
        $viewer = $this->createUser($managerRole, 'viewer@example.test');
        $assignee = $this->createUser($execRole, 'exec@example.test');

        $privateTaskId = \DB::table('execution_tasks')->insertGetId([
            'title' => 'Private salary review',
            'description' => 'Sensitive',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'priority' => 'high',
            'status' => 'completed',
            'context_label' => 'hr',
            'is_private' => true,
            'task_code' => 'EX-000001',
            'created_at' => now(),
            'updated_at' => now(),
            'last_status_changed_at' => now(),
        ]);

        $publicTaskId = \DB::table('execution_tasks')->insertGetId([
            'title' => 'Public ops task',
            'description' => 'Visible',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'priority' => 'medium',
            'status' => 'completed',
            'context_label' => 'operations',
            'is_private' => false,
            'task_code' => 'EX-000002',
            'created_at' => now(),
            'updated_at' => now(),
            'last_status_changed_at' => now(),
        ]);

        $viewerResponse = $this->actingAs($viewer)->get('/execution-desk?tab=team_all');
        $viewerResponse->assertOk()->assertSee('Public ops task')->assertDontSee('Private salary review');

        $forbidden = $this->actingAs($assignee)->put("/execution-desk/tasks/{$publicTaskId}/status", [
            'status' => 'closed',
        ]);
        $forbidden->assertStatus(403);

        $creatorClose = $this->actingAs($creator)->put("/execution-desk/tasks/{$publicTaskId}/status", [
            'status' => 'closed',
        ]);
        $creatorClose->assertRedirect();
        $this->assertDatabaseHas('execution_tasks', [
            'id' => $publicTaskId,
            'status' => 'closed',
        ]);

        $adminCanSeePrivate = $this->actingAs($admin)->get('/execution-desk?tab=team_all');
        $adminCanSeePrivate->assertOk()->assertSee('Private salary review');
        $this->assertDatabaseHas('execution_tasks', [
            'id' => $privateTaskId,
            'status' => 'completed',
        ]);
    }

    public function test_lead_manager_can_use_team_all_execution_desk_view(): void
    {
        $leadManagerRole = $this->createRole(Role::LEAD_MANAGER, 'Lead Manager');
        $execRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $leadManager = $this->createUser($leadManagerRole, 'lead-manager@example.test');
        $creator = $this->createUser($execRole, 'creator-lead-manager@example.test');
        $assignee = $this->createUser($execRole, 'assignee-lead-manager@example.test');

        \DB::table('execution_tasks')->insert([
            'title' => 'Visible lead bank task',
            'description' => 'Visible to elevated roles',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'priority' => 'medium',
            'status' => 'open',
            'context_label' => 'operations',
            'is_private' => false,
            'task_code' => 'EX-000004',
            'created_at' => now(),
            'updated_at' => now(),
            'last_status_changed_at' => now(),
        ]);

        $response = $this->actingAs($leadManager)->get('/execution-desk?tab=team_all');

        $response->assertOk()->assertSee('Visible lead bank task');
    }

    public function test_waiting_status_requires_reason_and_user(): void
    {
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $execRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $creator = $this->createUser($managerRole, 'creator@example.test');
        $assignee = $this->createUser($execRole, 'assignee@example.test');

        $taskId = \DB::table('execution_tasks')->insertGetId([
            'title' => 'Blocked task',
            'description' => 'Pending info',
            'assigned_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'priority' => 'medium',
            'status' => 'in_progress',
            'context_label' => 'operations',
            'is_private' => false,
            'task_code' => 'EX-000003',
            'created_at' => now(),
            'updated_at' => now(),
            'last_status_changed_at' => now(),
        ]);

        $response = $this->from('/execution-desk/tasks/' . $taskId)
            ->actingAs($assignee)
            ->put("/execution-desk/tasks/{$taskId}/status", [
                'status' => 'waiting',
            ]);

        $response->assertSessionHasErrors(['waiting_reason', 'waiting_on_user']);
        $this->assertDatabaseHas('execution_tasks', [
            'id' => $taskId,
            'status' => 'in_progress',
        ]);
    }

    public function test_admin_shared_saved_view_is_visible_to_other_users(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $managerRole = $this->createRole(Role::SALES_MANAGER, 'Sales Manager');
        $admin = $this->createUser($adminRole, 'admin2@example.test');
        $viewer = $this->createUser($managerRole, 'viewer2@example.test');

        $response = $this->actingAs($admin)->post('/execution-desk/saved-views', [
            'name' => 'Shared Overdue',
            'scope_tab' => 'team_all',
            'filters' => [
                'status' => 'open',
                'due_filter' => 'overdue',
            ],
            'is_shared' => 1,
        ]);

        $response->assertRedirect('/execution-desk?tab=team_all');
        $this->assertDatabaseHas('execution_saved_views', [
            'name' => 'Shared Overdue',
            'is_shared' => 1,
            'user_id' => $admin->id,
        ]);

        $viewerResponse = $this->actingAs($viewer)->get('/execution-desk');
        $viewerResponse->assertOk()->assertSee('Shared Overdue');
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken()->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('execution_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('assigned_by');
            $table->unsignedBigInteger('assigned_to');
            $table->string('priority');
            $table->string('status');
            $table->dateTime('due_at')->nullable();
            $table->unsignedInteger('estimated_time_minutes')->nullable();
            $table->unsignedInteger('actual_time_minutes')->nullable();
            $table->text('waiting_reason')->nullable();
            $table->unsignedBigInteger('waiting_on_user')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->string('context_label');
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('trigger_type')->default('manual');
            $table->boolean('is_private')->default(false);
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('execution_task_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('execution_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('attachment_kind')->default('file');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->text('link_url')->nullable();
            $table->string('link_title')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
        });

        Schema::create('execution_task_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('execution_saved_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('scope_tab')->default('my_queue');
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('telecaller_task_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('action_type')->nullable();
            $table->text('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
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
            $table->string('setting_type')->nullable();
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

        Schema::create('company_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_type')->nullable();
            $table->string('file_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function createRole(string $slug, string $name): Role
    {
        return Role::create([
            'slug' => $slug,
            'name' => $name,
        ]);
    }

    private function createUser(Role $role, string $email): User
    {
        return User::create([
            'name' => strtok($email, '@'),
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ])->load('role');
    }
}
