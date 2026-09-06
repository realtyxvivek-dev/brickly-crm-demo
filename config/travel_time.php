<?php

return [
    'ui' => [
        'max_popular_origins' => 6,
        'suggestions_limit' => 5,
        'min_query_length' => 3,
        'search_debounce_ms' => 350,
    ],

    'providers' => [
        'ola' => [
            'api_key' => env('OLA_MAPS_API_KEY'),
            'search_url' => env('OLA_MAPS_AUTOCOMPLETE_URL', 'https://api.olamaps.io/places/v1/autocomplete'),
            'directions_url' => env('OLA_MAPS_DIRECTIONS_URL', 'https://api.olamaps.io/routing/v1/directions'),
        ],
    ],

    'cache' => [
        'suggestions_ttl_seconds' => 86400,
        'routes_ttl_seconds' => 86400,
    ],

    'city_defaults' => [
        'lucknow' => [
            [
                'label' => 'Hazratganj',
                'latitude' => 26.8506,
                'longitude' => 80.9462,
                'category' => 'City Center',
                'display_order' => 1,
            ],
            [
                'label' => 'Chaudhary Charan Singh Airport',
                'latitude' => 26.7606,
                'longitude' => 80.8893,
                'category' => 'Airport',
                'display_order' => 2,
            ],
            [
                'label' => 'Charbagh Railway Station',
                'latitude' => 26.8319,
                'longitude' => 80.9232,
                'category' => 'Railway Station',
                'display_order' => 3,
            ],
            [
                'label' => 'Gomti Nagar',
                'latitude' => 26.8467,
                'longitude' => 81.0088,
                'category' => 'Residential Hub',
                'display_order' => 4,
            ],
            [
                'label' => 'Alambagh',
                'latitude' => 26.7892,
                'longitude' => 80.9024,
                'category' => 'Transit Hub',
                'display_order' => 5,
            ],
        ],
    ],
];
