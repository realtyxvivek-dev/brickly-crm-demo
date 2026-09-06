<?php

namespace App\Services\ProjectUrlImport\Parsers\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait ParsesPortalHtml
{
    protected function fetchHtml(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Accept-Language' => 'en-IN,en;q=0.9',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->timeout(20)->retry(1, 250)->get($url);

        $response->throw();

        return (string) $response->body();
    }

    protected function normalizeWhitespace(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value ?? '');
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function firstPattern(string $subject, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches)) {
                return $this->normalizeWhitespace($matches[1] ?? $matches[0] ?? null);
            }
        }

        return null;
    }

    protected function allPatternMatches(string $subject, string $pattern): array
    {
        if (!preg_match_all($pattern, $subject, $matches, PREG_SET_ORDER)) {
            return [];
        }

        return $matches;
    }

    protected function metaContent(string $html, string $property): ?string
    {
        $quoted = preg_quote($property, '/');

        return $this->firstPattern($html, [
            '/<meta[^>]+(?:property|name)="' . $quoted . '"[^>]+content="([^"]+)"/i',
            "/<meta[^>]+(?:property|name)='" . $quoted . "'[^>]+content='([^']+)'/i",
            '/<meta[^>]+content="([^"]+)"[^>]+(?:property|name)="' . $quoted . '"/i',
            "/<meta[^>]+content='([^']+)'[^>]+(?:property|name)='" . $quoted . "'/i",
        ]);
    }

    protected function titleTag(string $html): ?string
    {
        return $this->firstPattern($html, [
            '/<title[^>]*>(.*?)<\/title>/is',
        ]);
    }

    protected function textContent(string $html): string
    {
        return Str::of($html)
            ->replaceMatches('/<script\b[^>]*>.*?<\/script>/is', ' ')
            ->replaceMatches('/<style\b[^>]*>.*?<\/style>/is', ' ')
            ->replaceMatches('/<[^>]+>/', ' ')
            ->squish()
            ->toString();
    }

    protected function jsonLdObjects(string $html): array
    {
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches);

        $objects = [];
        foreach ($matches[1] ?? [] as $jsonChunk) {
            $decoded = json_decode(trim($jsonChunk), true);

            if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
                continue;
            }

            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                foreach ($decoded['@graph'] as $graphNode) {
                    if (is_array($graphNode)) {
                        $objects[] = $graphNode;
                    }
                }
                continue;
            }

            if (array_is_list($decoded)) {
                foreach ($decoded as $row) {
                    if (is_array($row)) {
                        $objects[] = $row;
                    }
                }
                continue;
            }

            $objects[] = $decoded;
        }

        return $objects;
    }

    protected function extractReraCodes(string $subject): array
    {
        preg_match_all('/\b(?:UPRERA|UPRERAPRJ)[A-Z0-9]+\b/i', $subject, $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[0] ?? [])));
    }

    protected function inferProjectStatus(string $subject): ?string
    {
        $subject = strtolower($subject);

        return match (true) {
            str_contains($subject, 'under construction') => 'under_construction',
            str_contains($subject, 'ready to move'), str_contains($subject, 'ready possession'), str_contains($subject, 'ready') => 'ready',
            str_contains($subject, 'prelaunch'), str_contains($subject, 'pre-launch') => 'prelaunch',
            default => null,
        };
    }

    protected function extractPossession(string $subject): ?string
    {
        return $this->firstPattern($subject, [
            '/(?:possession(?: date)?|possession by)\s*[:\-]?\s*([A-Za-z]+\s+\d{4})/i',
            '/(?:ready to move by|delivery by)\s*[:\-]?\s*([A-Za-z]+\s+\d{4})/i',
        ]);
    }

    protected function extractPriceRange(string $subject): ?string
    {
        return $this->firstPattern($subject, [
            '/(₹\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?\s*(?:-|to)\s*₹?\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?)/iu',
            '/(Rs\.?\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?\s*(?:-|to)\s*Rs\.?\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?)/iu',
        ]);
    }

    protected function extractStartingPrice(string $subject): ?string
    {
        return $this->firstPattern($subject, [
            '/(?:starting from|price starts at|starting price)\s*[:\-]?\s*(₹\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?)/iu',
            '/(₹\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?)/iu',
        ]);
    }

    protected function extractRatePerSqft(string $subject): ?string
    {
        return $this->firstPattern($subject, [
            '/(₹\s?[\d,]+(?:\.\d+)?\s*(?:per|\/)\s*sq\.?\s*ft\.?)/iu',
            '/(₹\s?[\d,]+(?:\.\d+)?\s*per\s*sq\.?\s*ft\.?)/iu',
        ]);
    }

    protected function extractSizeRange(string $subject): ?string
    {
        return $this->firstPattern($subject, [
            '/(\d{3,5}\s*(?:to|-)\s*\d{3,5}\s*sq\.?\s*ft\.?)/iu',
            '/(\d{3,5}\s*(?:to|-)\s*\d{3,5}\s*sqft)/iu',
        ]);
    }

    protected function extractVariantsFromText(string $subject): array
    {
        $variants = [];

        foreach ($this->allPatternMatches($subject, '/\b((?:\d(?:\.\d+)?)\s*BHK)\b[^₹\d]{0,40}?(\d{3,5})\s*sq\.?\s*ft\.?(?:[^₹]{0,50}?(₹\s?[\d.,]+\s*(?:Cr|L|Lakh|Crore)?))?/iu') as $match) {
            $unitType = strtoupper($this->normalizeWhitespace($match[1] ?? ''));
            $size = (int) ($match[2] ?? 0);
            if ($unitType === '' || $size <= 0) {
                continue;
            }

            $key = $unitType . '|' . $size;
            $variants[$key] = [
                'unit_type' => $unitType,
                'size_label' => $size . ' Sq.ft.',
                'builtup_area_sqft' => $size,
                'carpet_area_sqft' => null,
                'base_rate_per_sqft' => null,
                'manual_price_override' => null,
                'status' => 'available',
                'visible_on_public_page' => true,
                'is_featured' => count($variants) === 0,
                'is_price_on_request' => blank($match[3] ?? null),
                'display_price' => $this->normalizeWhitespace($match[3] ?? null),
            ];
        }

        return array_values($variants);
    }

    protected function confidence(string $level): string
    {
        return in_array($level, ['High', 'Medium', 'Needs Review'], true) ? $level : 'Needs Review';
    }
}
