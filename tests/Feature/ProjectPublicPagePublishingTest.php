<?php

namespace Tests\Feature;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectPublicPage;
use App\Models\ProjectShareLink;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use App\Models\Role;
use App\Models\User;
use App\Services\ProjectPublicPageService;
use App\Services\ProjectService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectPublicPagePublishingTest extends TestCase
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

    public function test_publish_fails_when_required_public_page_data_is_missing(): void
    {
        [$project, $user] = $this->seedPublishProject([
            'hero_cover_path' => null,
            'call_phone' => null,
        ], [
            'details_pdf_path' => null,
        ]);

        $service = app(ProjectPublicPageService::class);

        try {
            $service->publish($project, $user);
            $this->fail('Publish should have failed.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('hero_cover_image', $errors);
            $this->assertArrayHasKey('call_phone', $errors);
            $this->assertArrayNotHasKey('variant_1_pdf', $errors);
        }
    }

    public function test_publish_succeeds_when_required_data_exists(): void
    {
        [$project, $user] = $this->seedPublishProject();

        $service = app(ProjectPublicPageService::class);
        $page = $service->publish($project, $user);

        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertSame($user->id, $page->published_by);
    }

    public function test_publish_succeeds_without_uploaded_pdf_when_auto_pdf_can_be_generated(): void
    {
        [$project, $user] = $this->seedPublishProject([], [
            'details_pdf_path' => null,
        ]);

        $service = app(ProjectPublicPageService::class);
        $page = $service->publish($project, $user);

        $this->assertSame('published', $page->status);
    }

    public function test_publish_fails_without_uploaded_pdf_when_variant_cannot_generate_auto_pdf(): void
    {
        [$project, $user] = $this->seedPublishProject([], [
            'details_pdf_path' => null,
            'builtup_area_sqft' => null,
            'carpet_area_sqft' => null,
        ]);

        $service = app(ProjectPublicPageService::class);

        try {
            $service->publish($project, $user);
            $this->fail('Publish should have failed.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->assertArrayHasKey('variant_1_pdf', $errors);
        }
    }

    public function test_generated_pdf_is_returned_when_manual_pdf_is_missing(): void
    {
        [$project] = $this->seedPublishProject([], [
            'details_pdf_path' => null,
        ]);

        $variant = ProjectSizeVariant::query()->firstOrFail();
        $service = app(ProjectPublicPageService::class);
        $path = $service->downloadVariantPdf($variant);

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertStringStartsWith('%PDF-', file_get_contents($path, false, null, 0, 5));
    }

    public function test_uploaded_pdf_still_wins_over_generated_pdf(): void
    {
        [$project] = $this->seedPublishProject();

        $variant = ProjectSizeVariant::query()->firstOrFail();
        $manualPath = storage_path('app/public/details.pdf');
        File::ensureDirectoryExists(dirname($manualPath));
        file_put_contents($manualPath, 'manual-pdf');

        $service = app(ProjectPublicPageService::class);
        $resolvedPath = $service->downloadVariantPdf($variant);

        $this->assertSame($manualPath, $resolvedPath);
    }

    public function test_generated_pdf_cache_invalidates_when_variant_data_changes(): void
    {
        [$project] = $this->seedPublishProject([], [
            'details_pdf_path' => null,
        ]);

        $variant = ProjectSizeVariant::query()->firstOrFail();
        $service = app(ProjectPublicPageService::class);

        $firstPath = $service->downloadVariantPdf($variant);
        $variant->update(['final_price' => 15500000]);
        $secondPath = $service->downloadVariantPdf($variant->fresh());

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFileExists($secondPath);
    }

    public function test_share_link_expires_when_max_visits_threshold_is_hit(): void
    {
        [$project] = $this->seedPublishProject();

        $shareLink = ProjectShareLink::create([
            'project_id' => $project->id,
            'token' => 'visit-capped-token',
            'status' => 'active',
            'max_visits' => 1,
            'view_count' => 1,
        ]);

        $service = app(ProjectPublicPageService::class);
        $resolved = $service->resolveShareLink($shareLink->token);

        $this->assertSame('expired', $resolved?->status);
    }

    private function seedPublishProject(array $pageOverrides = [], array $variantOverrides = []): array
    {
        $role = Role::create([
            'name' => 'Admin',
            'slug' => Role::ADMIN,
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'publish-test@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $builder = Builder::create([
            'name' => 'Test Builder',
            'status' => 'active',
        ]);

        $project = Project::create([
            'builder_id' => $builder->id,
            'name' => 'Publish Ready Project',
            'project_type' => 'residential',
            'residential_sub_type' => 'flat',
            'project_status' => 'under_construction',
            'availability_type' => 'fresh',
            'city' => 'Lucknow',
            'area' => 'Gomti Nagar',
            'is_active' => true,
        ]);

        ProjectPublicPage::create(array_merge([
            'project_id' => $project->id,
            'hero_title' => 'Publish Ready Project',
            'hero_cover_path' => 'demo-hero.jpg',
            'base_rate_per_sqft' => 10000,
            'rounding_rule' => 'nearest_1000',
            'show_call' => true,
            'show_whatsapp' => true,
            'show_book_visit' => true,
            'show_request_callback' => false,
            'show_downloads' => true,
            'show_video' => false,
            'show_tour_360' => false,
            'call_phone' => '9876543210',
            'whatsapp_number' => '919876543210',
            'book_visit_url' => 'https://example.com/book-visit',
            'status' => 'draft',
            'preview_token' => 'preview-ready',
        ], $pageOverrides));

        $unitType = ProjectUnitType::create([
            'project_id' => $project->id,
            'name' => '3 BHK',
            'display_order' => 0,
            'is_primary' => true,
        ]);

        ProjectSizeVariant::create(array_merge([
            'project_id' => $project->id,
            'project_unit_type_id' => $unitType->id,
            'size_label' => '1450 Sq.ft.',
            'builtup_area_sqft' => 1450,
            'carpet_area_sqft' => 1100,
            'base_rate_per_sqft' => 10000,
            'rounding_rule' => 'nearest_1000',
            'calculated_price' => 14500000,
            'final_price' => 14500000,
            'status' => 'available',
            'visible_on_public_page' => true,
            'details_pdf_path' => 'details.pdf',
            'display_order' => 0,
        ], $variantOverrides));

        ProjectShareLink::create([
            'project_id' => $project->id,
            'token' => 'active-token',
            'status' => 'active',
        ]);

        return [$project, $user];
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

        Schema::create('builders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('builder_id')->nullable();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->text('short_overview')->nullable();
            $table->string('project_type')->nullable();
            $table->string('residential_sub_type')->nullable();
            $table->string('project_status')->nullable();
            $table->string('availability_type')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->decimal('land_area', 10, 2)->nullable();
            $table->string('land_area_unit')->nullable();
            $table->string('rera_no')->nullable();
            $table->date('possession_date')->nullable();
            $table->text('project_highlights')->nullable();
            $table->json('configuration_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->decimal('bsp_per_sqft', 15, 2)->nullable();
            $table->string('price_rounding_rule')->nullable();
            $table->timestamps();
        });

        Schema::create('project_public_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->text('short_intro')->nullable();
            $table->json('featured_badges')->nullable();
            $table->string('hero_cover_path')->nullable();
            $table->string('builder_logo_path')->nullable();
            $table->text('map_embed')->nullable();
            $table->text('location_summary')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('map_zoom')->nullable();
            $table->json('popular_origins')->nullable();
            $table->decimal('base_rate_per_sqft', 15, 2)->nullable();
            $table->string('rounding_rule')->nullable();
            $table->boolean('towers_enabled')->default(false);
            $table->boolean('show_call')->default(true);
            $table->boolean('show_whatsapp')->default(true);
            $table->boolean('show_book_visit')->default(true);
            $table->boolean('show_request_callback')->default(false);
            $table->boolean('show_downloads')->default(true);
            $table->boolean('show_video')->default(true);
            $table->boolean('show_tour_360')->default(true);
            $table->string('call_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('book_visit_url')->nullable();
            $table->text('callback_url')->nullable();
            $table->string('status')->default('draft');
            $table->string('preview_token')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->unsignedBigInteger('last_saved_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_unit_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('project_size_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('project_unit_type_id');
            $table->string('size_label');
            $table->decimal('carpet_area_sqft', 12, 2)->nullable();
            $table->decimal('builtup_area_sqft', 12, 2)->nullable();
            $table->decimal('base_rate_per_sqft', 15, 2)->nullable();
            $table->string('rounding_rule')->nullable();
            $table->decimal('calculated_price', 15, 2)->nullable();
            $table->decimal('manual_price_override', 15, 2)->nullable();
            $table->decimal('final_price', 15, 2)->nullable();
            $table->boolean('is_price_on_request')->default(false);
            $table->string('status')->default('available');
            $table->boolean('visible_on_public_page')->default(true);
            $table->string('floor_plan_image_path')->nullable();
            $table->string('details_pdf_path')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('project_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('asset_type');
            $table->string('title')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('source_type')->default('uploaded');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('preview_image_path')->nullable();
            $table->string('tracking_key')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('project_landmarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('label');
            $table->string('type')->nullable();
            $table->string('distance_text')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_share_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('advisor_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('token')->unique();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_visits')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });

        Schema::create('project_page_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('project_share_link_id')->nullable();
            $table->string('event_name');
            $table->string('section')->nullable();
            $table->string('session_id')->nullable();
            $table->text('meta')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }
}
