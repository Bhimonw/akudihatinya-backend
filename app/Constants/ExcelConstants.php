<?php

namespace App\Constants;

/**
 * Excel formatting constants and configurations
 * 
 * This class centralizes all Excel-related constants to improve maintainability
 * and reduce hard-coded values throughout the application.
 */
class ExcelConstants
{
    /**
     * Column mappings for monthly reports
     * Format: [month_number => [columns_array]]
     */
    public const MONTH_COLUMNS = [
        1 => ['D', 'E', 'F', 'G', 'H'],  // Januari
        2 => ['I', 'J', 'K', 'L', 'M'],  // Februari
        3 => ['N', 'O', 'P', 'Q', 'R'],  // Maret
        4 => ['S', 'T', 'U', 'V', 'W'],  // April
        5 => ['X', 'Y', 'Z', 'AA', 'AB'], // Mei
        6 => ['AC', 'AD', 'AE', 'AF', 'AG'], // Juni
        7 => ['AH', 'AI', 'AJ', 'AK', 'AL'], // Juli
        8 => ['AM', 'AN', 'AO', 'AP', 'AQ'], // Agustus
        9 => ['AR', 'AS', 'AT', 'AU', 'AV'], // September
        10 => ['AW', 'AX', 'AY', 'AZ', 'BA'], // Oktober
        11 => ['BB', 'BC', 'BD', 'BE', 'BF'], // November
        12 => ['BG', 'BH', 'BI', 'BJ', 'BK'], // Desember
    ];

    /**
     * Column mappings for quarterly reports
     * Format: [quarter_number => [columns_array]]
     */
    public const QUARTER_COLUMNS = [
        1 => ['D', 'E', 'F', 'G', 'H'],   // Q1 (Jan-Mar)
        2 => ['I', 'J', 'K', 'L', 'M'],   // Q2 (Apr-Jun)
        3 => ['N', 'O', 'P', 'Q', 'R'],   // Q3 (Jul-Sep)
        4 => ['S', 'T', 'U', 'V', 'W'],   // Q4 (Oct-Dec)
    ];

    /**
     * Total columns for different report types
     */
    public const TOTAL_COLUMNS = ['BL', 'BM', 'BN', 'BO', 'BP'];
    public const QUARTER_ONLY_COLUMNS = ['X', 'Y', 'Z', 'AA', 'AB'];
    public const QUARTER_TOTAL_COLUMNS = ['AC', 'AD', 'AE', 'AF', 'AG'];

    /**
     * Month names in Indonesian
     */
    public const MONTHS = [
        1 => 'JANUARI',
        2 => 'FEBRUARI', 
        3 => 'MARET',
        4 => 'APRIL',
        5 => 'MEI',
        6 => 'JUNI',
        7 => 'JULI',
        8 => 'AGUSTUS',
        9 => 'SEPTEMBER',
        10 => 'OKTOBER',
        11 => 'NOVEMBER',
        12 => 'DESEMBER'
    ];

    /**
     * Quarter names in Indonesian
     */
    public const QUARTERS = [
        1 => 'TRIWULAN I',
        2 => 'TRIWULAN II',
        3 => 'TRIWULAN III',
        4 => 'TRIWULAN IV'
    ];

    /**
     * Report type labels
     */
    public const REPORT_LABELS = [
        'all' => 'LAPORAN TAHUNAN',
        'monthly' => 'LAPORAN BULANAN',
        'quarterly' => 'LAPORAN TRIWULANAN',
        'puskesmas' => 'LAPORAN PUSKESMAS'
    ];

    /**
     * Excel styling colors (RGB hex values)
     */
    public const COLORS = [
        'HEADER_BACKGROUND' => 'E6E6FA',
        'TOTAL_ROW_BACKGROUND' => 'E6E6FA',
        'BORDER_COLOR' => '000000',
        'TEXT_COLOR' => '000000'
    ];

    /**
     * Font sizes for different elements
     */
    public const FONT_SIZES = [
        'TITLE' => 14,
        'SUBTITLE' => 12,
        'HEADER' => 11,
        'DATA' => 10,
        'FOOTER' => 9
    ];

    /**
     * Row positions in Excel sheets
     */
    public const ROW_POSITIONS = [
        'TITLE' => 1,
        'PERIOD_INFO' => 2,
        'HEADER_LEVEL_1' => 4,
        'HEADER_LEVEL_2' => 5,
        'HEADER_LEVEL_4' => 7,
        'DATA_START' => 10,
        'FOOTER_START' => 12
    ];

    /**
     * Template file names
     */
    public const TEMPLATES = [
        'all' => 'all.xlsx',
        'monthly' => 'monthly.xlsx',
        'quarterly' => 'quarterly.xlsx',
        'puskesmas' => 'puskesmas.xlsx'
    ];

    /**
     * Data category headers for level 2
     */
    public const DATA_CATEGORIES = [
        'KESELURUHAN',
        'BAYI BARU LAHIR',
        'BAYI',
        'ANAK BALITA',
        'ANAK PRASEKOLAH',
        'ANAK SEKOLAH',
        'REMAJA',
        'DEWASA',
        'LANSIA',
        'IBU HAMIL',
        'IBU MENYUSUI'
    ];

    /**
     * Header level 4 labels
     */
    public const HEADER_LEVEL_4 = [
        'L' => 'L',
        'P' => 'P', 
        'TOTAL' => 'TOTAL',
        'TS' => 'TS',
        '%S' => '%S'
    ];

    /**
     * Footer information templates
     */
    public const FOOTER_INFO = [
        'CREATED_LABEL' => 'Dibuat pada:',
        'SYSTEM_LABEL' => 'Dibuat oleh: Sistem Informasi Gizi Stunting',
        'ABBREVIATION_LABEL' => 'Keterangan:',
        'ABBREVIATION_LP' => 'L/P = Laki-laki/Perempuan',
        'ABBREVIATION_TS' => 'TS/%S = Tinggi Badan/Standar (%)',
        'DATA_SOURCE' => 'Sumber: Data Puskesmas Kabupaten/Kota'
    ];

    /**
     * Quarter-specific footer information
     */
    public const QUARTER_FOOTER_INFO = [
        'QUARTER_EXPLANATION' => 'Laporan triwulan merupakan akumulasi data 3 bulan dalam periode yang bersangkutan'
    ];

    /**
     * Performance and limits
     */
    public const LIMITS = [
        'MAX_ROWS_PER_SHEET' => 1000,
        'MAX_COLUMNS_PER_SHEET' => 100,
        'CACHE_TTL_SECONDS' => 3600,
        'MAX_FILE_SIZE_MB' => 50
    ];

    /**
     * Validation rules
     */
    public const VALIDATION = [
        'REQUIRED_PUSKESMAS_FIELDS' => [
            'nama_puskesmas',
            'sasaran', 
            'monthly_data'
        ],
        'VALID_REPORT_TYPES' => [
            'all',
            'monthly', 
            'quarterly',
            'puskesmas'
        ],
        'VALID_MONTHS' => range(1, 12),
        'VALID_QUARTERS' => range(1, 4)
    ];

    /**
     * Get month columns for specific month
     * 
     * @param int $month Month number (1-12)
     * @return array Column letters
     */
    public static function getMonthColumns(int $month): array
    {
        return self::MONTH_COLUMNS[$month] ?? [];
    }

    /**
     * Get quarter columns for specific quarter
     * 
     * @param int $quarter Quarter number (1-4)
     * @return array Column letters
     */
    public static function getQuarterColumns(int $quarter): array
    {
        return self::QUARTER_COLUMNS[$quarter] ?? [];
    }

    /**
     * Get month name in Indonesian
     * 
     * @param int $month Month number (1-12)
     * @return string Month name
     */
    public static function getMonthName(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }

    /**
     * Get quarter name in Indonesian
     * 
     * @param int $quarter Quarter number (1-4)
     * @return string Quarter name
     */
    public static function getQuarterName(int $quarter): string
    {
        return self::QUARTERS[$quarter] ?? '';
    }

    /**
     * Get report label by type
     * 
     * @param string $type Report type
     * @return string Report label
     */
    public static function getReportLabel(string $type): string
    {
        return self::REPORT_LABELS[$type] ?? '';
    }

    /**
     * Validate if month number is valid
     * 
     * @param int $month Month number
     * @return bool
     */
    public static function isValidMonth(int $month): bool
    {
        return in_array($month, self::VALIDATION['VALID_MONTHS']);
    }

    /**
     * Validate if quarter number is valid
     * 
     * @param int $quarter Quarter number
     * @return bool
     */
    public static function isValidQuarter(int $quarter): bool
    {
        return in_array($quarter, self::VALIDATION['VALID_QUARTERS']);
    }

    /**
     * Validate if report type is valid
     * 
     * @param string $type Report type
     * @return bool
     */
    public static function isValidReportType(string $type): bool
    {
        return in_array($type, self::VALIDATION['VALID_REPORT_TYPES']);
    }
}