<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for file uploads that can be easily
    | adjusted for different environments (local, staging, production).
    |
    */

    'profile_pictures' => [
        'disk' => env('UPLOAD_DISK', 'public'),
        'path' => env('PROFILE_PICTURES_PATH', 'profile-pictures'),
        'max_size' => env('PROFILE_PICTURES_MAX_SIZE', 2048), // KB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png', 
            'image/jpg',
            'image/gif',
            'image/webp'
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'dimensions' => [
            'min_width' => env('PROFILE_MIN_WIDTH', 100),
            'min_height' => env('PROFILE_MIN_HEIGHT', 100),
            'max_width' => env('PROFILE_MAX_WIDTH', 800),
            'max_height' => env('PROFILE_MAX_HEIGHT', 800),
        ],
        'optimization' => [
            'enabled' => env('PROFILE_PICTURE_OPTIMIZATION_ENABLED', true),
            'quality' => env('PROFILE_PICTURE_OPTIMIZATION_QUALITY', 85),
            'png_compression' => env('PROFILE_PICTURE_PNG_COMPRESSION', 6),
            'preserve_transparency' => env('PROFILE_PICTURE_PRESERVE_TRANSPARENCY', true),
        ],

        // Auto-resize configuration
        'auto_resize' => [
            'enabled' => env('PROFILE_PICTURE_AUTO_RESIZE_ENABLED', true),
            'quality' => env('PROFILE_PICTURE_AUTO_RESIZE_QUALITY', 85),
            'max_file_size' => env('PROFILE_PICTURE_AUTO_RESIZE_MAX_FILE_SIZE', 2048000), // 2MB
            'preserve_aspect_ratio' => env('PROFILE_PICTURE_PRESERVE_ASPECT_RATIO', true),
            'upscale_small_images' => env('PROFILE_PICTURE_UPSCALE_SMALL_IMAGES', false),
        ],

        // Minimum dimensions (for validation and auto-resize)
        'min_dimensions' => [
            'width' => env('PROFILE_PICTURE_MIN_WIDTH', 100),
            'height' => env('PROFILE_PICTURE_MIN_HEIGHT', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */

    'default_disk' => env('UPLOAD_DISK', 'public'),
    'fallback_disk' => env('UPLOAD_FALLBACK_DISK', 'local'),
    
    /*
    |--------------------------------------------------------------------------
    | URL Configuration
    |--------------------------------------------------------------------------
    */

    'base_url' => env('APP_URL', 'http://localhost:8000'),
    'storage_url' => env('STORAGE_URL', env('APP_URL', 'http://localhost:8000') . '/storage'),
    
    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    */

    'cdn' => [
        'enabled' => env('CDN_ENABLED', false),
        'base_url' => env('CDN_BASE_URL', ''),
        'profile_pictures_path' => env('CDN_PROFILE_PICTURES_PATH', '/profile-pictures'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */

    'security' => [
        'scan_uploads' => env('SCAN_UPLOADS', false),
        'quarantine_suspicious' => env('QUARANTINE_SUSPICIOUS_FILES', true),
        'generate_unique_names' => env('GENERATE_UNIQUE_NAMES', true),
        'validate_file_content' => env('VALIDATE_FILE_CONTENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'enable_cache' => env('UPLOAD_CACHE_ENABLED', true),
        'cache_ttl' => env('UPLOAD_CACHE_TTL', 3600), // seconds
        'lazy_loading' => env('LAZY_LOADING_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    */

    'cleanup' => [
        'enabled' => env('FILE_CLEANUP_ENABLED', true),
        'delete_old_on_update' => env('DELETE_OLD_ON_UPDATE', true),
        'orphaned_files_cleanup' => env('ORPHANED_FILES_CLEANUP', false),
        'cleanup_schedule' => env('CLEANUP_SCHEDULE', 'daily'),
        'retention_days' => env('FILE_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('UPLOAD_LOGGING_ENABLED', true),
        'level' => env('UPLOAD_LOG_LEVEL', 'info'),
        'log_successful_uploads' => env('LOG_SUCCESSFUL_UPLOADS', true),
        'log_failed_uploads' => env('LOG_FAILED_UPLOADS', true),
    ],

];