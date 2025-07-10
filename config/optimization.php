<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for various performance
    | optimizations including caching, database, and monitoring settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // Default cache TTL in minutes
        'default_ttl' => env('CACHE_DEFAULT_TTL', 30),
        
        // Long cache TTL for static data
        'long_ttl' => env('CACHE_LONG_TTL', 120),
        
        // Very long cache TTL for rarely changing data
        'very_long_ttl' => env('CACHE_VERY_LONG_TTL', 1440), // 24 hours
        
        // Cache tags configuration
        'tags' => [
            'enabled' => env('CACHE_TAGS_ENABLED', true),
            'prefix' => env('CACHE_TAGS_PREFIX', 'akudihatinya'),
        ],
        
        // Response caching
        'response' => [
            'enabled' => env('RESPONSE_CACHE_ENABLED', true),
            'ttl' => env('RESPONSE_CACHE_TTL', 15),
            'exclude_routes' => [
                'api.auth.*',
                'api.logout',
                'api.profile.update',
            ],
        ],
        
        // Statistics caching
        'statistics' => [
            'enabled' => env('STATISTICS_CACHE_ENABLED', true),
            'ttl' => env('STATISTICS_CACHE_TTL', 30),
            'pre_warm' => env('STATISTICS_CACHE_PRE_WARM', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Optimization
    |--------------------------------------------------------------------------
    */
    'database' => [
        // Query optimization
        'query' => [
            'log_slow_queries' => env('DB_LOG_SLOW_QUERIES', true),
            'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
            'explain_queries' => env('DB_EXPLAIN_QUERIES', false),
        ],
        
        // Connection optimization
        'connection' => [
            'pool_size' => env('DB_POOL_SIZE', 10),
            'max_connections' => env('DB_MAX_CONNECTIONS', 100),
            'connection_timeout' => env('DB_CONNECTION_TIMEOUT', 60),
        ],
        
        // Index optimization
        'indexes' => [
            'auto_analyze' => env('DB_AUTO_ANALYZE', true),
            'analyze_threshold' => env('DB_ANALYZE_THRESHOLD', 1000), // rows changed
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        // Global rate limits
        'global' => [
            'enabled' => env('RATE_LIMITING_ENABLED', true),
            'max_attempts' => env('RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_minutes' => env('RATE_LIMIT_DECAY_MINUTES', 1),
        ],
        
        // API specific rate limits
        'api' => [
            'statistics' => [
                'max_attempts' => env('RATE_LIMIT_STATISTICS', 30),
                'decay_minutes' => 1,
            ],
            'reports' => [
                'max_attempts' => env('RATE_LIMIT_REPORTS', 10),
                'decay_minutes' => 1,
            ],
            'patients' => [
                'max_attempts' => env('RATE_LIMIT_PATIENTS', 60),
                'decay_minutes' => 1,
            ],
        ],
        
        // Authentication rate limits
        'auth' => [
            'login' => [
                'max_attempts' => env('RATE_LIMIT_LOGIN', 5),
                'decay_minutes' => env('RATE_LIMIT_LOGIN_DECAY', 15),
            ],
            'register' => [
                'max_attempts' => env('RATE_LIMIT_REGISTER', 3),
                'decay_minutes' => env('RATE_LIMIT_REGISTER_DECAY', 60),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // Performance metrics collection
        'metrics' => [
            'enabled' => env('PERFORMANCE_METRICS_ENABLED', true),
            'collect_memory' => env('METRICS_COLLECT_MEMORY', true),
            'collect_queries' => env('METRICS_COLLECT_QUERIES', true),
            'collect_cache' => env('METRICS_COLLECT_CACHE', true),
        ],
        
        // Alerting thresholds
        'alerts' => [
            'memory_threshold' => env('ALERT_MEMORY_THRESHOLD', 80), // percentage
            'query_time_threshold' => env('ALERT_QUERY_TIME_THRESHOLD', 1000), // milliseconds
            'cache_hit_rate_threshold' => env('ALERT_CACHE_HIT_RATE_THRESHOLD', 70), // percentage
        ],
        
        // APM integration
        'apm' => [
            'enabled' => env('APM_ENABLED', false),
            'service_name' => env('APM_SERVICE_NAME', 'akudihatinya-backend'),
            'environment' => env('APM_ENVIRONMENT', env('APP_ENV', 'production')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Optimization
    |--------------------------------------------------------------------------
    */
    'memory' => [
        // Memory limits
        'limits' => [
            'web_request' => env('MEMORY_LIMIT_WEB', '256M'),
            'api_request' => env('MEMORY_LIMIT_API', '128M'),
            'queue_job' => env('MEMORY_LIMIT_QUEUE', '512M'),
            'command' => env('MEMORY_LIMIT_COMMAND', '1G'),
        ],
        
        // Garbage collection
        'gc' => [
            'enabled' => env('MEMORY_GC_ENABLED', true),
            'probability' => env('MEMORY_GC_PROBABILITY', 1),
            'divisor' => env('MEMORY_GC_DIVISOR', 100),
        ],
        
        // Memory usage monitoring
        'monitoring' => [
            'enabled' => env('MEMORY_MONITORING_ENABLED', true),
            'log_threshold' => env('MEMORY_LOG_THRESHOLD', 80), // percentage
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Optimization
    |--------------------------------------------------------------------------
    */
    'queue' => [
        // Queue performance
        'performance' => [
            'batch_size' => env('QUEUE_BATCH_SIZE', 100),
            'max_jobs' => env('QUEUE_MAX_JOBS', 1000),
            'timeout' => env('QUEUE_TIMEOUT', 300),
        ],
        
        // Queue monitoring
        'monitoring' => [
            'enabled' => env('QUEUE_MONITORING_ENABLED', true),
            'failed_job_threshold' => env('QUEUE_FAILED_THRESHOLD', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Optimization
    |--------------------------------------------------------------------------
    */
    'assets' => [
        // Compression
        'compression' => [
            'enabled' => env('ASSET_COMPRESSION_ENABLED', true),
            'gzip' => env('ASSET_GZIP_ENABLED', true),
            'brotli' => env('ASSET_BROTLI_ENABLED', false),
        ],
        
        // CDN configuration
        'cdn' => [
            'enabled' => env('CDN_ENABLED', false),
            'url' => env('CDN_URL'),
            'assets_path' => env('CDN_ASSETS_PATH', 'assets'),
        ],
        
        // Caching headers
        'caching' => [
            'max_age' => env('ASSET_CACHE_MAX_AGE', 31536000), // 1 year
            'etag' => env('ASSET_ETAG_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Optimization
    |--------------------------------------------------------------------------
    */
    'security' => [
        // HTTPS optimization
        'https' => [
            'force' => env('FORCE_HTTPS', true),
            'hsts' => [
                'enabled' => env('HSTS_ENABLED', true),
                'max_age' => env('HSTS_MAX_AGE', 31536000),
                'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
            ],
        ],
        
        // Security headers
        'headers' => [
            'csp' => env('CSP_ENABLED', true),
            'xss_protection' => env('XSS_PROTECTION_ENABLED', true),
            'content_type_nosniff' => env('CONTENT_TYPE_NOSNIFF_ENABLED', true),
            'frame_options' => env('FRAME_OPTIONS', 'DENY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Optimization
    |--------------------------------------------------------------------------
    */
    'development' => [
        // Debug optimization
        'debug' => [
            'queries' => env('DEBUG_QUERIES', false),
            'cache' => env('DEBUG_CACHE', false),
            'memory' => env('DEBUG_MEMORY', false),
        ],
        
        // Development tools
        'tools' => [
            'telescope' => env('TELESCOPE_ENABLED', false),
            'debugbar' => env('DEBUGBAR_ENABLED', false),
            'clockwork' => env('CLOCKWORK_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optimization Schedules
    |--------------------------------------------------------------------------
    */
    'schedules' => [
        // Cache warming schedule
        'cache_warm' => [
            'enabled' => env('SCHEDULE_CACHE_WARM_ENABLED', true),
            'frequency' => env('SCHEDULE_CACHE_WARM_FREQUENCY', 'hourly'),
        ],
        
        // Database optimization schedule
        'db_optimize' => [
            'enabled' => env('SCHEDULE_DB_OPTIMIZE_ENABLED', true),
            'frequency' => env('SCHEDULE_DB_OPTIMIZE_FREQUENCY', 'daily'),
        ],
        
        // Performance monitoring schedule
        'performance_check' => [
            'enabled' => env('SCHEDULE_PERFORMANCE_CHECK_ENABLED', true),
            'frequency' => env('SCHEDULE_PERFORMANCE_CHECK_FREQUENCY', 'every_five_minutes'),
        ],
    ],
];