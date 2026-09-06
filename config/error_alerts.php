<?php

return [
    'telegram' => [
        'enabled' => filter_var(env('ERROR_ALERT_TELEGRAM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'bot_token' => env('ERROR_ALERT_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('ERROR_ALERT_TELEGRAM_CHAT_ID'),
    ],
    'lead_duplicates' => [
        'monitoring_enabled' => filter_var(env('LEAD_DUPLICATE_MONITORING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'monitoring_until' => env('LEAD_DUPLICATE_MONITORING_UNTIL'),
    ],
];
