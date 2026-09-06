<?php

namespace App\Services\TravelTime\Contracts;

interface RouteProvider
{
    public function getDirections(string $mode, float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): ?array;
}
