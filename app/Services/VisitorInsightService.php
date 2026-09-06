<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VisitorInsightService
{
    public function enrich(Request $request, string $eventName, array $meta = []): array
    {
        $userAgent = (string) $request->userAgent();
        $meta['device'] = array_merge(
            $this->parseUserAgent($userAgent),
            is_array($meta['device'] ?? null) ? $meta['device'] : []
        );

        if (in_array($eventName, ['proposal_opened', 'page_view'], true)) {
            $meta['location'] = $this->approximateLocation($request->ip());
        }

        if (in_array($eventName, ['location_shared', 'location_denied'], true)) {
            $meta['exact_location'] = $this->sanitizeExactLocation($meta['exact_location'] ?? []);
        }

        $meta['client_hints'] = array_merge(
            $this->extractClientHints($request),
            is_array($meta['client_hints'] ?? null) ? $meta['client_hints'] : []
        );

        return $meta;
    }

    private function extractClientHints(Request $request): array
    {
        $screenWidth = (int) $request->header('X-Screen-Width', 0);
        $screenHeight = (int) $request->header('X-Screen-Height', 0);
        $language = Str::limit((string) $request->header('X-Language', ''), 24, '');

        return [
            'screen_width' => $screenWidth > 0 ? $screenWidth : null,
            'screen_height' => $screenHeight > 0 ? $screenHeight : null,
            'language' => $language !== '' ? $language : null,
        ];
    }

    private function parseUserAgent(string $userAgent): array
    {
        $lower = strtolower($userAgent);
        $isTablet = str_contains($lower, 'ipad') || str_contains($lower, 'tablet');
        $isMobile = !$isTablet && (str_contains($lower, 'mobile') || str_contains($lower, 'android') || str_contains($lower, 'iphone'));

        return [
            'type' => $isTablet ? 'tablet' : ($isMobile ? 'mobile' : 'desktop'),
            'os' => $this->detectOs($lower),
            'browser' => $this->detectBrowser($lower),
        ];
    }

    private function detectOs(string $lowerUserAgent): string
    {
        return match (true) {
            str_contains($lowerUserAgent, 'android') => 'Android',
            str_contains($lowerUserAgent, 'iphone'), str_contains($lowerUserAgent, 'ipad') => 'iOS',
            str_contains($lowerUserAgent, 'windows') => 'Windows',
            str_contains($lowerUserAgent, 'mac os'), str_contains($lowerUserAgent, 'macintosh') => 'macOS',
            str_contains($lowerUserAgent, 'linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private function detectBrowser(string $lowerUserAgent): string
    {
        return match (true) {
            str_contains($lowerUserAgent, 'edg/') => 'Edge',
            str_contains($lowerUserAgent, 'brave') => 'Brave',
            str_contains($lowerUserAgent, 'opr/'), str_contains($lowerUserAgent, 'opera') => 'Opera',
            str_contains($lowerUserAgent, 'chrome/') && !str_contains($lowerUserAgent, 'chromium') => 'Chrome',
            str_contains($lowerUserAgent, 'safari/') && !str_contains($lowerUserAgent, 'chrome/') => 'Safari',
            str_contains($lowerUserAgent, 'firefox/') => 'Firefox',
            default => 'Unknown',
        };
    }

    private function approximateLocation(?string $ip): array
    {
        if (!$ip || $this->isPrivateIp($ip)) {
            return ['status' => 'unavailable'];
        }

        try {
            $response = Http::timeout(2)->acceptJson()->get('https://ipwho.is/' . urlencode($ip));
            if (!$response->ok() || !$response->json('success')) {
                return ['status' => 'unavailable'];
            }

            return [
                'status' => 'available',
                'country' => Str::limit((string) $response->json('country'), 80, ''),
                'region' => Str::limit((string) $response->json('region'), 80, ''),
                'city' => Str::limit((string) $response->json('city'), 80, ''),
                'timezone' => Str::limit((string) data_get($response->json('timezone'), 'id'), 80, ''),
            ];
        } catch (\Throwable) {
            return ['status' => 'unavailable'];
        }
    }

    private function sanitizeExactLocation(array $location): array
    {
        $permission = (string) ($location['permission'] ?? 'unknown');
        if ($permission !== 'granted') {
            return ['permission' => $permission === 'denied' ? 'denied' : 'unavailable'];
        }

        return [
            'permission' => 'granted',
            'lat' => isset($location['lat']) ? round((float) $location['lat'], 2) : null,
            'lng' => isset($location['lng']) ? round((float) $location['lng'], 2) : null,
            'accuracy_m' => isset($location['accuracy_m']) ? (int) $location['accuracy_m'] : null,
        ];
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
