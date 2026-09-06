<?php

namespace App\Services\TravelTime\Providers;

use App\Models\SystemSettings;
use App\Services\TravelTime\Contracts\RouteProvider;
use Illuminate\Support\Facades\Http;

class OlaRouteProvider implements RouteProvider
{
    public function getDirections(string $mode, float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): ?array
    {
        $apiKey = $this->apiKey();
        if (!$apiKey) {
            return null;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->get(config('travel_time.providers.ola.directions_url'), [
                'api_key' => $apiKey,
                'origin' => $originLatitude . ',' . $originLongitude,
                'destination' => $destinationLatitude . ',' . $destinationLongitude,
                'mode' => $mode,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        $route = data_get($payload, 'routes.0')
            ?? data_get($payload, 'data.routes.0')
            ?? data_get($payload, 'route')
            ?? null;

        if (!is_array($route)) {
            return null;
        }

        $distanceMeters = data_get($route, 'distance')
            ?? data_get($route, 'summary.distance')
            ?? data_get($route, 'legs.0.distance')
            ?? null;

        $durationSeconds = data_get($route, 'duration')
            ?? data_get($route, 'summary.duration')
            ?? data_get($route, 'legs.0.duration')
            ?? null;

        if (!is_numeric($distanceMeters) || !is_numeric($durationSeconds)) {
            return null;
        }

        return [
            'distance_meters' => (float) $distanceMeters,
            'duration_seconds' => (float) $durationSeconds,
        ];
    }

    private function apiKey(): ?string
    {
        return SystemSettings::get('ola_maps_api_key')
            ?: config('travel_time.providers.ola.api_key');
    }
}
