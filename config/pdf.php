<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Generation Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for PDF generation in the
    | application, including memory limits, timeouts, and caching settings.
    |
    */

    'memory_limit' => env('PDF_MEMORY_LIMIT', '512M'),

    'time_limit' => env('PDF_TIME_LIMIT', 300),

    'paper_size' => env('PDF_PAPER_SIZE', 'A4'),

    'orientation' => [
        'default' => env('PDF_ORIENTATION', 'portrait'),
        'puskesmas' => 'portrait',
        'quarterly' => 'landscape',
        'summary' => 'portrait'
    ],

    'cache' => [
        'enabled' => env('PDF_CACHE_ENABLED', true),
        'ttl' => env('PDF_CACHE_TTL', 3600), // 1 hour
        'puskesmas_ttl' => env('PDF_PUSKESMAS_CACHE_TTL', 3600),
    ],

    'templates' => [
        'path' => resource_path('pdf'),
        'puskesmas' => 'puskesmas_statistics_pdf',
        'quarterly' => 'all_quarters_recap_pdf',
        'monthly' => 'monthly_statistics_pdf',
        'yearly' => 'yearly_statistics_pdf',
        'summary' => 'statistics_pdf'
    ],

    'filename' => [
        'prefix' => env('PDF_FILENAME_PREFIX', 'Laporan'),
        'timestamp_format' => 'Y-m-d_H-i-s',
        'include_correlation_id' => env('PDF_INCLUDE_CORRELATION_ID', false)
    ],

    'error_handling' => [
        'log_correlation_id' => true,
        'include_trace_in_logs' => env('PDF_LOG_TRACE', true),
        'max_retry_attempts' => env('PDF_MAX_RETRY', 3),
        'retry_delay' => env('PDF_RETRY_DELAY', 1000) // milliseconds
    ],

    'validation' => [
        'min_year' => env('PDF_MIN_YEAR', 2020),
        'max_year_offset' => env('PDF_MAX_YEAR_OFFSET', 1), // Current year + offset
        'allowed_disease_types' => ['ht', 'dm'],
        'max_data_points' => env('PDF_MAX_DATA_POINTS', 10000)
    ],

    'performance' => [
        'enable_compression' => env('PDF_ENABLE_COMPRESSION', true),
        'optimize_images' => env('PDF_OPTIMIZE_IMAGES', true),
        'chunk_size' => env('PDF_CHUNK_SIZE', 1000),
        'parallel_processing' => env('PDF_PARALLEL_PROCESSING', false)
    ]

];
