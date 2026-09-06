<?php

return [
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
    'login_config_id' => env('META_LOGIN_CONFIG_ID'),
    'graph_version' => env('META_GRAPH_VERSION', 'v23.0'),
    'redirect_uri' => env('META_OAUTH_REDIRECT_URI', rtrim((string) env('APP_URL', 'https://crm.bihtech.in'), '/') . '/integrations/facebook-connector/callback'),
    'webhook_verify_token' => env('META_OAUTH_WEBHOOK_VERIFY_TOKEN'),
    'webhook_signature_enabled' => filter_var(env('META_OAUTH_WEBHOOK_SIGNATURE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'default_scopes' => [
        'leads_retrieval',
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_metadata',
    ],
];
