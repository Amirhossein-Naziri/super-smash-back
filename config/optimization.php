<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Optimization Settings
    |--------------------------------------------------------------------------
    |
    | These settings help optimize the application performance
    |
    */

    'cache' => [
        'enabled' => env('CACHE_ENABLED', true),
        'default_ttl' => env('CACHE_DEFAULT_TTL', 300), // 5 minutes
        'stages_ttl' => env('CACHE_STAGES_TTL', 600), // 10 minutes
        'users_ttl' => env('CACHE_USERS_TTL', 300), // 5 minutes
        'admin_ttl' => env('CACHE_ADMIN_TTL', 600), // 10 minutes
    ],

    'database' => [
        'query_logging' => env('DB_QUERY_LOGGING', false),
        'connection_pooling' => env('DB_CONNECTION_POOLING', true),
        'max_connections' => env('DB_MAX_CONNECTIONS', 100),
    ],

    'telegram' => [
        'webhook_timeout' => env('TELEGRAM_WEBHOOK_TIMEOUT', 30),
        'background_processing' => env('TELEGRAM_BACKGROUND_PROCESSING', true),
        'rate_limiting' => env('TELEGRAM_RATE_LIMITING', true),
        'max_requests_per_minute' => env('TELEGRAM_MAX_REQUESTS_PER_MINUTE', 30),
    ],

    'performance' => [
        'memory_limit' => env('MEMORY_LIMIT', '256M'),
        'max_execution_time' => env('MAX_EXECUTION_TIME', 30),
        'gzip_compression' => env('GZIP_COMPRESSION', true),
        'response_caching' => env('RESPONSE_CACHING', true),
    ],

    'monitoring' => [
        'enabled' => env('MONITORING_ENABLED', true),
        'log_slow_queries' => env('LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'log_memory_usage' => env('LOG_MEMORY_USAGE', true),
        'memory_threshold' => env('MEMORY_THRESHOLD', 128), // MB
    ],
];
