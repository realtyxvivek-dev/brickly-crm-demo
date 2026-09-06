<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectUrlImportFlowTest extends TestCase
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

    public function test_housing_url_can_be_extracted_and_reviewed(): void
    {
        Http::fake([
            'https://housing.com/*' => Http::response($this->housingHtml(), 200),
        ]);

        $admin = $this->seedAdmin();

        $response = $this->actingAs($admin)->post(route('projects.import-url.extract'), [
            'source_url' => 'https://housing.com/buy-jashn-elevate-phase-1-by-jashn-realty-in-sushant-golf-city-lucknow-pid-322166',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('summary.source_key', 'housing');
        $response->assertJsonPath('summary.progress_stage', 'Preparing review');
        $response->assertJsonPath('summary.progress_percent', 100);
        $response->assertJsonPath('summary.hard_required_valid', true);
        $this->assertStringContainsString('/projects/import/url/review/', $response->json('review_url'));

        $reviewResponse = $this->actingAs($admin)->get($response->json('review_url'));
        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Review Project URL Import');
        $reviewResponse->assertSee('Jashn Elevate Phase 1');
        $reviewResponse->assertSee('Jashn Realty');
    }

    public function test_property_pistol_partial_extraction_still_allows_draft_create(): void
    {
        Http::fake([
            'https://uat-new.propertypistol.in/*' => Http::response($this->propertyPistolHtml(), 200),
        ]);

        $admin = $this->seedAdmin();

        $extractResponse = $this->actingAs($admin)->post(route('projects.import-url.extract'), [
            'source_url' => 'https://uat-new.propertypistol.in/projects/jashn-elevate-sushant-golf-city-pid-16943',
        ]);

        $extractResponse->assertOk();
        $token = $extractResponse->json('token');

        $createResponse = $this->actingAs($admin)->post(route('projects.import-url.create'), [
            'review_token' => $token,
            'project' => [
                'builder_name' => 'Jashn Realty',
                'project_name' => 'Jashn Elevate',
                'public_title' => 'Jashn Elevate',
                'city' => 'Lucknow',
                'area' => 'Sushant Golf City',
                'project_status' => 'under_construction',
                'project_type' => 'residential',
                'residential_sub_type' => 'flat',
                'availability_type' => 'fresh',
                'short_overview' => 'Imported from PropertyPistol.',
                'rounding_rule' => 'nearest_1000',
            ],
            'variants' => [
                [
                    'unit_type' => '3 BHK',
                    'size_label' => '1650 Sq.ft.',
                    'builtup_area_sqft' => '1650',
                    'status' => 'available',
                    'visible_on_public_page' => 1,
                    'is_featured' => 1,
                    'is_price_on_request' => 0,
                ],
            ],
            'assets' => [
                [
                    'asset_type' => 'video',
                    'title' => 'Walkthrough',
                    'external_url' => 'https://youtube.com/watch?v=demo',
                    'value' => 'Detected from PropertyPistol',
                ],
            ],
        ]);

        $createResponse->assertOk();
        $createResponse->assertJsonPath('success', true);
        $this->assertStringContainsString('/public-page/wizard', $createResponse->json('wizard_url'));

        $projectId = $createResponse->json('project_id');
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'Jashn Elevate',
            'city' => 'Lucknow',
            'area' => 'Sushant Golf City',
        ]);
        $this->assertDatabaseHas('project_public_pages', [
            'project_id' => $projectId,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('project_assets', [
            'project_id' => $projectId,
            'asset_type' => 'video',
            'external_url' => 'https://youtube.com/watch?v=demo',
        ]);
    }

    public function test_unsupported_url_returns_clean_error(): void
    {
        $admin = $this->seedAdmin();

        $response = $this->actingAs($admin)->post(route('projects.import-url.extract'), [
            'source_url' => 'https://example.com/project/demo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('Unsupported source', $response->json('message'));
    }

    private function seedAdmin(): User
    {
        $role = Role::create([
            'name' => 'Admin',
            'slug' => Role::ADMIN,
            'is_active' => true,
        ]);

        return User::create([
            'name' => 'Admin User',
            'email' => 'project-url-import-admin@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function housingHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Jashn Elevate Phase 1 in Sushant Golf City, Lucknow</title>
    <meta property="og:title" content="Jashn Elevate Phase 1">
    <meta property="description" content="Jashn Elevate Phase 1 by Jashn Realty in Sushant Golf City, Lucknow. Starting from ₹1.09 Cr.">
</head>
<body>
    <h1>Buy Jashn Elevate Phase 1 by Jashn Realty in Sushant Golf City, Lucknow</h1>
    <div>Possession Date: February 2029</div>
    <div>Under Construction</div>
    <div>Rate: ₹7,600 per sq. ft.</div>
    <div>Size Range 1434 to 2211 sq. ft.</div>
    <div>2 BHK 1434 sq. ft. ₹1.09 Cr</div>
    <div>3 BHK 1650 sq. ft. ₹1.25 Cr</div>
    <div>RERA UPRERAPRJ654285</div>
    <a href="https://housing.com/brochure/jashn-elevate.pdf">Brochure</a>
</body>
</html>
HTML;
    }

    private function propertyPistolHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Jashn Elevate | PropertyPistol</title>
    <meta property="og:title" content="Jashn Elevate">
    <meta property="description" content="Jashn Elevate in Sushant Golf City, Lucknow by Jashn Realty.">
</head>
<body>
    <h1>Jashn Elevate</h1>
    <div>Developer: Jashn Realty</div>
    <div>Sushant Golf City, Lucknow</div>
    <div>Under Construction</div>
    <div>Possession: February 2029</div>
    <div>3 BHK 1650 sq. ft. ₹1.25 Cr</div>
    <div>RERA UPRERAPRJ654285</div>
    <a href="https://youtube.com/watch?v=demo">Video</a>
</body>
</html>
HTML;
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
            $table->string('password');
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

        Schema::create('builders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
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
            $table->decimal('land_area', 12, 2)->nullable();
            $table->string('land_area_unit')->nullable();
            $table->string('rera_no')->nullable();
            $table->date('possession_date')->nullable();
            $table->text('project_highlights')->nullable();
            $table->json('configuration_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('builder_contact_id')->nullable();
            $table->string('contact_role')->nullable();
            $table->timestamps();
        });

        Schema::create('builder_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('builder_id')->nullable();
            $table->string('name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->timestamps();
        });

        Schema::create('pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->decimal('bsp_per_sqft', 12, 2)->nullable();
            $table->string('price_rounding_rule')->default('none');
            $table->timestamps();
        });

        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tower_id')->nullable();
            $table->string('unit_type')->nullable();
            $table->decimal('area_sqft', 12, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->boolean('is_starting_from')->default(false);
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
            $table->decimal('base_rate_per_sqft', 12, 2)->nullable();
            $table->string('rounding_rule')->nullable();
            $table->boolean('towers_enabled')->default(false);
            $table->boolean('show_call')->default(false);
            $table->boolean('show_whatsapp')->default(false);
            $table->boolean('show_book_visit')->default(false);
            $table->boolean('show_request_callback')->default(false);
            $table->boolean('show_downloads')->default(true);
            $table->boolean('show_video')->default(false);
            $table->boolean('show_tour_360')->default(false);
            $table->string('call_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('book_visit_url')->nullable();
            $table->string('callback_url')->nullable();
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
            $table->decimal('base_rate_per_sqft', 12, 2)->nullable();
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
            $table->string('source_type')->default('external');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
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
            $table->string('token')->nullable();
            $table->timestamps();
        });

        Schema::create('project_page_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('event_name');
            $table->timestamps();
        });

        Schema::create('project_url_import_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('import_token')->nullable();
            $table->string('source_key')->nullable();
            $table->text('normalized_url')->nullable();
            $table->string('parser_version')->nullable();
            $table->string('progress_stage')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('extracted_field_count')->default(0);
            $table->json('failed_selectors')->nullable();
            $table->json('warnings')->nullable();
            $table->string('status', 32)->default('failed');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }
}
