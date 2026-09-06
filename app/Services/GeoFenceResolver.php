<?php

namespace App\Services;

use App\Models\OfficeLocation;

class GeoFenceResolver
{
    public function resolve(?OfficeLocation $office, ?float $latitude, ?float $longitude): array
    {
        if (!$office || $latitude === null || $longitude === null || $office->latitude === null || $office->longitude === null) {
            return [
                'office' => $office,
                'distance' => null,
                'inside' => null,
            ];
        }

        $distance = $this->distanceMeters($latitude, $longitude, (float) $office->latitude, (float) $office->longitude);

        return [
            'office' => $office,
            'distance' => round($distance, 2),
            'inside' => $distance <= (float) $office->radius_meters,
        ];
    }

    public function distanceMeters(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
