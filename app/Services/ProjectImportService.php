<?php

namespace App\Services;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectLandmark;
use App\Models\ProjectPublicPage;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ProjectImportService
{
    private const TEMP_DISK = 'local';
    private const IMPORT_DIR = 'project-imports';
    private const STATE_DIR = 'project-import-states';

    private const PROJECT_REQUIRED_FIELDS = [
        'builder_name',
        'project_name',
        'project_type',
        'project_status',
        'city',
        'area',
    ];

    private const PROJECT_HEADERS = [
        'builder_name',
        'project_name',
        'public_title',
        'subtitle',
        'short_overview',
        'project_type',
        'residential_sub_type',
        'project_status',
        'availability_type',
        'city',
        'area',
        'address',
        'rera_no',
        'possession_date',
        'location_summary',
        'base_rate_per_sqft',
        'rounding_rule',
        'call_phone',
        'whatsapp_number',
        'show_call',
        'show_whatsapp',
        'show_book_visit',
        'show_request_callback',
    ];

    private const VARIANT_HEADERS = [
        'unit_type',
        'size_label',
        'builtup_area_sqft',
        'carpet_area_sqft',
        'base_rate_per_sqft',
        'manual_price_override',
        'status',
        'visible_on_public_page',
        'is_featured',
        'is_price_on_request',
    ];

    private const LANDMARK_MEDIA_HEADERS = [
        'row_type',
        'title',
        'value',
        'distance',
        'url',
    ];

    public function __construct(
        private readonly ProjectService $projectService,
    ) {
    }

    public function createTemplate(): string
    {
        $spreadsheet = new Spreadsheet();

        $projectSheet = $spreadsheet->getActiveSheet();
        $projectSheet->setTitle('Project Info');
        $this->writeHeaders($projectSheet, self::PROJECT_HEADERS);

        $variantSheet = $spreadsheet->createSheet();
        $variantSheet->setTitle('Unit Variants');
        $this->writeHeaders($variantSheet, self::VARIANT_HEADERS);

        $mediaSheet = $spreadsheet->createSheet();
        $mediaSheet->setTitle('Landmarks & Media');
        $this->writeHeaders($mediaSheet, self::LANDMARK_MEDIA_HEADERS);

        $path = storage_path('app/' . self::IMPORT_DIR . '/project-import-template-' . Str::random(12) . '.xlsx');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function previewImport(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Only .xlsx or .xls files are supported for project import.');
        }

        $storedPath = $file->storeAs(self::IMPORT_DIR, Str::uuid() . '.' . $extension, self::TEMP_DISK);
        $fullPath = Storage::disk(self::TEMP_DISK)->path($storedPath);
        $workbook = IOFactory::load($fullPath);

        $projectRows = $this->sheetRows($workbook->getSheetByName('Project Info'));
        $variantRows = $this->sheetRows($workbook->getSheetByName('Unit Variants'));
        $mediaRows = $this->sheetRows($workbook->getSheetByName('Landmarks & Media'));

        $payload = $this->buildPreviewPayload($projectRows, $variantRows, $mediaRows);
        $payload['file_path'] = $storedPath;

        $token = Str::random(40);
        Storage::disk(self::TEMP_DISK)->put(
            $this->statePath($token),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [
            'token' => $token,
            'summary' => Arr::only($payload, [
                'project',
                'variants',
                'landmarks',
                'assets',
                'builder_action',
                'warnings',
                'errors',
                'stats',
            ]),
        ];
    }

    public function createDraftProject(string $token): Project
    {
        $payload = $this->loadState($token);

        if (!empty($payload['errors'])) {
            throw new RuntimeException('Import has validation errors. Fix them before creating a draft project.');
        }

        return DB::transaction(function () use ($payload, $token) {
            $projectData = $payload['project'];

            $builder = Builder::firstOrCreate(
                ['name' => $projectData['builder_name']],
                ['status' => 'active']
            );

            $project = $this->projectService->createProject([
                'builder_id' => $builder->id,
                'name' => $projectData['project_name'],
                'short_overview' => $projectData['short_overview'],
                'project_type' => $projectData['project_type'],
                'residential_sub_type' => $projectData['residential_sub_type'],
                'project_status' => $projectData['project_status'],
                'availability_type' => $projectData['availability_type'],
                'city' => $projectData['city'],
                'area' => $projectData['area'],
                'rera_no' => $projectData['rera_no'],
                'possession_date' => $projectData['possession_date'],
                'is_active' => true,
            ]);

            $locationSummary = $projectData['location_summary'];
            if (!$locationSummary && $projectData['address']) {
                $locationSummary = $projectData['address'];
            }

            $publicPage = ProjectPublicPage::create([
                'project_id' => $project->id,
                'hero_title' => $projectData['public_title'] ?: $projectData['project_name'],
                'hero_subtitle' => $projectData['subtitle'],
                'short_intro' => $projectData['short_overview'],
                'location_summary' => $locationSummary,
                'base_rate_per_sqft' => $projectData['base_rate_per_sqft'],
                'rounding_rule' => $projectData['rounding_rule'],
                'show_call' => $projectData['show_call'],
                'show_whatsapp' => $projectData['show_whatsapp'],
                'show_book_visit' => $projectData['show_book_visit'],
                'show_request_callback' => $projectData['show_request_callback'],
                'show_downloads' => true,
                'show_video' => collect($payload['assets'])->contains(fn (array $asset) => $asset['asset_type'] === 'video'),
                'show_tour_360' => collect($payload['assets'])->contains(fn (array $asset) => $asset['asset_type'] === 'tour_360'),
                'call_phone' => $projectData['call_phone'],
                'whatsapp_number' => $projectData['whatsapp_number'],
                'status' => 'draft',
                'preview_token' => Str::random(32),
            ]);

            $project->pricingConfig()->updateOrCreate(
                ['project_id' => $project->id],
                [
                    'bsp_per_sqft' => $projectData['base_rate_per_sqft'],
                    'price_rounding_rule' => $projectData['rounding_rule'] ?: 'none',
                ]
            );

            $unitTypes = [];
            foreach ($payload['variants'] as $index => $variantData) {
                $unitTypeName = $variantData['unit_type'];
                if (!isset($unitTypes[$unitTypeName])) {
                    $unitTypes[$unitTypeName] = ProjectUnitType::create([
                        'project_id' => $project->id,
                        'name' => $unitTypeName,
                        'display_order' => count($unitTypes),
                        'is_primary' => count($unitTypes) === 0,
                    ]);
                }

                ProjectSizeVariant::create([
                    'project_id' => $project->id,
                    'project_unit_type_id' => $unitTypes[$unitTypeName]->id,
                    'size_label' => $variantData['size_label'],
                    'carpet_area_sqft' => $variantData['carpet_area_sqft'],
                    'builtup_area_sqft' => $variantData['builtup_area_sqft'],
                    'base_rate_per_sqft' => $variantData['base_rate_per_sqft'],
                    'rounding_rule' => $variantData['rounding_rule'] ?: null,
                    'calculated_price' => $variantData['calculated_price'],
                    'manual_price_override' => $variantData['manual_price_override'],
                    'final_price' => $variantData['final_price'],
                    'is_price_on_request' => $variantData['is_price_on_request'],
                    'status' => $variantData['status'],
                    'visible_on_public_page' => $variantData['visible_on_public_page'],
                    'display_order' => $index,
                    'is_featured' => $variantData['is_featured'],
                ]);
            }

            foreach ($payload['landmarks'] as $index => $landmarkData) {
                ProjectLandmark::create([
                    'project_id' => $project->id,
                    'label' => $landmarkData['title'],
                    'type' => $landmarkData['value'],
                    'distance_text' => $landmarkData['distance'],
                    'display_order' => $index,
                ]);
            }

            foreach ($payload['assets'] as $index => $assetData) {
                ProjectAsset::create([
                    'project_id' => $project->id,
                    'asset_type' => $assetData['asset_type'],
                    'title' => $assetData['title'],
                    'source_type' => 'external',
                    'external_url' => $assetData['external_url'],
                    'tracking_key' => Str::slug($assetData['title'] ?: ($assetData['asset_type'] . '-' . $index)),
                    'display_order' => $index,
                    'meta' => ['value' => $assetData['value']],
                ]);
            }

            Storage::disk(self::TEMP_DISK)->delete($this->statePath($token));
            if (!empty($payload['file_path'])) {
                Storage::disk(self::TEMP_DISK)->delete($payload['file_path']);
            }

            return $project->fresh();
        });
    }

    public function loadStateSummary(string $token): array
    {
        $payload = $this->loadState($token);

        return Arr::only($payload, [
            'project',
            'variants',
            'landmarks',
            'assets',
            'builder_action',
            'warnings',
            'errors',
            'stats',
        ]);
    }

    private function buildPreviewPayload(array $projectRows, array $variantRows, array $mediaRows): array
    {
        $errors = [];
        $warnings = [];

        if (count($projectRows) !== 1) {
            $errors[] = 'Project Info sheet must contain exactly one filled project row.';
        }

        $project = $projectRows[0] ?? [];
        foreach (self::PROJECT_REQUIRED_FIELDS as $field) {
            if (blank($project[$field] ?? null)) {
                $errors[] = "Project Info: {$field} is required.";
            }
        }

        $project = $this->normalizeProjectRow($project);
        $variants = [];
        if (count($variantRows) === 0) {
            $errors[] = 'Unit Variants sheet must contain at least one valid variant row.';
        }

        foreach ($variantRows as $row) {
            $variant = $this->normalizeVariantRow($row, $project);
            foreach ($variant['errors'] as $error) {
                $errors[] = 'Unit Variants row ' . $variant['_row_number'] . ': ' . $error;
            }
            unset($variant['errors']);
            if ($variant['size_label']) {
                $variants[] = $variant;
            }
        }

        $landmarks = [];
        $assets = [];

        foreach ($mediaRows as $row) {
            $normalized = $this->normalizeMediaRow($row);
            foreach ($normalized['errors'] as $error) {
                $errors[] = 'Landmarks & Media row ' . $normalized['_row_number'] . ': ' . $error;
            }

            if ($normalized['row_type'] === 'landmark' && $normalized['title']) {
                $landmarks[] = $normalized;
            } elseif ($normalized['asset_type'] && $normalized['external_url']) {
                $assets[] = $normalized;
            }
        }

        if (
            $project['project_name']
            && $project['city']
            && $project['area']
            && Project::query()
                ->where('name', $project['project_name'])
                ->where('city', $project['city'])
                ->where('area', $project['area'])
                ->exists()
        ) {
            $errors[] = 'A project with the same project name, city, and area already exists.';
        }

        $builder = null;
        if ($project['builder_name']) {
            $builder = Builder::where('name', $project['builder_name'])->first();
        }

        return [
            'project' => $project,
            'variants' => array_values($variants),
            'landmarks' => array_values($landmarks),
            'assets' => array_values($assets),
            'builder_action' => $builder ? 'existing_builder' : 'auto_create_builder',
            'warnings' => array_values($warnings),
            'errors' => array_values(array_unique($errors)),
            'stats' => [
                'unit_types' => collect($variants)->pluck('unit_type')->filter()->unique()->count(),
                'variants' => count($variants),
                'landmarks' => count($landmarks),
                'assets' => count($assets),
            ],
        ];
    }

    private function normalizeProjectRow(array $row): array
    {
        return [
            'builder_name' => trim((string) ($row['builder_name'] ?? '')),
            'project_name' => trim((string) ($row['project_name'] ?? '')),
            'public_title' => trim((string) ($row['public_title'] ?? '')),
            'subtitle' => trim((string) ($row['subtitle'] ?? '')),
            'short_overview' => trim((string) ($row['short_overview'] ?? '')),
            'project_type' => $this->normalizeEnum((string) ($row['project_type'] ?? ''), ['residential', 'commercial', 'mixed']),
            'residential_sub_type' => $this->normalizeEnum((string) ($row['residential_sub_type'] ?? 'flat'), ['plot', 'flat', 'villa']),
            'project_status' => $this->normalizeEnum((string) ($row['project_status'] ?? ''), ['prelaunch', 'under_construction', 'ready']),
            'availability_type' => $this->normalizeEnum((string) ($row['availability_type'] ?? 'fresh'), ['fresh', 'resale', 'both']),
            'city' => trim((string) ($row['city'] ?? '')),
            'area' => trim((string) ($row['area'] ?? '')),
            'address' => trim((string) ($row['address'] ?? '')),
            'rera_no' => trim((string) ($row['rera_no'] ?? '')),
            'possession_date' => $this->normalizeDate($row['possession_date'] ?? null),
            'location_summary' => trim((string) ($row['location_summary'] ?? '')),
            'base_rate_per_sqft' => $this->toFloatOrNull($row['base_rate_per_sqft'] ?? null),
            'rounding_rule' => $this->normalizeRoundingRule((string) ($row['rounding_rule'] ?? 'none')),
            'call_phone' => trim((string) ($row['call_phone'] ?? '')),
            'whatsapp_number' => trim((string) ($row['whatsapp_number'] ?? '')),
            'show_call' => $this->toBool($row['show_call'] ?? true),
            'show_whatsapp' => $this->toBool($row['show_whatsapp'] ?? true),
            'show_book_visit' => $this->toBool($row['show_book_visit'] ?? false),
            'show_request_callback' => $this->toBool($row['show_request_callback'] ?? false),
        ];
    }

    private function normalizeVariantRow(array $row, array $project): array
    {
        $errors = [];
        $unitType = trim((string) ($row['unit_type'] ?? ''));
        $sizeLabel = trim((string) ($row['size_label'] ?? ''));
        $builtup = $this->toFloatOrNull($row['builtup_area_sqft'] ?? null);
        $variantRate = $this->toFloatOrNull($row['base_rate_per_sqft'] ?? null) ?? $project['base_rate_per_sqft'];
        $manualPrice = $this->toFloatOrNull($row['manual_price_override'] ?? null);
        $roundingRule = $project['rounding_rule'];
        $status = $this->normalizeEnum((string) ($row['status'] ?? 'available'), ['available', 'hold', 'sold_out', 'hidden']);
        $isPriceOnRequest = $this->toBool($row['is_price_on_request'] ?? false);

        if ($unitType === '') {
            $errors[] = 'unit_type is required.';
        }

        if ($sizeLabel === '') {
            $errors[] = 'size_label is required.';
        }

        if ($builtup === null) {
            $errors[] = 'builtup_area_sqft is required.';
        }

        if (!$status) {
            $errors[] = 'status value is invalid.';
            $status = 'available';
        }

        $calculatedPrice = $this->calculatePrice($builtup, $variantRate, $roundingRule);

        return [
            '_row_number' => $row['_row_number'] ?? 0,
            'unit_type' => $unitType,
            'size_label' => $sizeLabel,
            'builtup_area_sqft' => $builtup,
            'carpet_area_sqft' => $this->toFloatOrNull($row['carpet_area_sqft'] ?? null),
            'base_rate_per_sqft' => $variantRate,
            'rounding_rule' => $roundingRule,
            'manual_price_override' => $manualPrice,
            'calculated_price' => $calculatedPrice,
            'final_price' => $isPriceOnRequest ? null : ($manualPrice ?? $calculatedPrice),
            'status' => $status,
            'visible_on_public_page' => $this->toBool($row['visible_on_public_page'] ?? true),
            'is_featured' => $this->toBool($row['is_featured'] ?? false),
            'is_price_on_request' => $isPriceOnRequest,
            'errors' => $errors,
        ];
    }

    private function normalizeMediaRow(array $row): array
    {
        $rowType = trim((string) ($row['row_type'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));
        $value = trim((string) ($row['value'] ?? ''));
        $distance = trim((string) ($row['distance'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        $errors = [];

        if ($rowType === '') {
            return [
                '_row_number' => $row['_row_number'] ?? 0,
                'row_type' => '',
                'title' => '',
                'value' => '',
                'distance' => '',
                'asset_type' => null,
                'external_url' => null,
                'errors' => [],
            ];
        }

        if (!in_array($rowType, ['landmark', 'video', 'tour_360', 'brochure', 'price_sheet'], true)) {
            $errors[] = 'row_type is invalid.';
        }

        if ($title === '') {
            $errors[] = 'title is required.';
        }

        $externalUrl = $url ?: $value;
        if ($rowType !== 'landmark' && filter_var($externalUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'url is required for media rows.';
        }

        return [
            '_row_number' => $row['_row_number'] ?? 0,
            'row_type' => $rowType,
            'title' => $title,
            'value' => $value,
            'distance' => $distance,
            'asset_type' => $rowType === 'landmark' ? null : $rowType,
            'external_url' => $rowType === 'landmark' ? null : $externalUrl,
            'errors' => $errors,
        ];
    }

    private function loadState(string $token): array
    {
        $path = $this->statePath($token);
        if (!Storage::disk(self::TEMP_DISK)->exists($path)) {
            throw new RuntimeException('Import preview expired. Please upload the Excel again.');
        }

        return json_decode((string) Storage::disk(self::TEMP_DISK)->get($path), true) ?? [];
    }

    private function statePath(string $token): string
    {
        return self::STATE_DIR . '/' . $token . '.json';
    }

    private function writeHeaders($sheet, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
    }

    private function sheetRows($sheet): array
    {
        if (!$sheet) {
            throw new RuntimeException('Expected sheet is missing from import file.');
        }

        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) === 0) {
            return [];
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rows[0]);
        $dataRows = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $assoc = [];
            $hasValue = false;

            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $row[$columnIndex] ?? null;
                $assoc[$header] = is_string($value) ? trim($value) : $value;
                if ($value !== null && $value !== '') {
                    $hasValue = true;
                }
            }

            if (!$hasValue) {
                continue;
            }

            $assoc['_row_number'] = $index + 2;
            $dataRows[] = $assoc;
        }

        return $dataRows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';

        return trim($header, '_');
    }

    private function normalizeEnum(string $value, array $allowed): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    private function normalizeRoundingRule(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'nearest_1000', '1000' => 'nearest_1000',
            'nearest_10000', '10000' => 'nearest_10000',
            'nearest_100000', '100000', 'nearest_1_lakh', '1_lakh' => 'nearest_100000',
            default => 'none',
        };
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', '₹', 'rs', 'inr', 'sq.ft.', 'sqft'], '', strtolower($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function calculatePrice(?float $builtupArea, ?float $baseRate, string $roundingRule): ?float
    {
        if (!$builtupArea || !$baseRate) {
            return null;
        }

        $price = $builtupArea * $baseRate;

        return match ($roundingRule) {
            'nearest_1000' => round($price / 1000) * 1000,
            'nearest_10000' => round($price / 10000) * 10000,
            'nearest_100000' => round($price / 100000) * 100000,
            default => $price,
        };
    }
}
