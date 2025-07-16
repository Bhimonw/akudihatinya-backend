<?php

namespace App\Formatters\Builders;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

/**
 * Builder untuk membuat header Excel dengan berbagai jenis laporan
 */
class ExcelHeaderBuilder
{
    protected $sheet;
    protected $reportType;
    
    // Mapping bulan ke kolom untuk data bulanan
    protected $monthColumns = [
        1 => ['D', 'E', 'F', 'G', 'H'],     // Jan: L, P, Total, TS, %S
        2 => ['I', 'J', 'K', 'L', 'M'],     // Feb: L, P, Total, TS, %S
        3 => ['N', 'O', 'P', 'Q', 'R'],     // Mar: L, P, Total, TS, %S
        4 => ['S', 'T', 'U', 'V', 'W'],     // Apr: L, P, Total, TS, %S
        5 => ['X', 'Y', 'Z', 'AA', 'AB'],   // May: L, P, Total, TS, %S
        6 => ['AC', 'AD', 'AE', 'AF', 'AG'], // Jun: L, P, Total, TS, %S
        7 => ['AH', 'AI', 'AJ', 'AK', 'AL'], // Jul: L, P, Total, TS, %S
        8 => ['AM', 'AN', 'AO', 'AP', 'AQ'], // Aug: L, P, Total, TS, %S
        9 => ['AR', 'AS', 'AT', 'AU', 'AV'], // Sep: L, P, Total, TS, %S
        10 => ['AW', 'AX', 'AY', 'AZ', 'BA'], // Oct: L, P, Total, TS, %S
        11 => ['BB', 'BC', 'BD', 'BE', 'BF'], // Nov: L, P, Total, TS, %S
        12 => ['BG', 'BH', 'BI', 'BJ', 'BK']  // Dec: L, P, Total, TS, %S
    ];
    
    // Mapping triwulan ke kolom
    protected $quarterColumns = [
        1 => ['BL', 'BM', 'BN', 'BO', 'BP'], // TW I: L, P, Total, TS, %S
        2 => ['BQ', 'BR', 'BS', 'BT', 'BU'], // TW II: L, P, Total, TS, %S
        3 => ['BV', 'BW', 'BX', 'BY', 'BZ'], // TW III: L, P, Total, TS, %S
        4 => ['CA', 'CB', 'CC', 'CD', 'CE']  // TW IV: L, P, Total, TS, %S
    ];
    
    // Kolom untuk total tahunan
    protected $totalColumns = ['CF', 'CG', 'CH', 'CI', 'CJ']; // Total: L, P, Total, TS, %S
    
    // Mapping kolom untuk quarterly report
    protected $quarterOnlyColumns = [
        1 => ['D', 'E', 'F', 'G', 'H'],     // TW I: L, P, Total, TS, %S
        2 => ['I', 'J', 'K', 'L', 'M'],     // TW II: L, P, Total, TS, %S
        3 => ['N', 'O', 'P', 'Q', 'R'],     // TW III: L, P, Total, TS, %S
        4 => ['S', 'T', 'U', 'V', 'W']      // TW IV: L, P, Total, TS, %S
    ];
    
    // Kolom untuk total tahunan quarterly
    protected $quarterTotalColumns = ['X', 'Y', 'Z', 'AA', 'AB']; // Total: L, P, Total, TS, %S

    public function __construct(Worksheet $sheet)
    {
        $this->sheet = $sheet;
    }

    /**
     * Setup headers berdasarkan jenis laporan
     */
    public function setupHeaders(string $diseaseType, int $year, string $reportType): void
    {
        $this->reportType = $reportType;
        
        $this->setupTitle($diseaseType, $year);
        $this->setupMainHeader();
        $this->setupPeriodHeaders();
        $this->setupCategoryHeaders();
    }

    /**
     * Setup judul laporan
     */
    private function setupTitle(string $diseaseType, int $year): void
    {
        $diseaseLabel = $diseaseType === 'ht' ? 'HIPERTENSI' : 'DIABETES MELITUS';
        $periodLabel = $this->getPeriodLabel();
        
        $this->sheet->setCellValue('A1', 'LAPORAN STATISTIK ' . $diseaseLabel . ' ' . $periodLabel . ' TAHUN ' . $year);
        $this->sheet->setCellValue('A2', 'DINAS KESEHATAN KABUPATEN/KOTA');
        
        // Merge cells untuk judul
        $lastColumn = $this->getLastColumn();
        $this->sheet->mergeCells('A1:' . $lastColumn . '1');
        $this->sheet->mergeCells('A2:' . $lastColumn . '2');
    }

    /**
     * Setup header utama
     */
    private function setupMainHeader(): void
    {
        $this->sheet->setCellValue('A4', 'NO');
        $this->sheet->setCellValue('B4', 'NAMA PUSKESMAS');
        $this->sheet->setCellValue('C4', 'SASARAN');
        
        // Merge cells untuk header utama
        $this->sheet->mergeCells('A4:A7');
        $this->sheet->mergeCells('B4:B7');
        $this->sheet->mergeCells('C4:C7');
    }

    /**
     * Setup header periode (bulanan/triwulan)
     */
    private function setupPeriodHeaders(): void
    {
        switch ($this->reportType) {
            case 'all':
                $this->setupAllPeriodHeaders();
                break;
            case 'monthly':
                $this->setupMonthlyPeriodHeaders();
                break;
            case 'quarterly':
                $this->setupQuarterlyPeriodHeaders();
                break;
            case 'puskesmas':
                $this->setupPuskesmasPeriodHeaders();
                break;
        }
    }

    /**
     * Setup header untuk laporan all (komprehensif)
     */
    private function setupAllPeriodHeaders(): void
    {
        // Header bulan
        $monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'];
        foreach ($monthNames as $index => $month) {
            $startCol = $this->monthColumns[$index + 1][0];
            $endCol = $this->monthColumns[$index + 1][4];
            $this->sheet->setCellValue($startCol . '4', $month);
            $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
        }
        
        // Header triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $startCol = $this->quarterColumns[$quarter][0];
            $endCol = $this->quarterColumns[$quarter][4];
            $this->sheet->setCellValue($startCol . '4', 'TW ' . $this->numberToRoman($quarter));
            $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
        }
        
        // Header total
        $startCol = $this->totalColumns[0];
        $endCol = $this->totalColumns[4];
        $this->sheet->setCellValue($startCol . '4', 'TOTAL');
        $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
    }

    /**
     * Setup header untuk laporan bulanan
     */
    private function setupMonthlyPeriodHeaders(): void
    {
        $monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'];
        foreach ($monthNames as $index => $month) {
            $startCol = $this->monthColumns[$index + 1][0];
            $endCol = $this->monthColumns[$index + 1][4];
            $this->sheet->setCellValue($startCol . '4', $month);
            $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
        }
    }

    /**
     * Setup header untuk laporan triwulan
     */
    private function setupQuarterlyPeriodHeaders(): void
    {
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $startCol = $this->quarterOnlyColumns[$quarter][0];
            $endCol = $this->quarterOnlyColumns[$quarter][4];
            $this->sheet->setCellValue($startCol . '4', 'TW ' . $this->numberToRoman($quarter));
            $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
        }
        
        // Header total untuk quarterly
        $startCol = $this->quarterTotalColumns[0];
        $endCol = $this->quarterTotalColumns[4];
        $this->sheet->setCellValue($startCol . '4', 'TOTAL');
        $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
    }

    /**
     * Setup header untuk template puskesmas
     */
    private function setupPuskesmasPeriodHeaders(): void
    {
        $monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'];
        foreach ($monthNames as $index => $month) {
            $startCol = $this->monthColumns[$index + 1][0];
            $endCol = $this->monthColumns[$index + 1][4];
            $this->sheet->setCellValue($startCol . '4', $month);
            $this->sheet->mergeCells($startCol . '4:' . $endCol . '4');
        }
    }

    /**
     * Setup header kategori (L, P, Total, TS, %S)
     */
    private function setupCategoryHeaders(): void
    {
        $categories = ['L', 'P', 'TOTAL', 'TS', '%S'];
        
        switch ($this->reportType) {
            case 'all':
                $this->setupAllCategoryHeaders($categories);
                break;
            case 'monthly':
                $this->setupMonthlyCategoryHeaders($categories);
                break;
            case 'quarterly':
                $this->setupQuarterlyCategoryHeaders($categories);
                break;
            case 'puskesmas':
                $this->setupPuskesmasCategoryHeaders($categories);
                break;
        }
    }

    /**
     * Setup kategori untuk laporan all
     */
    private function setupAllCategoryHeaders(array $categories): void
    {
        // Kategori untuk bulan
        for ($month = 1; $month <= 12; $month++) {
            foreach ($categories as $index => $category) {
                $col = $this->monthColumns[$month][$index];
                $this->sheet->setCellValue($col . '6', $category);
                $this->sheet->mergeCells($col . '6:' . $col . '7');
            }
        }
        
        // Kategori untuk triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            foreach ($categories as $index => $category) {
                $col = $this->quarterColumns[$quarter][$index];
                $this->sheet->setCellValue($col . '6', $category);
                $this->sheet->mergeCells($col . '6:' . $col . '7');
            }
        }
        
        // Kategori untuk total
        foreach ($categories as $index => $category) {
            $col = $this->totalColumns[$index];
            $this->sheet->setCellValue($col . '6', $category);
            $this->sheet->mergeCells($col . '6:' . $col . '7');
        }
    }

    /**
     * Setup kategori untuk laporan bulanan
     */
    private function setupMonthlyCategoryHeaders(array $categories): void
    {
        for ($month = 1; $month <= 12; $month++) {
            foreach ($categories as $index => $category) {
                $col = $this->monthColumns[$month][$index];
                $this->sheet->setCellValue($col . '6', $category);
                $this->sheet->mergeCells($col . '6:' . $col . '7');
            }
        }
    }

    /**
     * Setup kategori untuk laporan triwulan
     */
    private function setupQuarterlyCategoryHeaders(array $categories): void
    {
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            foreach ($categories as $index => $category) {
                $col = $this->quarterOnlyColumns[$quarter][$index];
                $this->sheet->setCellValue($col . '6', $category);
                $this->sheet->mergeCells($col . '6:' . $col . '7');
            }
        }
        
        // Kategori untuk total quarterly
        foreach ($categories as $index => $category) {
            $col = $this->quarterTotalColumns[$index];
            $this->sheet->setCellValue($col . '6', $category);
            $this->sheet->mergeCells($col . '6:' . $col . '7');
        }
    }

    /**
     * Setup kategori untuk template puskesmas
     */
    private function setupPuskesmasCategoryHeaders(array $categories): void
    {
        for ($month = 1; $month <= 12; $month++) {
            foreach ($categories as $index => $category) {
                $col = $this->monthColumns[$month][$index];
                $this->sheet->setCellValue($col . '6', $category);
                $this->sheet->mergeCells($col . '6:' . $col . '7');
            }
        }
    }

    /**
     * Get label periode berdasarkan jenis laporan
     */
    private function getPeriodLabel(): string
    {
        switch ($this->reportType) {
            case 'monthly':
                return 'BULANAN';
            case 'quarterly':
                return 'TRIWULAN';
            case 'puskesmas':
                return 'PUSKESMAS';
            default:
                return 'KOMPREHENSIF';
        }
    }

    /**
     * Get kolom terakhir berdasarkan jenis laporan
     */
    private function getLastColumn(): string
    {
        switch ($this->reportType) {
            case 'all':
                return 'CJ'; // Total columns end
            case 'monthly':
                return 'BK'; // December columns end
            case 'quarterly':
                return 'AB'; // Quarter total columns end
            case 'puskesmas':
                return 'BK'; // December columns end
            default:
                return 'CJ';
        }
    }

    /**
     * Convert number to Roman numeral
     */
    private function numberToRoman(int $number): string
    {
        $romanNumerals = [
            1 => 'I',
            2 => 'II', 
            3 => 'III',
            4 => 'IV'
        ];
        
        return $romanNumerals[$number] ?? (string)$number;
    }

    /**
     * Get month columns mapping
     */
    public function getMonthColumns(): array
    {
        return $this->monthColumns;
    }

    /**
     * Get quarter columns mapping
     */
    public function getQuarterColumns(): array
    {
        return $this->quarterColumns;
    }

    /**
     * Get total columns mapping
     */
    public function getTotalColumns(): array
    {
        return $this->totalColumns;
    }

    /**
     * Get quarter only columns mapping
     */
    public function getQuarterOnlyColumns(): array
    {
        return $this->quarterOnlyColumns;
    }

    /**
     * Get quarter total columns mapping
     */
    public function getQuarterTotalColumns(): array
    {
        return $this->quarterTotalColumns;
    }
}