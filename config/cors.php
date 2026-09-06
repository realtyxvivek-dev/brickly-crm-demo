<?php

// Get allowed origins from environment
$allowedOrigins = env('CORS_ALLOWED_ORIGINS');

if ($allowedOrigins) {
    // If explicitly set, use comma-separated list
    $allowedOrigins = array_map('trim', explode(',', $allowedOrigins));
} else {
    // Auto-detect based on environment
    $appEnv = env('APP_ENV', 'local');
    $appUrl = env('APP_URL');
    
    if ($appEnv === 'production' && $appUrl) {
        // In production, default to APP_URL for security
        $parsedUrl = parse_url($appUrl);
        if ($parsedUrl && isset($parsedUrl['scheme']) && isset($parsedUrl['host'])) {
            $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
            $allowedOrigins = [$parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $port];
        } else {
            $allowedOrigins = ['*']; // Fallback if URL parsing fails
        }
    } else {
        // In development, allow all origins
        $allowedOrigins = ['*'];
    }
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

