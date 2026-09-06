<?php

namespace Tests\Feature;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectPublicPage;
use App\Models\ProjectShareLink;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectPublicSharePageTest extends TestCase
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

    public function test_active_share_link_renders_public_page(): void
    {
        [$shareLink] = $this->seedPublicProject([], [], [
            'other_charges' => [
                ['name' => 'Parking', 'value' => '300000', 'type' => 'fixed'],
                ['name' => 'IFMS', 'value' => '75', 'type' => 'per_sqft'],
                ['name' => 'GST', 'value' => '5', 'type' => 'percent'],
                ['name' => 'Club Membership', 'value' => null, 'type' => 'on_request'],
            ],
        ]);

        $response = $this->get(route('projects.public-share.show', $shareLink->token));

        $response->assertOk();
        $response->assertSee('Mulberry Heights');
        $response->assertSee('Download Details PDF');
        $response->assertSee('Call Advisor');
        $response->assertSee('Other Charges');
        $response->assertSee('Parking');
        $response->assertSee('₹3,00,000');
        $response->assertSee('₹75 / sq.ft.');
        $response->assertSee('5%');
        $response->assertSee('On Request');
    }

    public function test_revoked_share_link_returns_unavailable_page(): void
    {
        [$shareLink] = $this->seedPublicProject([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $response = $this->get(route('projects.public-share.show', $shareLink->token));

        $response->assertStatus(410);
        $response->assertSee('Link Unavailable');
    }

    public function test_share_event_endpoint_records_tracking_event(): void
    {
        [$shareLink, $project] = $this->seedPublicProject();

        $response = $this->postJson(route('projects.public-share.events', $shareLink->token), [
            'event_name' => 'page_view',
            'section' => 'hero',
            'session_id' => 'test-session',
            'meta' => ['source' => 'test'],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('project_page_events', [
            'project_id' => $project->id,
            'project_share_link_id' => $shareLink->id,
            'event_name' => 'page_view',
            'section' => 'hero',
            'session_id' => 'test-session',
        ]);
    }

    public function test_share_variant_pdf_route_generates_pdf_when_manual_file_is_missing(): void
    {
        [$shareLink] = $this->seedPublicProject([], [
            'details_pdf_path' => null,
        ]);

        $variant = ProjectSizeVariant::query()->firstOrFail();

        $response = $this->get(route('projects.public-share.variant-pdf', [$shareLink->token, $variant]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_share_page_renders_travel_time_widget_when_coordinates_exist(): void
    {
        [$shareLink] = $this->seedPublicProject();

        $response = $this->get(route('projects.public-share.show', $shareLink->token));

        $response->assertOk();
        $response->assertSee('How far is this project from you?');
        $response->assertSee('Hazratganj');
        $response->assertSee('Enter your location');
    }

    public function test_project_specific_popular_origins_override_city_defaults(): void
    {
        [$shareLink] = $this->seedPublicProject([], [], [
            'popular_origins' => [
                [
                    'label' => 'Custom Mall',
                    'latitude' => 26.9000,
                    'longitude' => 81.0000,
                    'category' => 'Mall',
                    'display_order' => 1,
                ],
            ],
        ]);

        $response = $this->get(route('projects.public-share.show', $shareLink->token));

        $response->assertSee('Custom Mall');
        $response->assertSee('Mall');
    }

    public function test_non_lucknow_project_falls_back_to_search_only_widget(): void
    {
        [$shareLink] = $this->seedPublicProject([], [], [], ['city' => 'Delhi']);

        $response = $this->get(route('projects.public-share.show', $shareLink->token));

        $response->assertOk();
        $response->assertSee('Enter your location');
        $response->assertDontSee('Chaudhary Charan Singh Airport');
    }

    public function test_share_travel_suggestions_endpoint_returns_results(): void
    {
        Config::set('travel_time.providers.ola.api_key', 'test-key');
        Http::fake([
            '*' => Http::response([
                'predictions' => [
                    [
                        'description' => 'Hazratganj, Lucknow, Uttar Pradesh, India',
                        'location' => ['lat' => 26.8506, 'lng' => 80.9462],
                    ],
                ],
            ]),
        ]);

        [$shareLink] = $this->seedPublicProject();

        $response = $this->getJson(route('projects.public-share.travel-time.suggestions', $shareLink->token) . '?q=haz');

        $response->assertOk()
            ->assertJsonPath('results.0.label', 'Hazratganj, Lucknow, Uttar Pradesh, India');
    }

    public function test_share_travel_route_endpoint_returns_drive_and_walk_results(): void
    {
        Config::set('travel_time.providers.ola.api_key', 'test-key');
        Http::fake([
            '*' => Http::sequence()
                ->push(['routes' => [['distance' => 8400, 'duration' => 1080]]])
                ->push(['routes' => [['distance' => 6200, 'duration' => 4440]]]),
        ]);

        [$shareLink] = $this->seedPublicProject();

        $response = $this->postJson(route('projects.public-share.travel-time.route', $shareLink->token), [
            'origin' => [
                'label' => 'Hazratganj',
                'latitude' => 26.8506,
                'longitude' => 80.9462,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('drive.display_time', '18 mins')
            ->assertJsonPath('walk.display_distance', '6.2 km');
    }

    private function seedPublicProject(array $shareOverrides = [], array $variantOverrides = [], array $pageOverrides = [], array $projectOverrides = []): array
    {
        $builder = Builder::create([
            'name' => 'Rishita Developers',
            'status' => 'active',
        ]);

        $project = Project::create(array_merge([
            'builder_id' => $builder->id,
            'name' => 'Mulberry Heights',
            'project_type' => 'residential',
            'residential_sub_type' => 'flat',
            'project_status' => 'under_construction',
            'availability_type' => 'fresh',
            'city' => 'Lucknow',
            'area' => 'Sushant Golf City',
            'is_active' => true,
        ], $projectOverrides));

        ProjectPublicPage::create(array_merge([
            'project_id' => $project->id,
            'hero_title' => 'Mulberry Heights',
            'hero_subtitle' => 'Premium public share page',
            'hero_cover_path' => null,
            'latitude' => 26.7900,
            'longitude' => 81.0020,
            'map_zoom' => 13,
            'base_rate_per_sqft' => 11500,
            'rounding_rule' => 'nearest_100000',
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
            'preview_token' => 'preview-token',
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
            'carpet_area_sqft' => 1107,
            'base_rate_per_sqft' => 11500,
            'rounding_rule' => 'nearest_100000',
            'calculated_price' => 16675000,
            'final_price' => 16700000,
            'status' => 'available',
            'visible_on_public_page' => true,
            'details_pdf_path' => 'demo.pdf',
            'display_order' => 0,
        ], $variantOverrides));

        $shareLink = ProjectShareLink::create(array_merge([
            'project_id' => $project->id,
            'token' => 'share-token',
            'status' => 'active',
            'view_count' => 0,
        ], $shareOverrides));

        return [$shareLink, $project];
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
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
            $table->string('tracking_key');
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
