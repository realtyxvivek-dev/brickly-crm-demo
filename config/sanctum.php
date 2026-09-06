<?php

use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

// Get default localhost domains for development
$defaultDomains = 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,127.0.0.1:8007,127.0.0.1:8008,localhost:8008,::1';

// Extract domain from APP_URL if set
$appUrl = env('APP_URL');
$appUrlDomain = null;
if ($appUrl) {
    // Parse URL to extract domain and port
    $parsedUrl = parse_url($appUrl);
    if ($parsedUrl && isset($parsedUrl['host'])) {
        $host = $parsedUrl['host'];
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $appUrlDomain = $host . $port;
    }
}

// Get custom domains from environment (comma-separated)
$customDomains = env('SANCTUM_STATEFUL_DOMAINS', '');

// Build domains list
$domains = [];
if ($customDomains) {
    // If custom domains are provided, use them
    $domains = array_merge($domains, explode(',', $customDomains));
} else {
    // Otherwise, use defaults
    $domains = array_merge($domains, explode(',', $defaultDomains));
    
    // Add APP_URL domain if it's different from defaults
    if ($appUrlDomain && !in_array($appUrlDomain, $domains)) {
        $domains[] = $appUrlDomain;
    }
    
    // Also add current application URL with port (Sanctum helper)
    $currentAppUrl = Sanctum::currentApplicationUrlWithPort();
    if ($currentAppUrl && !in_array($currentAppUrl, $domains)) {
        $domains[] = $currentAppUrl;
    }
}

// Remove empty values and trim
$domains = array_filter(array_map('trim', $domains));
$domains = array_unique($domains);

return [

    'stateful' => array_values($domains),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],

];

