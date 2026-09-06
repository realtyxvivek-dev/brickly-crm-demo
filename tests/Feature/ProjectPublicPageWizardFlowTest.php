<?php

namespace Tests\Feature;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectPublicPage;
use App\Models\ProjectShareLink;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use App\Models\Role;
use App\Models\User;
use App\Services\ProjectPublicPageService;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectPublicPageWizardFlowTest extends TestCase
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

    public function test_admin_can_create_and_edit_public_page_draft(): void
    {
        [$admin, $builder] = $this->seedAdminAndBuilder();
        $service = app(ProjectPublicPageService::class);

        $createRequest = Request::create(route('projects.public-pages.save'), 'POST', [
            'builder_id' => $builder->id,
            'name' => 'Wizard Flow Project',
            'city' => 'Lucknow',
            'area' => 'Gomti Nagar',
            'hero_title' => 'Wizard Flow Project',
            'base_rate_per_sqft' => '11500',
            'rounding_rule' => 'nearest_1000',
            'call_phone' => '9876543210',
            'whatsapp_number' => '919876543210',
            'book_visit_url' => 'https://example.com/book',
            'other_charges' => [
                ['name' => 'Parking', 'value' => '300000', 'type' => 'fixed'],
                ['name' => 'IFMS', 'value' => '75', 'type' => 'per_sqft'],
                ['name' => 'GST', 'value' => '', 'type' => 'on_request'],
                ['name' => '', 'value' => '', 'type' => 'fixed'],
            ],
            'unit_types' => [
                [
                    'name' => '3 BHK',
                    'is_primary' => '1',
                    'variants' => [
                        [
                            'size_label' => '1450 Sq.ft.',
                            'builtup_area_sqft' => '1450',
                            'carpet_area_sqft' => '1100',
                            'visible_on_public_page' => '1',
                            'status' => 'available',
                            'details_pdf_path' => null,
                            'is_price_on_request' => '1',
                        ],
                    ],
                ],
            ],
        ]);
        $createRequest->setUserResolver(fn () => $admin);

        $project = $service->saveDraft($createRequest, null, $admin);

        $this->assertDatabaseHas('project_public_pages', [
            'project_id' => $project->id,
            'hero_title' => 'Wizard Flow Project',
        ]);
        $this->assertSame([
            ['name' => 'Parking', 'value' => '300000', 'type' => 'fixed'],
            ['name' => 'IFMS', 'value' => '75', 'type' => 'per_sqft'],
            ['name' => 'GST', 'value' => null, 'type' => 'on_request'],
        ], $project->publicPage->other_charges);
        $this->assertDatabaseHas('project_share_links', [
            'project_id' => $project->id,
            'status' => 'active',
        ]);

        $editRequest = Request::create(route('projects.public-pages.save'), 'POST', [
            'project_id' => $project->id,
            'builder_id' => $builder->id,
            'name' => 'Wizard Flow Project',
            'city' => 'Lucknow',
            'area' => 'Gomti Nagar',
            'hero_title' => 'Wizard Flow Project Updated',
            'base_rate_per_sqft' => '11500',
            'status' => 'hidden',
            'share_link_status' => 'revoked',
            'call_phone' => '9876543210',
            'whatsapp_number' => '919876543210',
            'book_visit_url' => 'https://example.com/book',
            'other_charges' => [
                ['name' => 'Parking', 'value' => '350000', 'type' => 'fixed'],
                ['name' => 'GST', 'value' => '5', 'type' => 'percent'],
            ],
            'unit_types' => [
                [
                    'id' => $project->publicUnitTypes()->first()->id,
                    'name' => '3 BHK',
                    'variants' => [
                        [
                            'id' => $project->sizeVariants()->first()->id,
                            'size_label' => '1450 Sq.ft.',
                            'builtup_area_sqft' => '1450',
                            'carpet_area_sqft' => '1100',
                            'visible_on_public_page' => '1',
                            'status' => 'available',
                            'is_price_on_request' => '1',
                        ],
                    ],
                ],
            ],
        ]);
        $editRequest->setUserResolver(fn () => $admin);

        $service->saveDraft($editRequest, $project, $admin);

        $this->assertDatabaseHas('project_public_pages', [
            'project_id' => $project->id,
            'hero_title' => 'Wizard Flow Project Updated',
            'status' => 'hidden',
        ]);
        $this->assertSame([
            ['name' => 'Parking', 'value' => '350000', 'type' => 'fixed'],
            ['name' => 'GST', 'value' => '5', 'type' => 'percent'],
        ], $project->fresh()->publicPage->other_charges);
        $this->assertDatabaseHas('project_share_links', [
            'project_id' => $project->id,
            'status' => 'revoked',
        ]);
    }

    public function test_preview_and_share_render_same_core_content(): void
    {
        [$admin] = $this->seedAdminAndBuilder();
        [$project, $shareLink] = $this->seedPublishedProject();

        $previewResponse = $this->actingAs($admin)->get(route('projects.public-pages.preview', $project));
        $shareResponse = $this->get(route('projects.public-share.show', $shareLink->token));

        $previewResponse->assertOk();
        $shareResponse->assertOk();

        foreach ([
            'Preview Share Project',
            'Download Details PDF',
            'Call Advisor',
            'Visible Plan',
        ] as $expectedText) {
            $previewResponse->assertSee($expectedText);
            $shareResponse->assertSee($expectedText);
        }

        $previewResponse->assertDontSee('Hidden Plan');
        $shareResponse->assertDontSee('Hidden Plan');
    }

    public function test_external_asset_redirects_from_preview_and_share_routes(): void
    {
        [$admin] = $this->seedAdminAndBuilder();
        [$project, $shareLink] = $this->seedPublishedProject();

        $asset = ProjectAsset::create([
            'project_id' => $project->id,
            'asset_type' => 'video',
            'title' => 'Walkthrough',
            'source_type' => 'external',
            'external_url' => 'https://example.com/video',
            'tracking_key' => 'walkthrough-video',
            'display_order' => 0,
        ]);

        $previewResponse = $this->actingAs($admin)->get(route('projects.public-pages.asset', [$project, $asset]));
        $shareResponse = $this->get(route('projects.public-share.asset', [$shareLink->token, $asset]));

        $previewResponse->assertRedirect('https://example.com/video');
        $shareResponse->assertRedirect('https://example.com/video');
        $this->assertDatabaseHas('project_page_events', [
            'project_id' => $project->id,
            'event_name' => 'video_start',
        ]);
    }

    private function seedAdminAndBuilder(): array
    {
        static $userCounter = 1;

        $role = Role::firstOrCreate([
            'slug' => Role::ADMIN,
        ], [
            'name' => 'Admin',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'wizard-admin-' . $userCounter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $builder = Builder::firstOrCreate(
            ['name' => 'Wizard Builder'],
            ['status' => 'active']
        );

        return [$admin, $builder];
    }

    private function seedPublishedProject(): array
    {
        [, $builder] = $this->seedAdminAndBuilder();

        $project = Project::create([
            'builder_id' => $builder->id,
            'name' => 'Preview Share Project',
            'project_type' => 'residential',
            'residential_sub_type' => 'flat',
            'project_status' => 'under_construction',
            'availability_type' => 'fresh',
            'city' => 'Lucknow',
            'area' => 'Sushant Golf City',
            'is_active' => true,
        ]);

        ProjectPublicPage::create([
            'project_id' => $project->id,
            'hero_title' => 'Preview Share Project',
            'hero_subtitle' => 'Minimal premium public page',
            'hero_cover_path' => 'demo.jpg',
            'show_call' => true,
            'show_whatsapp' => true,
            'show_book_visit' => true,
            'show_request_callback' => false,
            'show_downloads' => true,
            'show_video' => false,
            'show_tour_360' => false,
            'call_phone' => '9876543210',
            'whatsapp_number' => '919876543210',
            'book_visit_url' => 'https://example.com/book',
            'status' => 'published',
            'preview_token' => 'preview-route-token',
        ]);

        $unitType = ProjectUnitType::create([
            'project_id' => $project->id,
            'name' => '3 BHK',
            'display_order' => 0,
            'is_primary' => true,
        ]);

        ProjectSizeVariant::create([
            'project_id' => $project->id,
            'project_unit_type_id' => $unitType->id,
            'size_label' => 'Visible Plan',
            'builtup_area_sqft' => 1450,
            'carpet_area_sqft' => 1100,
            'base_rate_per_sqft' => 11500,
            'rounding_rule' => 'nearest_1000',
            'calculated_price' => 16675000,
            'final_price' => 16675000,
            'status' => 'available',
            'visible_on_public_page' => true,
            'details_pdf_path' => 'details.pdf',
        ]);

        ProjectSizeVariant::create([
            'project_id' => $project->id,
            'project_unit_type_id' => $unitType->id,
            'size_label' => 'Hidden Plan',
            'builtup_area_sqft' => 1600,
            'carpet_area_sqft' => 1200,
            'base_rate_per_sqft' => 12000,
            'rounding_rule' => 'nearest_1000',
            'calculated_price' => 19200000,
            'final_price' => 19200000,
            'status' => 'hidden',
            'visible_on_public_page' => false,
            'details_pdf_path' => 'hidden.pdf',
        ]);

        $shareLink = ProjectShareLink::create([
            'project_id' => $project->id,
            'token' => 'preview-share-token',
            'status' => 'active',
        ]);

        return [$project, $shareLink];
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->text('permissions')->nullable();
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

        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('tower_id')->nullable();
            $table->string('unit_type')->nullable();
            $table->decimal('area_sqft', 12, 2)->nullable();
            $table->decimal('calculated_price', 15, 2)->nullable();
            $table->string('display_label')->nullable();
            $table->boolean('is_starting_from')->default(false);
            $table->softDeletes();
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
            $table->json('other_charges')->nullable();
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
