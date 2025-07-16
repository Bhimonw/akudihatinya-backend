<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excel Export Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for Excel export functionality.
    | You can customize templates, styling, performance settings, and more.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Template Files
    |--------------------------------------------------------------------------
    |
    | Define the template files for different report types.
    | These files should be located in the storage/app/templates directory.
    |
    */
    'templates' => [
        'all' => 'all.xlsx',
        'monthly' => 'monthly.xlsx',
        'quarterly' => 'quarterly.xlsx',
        'puskesmas' => 'puskesmas.xlsx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Paths
    |--------------------------------------------------------------------------
    |
    | Define the base path for template files.
    |
    */
    'template_path' => storage_path('app/templates'),
    'output_path' => storage_path('app/exports'),

    /*
    |--------------------------------------------------------------------------
    | Styling Configuration
    |--------------------------------------------------------------------------
    |
    | Define colors, fonts, and other styling options for Excel exports.
    |
    */
    'styling' => [
        'colors' => [
            'header_background' => 'E6E6FA',
            'total_background' => 'E6E6FA',
            'border_color' => '000000',
            'text_color' => '000000',
            'error_background' => 'FFE6E6',
            'warning_background' => 'FFF2CC',
        ],
        
        'fonts' => [
            'family' => 'Calibri',
            'sizes' => [
                'title' => 14,
                'subtitle' => 12,
                'header' => 11,
                'data' => 10,
                'footer' => 9,
            ],
        ],
        
        'borders' => [
            'default' => 'thin',
            'header' => 'medium',
            'total' => 'medium',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Row and Column Configuration
    |--------------------------------------------------------------------------
    |
    | Define standard row positions and column settings.
    |
    */
    'layout' => [
        'rows' => [
            'title' => 1,
            'period_info' => 2,
            'header_level_1' => 4,
            'header_level_2' => 5,
            'header_level_4' => 7,
            'data_start' => 10,
            'footer_start' => 12,
        ],
        
        'columns' => [
            'puskesmas_info' => ['A', 'B', 'C'], // NO, NAMA PUSKESMAS, SASARAN
            'auto_size' => true,
            'min_width' => 8,
            'max_width' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings for Excel generation.
    |
    */
    'performance' => [
        'cache' => [
            'enabled' => env('EXCEL_CACHE_ENABLED', true),
            'ttl' => env('EXCEL_CACHE_TTL', 3600), // 1 hour
            'prefix' => 'excel_export_',
        ],
        
        'limits' => [
            'max_rows_per_sheet' => env('EXCEL_MAX_ROWS', 1000),
            'max_columns_per_sheet' => env('EXCEL_MAX_COLUMNS', 100),
            'max_file_size_mb' => env('EXCEL_MAX_FILE_SIZE', 50),
            'memory_limit' => env('EXCEL_MEMORY_LIMIT', '512M'),
            'execution_time_limit' => env('EXCEL_TIME_LIMIT', 300), // 5 minutes
        ],
        
        'optimization' => [
            'read_only' => true,
            'read_data_only' => false,
            'calculate_formulas' => false,
            'format_only' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for data integrity.
    |
    */
    'validation' => [
        'required_fields' => [
            'puskesmas' => ['nama_puskesmas', 'sasaran', 'monthly_data'],
            'monthly_data' => ['male', 'female', 'standard', 'non_standard'],
        ],
        
        'data_types' => [
            'sasaran' => 'integer',
            'male' => 'integer',
            'female' => 'integer',
            'standard' => 'integer',
            'non_standard' => 'integer',
        ],
        
        'ranges' => [
            'year' => ['min' => 2000, 'max' => 2050],
            'month' => ['min' => 1, 'max' => 12],
            'quarter' => ['min' => 1, 'max' => 4],
            'percentage' => ['min' => 0, 'max' => 100],
        ],
        
        'string_lengths' => [
            'nama_puskesmas' => ['min' => 1, 'max' => 255],
            'report_title' => ['min' => 1, 'max' => 100],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Define localized strings and formats.
    |
    */
    'localization' => [
        'language' => 'id', // Indonesian
        
        'months' => [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET',
            4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
            7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER',
            10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
        ],
        
        'quarters' => [
            1 => 'TRIWULAN I', 2 => 'TRIWULAN II',
            3 => 'TRIWULAN III', 4 => 'TRIWULAN IV'
        ],
        
        'report_types' => [
            'all' => 'LAPORAN TAHUNAN',
            'monthly' => 'LAPORAN BULANAN',
            'quarterly' => 'LAPORAN TRIWULANAN',
            'puskesmas' => 'LAPORAN PUSKESMAS'
        ],
        
        'labels' => [
            'created_on' => 'Dibuat pada:',
            'created_by' => 'Dibuat oleh: Sistem Informasi Gizi Stunting',
            'notes' => 'Keterangan:',
            'abbreviations' => [
                'lp' => 'L/P = Laki-laki/Perempuan',
                'ts' => 'TS/%S = Tinggi Badan/Standar (%)',
            ],
            'data_source' => 'Sumber: Data Puskesmas Kabupaten/Kota',
            'quarter_note' => 'Laporan triwulan merupakan akumulasi data 3 bulan dalam periode yang bersangkutan',
        ],
        
        'date_format' => 'd F Y H:i:s',
        'number_format' => [
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'decimal_places' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure error handling and logging options.
    |
    */
    'error_handling' => [
        'log_errors' => env('EXCEL_LOG_ERRORS', true),
        'log_warnings' => env('EXCEL_LOG_WARNINGS', true),
        'throw_on_error' => env('EXCEL_THROW_ON_ERROR', true),
        'default_values' => [
            'missing_data' => 0,
            'invalid_percentage' => 0.0,
            'missing_name' => 'N/A',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security-related options.
    |
    */
    'security' => [
        'allowed_extensions' => ['xlsx', 'xls'],
        'scan_for_viruses' => env('EXCEL_VIRUS_SCAN', false),
        'sanitize_input' => true,
        'validate_templates' => true,
        'max_upload_size' => env('EXCEL_MAX_UPLOAD_SIZE', '10M'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Options
    |--------------------------------------------------------------------------
    |
    | Configure export behavior and options.
    |
    */
    'export' => [
        'default_format' => 'xlsx',
        'include_charts' => false,
        'include_images' => false,
        'compress_output' => true,
        'auto_download' => true,
        
        'filename' => [
            'prefix' => 'laporan_gizi_',
            'include_timestamp' => true,
            'timestamp_format' => 'Y-m-d_H-i-s',
            'sanitize' => true,
        ],
        
        'headers' => [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment',
            'Cache-Control' => 'max-age=0',
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => 'now',
            'Pragma' => 'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Settings
    |--------------------------------------------------------------------------
    |
    | Settings for development and debugging.
    |
    */
    'development' => [
        'debug_mode' => env('EXCEL_DEBUG', false),
        'profile_performance' => env('EXCEL_PROFILE', false),
        'save_debug_files' => env('EXCEL_SAVE_DEBUG', false),
        'debug_path' => storage_path('app/debug/excel'),
        
        'mock_data' => [
            'enabled' => env('EXCEL_MOCK_DATA', false),
            'sample_size' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    |
    | Settings for integration with other systems.
    |
    */
    'integration' => [
        'api' => [
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
        ],
        
        'queue' => [
            'enabled' => env('EXCEL_QUEUE_ENABLED', false),
            'connection' => env('EXCEL_QUEUE_CONNECTION', 'default'),
            'queue_name' => env('EXCEL_QUEUE_NAME', 'excel-exports'),
        ],
        
        'notifications' => [
            'enabled' => env('EXCEL_NOTIFICATIONS', false),
            'channels' => ['mail', 'database'],
            'notify_on_completion' => true,
            'notify_on_error' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup and Archive
    |--------------------------------------------------------------------------
    |
    | Settings for backup and archiving of generated files.
    |
    */
    'backup' => [
        'enabled' => env('EXCEL_BACKUP_ENABLED', false),
        'retention_days' => env('EXCEL_BACKUP_RETENTION', 30),
        'backup_path' => storage_path('app/backups/excel'),
        'compress_backups' => true,
        'auto_cleanup' => true,
    ],

];