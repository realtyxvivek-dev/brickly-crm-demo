<?php

namespace App\Services\ProjectUrlImport\Parsers;

use App\Services\ProjectUrlImport\Parsers\Concerns\ParsesPortalHtml;

class PropertyPistolProjectParser implements ProjectUrlParserInterface
{
    use ParsesPortalHtml;

    public function sourceKey(): string
    {
        return 'propertypistol';
    }

    public function parserVersion(): string
    {
        return 'propertypistol-v2';
    }

    public function extract(string $url): array
    {
        $html = $this->fetchHtml($url);
        $text = $this->textContent($html);
        $nuxtProject = $this->extractNuxtProject($html);
        $warnings = [];
        $failedSelectors = [];

        $projectName = $nuxtProject['project_name'] ?? $this->metaContent($html, 'og:title')
            ?: $this->firstPattern($text, [
                '/projects?\s+([A-Za-z0-9&.\- ]+?)\s+(?:overview|details|price|in)\b/i',
            ])
            ?: $this->titleTag($html);

        $builder = $nuxtProject['builder_name'] ?? $this->firstPattern($text, [
            '/(?:developer|builder)\s*[:\-]?\s*([A-Za-z0-9&.\- ]+?)(?:\s{2,}|RERA|Possession|Project Status|Price)/i',
            '/\bby\s+([A-Za-z0-9&.\- ]+?)\s+(?:at|in|rera|project)\b/i',
        ]);

        if (!$builder) {
            $failedSelectors[] = 'builder_name';
            $warnings[] = 'Builder name was not confidently detected.';
        }

        $city = $nuxtProject['city_name'] ?? (str_contains(strtolower($text), 'lucknow') ? 'Lucknow' : null);
        $area = $nuxtProject['region_name'] ?? $this->firstPattern($text, [
            '/([A-Za-z0-9&.\- ]+?),\s*Lucknow\b/i',
            '/in\s+([A-Za-z0-9&.\- ]+?)\s+(?:Lucknow|Uttar Pradesh)\b/i',
        ]);

        if (!$area) {
            $failedSelectors[] = 'area';
        }

        $projectStatus = $this->normalizeStatus($nuxtProject['status_name'] ?? null) ?: $this->inferProjectStatus($text);
        $possession = $nuxtProject['posession_date'] ?? $this->extractPossession($text);
        $reraCodes = $this->extractReraCodes(($nuxtProject['rera_ids'] ?? '') . ' ' . $text);

        $variants = $this->extractVariantsFromNuxt($nuxtProject);
        if (count($variants) === 0) {
            $variants = $this->extractVariantsFromText($text);
        }
        if (count($variants) === 0) {
            $warnings[] = 'No structured unit variants were detected.';
            $failedSelectors[] = 'variants';
        }

        $videoUrl = $this->firstPattern($html, [
            '/https:\/\/(?:www\.)?youtube\.com\/watch\?v=[^"\']+/i',
            '/https:\/\/youtu\.be\/[^"\']+/i',
        ]);

        $tourUrl = $this->firstPattern($html, [
            '/https:\/\/[^"\']*(?:360|tour)[^"\']+/i',
        ]);

        $galleryAssets = $this->extractGalleryAssets($html, $nuxtProject);
        if (count($galleryAssets) === 0) {
            $warnings[] = 'No gallery or plan images were detected.';
        }

        $amenities = $this->extractAmenities($nuxtProject);
        $videoAssets = $this->extractVideoAssets($nuxtProject, $videoUrl);
        $brochureAsset = $this->extractBrochureAsset($nuxtProject);

        $startingPrice = $this->formatStartingPrice($nuxtProject['starting_price'] ?? null)
            ?: ($nuxtProject['price'] ?? null)
            ?: $this->extractStartingPrice($text);

        $priceRange = $this->formatPriceRangeFromConfigs($nuxtProject['configuration_summary'] ?? [])
            ?: $this->extractPriceRange($text);

        $ratePerSqft = $this->formatRatePerSqft($nuxtProject['avg_price'] ?? null)
            ?: $this->extractRatePerSqft($text);

        $sizeRange = $this->formatSizeRangeFromNuxt($nuxtProject)
            ?: $this->extractSizeRange($text);

        $projectHighlights = collect([
            $nuxtProject['details'] ?? null,
            $nuxtProject['land_area_in_acres'] ?? null,
            isset($nuxtProject['floors']) && filled($nuxtProject['floors']) ? ($nuxtProject['floors'] . ' Floors') : null,
            $nuxtProject['project_type'] ?? null,
        ])->filter()->implode(' | ');

        return [
            'project' => [
                'builder_name' => $builder,
                'project_name' => $projectName,
                'public_title' => $projectName,
                'subtitle' => null,
                'short_overview' => $nuxtProject['description'] ?? $this->metaContent($html, 'description') ?: $this->metaContent($html, 'og:description'),
                'project_highlights' => $projectHighlights ?: null,
                'project_type' => 'residential',
                'residential_sub_type' => 'flat',
                'project_status' => $projectStatus,
                'availability_type' => 'fresh',
                'city' => $city,
                'area' => $area,
                'address' => $nuxtProject['location'] ?? ($area && $city ? $area . ', ' . $city : null),
                'rera_no' => count($reraCodes) ? implode(', ', $reraCodes) : null,
                'possession_date' => $possession,
                'location_summary' => $nuxtProject['location'] ?? ($area && $city ? $area . ', ' . $city : null),
                'base_rate_per_sqft' => $this->numericRate($nuxtProject['avg_price'] ?? null),
                'rounding_rule' => 'nearest_1000',
                'call_phone' => null,
                'whatsapp_number' => null,
                'show_call' => false,
                'show_whatsapp' => false,
                'show_book_visit' => false,
                'show_request_callback' => true,
            ],
            'pricing' => [
                'starting_price' => $startingPrice,
                'price_range' => $priceRange,
                'rate_per_sqft' => $ratePerSqft,
                'size_range' => $sizeRange,
            ],
            'variants' => $variants,
            'assets' => array_values(array_filter([
                ...$videoAssets,
                $tourUrl ? [
                    'asset_type' => 'tour_360',
                    'title' => '360 Tour',
                    'value' => 'Detected from PropertyPistol',
                    'external_url' => $tourUrl,
                ] : null,
                $brochureAsset,
                ...$galleryAssets,
            ])),
            'landmarks' => [],
            'amenities' => $amenities,
            'confidence' => [
                'project.builder_name' => $builder ? 'High' : 'Needs Review',
                'project.project_name' => $projectName ? 'High' : 'Needs Review',
                'project.city' => $city ? 'High' : 'Needs Review',
                'project.area' => $area ? 'High' : 'Needs Review',
                'project.rera_no' => count($reraCodes) ? 'High' : 'Needs Review',
                'pricing.starting_price' => $startingPrice ? 'High' : 'Needs Review',
                'pricing.price_range' => $priceRange ? 'High' : 'Needs Review',
                'pricing.rate_per_sqft' => $ratePerSqft ? 'High' : 'Needs Review',
                'variants' => count($variants) ? 'High' : 'Needs Review',
            ],
            'field_sources' => [
                'project_name' => $url,
                'builder_name' => $url,
                'city' => $url,
                'area' => $url,
                'pricing' => $url,
                'variants' => $url,
            ],
            'warnings' => array_values(array_unique($warnings)),
            'failed_selectors' => array_values(array_unique($failedSelectors)),
        ];
    }

    private function extractNuxtProject(string $html): array
    {
        if (!preg_match('/<script type="application\/json" id="__NUXT_DATA__"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [];
        }

        $payload = json_decode($matches[1], true);
        if (!is_array($payload)) {
            return [];
        }

        $cache = [];
        $root = $this->resolveNuxtValue(0, $payload, $cache);

        return $root['data']['project']['project'] ?? [];
    }

    private function resolveNuxtValue(mixed $value, array $pool, array &$cache): mixed
    {
        if (is_int($value)) {
            if ($value < 0 || $value >= count($pool)) {
                return $value;
            }

            if (array_key_exists($value, $cache)) {
                return $cache[$value];
            }

            $raw = $pool[$value];
            if (!is_array($raw)) {
                $cache[$value] = $raw;
                return $raw;
            }

            $cache[$value] = null;
            $resolved = $this->resolveNuxtArray($raw, $pool, $cache);
            $cache[$value] = $resolved;

            return $resolved;
        }

        if (is_array($value)) {
            return $this->resolveNuxtArray($value, $pool, $cache);
        }

        return $value;
    }

    private function resolveNuxtArray(array $value, array $pool, array &$cache): mixed
    {
        if (array_is_list($value) && count($value) === 2 && is_string($value[0]) && in_array($value[0], ['ShallowReactive', 'Reactive'], true)) {
            return $this->resolveNuxtValue($value[1], $pool, $cache);
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->resolveNuxtValue($item, $pool, $cache), $value);
        }

        $resolved = [];
        foreach ($value as $key => $item) {
            $resolved[$key] = $this->resolveNuxtValue($item, $pool, $cache);
        }

        return $resolved;
    }

    private function normalizeStatus(?string $status): ?string
    {
        if (!$status) {
            return null;
        }

        $status = strtolower(trim($status));

        return match (true) {
            str_contains($status, 'under construction') => 'under_construction',
            str_contains($status, 'ready') => 'ready',
            str_contains($status, 'prelaunch'), str_contains($status, 'pre-launch') => 'prelaunch',
            default => null,
        };
    }

    private function extractVariantsFromNuxt(array $project): array
    {
        $variants = [];
        foreach (($project['unit_plan'] ?? []) as $group) {
            $unitType = $group['label'] ?? null;
            foreach (($group['data'] ?? []) as $index => $item) {
                $carpetArea = $item['carpet_area'] ?? null;
                $builtupArea = $item['built_up_area'] ?? $carpetArea;
                $displayPrice = $item['price'] ?? $item['price_preference'] ?? null;
                $sizeLabel = $builtupArea ? number_format((float) $builtupArea, 0) . ' Sq.ft.' : ($unitType ?: 'Variant');

                $variants[] = [
                    'unit_type' => $item['flat_type'] ?? $unitType,
                    'size_label' => $sizeLabel,
                    'builtup_area_sqft' => $builtupArea,
                    'carpet_area_sqft' => $carpetArea,
                    'base_rate_per_sqft' => null,
                    'manual_price_override' => $item['actual_price'] ?? null,
                    'status' => 'available',
                    'visible_on_public_page' => true,
                    'is_featured' => count($variants) === 0,
                    'is_price_on_request' => blank($item['actual_price'] ?? null),
                    'display_price' => $displayPrice,
                ];
            }
        }

        if (count($variants) > 0) {
            return $variants;
        }

        foreach (($project['configuration_summary'] ?? []) as $config) {
            $carpetRange = $config['carpet_area'] ?? null;
            $numericArea = $this->extractFirstNumber($carpetRange);
            $variants[] = [
                'unit_type' => $config['flat_type_name'] ?? null,
                'size_label' => $numericArea ? number_format($numericArea, 0) . ' Sq.ft.' : ($carpetRange ?: 'Variant'),
                'builtup_area_sqft' => $numericArea,
                'carpet_area_sqft' => $numericArea,
                'base_rate_per_sqft' => null,
                'manual_price_override' => null,
                'status' => 'available',
                'visible_on_public_page' => true,
                'is_featured' => count($variants) === 0,
                'is_price_on_request' => str_contains(strtolower((string) ($config['price'] ?? '')), 'request'),
                'display_price' => $config['price'] ?? null,
            ];
        }

        return $variants;
    }

    private function extractGalleryAssets(string $html, array $project): array
    {
        $assets = [];
        $seen = [];

        $imageUrls = is_array($project['image_url'] ?? null) ? $project['image_url'] : array_filter([$project['image_url'] ?? null]);
        foreach ($imageUrls as $index => $imageUrl) {
            if (!$imageUrl || isset($seen[$imageUrl])) {
                continue;
            }
            $seen[$imageUrl] = true;
            $assets[] = [
                'asset_type' => 'gallery_image',
                'title' => 'Project Image ' . ($index + 1),
                'value' => 'Detected from PropertyPistol',
                'external_url' => $imageUrl,
            ];
        }

        foreach (($project['master_plan_url'] ?? []) as $index => $imageUrl) {
            if (!$imageUrl || isset($seen[$imageUrl])) {
                continue;
            }
            $seen[$imageUrl] = true;
            $assets[] = [
                'asset_type' => 'gallery_image',
                'title' => $index === 0 ? 'Master Plan' : 'Master Plan ' . ($index + 1),
                'value' => 'Detected from PropertyPistol',
                'external_url' => $imageUrl,
            ];
        }

        foreach (($project['floor_plan_urls'] ?? []) as $index => $imageUrl) {
            if (!$imageUrl || isset($seen[$imageUrl])) {
                continue;
            }
            $seen[$imageUrl] = true;
            $assets[] = [
                'asset_type' => 'gallery_image',
                'title' => 'Floor Plan ' . ($index + 1),
                'value' => 'Detected from PropertyPistol',
                'external_url' => $imageUrl,
            ];
        }

        if (count($assets) < 2 && preg_match_all('/data-fancybox="project-gallery"[^>]+href="([^"]+)"/i', $html, $matches)) {
            foreach ($matches[1] as $index => $imageUrl) {
                if (!$imageUrl || isset($seen[$imageUrl])) {
                    continue;
                }
                $seen[$imageUrl] = true;
                $assets[] = [
                    'asset_type' => 'gallery_image',
                    'title' => 'Gallery Image ' . ($index + 1),
                    'value' => 'Detected from PropertyPistol',
                    'external_url' => $imageUrl,
                ];
            }
        }

        return array_slice($assets, 0, 12);
    }

    private function extractAmenities(array $project): array
    {
        return collect($project['amenities_details'] ?? [])
            ->map(function ($item) {
                return [
                    'name' => $item['name'] ?? null,
                    'icon_url' => $item['icon_url'] ?? null,
                ];
            })
            ->filter(fn ($item) => filled($item['name'] ?? null))
            ->values()
            ->all();
    }

    private function extractVideoAssets(array $project, ?string $fallbackVideoUrl): array
    {
        $assets = [];
        $seen = [];

        foreach (($project['videos'] ?? []) as $index => $videoUrl) {
            if (!$videoUrl || isset($seen[$videoUrl])) {
                continue;
            }

            $seen[$videoUrl] = true;
            $assets[] = [
                'asset_type' => 'video',
                'title' => $index === 0 ? 'Video Walkthrough' : 'Video Walkthrough ' . ($index + 1),
                'value' => 'Detected from PropertyPistol',
                'external_url' => $videoUrl,
            ];
        }

        if ($fallbackVideoUrl && !isset($seen[$fallbackVideoUrl])) {
            $assets[] = [
                'asset_type' => 'video',
                'title' => 'Video Walkthrough',
                'value' => 'Detected from PropertyPistol',
                'external_url' => $fallbackVideoUrl,
            ];
        }

        return $assets;
    }

    private function extractBrochureAsset(array $project): ?array
    {
        $brochureUrl = $project['brochure_url'] ?? null;
        if (!$brochureUrl) {
            return null;
        }

        return [
            'asset_type' => 'brochure',
            'title' => $project['brochure_name'] ?? 'Project Brochure',
            'value' => 'Detected from PropertyPistol',
            'external_url' => $brochureUrl,
        ];
    }

    private function formatStartingPrice(mixed $startingPrice): ?string
    {
        if (!is_numeric($startingPrice)) {
            return null;
        }

        $startingPrice = (float) $startingPrice;

        if ($startingPrice >= 10000000) {
            return number_format($startingPrice / 10000000, 2) . ' Cr';
        }

        if ($startingPrice >= 100000) {
            return number_format($startingPrice / 100000, 2) . ' L';
        }

        return number_format($startingPrice, 0);
    }

    private function formatPriceRangeFromConfigs(array $configs): ?string
    {
        $prices = collect($configs)
            ->pluck('price')
            ->filter()
            ->values();

        if ($prices->isEmpty()) {
            return null;
        }

        if ($prices->count() === 1) {
            return $prices->first();
        }

        return $prices->join(' | ');
    }

    private function formatRatePerSqft(?string $avgPrice): ?string
    {
        if (!$avgPrice) {
            return null;
        }

        return $avgPrice . ' per Sqft.';
    }

    private function numericRate(?string $avgPrice): ?float
    {
        if (!$avgPrice) {
            return null;
        }

        if (preg_match('/([\d.]+)\s*K/i', $avgPrice, $matches)) {
            return (float) $matches[1] * 1000;
        }

        return $this->extractFirstNumber($avgPrice);
    }

    private function formatSizeRangeFromNuxt(array $project): ?string
    {
        $range = $project['carpet_area'] ?? null;
        if (!$range) {
            return null;
        }

        return $range . ' Sq.ft.';
    }

    private function extractFirstNumber(?string $value): ?float
    {
        if (!$value) {
            return null;
        }

        if (!preg_match('/[\d,]+(?:\.\d+)?/', $value, $matches)) {
            return null;
        }

        return (float) str_replace(',', '', $matches[0]);
    }
}
