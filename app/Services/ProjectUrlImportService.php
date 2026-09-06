<?php

namespace App\Services;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectLandmark;
use App\Models\ProjectPublicPage;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use App\Models\ProjectUrlImportLog;
use App\Services\ProjectUrlImport\Parsers\HousingProjectParser;
use App\Services\ProjectUrlImport\Parsers\ProjectUrlParserInterface;
use App\Services\ProjectUrlImport\Parsers\PropertyPistolProjectParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ProjectUrlImportService
{
    private const TEMP_DISK = 'local';
    private const STATE_DIR = 'project-url-import-states';
    private const REQUIRED_FIELDS = ['builder_name', 'project_name', 'city', 'area'];

    public function __construct(
        private readonly ProjectService $projectService,
    ) {
    }

    public function extractFromUrl(string $url, ?int $userId = null): array
    {
        $startedAt = microtime(true);
        $token = Str::random(40);
        $normalizedUrl = $this->normalizeUrl($url);
        $stage = 'Detecting source';
        $progress = 10;
        $sourceKey = $this->detectSource($normalizedUrl);

        if ($sourceKey === null) {
            $this->logImport([
                'user_id' => $userId,
                'import_token' => $token,
                'normalized_url' => $normalizedUrl,
                'progress_stage' => $stage,
                'progress_percent' => $progress,
                'status' => 'failed',
                'warnings' => ['Unsupported URL source.'],
                'failed_selectors' => ['source_detection'],
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            throw new RuntimeException('Unsupported source. Phase 1 currently supports only Housing and PropertyPistol URLs.');
        }

        $parser = $this->parserFor($sourceKey);
        $payload = $parser->extract($normalizedUrl);

        $review = $this->buildReviewPayload($payload, $parser, $normalizedUrl, $token);
        Storage::disk(self::TEMP_DISK)->put(
            $this->statePath($token),
            json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->logImport([
            'user_id' => $userId,
            'import_token' => $token,
            'source_key' => $parser->sourceKey(),
            'normalized_url' => $normalizedUrl,
            'parser_version' => $parser->parserVersion(),
            'progress_stage' => $review['progress_stage'],
            'progress_percent' => $review['progress_percent'],
            'extracted_field_count' => $review['stats']['extracted_fields'],
            'failed_selectors' => $review['failed_selectors'],
            'warnings' => $review['warnings'],
            'status' => $review['status'],
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return [
            'token' => $token,
            'review_url' => route('projects.import-url.review', $token),
            'summary' => Arr::only($review, [
                'source_key',
                'normalized_url',
                'progress_stage',
                'progress_percent',
                'stats',
                'warnings',
                'errors',
                'hard_required_valid',
            ]),
        ];
    }

    public function reviewPayload(string $token): array
    {
        return $this->loadState($token);
    }

    public function createDraftProject(string $token, array $reviewedPayload): Project
    {
        $payload = $this->mergeReviewedPayload($this->loadState($token), $reviewedPayload);

        foreach (self::REQUIRED_FIELDS as $field) {
            if (blank($payload['project'][$field] ?? null)) {
                throw new RuntimeException('Missing required field: ' . $field);
            }
        }

        return DB::transaction(function () use ($payload, $token) {
            $projectData = $payload['project'];

            $builder = Builder::firstOrCreate(
                ['name' => $projectData['builder_name']],
                ['status' => 'active']
            );

            $configurationSummary = collect($payload['variants'])
                ->pluck('unit_type')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $project = $this->projectService->createProject([
                'builder_id' => $builder->id,
                'name' => $projectData['project_name'],
                'short_overview' => $projectData['short_overview'],
                'project_type' => $projectData['project_type'] ?: 'residential',
                'residential_sub_type' => $projectData['residential_sub_type'] ?: 'flat',
                'project_status' => $projectData['project_status'],
                'availability_type' => $projectData['availability_type'] ?: 'fresh',
                'city' => $projectData['city'],
                'area' => $projectData['area'],
                'rera_no' => $projectData['rera_no'],
                'project_highlights' => $projectData['project_highlights'],
                'configuration_summary' => $configurationSummary,
                'is_active' => true,
            ]);

            ProjectPublicPage::create([
                'project_id' => $project->id,
                'hero_title' => $projectData['public_title'] ?: $projectData['project_name'],
                'hero_subtitle' => $projectData['subtitle'],
                'short_intro' => $projectData['short_overview'],
                'location_summary' => $projectData['location_summary'] ?: $projectData['address'],
                'base_rate_per_sqft' => $projectData['base_rate_per_sqft'],
                'rounding_rule' => $projectData['rounding_rule'] ?: 'nearest_1000',
                'show_call' => (bool) ($projectData['show_call'] ?? false),
                'show_whatsapp' => (bool) ($projectData['show_whatsapp'] ?? false),
                'show_book_visit' => (bool) ($projectData['show_book_visit'] ?? false),
                'show_request_callback' => (bool) ($projectData['show_request_callback'] ?? true),
                'show_downloads' => true,
                'show_video' => collect($payload['assets'])->contains(fn (array $asset) => ($asset['asset_type'] ?? null) === 'video'),
                'show_tour_360' => collect($payload['assets'])->contains(fn (array $asset) => ($asset['asset_type'] ?? null) === 'tour_360'),
                'call_phone' => $projectData['call_phone'],
                'whatsapp_number' => $projectData['whatsapp_number'],
                'status' => 'draft',
                'preview_token' => Str::random(32),
            ]);

            $project->pricingConfig()->updateOrCreate(
                ['project_id' => $project->id],
                [
                    'bsp_per_sqft' => $this->toNumber($projectData['base_rate_per_sqft']),
                    'price_rounding_rule' => $projectData['rounding_rule'] ?: 'nearest_1000',
                ]
            );

            $unitTypeMap = [];
            foreach ($payload['variants'] as $index => $variant) {
                $unitTypeName = $variant['unit_type'] ?: 'Configuration';

                if (!isset($unitTypeMap[$unitTypeName])) {
                    $unitTypeMap[$unitTypeName] = ProjectUnitType::create([
                        'project_id' => $project->id,
                        'name' => $unitTypeName,
                        'display_order' => count($unitTypeMap),
                        'is_primary' => count($unitTypeMap) === 0,
                    ]);
                }

                $effectiveRate = $this->toNumber($variant['base_rate_per_sqft']) ?: $this->toNumber($projectData['base_rate_per_sqft']);
                $calculatedPrice = $this->calculatePrice($this->toNumber($variant['builtup_area_sqft']), $effectiveRate);
                $manualOverride = $this->toNumber($variant['manual_price_override']);

                ProjectSizeVariant::create([
                    'project_id' => $project->id,
                    'project_unit_type_id' => $unitTypeMap[$unitTypeName]->id,
                    'size_label' => $variant['size_label'],
                    'carpet_area_sqft' => $this->toNumber($variant['carpet_area_sqft']),
                    'builtup_area_sqft' => $this->toNumber($variant['builtup_area_sqft']),
                    'base_rate_per_sqft' => $effectiveRate,
                    'rounding_rule' => $projectData['rounding_rule'] ?: null,
                    'calculated_price' => $calculatedPrice,
                    'manual_price_override' => $manualOverride,
                    'final_price' => $manualOverride ?: $calculatedPrice,
                    'is_price_on_request' => (bool) ($variant['is_price_on_request'] ?? false),
                    'status' => $variant['status'] ?: 'available',
                    'visible_on_public_page' => (bool) ($variant['visible_on_public_page'] ?? true),
                    'display_order' => $index,
                    'is_featured' => (bool) ($variant['is_featured'] ?? false),
                ]);
            }

            foreach ($payload['landmarks'] as $index => $landmark) {
                if (blank($landmark['title'] ?? null)) {
                    continue;
                }

                ProjectLandmark::create([
                    'project_id' => $project->id,
                    'label' => $landmark['title'],
                    'type' => $landmark['value'] ?: 'landmark',
                    'distance_text' => $landmark['distance'],
                    'display_order' => $index,
                ]);
            }

            foreach ($payload['assets'] as $index => $asset) {
                if (blank($asset['external_url'] ?? null)) {
                    continue;
                }

                ProjectAsset::create([
                    'project_id' => $project->id,
                    'asset_type' => $asset['asset_type'] ?: 'brochure',
                    'title' => $asset['title'] ?: ucfirst(str_replace('_', ' ', $asset['asset_type'] ?? 'asset')),
                    'source_type' => 'external',
                    'external_url' => $asset['external_url'],
                    'tracking_key' => Str::slug(($asset['asset_type'] ?? 'asset') . '-' . ($asset['title'] ?? $index)),
                    'display_order' => $index,
                    'meta' => ['value' => $asset['value'] ?? null],
                ]);
            }

            Storage::disk(self::TEMP_DISK)->delete($this->statePath($token));

            return $project->fresh();
        });
    }

    public function prepareDetectedImagesDownload(string $token): array
    {
        $state = $this->loadState($token);
        $images = collect($state['assets'] ?? [])
            ->filter(fn (array $asset) => ($asset['asset_type'] ?? null) === 'gallery_image' && filled($asset['external_url'] ?? null))
            ->values();

        if ($images->isEmpty()) {
            throw new RuntimeException('No detected images available to download.');
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'project-images-');
        if ($tempBase === false) {
            throw new RuntimeException('Could not prepare image download archive.');
        }

        @unlink($tempBase);
        $zipPath = $tempBase . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create image archive.');
        }

        $added = 0;
        foreach ($images as $index => $asset) {
            $url = (string) ($asset['external_url'] ?? '');
            if ($url === '') {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                ])->timeout(30)->retry(1, 250)->get($url);

                if (!$response->successful() || blank($response->body())) {
                    continue;
                }

                $filename = $this->imageFilename($url, (string) ($asset['title'] ?? ''), $index, $response->header('Content-Type'));
                $zip->addFromString($filename, $response->body());
                $added++;
            } catch (\Throwable) {
                continue;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new RuntimeException('Detected image URLs were found, but none could be downloaded right now.');
        }

        return [
            'path' => $zipPath,
            'filename' => 'detected-project-images-' . now()->format('Ymd-His') . '.zip',
        ];
    }

    private function buildReviewPayload(array $payload, ProjectUrlParserInterface $parser, string $normalizedUrl, string $token): array
    {
        $project = array_merge([
            'builder_name' => null,
            'project_name' => null,
            'public_title' => null,
            'subtitle' => null,
            'short_overview' => null,
            'project_highlights' => null,
            'project_type' => 'residential',
            'residential_sub_type' => 'flat',
            'project_status' => null,
            'availability_type' => 'fresh',
            'city' => null,
            'area' => null,
            'address' => null,
            'rera_no' => null,
            'possession_date' => null,
            'location_summary' => null,
            'base_rate_per_sqft' => null,
            'rounding_rule' => 'nearest_1000',
            'call_phone' => null,
            'whatsapp_number' => null,
            'show_call' => false,
            'show_whatsapp' => false,
            'show_book_visit' => false,
            'show_request_callback' => true,
        ], $payload['project'] ?? []);

        $variants = collect($payload['variants'] ?? [])->map(function ($variant) {
            return array_merge([
                'unit_type' => null,
                'size_label' => null,
                'builtup_area_sqft' => null,
                'carpet_area_sqft' => null,
                'base_rate_per_sqft' => null,
                'manual_price_override' => null,
                'status' => 'available',
                'visible_on_public_page' => true,
                'is_featured' => false,
                'is_price_on_request' => false,
                'display_price' => null,
            ], $variant);
        })->values()->all();

        $assets = collect($payload['assets'] ?? [])->map(fn ($asset) => array_merge([
            'asset_type' => 'brochure',
            'title' => null,
            'value' => null,
            'external_url' => null,
        ], $asset))->values()->all();

        $landmarks = collect($payload['landmarks'] ?? [])->map(fn ($landmark) => array_merge([
            'title' => null,
            'value' => 'landmark',
            'distance' => null,
        ], $landmark))->values()->all();

        $confidence = $payload['confidence'] ?? [];
        $warnings = array_values(array_unique($payload['warnings'] ?? []));
        $failedSelectors = array_values(array_unique($payload['failed_selectors'] ?? []));
        $amenities = collect($payload['amenities'] ?? [])->values()->all();
        $extractedFieldCount = $this->countExtractedFields($project, $payload['pricing'] ?? [], $variants, $assets, $landmarks, $amenities);
        $needsReview = $this->countNeedsReview($confidence, $project);

        return [
            'token' => $token,
            'source_key' => $parser->sourceKey(),
            'parser_version' => $parser->parserVersion(),
            'normalized_url' => $normalizedUrl,
            'progress_stage' => 'Preparing review',
            'progress_percent' => 100,
            'progress_steps' => [
                ['label' => 'Detecting source', 'percent' => 15, 'status' => 'done'],
                ['label' => 'Extracting basics', 'percent' => 40, 'status' => 'done'],
                ['label' => 'Extracting pricing', 'percent' => 65, 'status' => 'done'],
                ['label' => 'Extracting variants', 'percent' => 85, 'status' => 'done'],
                ['label' => 'Preparing review', 'percent' => 100, 'status' => 'done'],
            ],
            'project' => $project,
            'pricing' => $payload['pricing'] ?? [],
            'variants' => $variants,
            'assets' => $assets,
            'landmarks' => $landmarks,
            'amenities' => $amenities,
            'confidence' => $confidence,
            'field_sources' => $payload['field_sources'] ?? [],
            'warnings' => $warnings,
            'errors' => [],
            'failed_selectors' => $failedSelectors,
            'status' => count($failedSelectors) > 0 ? 'partial' : 'success',
            'hard_required_valid' => $this->hasRequiredFields($project),
            'stats' => [
                'extracted_fields' => $extractedFieldCount,
                'needs_review' => $needsReview,
                'warnings' => count($warnings),
                'variants' => count($variants),
                'assets' => count($assets),
                'landmarks' => count($landmarks),
                'amenities' => count($amenities),
            ],
        ];
    }

    private function mergeReviewedPayload(array $state, array $reviewed): array
    {
        $state['project'] = array_merge($state['project'] ?? [], $reviewed['project'] ?? []);
        $state['pricing'] = array_merge($state['pricing'] ?? [], $reviewed['pricing'] ?? []);
        $state['variants'] = collect($reviewed['variants'] ?? $state['variants'] ?? [])
            ->map(function ($variant) {
                return array_merge([
                    'unit_type' => null,
                    'size_label' => null,
                    'builtup_area_sqft' => null,
                    'carpet_area_sqft' => null,
                    'base_rate_per_sqft' => null,
                    'manual_price_override' => null,
                    'status' => 'available',
                    'visible_on_public_page' => true,
                    'is_featured' => false,
                    'is_price_on_request' => false,
                ], $variant);
            })->filter(fn ($variant) => filled($variant['unit_type'] ?? null) || filled($variant['size_label'] ?? null))->values()->all();
        $state['assets'] = collect($reviewed['assets'] ?? $state['assets'] ?? [])->values()->all();
        $state['landmarks'] = collect($reviewed['landmarks'] ?? $state['landmarks'] ?? [])->values()->all();

        return $state;
    }

    private function detectSource(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        return match (true) {
            str_contains($host, 'housing.com') => 'housing',
            str_contains($host, 'propertypistol.in'), str_contains($host, 'propertypistol.com') => 'propertypistol',
            default => null,
        };
    }

    private function parserFor(string $sourceKey): ProjectUrlParserInterface
    {
        return match ($sourceKey) {
            'housing' => new HousingProjectParser(),
            'propertypistol' => new PropertyPistolProjectParser(),
            default => throw new RuntimeException('Unsupported parser source.'),
        };
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (!Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }

    private function loadState(string $token): array
    {
        $path = $this->statePath($token);
        if (!Storage::disk(self::TEMP_DISK)->exists($path)) {
            throw new RuntimeException('Import session expired. Please extract the URL again.');
        }

        $payload = json_decode((string) Storage::disk(self::TEMP_DISK)->get($path), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Import session could not be read.');
        }

        return $payload;
    }

    private function statePath(string $token): string
    {
        return self::STATE_DIR . '/' . $token . '.json';
    }

    private function hasRequiredFields(array $project): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (blank($project[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function countNeedsReview(array $confidence, array $project): int
    {
        $count = collect($confidence)->filter(fn ($level) => $level === 'Needs Review')->count();

        foreach (self::REQUIRED_FIELDS as $field) {
            if (blank($project[$field] ?? null)) {
                $count++;
            }
        }

        return $count;
    }

    private function countExtractedFields(array $project, array $pricing, array $variants, array $assets, array $landmarks, array $amenities): int
    {
        $projectCount = collect($project)->filter(fn ($value) => filled($value))->count();
        $pricingCount = collect($pricing)->filter(fn ($value) => filled($value))->count();
        $variantCount = collect($variants)->sum(fn ($variant) => collect($variant)->filter(fn ($value) => filled($value))->count());
        $assetCount = collect($assets)->sum(fn ($asset) => collect($asset)->filter(fn ($value) => filled($value))->count());
        $landmarkCount = collect($landmarks)->sum(fn ($landmark) => collect($landmark)->filter(fn ($value) => filled($value))->count());
        $amenityCount = collect($amenities)->sum(fn ($amenity) => collect($amenity)->filter(fn ($value) => filled($value))->count());

        return $projectCount + $pricingCount + $variantCount + $assetCount + $landmarkCount + $amenityCount;
    }

    private function calculatePrice(?float $builtupArea, ?float $rate): ?float
    {
        if (!$builtupArea || !$rate) {
            return null;
        }

        return round($builtupArea * $rate, 2);
    }

    private function toNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.]/', '', (string) $value);

        return $normalized !== '' ? (float) $normalized : null;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function imageFilename(string $url, string $title, int $index, ?string $contentType): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension === '' && $contentType) {
            $extension = match (strtolower(trim(explode(';', $contentType)[0]))) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                default => 'jpg',
            };
        }

        $baseName = Str::slug($title ?: ('project-image-' . ($index + 1)));
        if ($baseName === '') {
            $baseName = 'project-image-' . ($index + 1);
        }

        return $baseName . '.' . ($extension ?: 'jpg');
    }

    private function logImport(array $payload): void
    {
        try {
            ProjectUrlImportLog::create($payload);
        } catch (\Throwable) {
            // Observability should not block the import flow.
        }
    }
}
