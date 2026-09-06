<?php

namespace App\Services\TravelTime;

use App\Models\ProjectTravelTimeLog;
use App\Models\Project;
use App\Models\SystemSettings;
use App\Services\TravelTime\Contracts\LocationSearchProvider;
use App\Services\TravelTime\Contracts\RouteProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TravelTimeService
{
    public function __construct(
        private readonly LocationSearchProvider $locationSearchProvider,
        private readonly RouteProvider $routeProvider,
    ) {
    }

    public function widgetPayload(Project $project): array
    {
        $page = $project->publicPage;
        $destination = $this->destinationForProject($project);
        $resolution = $this->resolvePopularOrigins($project);
        $popularOrigins = $resolution['origins'];

        return [
            'enabled' => $destination !== null,
            'provider_ready' => filled($this->apiKey()),
            'destination' => $destination,
            'popular_origins' => $popularOrigins,
            'origin_source' => $resolution['source'],
            'placeholder' => 'Enter your location',
            'helper_hint' => 'e.g. Hazratganj, Airport',
            'fallback_message' => 'Travel time will be available once project location is added.',
            'trust_text' => 'Approx travel time based on map data',
            'ui' => config('travel_time.ui'),
        ];
    }

    public function searchSuggestions(Project $project, string $query, ?string $shareToken = null): array
    {
        $normalized = trim($query);
        if (mb_strlen($normalized) < (int) config('travel_time.ui.min_query_length', 3)) {
            return [];
        }

        $cacheKey = 'travel-time:suggestions:' . md5(Str::lower($normalized));
        $cacheHit = Cache::has($cacheKey);

        $results = Cache::remember($cacheKey, (int) config('travel_time.cache.suggestions_ttl_seconds', 86400), function () use ($normalized) {
            return $this->locationSearchProvider->searchSuggestions(
                $normalized,
                (int) config('travel_time.ui.suggestions_limit', 5)
            );
        });

        $this->log($project, $shareToken, 'suggestions', 'search', [
            'query' => $normalized,
            'status' => empty($results) ? 'no_suggestions' : 'success',
            'cache_hit' => $cacheHit,
            'meta' => ['count' => count($results)],
        ]);

        return $results;
    }

    public function routeSummary(Project $project, array $origin, string $originSource = 'search', ?string $shareToken = null): array
    {
        $destination = $this->destinationForProject($project);
        if (!$destination) {
            return [
                'status' => 'project_coordinates_missing',
                'message' => 'Travel time will be available once project location is added.',
            ];
        }

        $originLabel = trim((string) Arr::get($origin, 'label'));
        $originLatitude = $this->floatOrNull(Arr::get($origin, 'latitude'));
        $originLongitude = $this->floatOrNull(Arr::get($origin, 'longitude'));

        if ($originLabel === '' || $originLatitude === null || $originLongitude === null) {
            return [
                'status' => 'route_unavailable',
                'message' => 'Travel time unavailable for this route.',
            ];
        }

        [$drive, $driveCacheHit] = $this->cachedRoute('driving', $originLatitude, $originLongitude, $destination['latitude'], $destination['longitude']);
        [$walk, $walkCacheHit] = $this->cachedRoute('walking', $originLatitude, $originLongitude, $destination['latitude'], $destination['longitude']);

        if (!$drive && !$walk) {
            $this->log($project, $shareToken, 'route', $originSource, [
                'origin_label' => $originLabel,
                'status' => 'route_unavailable',
                'cache_hit' => $driveCacheHit || $walkCacheHit,
            ]);

            return [
                'status' => 'route_unavailable',
                'message' => 'Travel time unavailable for this route.',
                'origin' => [
                    'label' => $originLabel,
                    'latitude' => $originLatitude,
                    'longitude' => $originLongitude,
                ],
                'destination' => $destination,
            ];
        }

        $this->log($project, $shareToken, 'route', $originSource, [
            'origin_label' => $originLabel,
            'status' => 'success',
            'drive_available' => (bool) $drive,
            'walk_available' => (bool) $walk,
            'cache_hit' => $driveCacheHit || $walkCacheHit,
        ]);

        return [
            'status' => 'success',
            'origin' => [
                'label' => $originLabel,
                'latitude' => $originLatitude,
                'longitude' => $originLongitude,
            ],
            'destination' => $destination,
            'drive' => $drive ? $this->presentRouteMode('Drive', $drive) : null,
            'walk' => $walk ? $this->presentRouteMode('Walk', $walk) : null,
            'directions_url' => $this->googleDirectionsUrl($originLatitude, $originLongitude, $destination['latitude'], $destination['longitude']),
        ];
    }

    public function destinationForProject(Project $project): ?array
    {
        $page = $project->publicPage;
        $latitude = $this->floatOrNull($page?->latitude);
        $longitude = $this->floatOrNull($page?->longitude);

        if ($latitude === null || $longitude === null) {
            $derived = $this->coordinatesFromMapEmbed((string) ($page?->map_embed ?? ''));
            $latitude = $latitude ?? data_get($derived, 'latitude');
            $longitude = $longitude ?? data_get($derived, 'longitude');
        }

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'label' => $project->name . ($project->formatted_location ? ', ' . $project->formatted_location : ''),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'map_zoom' => $page?->map_zoom ?: 13,
        ];
    }

    public function popularOriginsForProject(Project $project): array
    {
        return $this->resolvePopularOrigins($project)['origins'];
    }

    public function resolvePopularOrigins(Project $project): array
    {
        $pageOrigins = collect(Arr::wrap($project->publicPage?->popular_origins))
            ->map(fn ($origin) => $this->normalizeOrigin($origin))
            ->filter()
            ->sortBy('display_order')
            ->values();

        if ($pageOrigins->isNotEmpty()) {
            return [
                'source' => 'project',
                'origins' => $pageOrigins
                    ->take((int) config('travel_time.ui.max_popular_origins', 6))
                    ->values()
                    ->all(),
            ];
        }

        $cityOrigins = collect($this->cityDefaultsForProject($project))
            ->map(fn ($origin) => $this->normalizeOrigin($origin))
            ->filter()
            ->sortBy('display_order')
            ->take((int) config('travel_time.ui.max_popular_origins', 6))
            ->values()
            ->all();

        if (!empty($cityOrigins)) {
            return [
                'source' => 'city',
                'origins' => $cityOrigins,
            ];
        }

        return [
            'source' => 'search',
            'origins' => [],
        ];
    }

    private function cachedRoute(string $mode, float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): array
    {
        $cacheKey = 'travel-time:route:' . md5(implode('|', [
            'ola',
            $mode,
            round($originLatitude, 6),
            round($originLongitude, 6),
            round($destinationLatitude, 6),
            round($destinationLongitude, 6),
        ]));

        $cacheHit = Cache::has($cacheKey);
        $route = Cache::remember($cacheKey, (int) config('travel_time.cache.routes_ttl_seconds', 86400), function () use ($mode, $originLatitude, $originLongitude, $destinationLatitude, $destinationLongitude) {
            return $this->routeProvider->getDirections(
                $mode,
                $originLatitude,
                $originLongitude,
                $destinationLatitude,
                $destinationLongitude
            );
        });

        return [$route, $cacheHit];
    }

    private function presentRouteMode(string $label, array $route): array
    {
        $minutes = max(1, (int) ceil(((float) $route['duration_seconds']) / 60));
        $km = round(((float) $route['distance_meters']) / 1000, 1);

        return [
            'label' => $label,
            'minutes' => $minutes,
            'km' => $km,
            'display_time' => $minutes . ' mins',
            'display_distance' => $km . ' km',
        ];
    }

    private function googleDirectionsUrl(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): string
    {
        return 'https://www.google.com/maps/dir/?api=1&origin='
            . $originLatitude . ',' . $originLongitude
            . '&destination=' . $destinationLatitude . ',' . $destinationLongitude
            . '&travelmode=driving';
    }

    private function normalizeOrigin($origin): ?array
    {
        $label = trim((string) Arr::get($origin, 'label'));
        $latitude = $this->floatOrNull(Arr::get($origin, 'latitude'));
        $longitude = $this->floatOrNull(Arr::get($origin, 'longitude'));

        if ($label === '' || $latitude === null || $longitude === null) {
            return null;
        }

        return [
            'label' => $label,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'category' => trim((string) Arr::get($origin, 'category', '')),
            'display_order' => (int) Arr::get($origin, 'display_order', 0),
        ];
    }

    private function isLucknow(Project $project): bool
    {
        $city = Str::of((string) $project->city)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return $city === 'lucknow';
    }

    private function cityDefaultsForProject(Project $project): array
    {
        $normalizedCity = Str::of((string) $project->city)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();
        if ($normalizedCity === '') {
            return [];
        }

        $systemDefaults = json_decode((string) SystemSettings::get('travel_time_city_defaults', '{}'), true);
        $systemOrigins = Arr::get($systemDefaults, $normalizedCity);
        if (is_array($systemOrigins) && !empty($systemOrigins)) {
            return $systemOrigins;
        }

        return config('travel_time.city_defaults.' . $normalizedCity, []);
    }

    private function apiKey(): ?string
    {
        return SystemSettings::get('ola_maps_api_key')
            ?: config('travel_time.providers.ola.api_key');
    }

    private function floatOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function coordinatesFromMapEmbed(string $value): ?array
    {
        $patterns = [
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/!2d(-?\d+(?:\.\d+)?)!3d(-?\d+(?:\.\d+)?)/',
            '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/[?&]ll=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $value, $match)) {
                continue;
            }

            if ($pattern === '/!2d(-?\d+(?:\.\d+)?)!3d(-?\d+(?:\.\d+)?)/') {
                return [
                    'latitude' => (float) $match[2],
                    'longitude' => (float) $match[1],
                ];
            }

            return [
                'latitude' => (float) $match[1],
                'longitude' => (float) $match[2],
            ];
        }

        return null;
    }

    private function log(Project $project, ?string $shareToken, string $action, string $originSource, array $payload): void
    {
        if (!class_exists(ProjectTravelTimeLog::class) || !Schema::hasTable('project_travel_time_logs')) {
            return;
        }

        ProjectTravelTimeLog::query()->create([
            'project_id' => $project->id,
            'share_token' => $shareToken,
            'action' => $action,
            'origin_source' => $originSource,
            'query' => $payload['query'] ?? null,
            'origin_label' => $payload['origin_label'] ?? null,
            'status' => $payload['status'] ?? null,
            'drive_available' => $payload['drive_available'] ?? false,
            'walk_available' => $payload['walk_available'] ?? false,
            'cache_hit' => $payload['cache_hit'] ?? false,
            'provider' => 'ola',
            'meta' => $payload['meta'] ?? null,
        ]);
    }
}
