<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use App\Formatters\ExcelExportFormatter;

/**
 * Excel Facade
 * 
 * Provides easy access to Excel export functionality throughout the application.
 * 
 * @method static \PhpOffice\PhpSpreadsheet\Spreadsheet formatAllExcel(array $puskesmasData, int $year)
 * @method static \PhpOffice\PhpSpreadsheet\Spreadsheet formatMonthlyExcel(array $puskesmasData, int $year, int $month)
 * @method static \PhpOffice\PhpSpreadsheet\Spreadsheet formatQuarterlyExcel(array $puskesmasData, int $year, int $quarter)
 * @method static \PhpOffice\PhpSpreadsheet\Spreadsheet formatPuskesmasExcel(array $puskesmasData, int $year, string $puskesmasName)
 * @method static void setupHeaders(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $reportType, int $year, int $month = null, int $quarter = null)
 * @method static string getPeriodLabel(string $reportType, int $year, int $month = null, int $quarter = null)
 * @method static void applyExcelStyling(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $reportType)
 * @method static void fillTotalRowData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $puskesmasData, string $reportType, int $year, int $month = null, int $quarter = null)
 * 
 * @see \App\Formatters\ExcelExportFormatter
 */
class Excel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ExcelExportFormatter::class;
    }

    /**
     * Create a new Excel export for all data (yearly report).
     *
     * @param array $puskesmasData
     * @param int $year
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function yearly(array $puskesmasData, int $year)
    {
        return static::formatAllExcel($puskesmasData, $year);
    }

    /**
     * Create a new Excel export for monthly data.
     *
     * @param array $puskesmasData
     * @param int $year
     * @param int $month
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function monthly(array $puskesmasData, int $year, int $month)
    {
        return static::formatMonthlyExcel($puskesmasData, $year, $month);
    }

    /**
     * Create a new Excel export for quarterly data.
     *
     * @param array $puskesmasData
     * @param int $year
     * @param int $quarter
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function quarterly(array $puskesmasData, int $year, int $quarter)
    {
        return static::formatQuarterlyExcel($puskesmasData, $year, $quarter);
    }

    /**
     * Create a new Excel export for specific puskesmas data.
     *
     * @param array $puskesmasData
     * @param int $year
     * @param string $puskesmasName
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public static function puskesmas(array $puskesmasData, int $year, string $puskesmasName)
    {
        return static::formatPuskesmasExcel($puskesmasData, $year, $puskesmasName);
    }

    /**
     * Export data and download immediately.
     *
     * @param string $type
     * @param array $puskesmasData
     * @param int $year
     * @param int|null $month
     * @param int|null $quarter
     * @param string|null $puskesmasName
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public static function download(string $type, array $puskesmasData, int $year, int $month = null, int $quarter = null, string $puskesmasName = null)
    {
        $spreadsheet = static::createByType($type, $puskesmasData, $year, $month, $quarter, $puskesmasName);
        
        $filename = static::generateFilename($type, $year, $month, $quarter, $puskesmasName);
        
        return static::downloadSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Save Excel file to storage.
     *
     * @param string $type
     * @param array $puskesmasData
     * @param int $year
     * @param int|null $month
     * @param int|null $quarter
     * @param string|null $puskesmasName
     * @param string|null $customPath
     * @return string Path to saved file
     */
    public static function save(string $type, array $puskesmasData, int $year, int $month = null, int $quarter = null, string $puskesmasName = null, string $customPath = null)
    {
        $spreadsheet = static::createByType($type, $puskesmasData, $year, $month, $quarter, $puskesmasName);
        
        $filename = static::generateFilename($type, $year, $month, $quarter, $puskesmasName);
        $path = $customPath ?: config('excel.output_path', storage_path('app/exports'));
        $fullPath = $path . DIRECTORY_SEPARATOR . $filename;
        
        static::saveSpreadsheet($spreadsheet, $fullPath);
        
        return $fullPath;
    }

    /**
     * Create spreadsheet by type.
     *
     * @param string $type
     * @param array $puskesmasData
     * @param int $year
     * @param int|null $month
     * @param int|null $quarter
     * @param string|null $puskesmasName
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    protected static function createByType(string $type, array $puskesmasData, int $year, int $month = null, int $quarter = null, string $puskesmasName = null)
    {
        switch ($type) {
            case 'yearly':
            case 'all':
                return static::yearly($puskesmasData, $year);
            
            case 'monthly':
                if ($month === null) {
                    throw new \InvalidArgumentException('Month is required for monthly reports');
                }
                return static::monthly($puskesmasData, $year, $month);
            
            case 'quarterly':
                if ($quarter === null) {
                    throw new \InvalidArgumentException('Quarter is required for quarterly reports');
                }
                return static::quarterly($puskesmasData, $year, $quarter);
            
            case 'puskesmas':
                if ($puskesmasName === null) {
                    throw new \InvalidArgumentException('Puskesmas name is required for puskesmas reports');
                }
                return static::puskesmas($puskesmasData, $year, $puskesmasName);
            
            default:
                throw new \InvalidArgumentException("Unsupported report type: {$type}");
        }
    }

    /**
     * Generate filename for export.
     *
     * @param string $type
     * @param int $year
     * @param int|null $month
     * @param int|null $quarter
     * @param string|null $puskesmasName
     * @return string
     */
    protected static function generateFilename(string $type, int $year, int $month = null, int $quarter = null, string $puskesmasName = null)
    {
        $config = config('excel.export.filename', []);
        $prefix = $config['prefix'] ?? 'laporan_gizi_';
        $includeTimestamp = $config['include_timestamp'] ?? true;
        $timestampFormat = $config['timestamp_format'] ?? 'Y-m-d_H-i-s';
        
        $parts = [$prefix, $type, $year];
        
        if ($month !== null) {
            $parts[] = sprintf('%02d', $month);
        }
        
        if ($quarter !== null) {
            $parts[] = 'Q' . $quarter;
        }
        
        if ($puskesmasName !== null) {
            $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $puskesmasName);
            $parts[] = $sanitized;
        }
        
        if ($includeTimestamp) {
            $parts[] = date($timestampFormat);
        }
        
        return implode('_', $parts) . '.xlsx';
    }

    /**
     * Download spreadsheet as response.
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected static function downloadSpreadsheet($spreadsheet, string $filename)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_export_');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        
        $headers = config('excel.export.headers', []);
        
        return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend();
    }

    /**
     * Save spreadsheet to file.
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param string $path
     * @return void
     */
    protected static function saveSpreadsheet($spreadsheet, string $path)
    {
        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * Validate data before export.
     *
     * @param array $puskesmasData
     * @param string $type
     * @param int $year
     * @param int|null $month
     * @param int|null $quarter
     * @return bool
     */
    public static function validate(array $puskesmasData, string $type, int $year, int $month = null, int $quarter = null)
    {
        $validator = app('excel.validator');
        
        return $validator->validateExportParameters([
            'puskesmas_data' => $puskesmasData,
            'report_type' => $type,
            'year' => $year,
            'month' => $month,
            'quarter' => $quarter,
        ]);
    }

    /**
     * Get available report types.
     *
     * @return array
     */
    public static function getReportTypes()
    {
        return ['yearly', 'monthly', 'quarterly', 'puskesmas'];
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function config(string $key, $default = null)
    {
        return config("excel.{$key}", $default);
    }
}