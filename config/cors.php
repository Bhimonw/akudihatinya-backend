<?php

// config/cors.php
// Konfigurasi CORS untuk mengizinkan frontend mengakses API

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:5174', // Alternative Vite dev server port
        'http://127.0.0.1:5500', // For Live Server testing
        'http://localhost:5500', // Alternative Live Server port
        env('APP_URL', 'http://localhost:8000'), // Laravel app URL for SPA integration
        'http://localhost:8000', // Default Laravel development server
        'http://127.0.0.1:8000', // Alternative localhost format
        'https://akudihatinya.banjarkab.go.id', // Production frontend domain
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
