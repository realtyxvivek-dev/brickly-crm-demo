<?php

namespace App\Services\TravelTime\Contracts;

interface LocationSearchProvider
{
    public function searchSuggestions(string $query, int $limit = 5): array;
}
