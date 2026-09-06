<?php

namespace App\Services\ProjectUrlImport\Parsers;

use App\Services\ProjectUrlImport\Parsers\Concerns\ParsesPortalHtml;

class HousingProjectParser implements ProjectUrlParserInterface
{
    use ParsesPortalHtml;

    public function sourceKey(): string
    {
        return 'housing';
    }

    public function parserVersion(): string
    {
        return 'housing-v1';
    }

    public function extract(string $url): array
    {
        $html = $this->fetchHtml($url);
        $text = $this->textContent($html);
        $jsonLd = $this->jsonLdObjects($html);
        $warnings = [];
        $failedSelectors = [];

        $projectName = $this->metaContent($html, 'og:title')
            ?: $this->firstPattern($text, ['/buy\s+(.+?)\s+by\s+/i'])
            ?: $this->titleTag($html);

        if (!$projectName) {
            $failedSelectors[] = 'project_name';
        }

        $builder = $this->firstPattern($text, [
            '/\bby\s+([A-Za-z0-9&.\- ]+?)\s+in\s+/i',
        ]);

        if (!$builder) {
            $failedSelectors[] = 'builder_name';
            $warnings[] = 'Builder name was not confidently detected.';
        }

        $area = $this->firstPattern($text, [
            '/\bin\s+([A-Za-z0-9&.\- ]+?),\s*Lucknow\b/i',
        ]);
        $city = str_contains(strtolower($text), 'lucknow') ? 'Lucknow' : null;

        if (!$area) {
            $failedSelectors[] = 'area';
        }

        $overview = $this->metaContent($html, 'description')
            ?: $this->metaContent($html, 'og:description');

        $reraCodes = $this->extractReraCodes($text);
        if (count($reraCodes) === 0) {
            $warnings[] = 'RERA number not detected.';
        }

        $variants = $this->extractVariantsFromText($text);
        if (count($variants) === 0) {
            $warnings[] = 'No structured unit variants were detected.';
            $failedSelectors[] = 'variants';
        }

        $galleryImages = [];
        if (preg_match_all('/https:\/\/[^"\']+\.(?:jpg|jpeg|png|webp)/i', $html, $matches)) {
            $galleryImages = array_slice(array_values(array_unique($matches[0])), 0, 8);
        }

        $brochureUrl = $this->firstPattern($html, [
            '/https:\/\/[^"\']*brochure[^"\']+/i',
        ]);

        return [
            'project' => [
                'builder_name' => $builder,
                'project_name' => $projectName,
                'public_title' => $projectName,
                'subtitle' => null,
                'short_overview' => $overview,
                'project_type' => 'residential',
                'residential_sub_type' => 'flat',
                'project_status' => $this->inferProjectStatus($text),
                'availability_type' => 'fresh',
                'city' => $city,
                'area' => $area,
                'address' => $area && $city ? $area . ', ' . $city : null,
                'rera_no' => count($reraCodes) ? implode(', ', $reraCodes) : null,
                'possession_date' => $this->extractPossession($text),
                'location_summary' => $area && $city ? $area . ', ' . $city : null,
                'base_rate_per_sqft' => null,
                'rounding_rule' => 'nearest_1000',
                'call_phone' => null,
                'whatsapp_number' => null,
                'show_call' => false,
                'show_whatsapp' => false,
                'show_book_visit' => false,
                'show_request_callback' => true,
            ],
            'pricing' => [
                'starting_price' => $this->extractStartingPrice($text),
                'price_range' => $this->extractPriceRange($text),
                'rate_per_sqft' => $this->extractRatePerSqft($text),
                'size_range' => $this->extractSizeRange($text),
            ],
            'variants' => $variants,
            'assets' => array_values(array_filter([
                $brochureUrl ? [
                    'asset_type' => 'brochure',
                    'title' => 'Brochure',
                    'value' => 'Detected from Housing',
                    'external_url' => $brochureUrl,
                ] : null,
                ...array_map(fn (string $imageUrl, int $index) => [
                    'asset_type' => 'gallery_image',
                    'title' => 'Gallery Image ' . ($index + 1),
                    'value' => 'Detected from Housing',
                    'external_url' => $imageUrl,
                ], $galleryImages, array_keys($galleryImages)),
            ])),
            'landmarks' => [],
            'amenities' => [],
            'confidence' => [
                'project.builder_name' => $builder ? 'Medium' : 'Needs Review',
                'project.project_name' => $projectName ? 'High' : 'Needs Review',
                'project.city' => $city ? 'High' : 'Needs Review',
                'project.area' => $area ? 'Medium' : 'Needs Review',
                'project.rera_no' => count($reraCodes) ? 'High' : 'Needs Review',
                'pricing.starting_price' => $this->extractStartingPrice($text) ? 'Medium' : 'Needs Review',
                'pricing.price_range' => $this->extractPriceRange($text) ? 'Medium' : 'Needs Review',
                'pricing.rate_per_sqft' => $this->extractRatePerSqft($text) ? 'Medium' : 'Needs Review',
                'variants' => count($variants) ? 'Medium' : 'Needs Review',
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
}
