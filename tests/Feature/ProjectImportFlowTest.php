<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProjectImportFlowTest extends TestCase
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

    public function test_admin_can_download_project_import_template(): void
    {
        $admin = $this->seedAdmin();

        $response = $this->actingAs($admin)->get(route('projects.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('project-import-template.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_preview_shows_errors_for_empty_template(): void
    {
        $admin = $this->seedAdmin();
        $file = $this->buildWorkbook([], [], []);

        $response = $this->actingAs($admin)->post(route('projects.import.preview'), [
            'import_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertContains('Project Info sheet must contain exactly one filled project row.', $response->json('preview.errors'));
        $this->assertContains('Unit Variants sheet must contain at least one valid variant row.', $response->json('preview.errors'));
        $response->assertJsonFragment([
            'message' => 'Import preview generated successfully.',
        ]);
    }

    public function test_preview_and_create_draft_project_from_excel(): void
    {
        $admin = $this->seedAdmin();

        $file = $this->buildWorkbook(
            [[
                'builder_name' => 'Excel Builder',
                'project_name' => 'Excel Import Project',
                'public_title' => 'Excel Import Project',
                'subtitle' => 'Draft from Excel',
                'short_overview' => 'Imported using sample workbook.',
                'project_type' => 'residential',
                'residential_sub_type' => 'flat',
                'project_status' => 'under_construction',
                'availability_type' => 'fresh',
                'city' => 'Lucknow',
                'area' => 'Sushant Golf City',
                'address' => 'Sector A',
                'rera_no' => 'RERA-123',
                'possession_date' => '2027-12-01',
                'location_summary' => 'Near clubhouse',
                'base_rate_per_sqft' => '11500',
                'rounding_rule' => 'nearest_1000',
                'call_phone' => '9876543210',
                'whatsapp_number' => '919876543210',
                'show_call' => 'yes',
                'show_whatsapp' => 'yes',
                'show_book_visit' => 'no',
                'show_request_callback' => 'no',
            ]],
            [
                [
                    'unit_type' => '3 BHK',
                    'size_label' => '1450 Sq.ft.',
                    'builtup_area_sqft' => '1450',
                    'carpet_area_sqft' => '1100',
                    'base_rate_per_sqft' => '',
                    'manual_price_override' => '',
                    'status' => 'available',
                    'visible_on_public_page' => 'yes',
                    'is_featured' => 'yes',
                    'is_price_on_request' => 'no',
                ],
                [
                    'unit_type' => '4 BHK',
                    'size_label' => '1890 Sq.ft.',
                    'builtup_area_sqft' => '1890',
                    'carpet_area_sqft' => '1500',
                    'base_rate_per_sqft' => '13000',
                    'manual_price_override' => '25500000',
                    'status' => 'hold',
                    'visible_on_public_page' => 'true',
                    'is_featured' => 'false',
                    'is_price_on_request' => 'false',
                ],
            ],
            [
                [
                    'row_type' => 'landmark',
                    'title' => 'Phoenix Mall',
                    'value' => 'shopping',
                    'distance' => '4 km',
                    'url' => '',
                ],
                [
                    'row_type' => 'video',
                    'title' => 'Walkthrough',
                    'value' => '',
                    'distance' => '',
                    'url' => 'https://example.com/video',
                ],
            ]
        );

        $previewResponse = $this->actingAs($admin)->post(route('projects.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk();
        $previewResponse->assertJsonPath('preview.builder_action', 'auto_create_builder');
        $previewResponse->assertJsonPath('preview.stats.unit_types', 2);
        $previewResponse->assertJsonPath('preview.stats.variants', 2);
        $previewResponse->assertJsonPath('preview.stats.landmarks', 1);
        $previewResponse->assertJsonPath('preview.stats.assets', 1);
        $previewResponse->assertJsonCount(0, 'preview.errors');

        $token = $previewResponse->json('token');

        $createResponse = $this->actingAs($admin)->post(route('projects.import.create'), [
            'preview_token' => $token,
        ]);

        $createResponse->assertOk();
        $createResponse->assertJsonPath('success', true);

        $projectId = $createResponse->json('project_id');

        $this->assertDatabaseHas('builders', [
            'name' => 'Excel Builder',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'Excel Import Project',
            'city' => 'Lucknow',
            'area' => 'Sushant Golf City',
        ]);

        $this->assertDatabaseHas('project_public_pages', [
            'project_id' => $projectId,
            'status' => 'draft',
            'hero_title' => 'Excel Import Project',
        ]);

        $this->assertDatabaseHas('project_assets', [
            'project_id' => $projectId,
            'asset_type' => 'video',
            'external_url' => 'https://example.com/video',
        ]);

        $this->assertDatabaseHas('project_landmarks', [
            'project_id' => $projectId,
            'label' => 'Phoenix Mall',
        ]);

        $wizardUrl = $createResponse->json('wizard_url');
        $this->assertNotNull($wizardUrl);
        $this->assertStringContainsString('/public-page/wizard', $wizardUrl);
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
            'email' => 'project-import-admin@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function buildWorkbook(array $projectRows, array $variantRows, array $mediaRows): UploadedFile
    {
        $headers = [
            'Project Info' => [
                'builder_name', 'project_name', 'public_title', 'subtitle', 'short_overview', 'project_type',
                'residential_sub_type', 'project_status', 'availability_type', 'city', 'area', 'address',
                'rera_no', 'possession_date', 'location_summary', 'base_rate_per_sqft', 'rounding_rule',
                'call_phone', 'whatsapp_number', 'show_call', 'show_whatsapp', 'show_book_visit', 'show_request_callback',
            ],
            'Unit Variants' => [
                'unit_type', 'size_label', 'builtup_area_sqft', 'carpet_area_sqft', 'base_rate_per_sqft',
                'manual_price_override', 'status', 'visible_on_public_page', 'is_featured', 'is_price_on_request',
            ],
            'Landmarks & Media' => [
                'row_type', 'title', 'value', 'distance', 'url',
            ],
        ];

        $rowsBySheet = [
            'Project Info' => $projectRows,
            'Unit Variants' => $variantRows,
            'Landmarks & Media' => $mediaRows,
        ];

        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;
        foreach ($headers as $sheetName => $sheetHeaders) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            foreach ($sheetHeaders as $column => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($column + 1) . '1', $header);
            }

            $rowNumber = 2;
            foreach ($rowsBySheet[$sheetName] as $row) {
                foreach ($sheetHeaders as $column => $header) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($column + 1) . $rowNumber, $row[$header] ?? '');
                }
                $rowNumber++;
            }

            $sheetIndex++;
        }

        $path = storage_path('app/testing/project-import-' . uniqid() . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'project-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
            $table->decimal('calculated_price', 14, 2)->nullable();
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
            $table->boolean('show_call')->default(true);
            $table->boolean('show_whatsapp')->default(true);
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
            $table->decimal('calculated_price', 14, 2)->nullable();
            $table->decimal('manual_price_override', 14, 2)->nullable();
            $table->decimal('final_price', 14, 2)->nullable();
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
            $table->string('source_type')->nullable();
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
    }
}
