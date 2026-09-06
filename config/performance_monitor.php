<?php

return [
    'enabled' => env('PERFORMANCE_MONITOR_ENABLED', env('APP_ENV') === 'production'),
    'log_channel' => env('PERFORMANCE_MONITOR_CHANNEL', 'performance'),
    'request_threshold_ms' => (float) env('PERFORMANCE_MONITOR_REQUEST_THRESHOLD_MS', 800),
    'query_count_threshold' => (int) env('PERFORMANCE_MONITOR_QUERY_COUNT_THRESHOLD', 25),
    'query_time_threshold_ms' => (float) env('PERFORMANCE_MONITOR_QUERY_TIME_THRESHOLD_MS', 250),
    'slow_query_threshold_ms' => (float) env('PERFORMANCE_MONITOR_SLOW_QUERY_THRESHOLD_MS', 75),
    'max_logged_queries' => (int) env('PERFORMANCE_MONITOR_MAX_LOGGED_QUERIES', 5),
];
