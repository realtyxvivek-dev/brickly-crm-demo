<?php

namespace App\Services\TravelTime\Providers;

use App\Models\SystemSettings;
use App\Services\TravelTime\Contracts\LocationSearchProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class OlaLocationSearchProvider implements LocationSearchProvider
{
    public function searchSuggestions(string $query, int $limit = 5): array
    {
        $apiKey = $this->apiKey();
        if (!$apiKey) {
            return [];
        }

        $response = Http::timeout(8)
            ->acceptJson()
            ->get(config('travel_time.providers.ola.search_url'), [
                'api_key' => $apiKey,
                'input' => $query,
                'limit' => $limit,
            ]);

        if (!$response->successful()) {
            return [];
        }

        $rows = Arr::wrap(
            data_get($response->json(), 'predictions')
            ?? data_get($response->json(), 'results')
            ?? data_get($response->json(), 'data')
            ?? []
        );

        return collect($rows)
            ->map(function ($row) {
                $latitude = $this->extractFloat($row, [
                    'latitude',
                    'lat',
                    'location.lat',
                    'geometry.location.lat',
                ]);
                $longitude = $this->extractFloat($row, [
                    'longitude',
                    'lng',
                    'lon',
                    'location.lng',
                    'location.lon',
                    'geometry.location.lng',
                ]);

                if ($latitude === null || $longitude === null) {
                    return null;
                }

                $label = (string) (
                    data_get($row, 'description')
                    ?? data_get($row, 'display_name')
                    ?? data_get($row, 'formatted_address')
                    ?? data_get($row, 'name')
                    ?? ''
                );

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function apiKey(): ?string
    {
        return SystemSettings::get('ola_maps_api_key')
            ?: config('travel_time.providers.ola.api_key');
    }

    private function extractFloat(array $row, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = data_get($row, $path);
            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        return null;
    }
}
