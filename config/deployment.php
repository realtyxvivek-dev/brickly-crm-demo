<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    |
    | Configure deployment settings for one-click deployment from localhost
    | to production server.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | If your production server has a webhook endpoint that triggers
    | git pull and deployment, set the URL here.
    | Example: https://yoursite.com/api/deploy/webhook
    |
    */
    'webhook_url' => env('DEPLOYMENT_WEBHOOK_URL', null),

    /*
    |--------------------------------------------------------------------------
    | SSH Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SSH connection for direct server deployment.
    | Leave null if using webhook method.
    |
    */
    'ssh' => [
        'host' => env('DEPLOYMENT_SSH_HOST', null),
        'port' => env('DEPLOYMENT_SSH_PORT', 22),
        'username' => env('DEPLOYMENT_SSH_USERNAME', null),
        'private_key_path' => env('DEPLOYMENT_SSH_KEY_PATH', null),
        'deploy_path' => env('DEPLOYMENT_SSH_DEPLOY_PATH', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Git Configuration
    |--------------------------------------------------------------------------
    |
    | Git repository settings for deployment.
    |
    */
    'git' => [
        'branch' => env('DEPLOYMENT_GIT_BRANCH', 'main'),
        'remote' => env('DEPLOYMENT_GIT_REMOTE', 'origin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Commands
    |--------------------------------------------------------------------------
    |
    | Commands to run on server after git pull.
    | These will be executed in order.
    |
    */
    'commands' => [
        'composer install --no-dev --optimize-autoloader',
        'php artisan migrate --force',
        'php artisan config:cache',
        'php artisan route:cache',
        'php artisan view:cache',
    ],
];
