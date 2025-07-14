<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use App\Services\Statistics\StatisticsService;
use App\Traits\Calculation\PercentageCalculationTrait;
use Illuminate\Support\Facades\Log;

/**
 * Base class untuk semua Admin Formatter
 * Menyediakan fungsi-fungsi umum yang digunakan oleh formatter Excel
 */
abstract class BaseAdminFormatter
{
    use PercentageCalculationTrait;
    protected $statisticsService;
    protected $sheet;
    
    // Common styling constants
    const HEADER_BACKGROUND_COLOR = 'E6E6FA';
    const TOTAL_ROW_BACKGROUND_COLOR = 'FFFF99';
    const TITLE_FONT_SIZE = 14;
    const SUBTITLE_FONT_SIZE = 12;
    const HEADER_FONT_SIZE = 10;
    const DATA_FONT_SIZE = 9;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Abstract method yang harus diimplementasikan oleh setiap formatter
     */
    abstract public function format(string $diseaseType = 'ht', int $year = null, array $additionalData = []): Spreadsheet;

    /**
     * Get disease type label in Indonesian
     */
    protected function getDiseaseTypeLabel(string $diseaseType): string
    {
        $labels = [
            'ht' => 'Hipertensi',
            'dm' => 'Diabetes Melitus',
            'both' => 'Hipertensi dan Diabetes Melitus'
        ];
        
        return $labels[$diseaseType] ?? 'Hipertensi';
    }

    /**
     * Get month name in Indonesian
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $months[$month] ?? 'Bulan Tidak Valid';
    }

    /**
     * Get quarter name in Indonesian
     */
    protected function getQuarterName(int $quarter): string
    {
        $quarters = [
            1 => 'Triwulan I',
            2 => 'Triwulan II', 
            3 => 'Triwulan III',
            4 => 'Triwulan IV'
        ];
        
        return $quarters[$quarter] ?? 'Triwulan Tidak Valid';
    }

    /**
     * Apply common Excel styling
     */
    protected function applyCommonStyling()
    {
        if (!$this->sheet) {
            return;
        }
        
        // Get the highest column and row
        $highestColumn = $this->sheet->getHighestColumn();
        $highestRow = $this->sheet->getHighestRow();
        
        // Style untuk judul (baris 1)
        $this->sheet->getStyle('A1')->getFont()
            ->setBold(true)
            ->setSize(self::TITLE_FONT_SIZE);
        $this->sheet->getStyle('A1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Auto-size columns
        foreach (range('A', $highestColumn) as $col) {
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Border untuk semua data
        if ($highestRow > 1) {
            $dataRange = 'A1:' . $highestColumn . $highestRow;
            $this->sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }
    }

    /**
     * Apply header styling
     */
    protected function applyHeaderStyling(string $range)
    {
        if (!$this->sheet) {
            return;
        }
        
        $this->sheet->getStyle($range)->getFont()->setBold(true);
        $this->sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        
        // Background color untuk header
        $this->sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::HEADER_BACKGROUND_COLOR);
    }

    /**
     * Apply total row styling
     */
    protected function applyTotalRowStyling(int $row, string $endColumn = null)
    {
        if (!$this->sheet) {
            return;
        }
        
        $endColumn = $endColumn ?? $this->sheet->getHighestColumn();
        $range = 'A' . $row . ':' . $endColumn . $row;
        
        $this->sheet->getStyle($range)->getFont()->setBold(true);
        $this->sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB(self::TOTAL_ROW_BACKGROUND_COLOR);
    }

    /**
     * Format number with thousand separator
     * Menghilangkan koma pada angka bulat
     */
    protected function formatNumber($number): string
    {
        $num = $number ?? 0;
        // Jika angka adalah bilangan bulat, tidak perlu koma
        if (is_numeric($num) && $num == intval($num)) {
            return number_format($num, 0, '', '.');
        }
        // Jika ada desimal, gunakan koma sebagai pemisah desimal
        return number_format($num, 2, ',', '.');
    }

    /**
     * Format percentage
     */
    protected function formatPercentage($value, int $decimals = 2): string
    {
        return number_format($value ?? 0, $decimals, ',', '.') . '%';
    }

    /**
     * Calculate percentage safely with 0-100% constraint
     * Alias untuk calculateStandardPercentage dari trait
     */
    protected function calculatePercentage($numerator, $denominator, int $decimals = 2): float
    {
        return $this->calculateStandardPercentage($numerator, $denominator, $decimals);
    }

    // Method calculateAchievementPercentage sekarang tersedia melalui PercentageCalculationTrait

    /**
     * Merge cells safely
     */
    protected function mergeCells(string $range)
    {
        if (!$this->sheet) {
            return;
        }
        
        try {
            $this->sheet->mergeCells($range);
        } catch (\Exception $e) {
            Log::warning('Failed to merge cells: ' . $range, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Set cell value safely
     */
    protected function setCellValue(string $coordinate, $value)
    {
        if (!$this->sheet) {
            return;
        }
        
        try {
            $this->sheet->setCellValue($coordinate, $value);
        } catch (\Exception $e) {
            Log::warning('Failed to set cell value: ' . $coordinate, [
                'value' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper function untuk increment kolom Excel
     */
    protected function incrementColumn(string $column, int $increment = 1): string
    {
        try {
            $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column);
            $newColumnIndex = $columnIndex + $increment;
            return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($newColumnIndex);
        } catch (\Exception $e) {
            Log::warning('Failed to increment column: ' . $column, ['error' => $e->getMessage()]);
            return $column;
        }
    }

    /**
     * Validate common input parameters
     */
    protected function validateCommonInput(string $diseaseType, int $year): bool
    {
        $validDiseaseTypes = ['ht', 'dm', 'both'];
        
        if (!in_array($diseaseType, $validDiseaseTypes)) {
            throw new \InvalidArgumentException("Invalid disease type: {$diseaseType}. Valid types: " . implode(', ', $validDiseaseTypes));
        }
        
        $currentYear = date('Y');
        if ($year < 2020 || $year > $currentYear + 1) {
            throw new \InvalidArgumentException("Invalid year: {$year}. Year must be between 2020 and " . ($currentYear + 1));
        }
        
        return true;
    }

    /**
     * Get standard filename for exports
     */
    protected function getStandardFilename(string $reportType, string $diseaseType, int $year, string $suffix = ''): string
    {
        $diseaseLabel = $this->getDiseaseTypeLabel($diseaseType);
        $diseaseShort = $diseaseType === 'ht' ? 'HT' : ($diseaseType === 'dm' ? 'DM' : 'HT-DM');
        
        $filename = "Laporan_{$reportType}_{$diseaseShort}_{$year}";
        
        if ($suffix) {
            $filename .= "_{$suffix}";
        }
        
        return $filename . '.xlsx';
    }

    /**
     * Log formatter activity
     */
    protected function logActivity(string $action, array $context = [])
    {
        $className = get_class($this);
        $context['formatter'] = $className;
        
        Log::info("Formatter Activity: {$action}", $context);
    }

    /**
     * Log formatter error
     */
    protected function logError(string $action, \Exception $e, array $context = [])
    {
        $className = get_class($this);
        $context['formatter'] = $className;
        $context['error'] = $e->getMessage();
        $context['trace'] = $e->getTraceAsString();
        
        Log::error("Formatter Error: {$action}", $context);
    }

    /**
     * Get achievement status based on percentage
     */
    protected function getAchievementStatus(float $percentage): array
    {
        if ($percentage >= 100) {
            return [
                'text' => 'SANGAT BAIK',
                'color' => '008000', // Green
                'description' => 'Target tercapai dengan sangat baik (≥100%)'
            ];
        } elseif ($percentage >= 80) {
            return [
                'text' => 'BAIK',
                'color' => '0066CC', // Blue
                'description' => 'Target tercapai dengan baik (80-99%)'
            ];
        } elseif ($percentage >= 60) {
            return [
                'text' => 'CUKUP',
                'color' => 'FF8C00', // Orange
                'description' => 'Target tercapai cukup (60-79%)'
            ];
        } else {
            return [
                'text' => 'KURANG',
                'color' => 'FF0000', // Red
                'description' => 'Target belum tercapai (<60%)'
            ];
        }
    }

    /**
     * Add watermark or footer information
     */
    protected function addFooterInfo(int $startRow, array $additionalInfo = [])
    {
        if (!$this->sheet) {
            return;
        }
        
        $row = $startRow + 2;
        
        // Generated timestamp
        $this->setCellValue('A' . $row, 'Laporan dibuat pada: ' . date('d/m/Y H:i:s'));
        $this->sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(8);
        $row++;
        
        // System info
        $this->setCellValue('A' . $row, 'Sistem Informasi Kesehatan - Akudihatinya');
        $this->sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(8);
        $row++;
        
        // Additional info if provided
        foreach ($additionalInfo as $info) {
            $this->setCellValue('A' . $row, $info);
            $this->sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(8);
            $row++;
        }
    }
}