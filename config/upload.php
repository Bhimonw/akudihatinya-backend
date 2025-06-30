<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for file uploads in the application.
    |
    */

    'profile_pictures' => [
        'path' => 'img',
        'max_size' => null, // No size limit - handled by chunked upload
        'allowed_mimes' => ['jpeg', 'png', 'jpg', 'gif', 'webp'],
        'dimensions' => [
            'min_width' => 100,
            'min_height' => 100,
            'max_width' => 2000, // Will be resized if larger
            'max_height' => 2000, // Will be resized if larger
        ],
        'optimization' => [
            'auto_optimize' => true,
            'quality' => 85, // JPEG/WebP quality (1-100)
            'png_compression' => 6, // PNG compression level (0-9)
            'enable_cache' => true,
            'cache_path' => 'img/cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | General Upload Settings
    |--------------------------------------------------------------------------
    */

    'disk' => env('UPLOAD_DISK', 'local'),
    'url_prefix' => env('APP_URL', 'http://localhost:8000'),
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'scan_for_viruses' => env('UPLOAD_VIRUS_SCAN', false),
    'generate_thumbnails' => env('UPLOAD_GENERATE_THUMBNAILS', false),
];