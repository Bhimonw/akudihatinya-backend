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
        'max_size' => 2048, // KB
        'allowed_mimes' => ['jpeg', 'png', 'jpg', 'gif', 'webp'],
        'dimensions' => [
            'min_width' => 100,
            'min_height' => 100,
            'max_width' => 2000,
            'max_height' => 2000,
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