<?php

return [
    'meta_sync_enabled' => filter_var(env('META_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'meta_dataset_id' => env('META_DATASET_ID'),
    'meta_system_user_token' => env('META_SYSTEM_USER_TOKEN'),
    'test_event_code' => env('META_TEST_EVENT_CODE'),
];
