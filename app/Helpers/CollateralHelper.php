<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CollateralHelper
{
    /**
     * Detect link type from URL.
     */
    public static function detectLinkType(string $url): ?string
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        } elseif (str_contains($url, 'drive.google.com')) {
            return 'google_drive';
        }

        return null;
    }

    /**
     * Validate YouTube link.
     */
    public static function validateYouTubeLink(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be'));
    }

    public static function extractYouTubeVideoId(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be' && $path !== '') {
            return strtok($path, '?') ?: null;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            if (!empty($query['v'])) {
                return (string) $query['v'];
            }

            if (str_starts_with($path, 'shorts/')) {
                return strtok(substr($path, 7), '?') ?: null;
            }

            if (str_starts_with($path, 'embed/')) {
                return strtok(substr($path, 6), '?') ?: null;
            }
        }

        return null;
    }

    public static function getYouTubeThumbnailUrl(string $url): ?string
    {
        $videoId = self::extractYouTubeVideoId($url);

        return $videoId ? 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg' : null;
    }

    public static function getYouTubeEmbedUrl(string $url): ?string
    {
        $videoId = self::extractYouTubeVideoId($url);

        return $videoId
            ? 'https://www.youtube.com/embed/' . $videoId . '?rel=0&modestbranding=1&controls=0&iv_load_policy=3&disablekb=1&playsinline=1'
            : null;
    }

    public static function getYouTubeTitle(string $url): ?string
    {
        $videoId = self::extractYouTubeVideoId($url);

        if (!$videoId) {
            return null;
        }

        if (app()->environment('testing')) {
            return null;
        }

        return Cache::remember('youtube_title_' . $videoId, now()->addDays(7), function () use ($url): ?string {
            try {
                $response = Http::timeout(3)->get('https://www.youtube.com/oembed', [
                    'url' => $url,
                    'format' => 'json',
                ]);

                if (!$response->successful()) {
                    return null;
                }

                return trim((string) $response->json('title')) ?: null;
            } catch (\Throwable $exception) {
                return null;
            }
        });
    }

    /**
     * Validate Google Drive link.
     */
    public static function validateDriveLink(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               str_contains($url, 'drive.google.com');
    }

    /**
     * Get category icon.
     */
    public static function getCategoryIcon(string $category): string
    {
        return match($category) {
            'brochure' => '📄',
            'floor_plans' => '📐',
            'layout_plan' => '🗺',
            'videos' => '🎥',
            'price_sheet' => '💰',
            'legal_approvals' => '📁',
            default => '📋',
        };
    }

    /**
     * Generate button HTML (for Blade views).
     */
    public static function generateButtonHtml(array $buttonData): string
    {
        $icon = $buttonData['icon'] ?? '📋';
        $category = $buttonData['category'] ?? 'other';
        $count = $buttonData['count'] ?? 0;
        $hasLatest = $buttonData['has_latest'] ?? false;
        $items = $buttonData['items'] ?? [];

        $buttonClass = $hasLatest ? 'btn-primary' : 'btn-secondary';
        $badge = $count > 1 ? " <span class='badge'>{$count}</span>" : '';
        $latestBadge = $hasLatest ? " <span class='badge badge-latest'>Latest</span>" : '';

        $html = "<button class='btn {$buttonClass} collateral-btn' data-category='{$category}'>";
        $html .= "{$icon} " . ucfirst(str_replace('_', ' ', $category));
        $html .= $badge . $latestBadge;
        $html .= "</button>";

        return $html;
    }
}
