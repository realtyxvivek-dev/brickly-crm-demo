<?php

return [
    'graph_version' => env('INSTAGRAM_GRAPH_VERSION', 'v21.0'),
    'client_id' => env('INSTAGRAM_CLIENT_ID'),
    'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
    'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
    'scopes' => array_values(array_filter(array_map('trim', explode(',', env(
        'INSTAGRAM_SCOPES',
        'instagram_business_basic,instagram_business_manage_comments,instagram_business_manage_messages'
    ))))),
    'base_url' => env('INSTAGRAM_BASE_URL', 'https://graph.instagram.com'),
    'auth_url' => env('INSTAGRAM_AUTH_URL', 'https://www.instagram.com/oauth/authorize'),
    'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
    'app_secret' => env('INSTAGRAM_APP_SECRET', env('INSTAGRAM_CLIENT_SECRET')),
    'webhook_signature_enabled' => filter_var(env('INSTAGRAM_WEBHOOK_SIGNATURE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
