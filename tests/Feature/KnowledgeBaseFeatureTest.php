<?php

namespace Tests\Feature;

use App\Models\KnowledgeBaseAssignment;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Models\KnowledgeBasePath;
use App\Models\KnowledgeBasePathAssignment;
use App\Models\KnowledgeBaseProgress;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KnowledgeBaseFeatureTest extends TestCase
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
        $this->withoutMiddleware([\App\Http\Middleware\CheckInstallation::class]);
    }

    public function test_library_shows_published_items_and_assigned_tab_marks_required(): void
    {
        [$user] = $this->seedBaseData();

        $published = KnowledgeBaseItem::create([
            'category_id' => KnowledgeBaseCategory::first()->id,
            'title' => 'Lead Calling SOP',
            'slug' => 'lead-calling-sop',
            'short_summary' => 'How to call and qualify fresh leads.',
            'article_content' => 'This is the article body.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        KnowledgeBaseItem::create([
            'category_id' => KnowledgeBaseCategory::first()->id,
            'title' => 'Draft Hidden Topic',
            'slug' => 'draft-hidden-topic',
            'short_summary' => 'Should not appear.',
            'article_content' => 'Hidden content.',
            'status' => 'draft',
        ]);

        KnowledgeBaseAssignment::create([
            'knowledge_base_item_id' => $published->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        $assignedResponse = $this->actingAs($user)->get(route('knowledge-base.index'));
        $assignedResponse->assertOk();
        $assignedResponse->assertSee('Lead Calling SOP');
        $assignedResponse->assertSee('Required');
        $assignedResponse->assertDontSee('Draft Hidden Topic');

        $libraryResponse = $this->actingAs($user)->get(route('knowledge-base.index', ['tab' => 'library']));
        $libraryResponse->assertOk();
        $libraryResponse->assertSee('Lead Calling SOP');
        $libraryResponse->assertDontSee('Draft Hidden Topic');
    }

    public function test_article_progress_marks_topic_completed_after_threshold(): void
    {
        [$user] = $this->seedBaseData();

        $item = KnowledgeBaseItem::create([
            'category_id' => KnowledgeBaseCategory::first()->id,
            'title' => 'CRM Dashboard Guide',
            'slug' => 'crm-dashboard-guide',
            'article_content' => 'Long article body',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('knowledge-base.progress', $item->id), [
            'event' => 'article_tick',
            'seconds' => 15,
        ]);

        $response->assertOk()->assertJson(['status' => 'in_progress']);

        $response = $this->actingAs($user)->postJson(route('knowledge-base.progress', $item->id), [
            'event' => 'article_scroll',
            'percent' => 25,
        ]);

        $response->assertOk()->assertJson(['status' => 'completed']);

        $this->assertDatabaseHas('knowledge_base_progress', [
            'knowledge_base_item_id' => $item->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);
    }

    public function test_selected_role_visibility_hides_topic_from_other_users(): void
    {
        [$user, $crmUser] = $this->seedBaseData();

        KnowledgeBaseItem::create([
            'category_id' => KnowledgeBaseCategory::first()->id,
            'title' => 'CRM Only Playbook',
            'slug' => 'crm-only-playbook',
            'status' => 'published',
            'visibility_type' => 'selected_roles',
            'visible_role_slugs' => [Role::CRM],
            'published_at' => now(),
            'content_updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('knowledge-base.index', ['tab' => 'library']))
            ->assertDontSee('CRM Only Playbook');

        $this->actingAs($crmUser)
            ->get(route('knowledge-base.index', ['tab' => 'library']))
            ->assertSee('CRM Only Playbook');
    }

    public function test_due_soon_assignment_badge_is_shown(): void
    {
        [$user] = $this->seedBaseData();

        $item = KnowledgeBaseItem::create([
            'category_id' => KnowledgeBaseCategory::first()->id,
            'title' => 'Pending SOP',
            'slug' => 'pending-sop',
            'article_content' => 'Pending',
            'status' => 'published',
            'published_at' => now(),
            'content_updated_at' => now(),
        ]);

        KnowledgeBaseAssignment::create([
            'knowledge_base_item_id' => $item->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'is_required' => true,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Due Soon');
    }

    public function test_learning_path_and_reports_pages_are_accessible_for_allowed_roles(): void
    {
        [$user, $crmUser] = $this->seedBaseData();

        $path = KnowledgeBasePath::create([
            'title' => 'Sales Onboarding',
            'slug' => 'sales-onboarding',
            'status' => 'published',
            'published_at' => now(),
        ]);

        KnowledgeBasePathAssignment::create([
            'knowledge_base_path_id' => $path->id,
            'user_id' => $user->id,
            'assigned_by' => $crmUser->id,
            'assigned_at' => now(),
            'is_required' => true,
        ]);

        $this->actingAs($crmUser)
            ->get(route('admin.knowledge-base.paths.index'))
            ->assertOk()
            ->assertSee('Sales Onboarding');

        $this->actingAs($crmUser)
            ->get(route('admin.knowledge-base.reports.index'))
            ->assertOk();
    }

    public function test_non_crm_user_cannot_access_management_screen(): void
    {
        [$user, $crmUser] = $this->seedBaseData();

        $this->actingAs($user)
            ->get(route('admin.knowledge-base.index'))
            ->assertForbidden();

        $this->actingAs($crmUser)
            ->get(route('admin.knowledge-base.index'))
            ->assertOk();
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
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

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_base_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_summary')->nullable();
            $table->longText('article_content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->text('change_summary')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility_type')->default('all_users');
            $table->json('visible_role_slugs')->nullable();
            $table->json('visible_user_ids')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_base_item_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_required')->default(true);
            $table->date('due_date')->nullable();
            $table->boolean('completed_late')->default(false);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_base_item_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('not_started');
            $table->unsignedInteger('article_seconds_viewed')->default(0);
            $table->unsignedTinyInteger('article_scroll_percent')->default(0);
            $table->unsignedTinyInteger('video_progress_percent')->default(0);
            $table->unsignedInteger('video_last_position_seconds')->default(0);
            $table->timestamp('pdf_opened_at')->nullable();
            $table->timestamp('pdf_interacted_at')->nullable();
            $table->unsignedInteger('pdf_seconds_viewed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_paths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_summary')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_base_path_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_base_path_id');
            $table->unsignedBigInteger('knowledge_base_item_id');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('knowledge_base_path_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_base_path_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_required')->default(true);
            $table->date('due_date')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();
        });
    }

    private function seedBaseData(): array
    {
        $salesRole = Role::create([
            'name' => 'Sales Executive',
            'slug' => Role::SALES_EXECUTIVE,
            'is_active' => true,
        ]);

        $crmRole = Role::create([
            'name' => 'CRM',
            'slug' => Role::CRM,
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Assigned User',
            'email' => 'assigned-user@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $salesRole->id,
            'is_active' => true,
        ]);

        $crmUser = User::create([
            'name' => 'CRM User',
            'email' => 'crm-user@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $crmRole->id,
            'is_active' => true,
        ]);

        KnowledgeBaseCategory::create([
            'name' => 'CRM Usage',
            'slug' => 'crm-usage',
            'display_order' => 1,
            'is_active' => true,
        ]);

        return [$user, $crmUser];
    }
}
