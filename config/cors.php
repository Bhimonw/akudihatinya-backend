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

    // Limit paths instead of wildcard to reduce unnecessary CORS surface
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        // Production domain MUST be explicitly set in env for deploys
        function_exists('env') ? env('FRONTEND_URL') : ($_ENV['FRONTEND_URL'] ?? null),
        function_exists('env') ? env('APP_URL') : ($_ENV['APP_URL'] ?? null),
        // Local development fallbacks (only enabled when APP_ENV != production)
        ((($_ENV['APP_ENV'] ?? (function_exists('env') ? env('APP_ENV') : null)) !== 'production')) ? 'http://localhost:5173' : null,
        ((($_ENV['APP_ENV'] ?? (function_exists('env') ? env('APP_ENV') : null)) !== 'production')) ? 'http://127.0.0.1:5173' : null,
    ]),

    'allowed_origins_patterns' => [],

    // Restrict headers where possible; wildcard acceptable if dynamic custom headers are used
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
    // NOTE: When using credentials=true, wildcard origins are NOT allowed. Ensure env FRONTEND_URL is set in production.
];
