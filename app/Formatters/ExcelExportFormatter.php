<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\Statistics\StatisticsService;
  use App\Formatters\Builders\ExcelHeaderBuilder;
use App\Formatters\Builders\ExcelDataBuilder;
use App\Formatters\Builders\ExcelTotalRowBuilder;
use App\Formatters\Helpers\ExcelPageSetupHelper;
use App\Formatters\Helpers\ExcelCleanupHelper;
use App\Formatters\Helpers\ExcelDimensionHelper;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export Excel berdasarkan template all.xlsx, monthly.xlsx, quarterly.xlsx, dan puskesmas.xlsx
 * Mengikuti struktur yang telah ditentukan dengan header bertingkat dan data bulanan/triwulan
 */
class ExcelExportFormatter
{
    protected $statisticsService;
    protected $sheet;
    protected $currentRow = 7; // Mulai dari baris 7 untuk data
    protected $reportType; // Jenis laporan: 'all', 'monthly', 'quarterly', 'puskesmas'
    
    // Helper instances
    protected $headerBuilder;
    protected $dataBuilder;
    protected $totalRowBuilder;
    protected $pageSetupHelper;
    protected $cleanupHelper;
    protected $dimensionHelper;
    
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
    
    // Mapping triwulan ke kolom (optimized)
    protected $quarterColumns = [
        1 => ['BL', 'BM', 'BN', 'BO', 'BP'], // TW I: L, P, Total, TS, %S
        2 => ['BQ', 'BR', 'BS', 'BT', 'BU'], // TW II: L, P, Total, TS, %S
        3 => ['BV', 'BW', 'BX', 'BY', 'BZ'], // TW III: L, P, Total, TS, %S
        4 => ['CA', 'CB', 'CC', 'CD', 'CE']  // TW IV: L, P, Total, TS, %S
    ];
    
    // Kolom untuk total tahunan dan persentase (optimized)
    protected $totalColumns = ['CF', 'CG', 'CH', 'CI', 'CJ']; // Total: L, P, Total, TS, %S
    
    // Mapping kolom untuk quarterly report (optimized)
    protected $quarterOnlyColumns = [
        1 => ['D', 'E', 'F', 'G', 'H'],     // TW I: L, P, Total, TS, %S
        2 => ['I', 'J', 'K', 'L', 'M'],     // TW II: L, P, Total, TS, %S
        3 => ['N', 'O', 'P', 'Q', 'R'],     // TW III: L, P, Total, TS, %S
        4 => ['S', 'T', 'U', 'V', 'W']      // TW IV: L, P, Total, TS, %S
    ];
    
    // Kolom untuk total tahunan quarterly (optimized)
    protected $quarterTotalColumns = ['X', 'Y', 'Z', 'AA', 'AB']; // Total: L, P, Total, TS, %S

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }
    
    /**
     * Initialize helper instances
     */
    protected function initializeHelpers(): void
    {
        $this->headerBuilder = new ExcelHeaderBuilder($this->sheet, $this->reportType);
        // Create StatisticsCalculator instance for ExcelDataBuilder and ExcelTotalRowBuilder
        $statisticsCalculator = new \App\Formatters\Calculators\StatisticsCalculator();
        $this->dataBuilder = new ExcelDataBuilder($this->sheet, $statisticsCalculator, $this->reportType);
        $this->totalRowBuilder = new ExcelTotalRowBuilder($this->sheet, $statisticsCalculator);
        $this->pageSetupHelper = new ExcelPageSetupHelper($this->sheet);
        $this->cleanupHelper = new ExcelCleanupHelper($this->sheet);
        $this->dimensionHelper = new ExcelDimensionHelper($this->sheet, $this->reportType);
    }
    
    /**
     * Apply styling untuk area data dengan optimasi performa
     */
    private function applyDataAreaStyling(string $lastDataColumn, int $lastDataRow): void
    {
        $this->dimensionHelper->applyDataAreaStyling(8, $lastDataRow, $lastDataColumn);
    }
    
    /**
     * Set dimensi optimal untuk baris dan kolom
     */
    private function setOptimalDimensions(string $lastDataColumn, int $lastDataRow): void
    {
        $this->dimensionHelper->setOptimalDimensions();
    }
    
    /**
     * Finalisasi setup Excel dengan semua konfigurasi halaman
     */
    /**
     * Finalisasi setup Excel dengan konfigurasi optimal
     */
    private function finalizeExcelSetup(string $lastDataColumn, int $lastDataRow): void
    {
        $this->pageSetupHelper->finalizeExcelSetup($lastDataColumn, $lastDataRow);
    }

    /**
     * Format spreadsheet untuk export all.xlsx (rekap tahunan komprehensif)
     */
    public function formatAllExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $puskesmasData = [])
    {
        try {
            $this->sheet = $spreadsheet->getActiveSheet();
            $this->sheet->setTitle('Laporan');
            $this->reportType = 'all';
            
            // Initialize helpers
            $this->initializeHelpers();
            
            // Reset current row
            $this->currentRow = 7;
            
            // Setup header struktur menggunakan HeaderBuilder
            $this->headerBuilder->setupHeaders($diseaseType, $year, $this->reportType);
            
            // Isi data puskesmas menggunakan DataBuilder
        $this->dataBuilder->fillPuskesmasData($puskesmasData, $year, $diseaseType, $this->reportType);
            
            // Apply styling
            $this->applyExcelStyling();
            
            // Set document properties menggunakan PageSetupHelper
            $this->pageSetupHelper->setDocumentProperties($spreadsheet, $diseaseType, $year);
            
            Log::info('Excel formatting completed successfully', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'data_rows' => count($puskesmasData)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error formatting Excel spreadsheet', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
        }
        
        return $spreadsheet;
    }

    /**
     * Format spreadsheet untuk export monthly.xlsx (laporan bulanan)
     */
    public function formatMonthlyExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $puskesmasData = [])
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Laporan');
        $this->reportType = 'monthly';
        
        // Initialize helpers
        $this->initializeHelpers();
        
        // Setup header struktur untuk monthly
        $this->headerBuilder->setupHeaders($diseaseType, $year, $this->reportType);
        
        // Isi data puskesmas untuk monthly
        $this->dataBuilder->fillPuskesmasData($puskesmasData, $year, $diseaseType, $this->reportType);
        
        // Apply styling
        $this->applyExcelStyling();
        
        return $spreadsheet;
    }

    /**
     * Format spreadsheet untuk export quarterly.xlsx (laporan triwulan)
     */
    public function formatQuarterlyExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $puskesmasData = [])
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Laporan');
        $this->reportType = 'quarterly';
        
        // Initialize helpers
        $this->initializeHelpers();
        
        // Setup header struktur untuk quarterly
        $this->headerBuilder->setupHeaders($diseaseType, $year, $this->reportType);
        
        // Isi data puskesmas untuk quarterly
        $this->dataBuilder->fillPuskesmasData($puskesmasData, $year, $diseaseType, $this->reportType);
        
        // Apply styling
        $this->applyExcelStyling();
        
        return $spreadsheet;
    }

    /**
     * Format spreadsheet untuk export puskesmas.xlsx (template per puskesmas)
     */
    public function formatPuskesmasExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, $puskesmasData = null)
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Laporan');
        $this->reportType = 'puskesmas';
        $this->initializeHelpers();
        
        // Setup header struktur untuk puskesmas
        $this->headerBuilder->setupHeaders($diseaseType, $year, $this->reportType);
        
        // Isi metadata puskesmas jika ada
        if ($puskesmasData) {
            $this->dataBuilder->fillSinglePuskesmasData($puskesmasData, $year, $diseaseType);
        }
        
        // Apply styling
        $this->applyExcelStyling();
        $this->pageSetupHelper->setDocumentProperties($spreadsheet, $diseaseType, $year);
        
        return $spreadsheet;
    }

    /**
     * Setup header struktur Excel sesuai spesifikasi
     */
    protected function setupHeaders(string $diseaseType, int $year, string $reportType)
    {
        // Baris 1: Judul Utama
        $diseaseTypeLabel = $diseaseType === 'ht' ? 'Hipertensi' : ($diseaseType === 'dm' ? 'Diabetes Melitus' : 'Hipertensi dan Diabetes Melitus');
        $this->sheet->setCellValue('A1', "LAPORAN PELAYANAN KESEHATAN PADA PENDERITA {$diseaseTypeLabel}");
        
        // Baris 2: Tahun dan Periode
        $periodLabel = $this->getPeriodLabel($reportType);
        $this->sheet->setCellValue('A2', "TAHUN {$year} - {$periodLabel}");
        
        // Baris 3: Kosong untuk spacing
        
        // Baris 4: Header tingkat 1
        $this->sheet->setCellValue('A4', 'NO');
        $this->sheet->setCellValue('B4', 'NAMA PUSKESMAS');
        $this->sheet->setCellValue('C4', 'SASARAN');
        
        // Setup header berdasarkan jenis laporan
        switch ($reportType) {
            case 'all':
                $this->setupAllHeaders();
                break;
            case 'monthly':
                $this->setupMonthlyHeaders();
                break;
            case 'quarterly':
                $this->setupQuarterlyHeaders();
                break;
            case 'puskesmas':
                $this->setupPuskesmasHeaders();
                break;
        }
    }
    
    /**
     * Get period label berdasarkan jenis laporan
     */
    protected function getPeriodLabel(string $reportType): string
    {
        switch ($reportType) {
            case 'all':
                return 'LAPORAN KOMPREHENSIF (BULANAN + TRIWULAN + TAHUNAN)';
            case 'monthly':
                return 'LAPORAN BULANAN';
            case 'quarterly':
                return 'LAPORAN TRIWULAN';
            case 'puskesmas':
                return 'TEMPLATE PUSKESMAS';
            default:
                return 'LAPORAN KESEHATAN';
        }
    }

    /**
     * Setup header untuk laporan all.xlsx (bulanan + triwulan + total)
     */
    protected function setupAllHeaders()
    {
        // Baris 5: Header tingkat 2 - Periode dengan grouping yang lebih rapi
        $months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 
                  'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        
        $col = 'D';
        foreach ($months as $monthIndex => $month) {
            $this->sheet->setCellValue($col . '5', $month);
            // Merge cells untuk setiap bulan (5 kolom: L, P, TOTAL, TS, %S)
            $endCol = $this->incrementColumn($col, 4);
            $this->sheet->mergeCells($col . '5:' . $endCol . '5');
            
            // Tambahkan header triwulan setiap 3 bulan dengan spacing yang lebih baik
            if (($monthIndex + 1) % 3 == 0) {
                $quarterNum = intval(($monthIndex + 1) / 3);
                $quarterLabel = 'TRIWULAN ' . $this->numberToRoman($quarterNum);
                
                // Set header triwulan di baris 4 dengan merge yang lebih luas
                $quarterStartCol = $this->incrementColumn('D', ($quarterNum - 1) * 15); // 3 bulan * 5 kolom = 15
                $quarterEndCol = $this->incrementColumn($quarterStartCol, 14); // 15 kolom - 1
                $this->sheet->setCellValue($quarterStartCol . '4', $quarterLabel);
                $this->sheet->mergeCells($quarterStartCol . '4:' . $quarterEndCol . '4');
                
                // Style untuk header triwulan
                $this->sheet->getStyle($quarterStartCol . '4:' . $quarterEndCol . '4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '0F243E']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'C5D9F1'] // Light blue untuk triwulan
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F4E79']]
                    ]
                ]);
            }
            
            $col = $this->incrementColumn($col, 5); // Skip 5 columns for each month
        }
        
        // Header triwulan summary di kolom terpisah dengan spacing yang lebih baik
        $quarters = ['TRIWULAN I', 'TRIWULAN II', 'TRIWULAN III', 'TRIWULAN IV'];
        foreach ($quarters as $i => $quarter) {
            $quarterCol = $this->quarterColumns[$i + 1][0];
            $this->sheet->setCellValue($quarterCol . '5', $quarter . ' (SUMMARY)');
            // Merge cells untuk setiap triwulan summary (5 kolom: L, P, TOTAL, TS, %S)
            $endQuarterCol = $this->incrementColumn($quarterCol, 4);
            $this->sheet->mergeCells($quarterCol . '5:' . $endQuarterCol . '5');
        }
        
        // Total tahunan dengan merge dan styling yang lebih menonjol
        $this->sheet->setCellValue($this->totalColumns[0] . '5', 'TOTAL TAHUNAN');
        $endTotalCol = $this->incrementColumn($this->totalColumns[0], 4);
        $this->sheet->mergeCells($this->totalColumns[0] . '5:' . $endTotalCol . '5');
        
        // Style khusus untuk total tahunan
        $this->sheet->getStyle($this->totalColumns[0] . '5:' . $endTotalCol . '5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'] // Dark blue untuk total
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => '1F4E79']]
            ]
        ]);
        
        // Baris 6: Header tingkat 4 - Kategori data
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header untuk laporan monthly.xlsx
     */
    protected function setupMonthlyHeaders()
    {
        $months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 
                  'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        
        $col = 'D';
        foreach ($months as $month) {
            $this->sheet->setCellValue($col . '5', $month);
            // Merge cells untuk setiap bulan (5 kolom: L, P, TOTAL, TS, %S)
            $endCol = $this->incrementColumn($col, 4);
            $this->sheet->mergeCells($col . '5:' . $endCol . '5');
            $col = $this->incrementColumn($col, 5);
        }
        
        // Total tahunan dengan merge
        $this->sheet->setCellValue($this->totalColumns[0] . '5', 'TOTAL TAHUNAN');
        $endTotalCol = $this->incrementColumn($this->totalColumns[0], 4);
        $this->sheet->mergeCells($this->totalColumns[0] . '5:' . $endTotalCol . '5');
        
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header untuk laporan quarterly.xlsx (optimized)
     */
    protected function setupQuarterlyHeaders()
    {
        $quarters = ['TRIWULAN I', 'TRIWULAN II', 'TRIWULAN III', 'TRIWULAN IV'];
        
        foreach ($quarters as $i => $quarter) {
            $quarterCol = $this->quarterOnlyColumns[$i + 1][0];
            $this->sheet->setCellValue($quarterCol . '5', $quarter);
            // Merge cells untuk setiap triwulan (5 kolom: L, P, TOTAL, TS, %S)
            $endQuarterCol = $this->incrementColumn($quarterCol, 4);
            $this->sheet->mergeCells($quarterCol . '5:' . $endQuarterCol . '5');
        }
        
        // Total tahunan (optimized) dengan merge
        $this->sheet->setCellValue($this->quarterTotalColumns[0] . '5', 'TOTAL TAHUNAN');
        $endTotalCol = $this->incrementColumn($this->quarterTotalColumns[0], 4);
        $this->sheet->mergeCells($this->quarterTotalColumns[0] . '5:' . $endTotalCol . '5');
        
        $this->setupDataCategoryHeadersQuarterly();
    }

    /**
     * Setup header untuk template puskesmas.xlsx
     */
    protected function setupPuskesmasHeaders()
    {
        // Template dasar untuk puskesmas individual
        $this->sheet->setCellValue('D5', 'DATA BULANAN');
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header kategori data (S, TS, %S, L, P, TOTAL)
     */
    protected function setupDataCategoryHeaders()
    {
        $categories = ['L', 'P', 'TOTAL', 'TS', '%S'];
        
        // Untuk setiap bulan
        foreach ($this->monthColumns as $month => $columns) {
            foreach ($categories as $i => $category) {
                if (isset($columns[$i])) {
                    $this->sheet->setCellValue($columns[$i] . '7', $category);
                }
            }
        }
        
        // Untuk setiap triwulan
        foreach ($this->quarterColumns as $quarter => $columns) {
            foreach ($categories as $i => $category) {
                if (isset($columns[$i])) {
                    $this->sheet->setCellValue($columns[$i] . '7', $category);
                }
            }
        }
        
        // Untuk total tahunan
        foreach ($categories as $i => $category) {
            if (isset($this->totalColumns[$i])) {
                $this->sheet->setCellValue($this->totalColumns[$i] . '7', $category);
            }
        }
    }
    
    /**
     * Setup header kategori data untuk quarterly report (optimized)
     */
    protected function setupDataCategoryHeadersQuarterly()
    {
        $categories = ['L', 'P', 'TOTAL', 'TS', '%S'];
        
        // Untuk setiap triwulan (optimized)
        foreach ($this->quarterOnlyColumns as $quarter => $columns) {
            foreach ($categories as $i => $category) {
                if (isset($columns[$i])) {
                    $this->sheet->setCellValue($columns[$i] . '7', $category);
                }
            }
        }
        
        // Untuk total tahunan quarterly (optimized)
        foreach ($categories as $i => $category) {
            if (isset($this->quarterTotalColumns[$i])) {
                $this->sheet->setCellValue($this->quarterTotalColumns[$i] . '7', $category);
            }
        }
    }

    /**
     * Isi data puskesmas ke dalam spreadsheet dengan optimasi performa
     */
    protected function fillPuskesmasData(array $puskesmasData, int $year, string $diseaseType, string $reportType)
    {
        $config = $this->getDataFillConfig();
        $currentRow = $config['startRow'];
        
        // Cleanup template yang tidak diperlukan
        $this->cleanupTemplateRows();
        
        // Fill data puskesmas dengan batch processing
        $currentRow = $this->processPuskesmasData($puskesmasData, $currentRow, $year, $diseaseType, $reportType);
        
        // Finalisasi dengan total dan footer
        $this->finalizeDataSheet($currentRow, $puskesmasData, $year, $diseaseType, $reportType);
    }
    
    /**
     * Get konfigurasi untuk pengisian data
     */
    private function getDataFillConfig(): array
    {
        return [
            'startRow' => 10,
            'templateRowsToRemove' => [34, 7], // [start, count]
            'footerSpacing' => 5
        ];
    }
    
    /**
     * Cleanup baris template yang tidak diperlukan
     */
    private function cleanupTemplateRows(): void
    {
        $config = $this->getDataFillConfig();
        $this->sheet->removeRow($config['templateRowsToRemove'][0], $config['templateRowsToRemove'][1]);
    }
    
    /**
     * Process data puskesmas dengan efisien
     */
    private function processPuskesmasData(array $puskesmasData, int $startRow, int $year, string $diseaseType, string $reportType): int
    {
        $currentRow = $startRow;
        
        foreach ($puskesmasData as $index => $data) {
            $this->fillPuskesmasRowData($currentRow, $index + 1, $data, $year, $diseaseType, $reportType);
            $currentRow++;
        }
        
        return $currentRow;
    }
    
    /**
     * Fill data untuk satu baris puskesmas
     */
    private function fillPuskesmasRowData(int $row, int $number, array $data, int $year, string $diseaseType, string $reportType): void
    {
        // Basic info columns
        $this->fillBasicPuskesmasInfo($row, $number, $data);
        
        // Statistical data berdasarkan report type
        $this->fillStatisticalData($row, $data, $year, $diseaseType, $reportType);
    }
    
    /**
     * Fill informasi dasar puskesmas (NO, NAMA, SASARAN)
     */
    private function fillBasicPuskesmasInfo(int $row, int $number, array $data): void
    {
        $basicData = [
            'A' => $number,
            'B' => $data['nama_puskesmas'] ?? '',
            'C' => $data['sasaran'] ?? 0
        ];
        
        foreach ($basicData as $column => $value) {
            $this->sheet->setCellValue($column . $row, $value);
        }
    }
    
    /**
     * Fill data statistik berdasarkan report type
     */
    private function fillStatisticalData(int $row, array $data, int $year, string $diseaseType, string $reportType): void
    {
        $fillMethods = [
            'all' => 'fillAllData',
            'monthly' => 'fillMonthlyData',
            'quarterly' => 'fillQuarterlyData'
        ];
        
        $method = $fillMethods[$reportType] ?? $fillMethods['monthly'];
        $this->$method($row, $data, $year, $diseaseType);
    }
    
    /**
     * Finalisasi sheet dengan total dan footer
     */
    private function finalizeDataSheet(int $currentRow, array $puskesmasData, int $year, string $diseaseType, string $reportType): void
    {
        // Add total row
        $this->addTotalRow($currentRow, $puskesmasData, $reportType);
        
        // Add footer dengan spacing
        $this->addFooter($currentRow + 2, $year, $diseaseType, $reportType);
        
        // Update current row untuk styling
        $config = $this->getDataFillConfig();
        $this->currentRow = $currentRow + $config['footerSpacing'];
    }
    
    /**
     * Tambahkan footer dengan informasi tambahan (Simplified & Optimized)
     */
    protected function addFooter(int $startRow, int $year, string $diseaseType, string $reportType)
    {
        $footerRow = $startRow + 2;
        
        // Footer data dengan struktur yang lebih bersih
        $footerData = [
            ['text' => 'Tanggal Pembuatan: ' . date('d/m/Y H:i:s'), 'style' => ['size' => 9, 'italic' => true]],
            ['text' => 'Dibuat oleh: Sistem Akudihatinya - Dinas Kesehatan', 'style' => ['size' => 9, 'italic' => true]],
            ['text' => '', 'style' => []], // Baris kosong
            ['text' => 'Keterangan:', 'style' => ['size' => 10, 'bold' => true]],
            ['text' => '• L = Laki-laki, P = Perempuan', 'style' => ['size' => 9]],
            ['text' => '• TS = Tidak Standar, %S = Persentase Standar', 'style' => ['size' => 9]],
            ['text' => $this->getReportTypeDescription($reportType), 'style' => ['size' => 9]]
        ];
        
        // Render footer dengan loop yang efisien
        foreach ($footerData as $item) {
            if (!empty($item['text'])) {
                $this->sheet->setCellValue('A' . $footerRow, $item['text']);
                $this->applyFooterStyle('A' . $footerRow, $item['style']);
            }
            $footerRow++;
        }
        
        // Cleanup footer area dengan metode yang disederhanakan
        $this->cleanupFooterArea($startRow, $footerRow);
    }
    
    /**
     * Get description berdasarkan report type
     */
    private function getReportTypeDescription(string $reportType): string
    {
        $descriptions = [
            'quarterly' => '• TW = Triwulan (TW I: Jan-Mar, TW II: Apr-Jun, TW III: Jul-Sep, TW IV: Oct-Des)',
            'default' => '• Data dihitung berdasarkan pemeriksaan yang tercatat dalam sistem'
        ];
        
        return $descriptions[$reportType] ?? $descriptions['default'];
    }
    
    /**
     * Apply footer styling dengan metode yang disederhanakan
     */
    private function applyFooterStyle(string $cell, array $style): void
    {
        $cellStyle = $this->sheet->getStyle($cell)->getFont();
        
        if (isset($style['size'])) $cellStyle->setSize($style['size']);
        if (isset($style['bold'])) $cellStyle->setBold($style['bold']);
        if (isset($style['italic'])) $cellStyle->setItalic($style['italic']);
    }
    
    /**
     * Cleanup footer area dengan metode yang efisien
     */
    private function cleanupFooterArea(int $startRow, int $endRow): void
    {
        $lastColumn = $this->getLastStatisticsColumn();
        $footerRange = 'A' . ($startRow + 1) . ':' . $lastColumn . ($endRow + 2);
        
        $this->sheet->getStyle($footerRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            'fill' => ['fillType' => Fill::FILL_NONE],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_TOP
            ]
        ]);
    }

    /**
     * Isi data untuk laporan all.xlsx (bulanan + triwulan + total)
     */
    protected function fillAllData(int $row, array $data, int $year, string $diseaseType)
    {
        // Data bulanan
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $data['monthly_data'][$month] ?? [];
            $this->fillMonthDataToColumns($row, $month, $monthData);
        }
        
        // Data triwulan (dihitung dari data bulanan)
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $this->calculateQuarterData($data['monthly_data'] ?? [], $quarter);
            $this->fillQuarterDataToColumns($row, $quarter, $quarterData);
        }
        
        // Total tahunan
        $totalData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
        $this->fillTotalDataToColumns($row, $totalData);
    }

    /**
     * Isi data untuk laporan monthly.xlsx
     */
    protected function fillMonthlyData(int $row, array $data, int $year, string $diseaseType)
    {
        // Data bulanan
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $data['monthly_data'][$month] ?? [];
            $this->fillMonthDataToColumns($row, $month, $monthData);
        }
        
        // Total tahunan
        $totalData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
        $this->fillTotalDataToColumns($row, $totalData);
    }

    /**
     * Isi data untuk laporan quarterly.xlsx (optimized)
     */
    protected function fillQuarterlyData(int $row, array $data, int $year, string $diseaseType)
    {
        // Data triwulan (optimized)
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $this->calculateQuarterData($data['monthly_data'] ?? [], $quarter);
            $this->fillQuarterOnlyDataToColumns($row, $quarter, $quarterData);
        }
        
        // Total tahunan (optimized)
        $totalData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
        $this->fillQuarterTotalDataToColumns($row, $totalData);
    }

    /**
     * Isi data untuk single puskesmas template
     */
    protected function fillSinglePuskesmasData($puskesmasData, int $year, string $diseaseType)
    {
        // Baris 3: Nama puskesmas
        $this->sheet->setCellValue('B3', $puskesmasData['nama_puskesmas'] ?? '');
        
        // Baris 2: Sasaran (jika ada)
        if (isset($puskesmasData['sasaran'])) {
            $this->sheet->setCellValue('C2', $puskesmasData['sasaran']);
        }
    }

    /**
     * Isi data bulanan ke kolom yang sesuai
     */
    protected function fillMonthDataToColumns(int $row, int $month, array $monthData)
    {
        if (!isset($this->monthColumns[$month])) {
            return;
        }
        
        $columns = $this->monthColumns[$month];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $row, $monthData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $row, $monthData['female'] ?? 0);
        
        // TOTAL
        $total = ($monthData['male'] ?? 0) + ($monthData['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($columns[3] . $row, $monthData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $monthData['standard'] ?? 0;
        // Hitung persentase standar (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        // Set nilai persentase dalam format desimal (75% = 0.75)
        $this->sheet->setCellValue($columns[4] . $row, $percentage / 100);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle($columns[4] . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    }

    /**
     * Isi data triwulan ke kolom yang sesuai
     */
    protected function fillQuarterDataToColumns(int $row, int $quarter, array $quarterData)
    {
        if (!isset($this->quarterColumns[$quarter])) {
            return;
        }
        
        $columns = $this->quarterColumns[$quarter];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $row, $quarterData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $row, $quarterData['female'] ?? 0);
        
        // TOTAL
        $total = ($quarterData['male'] ?? 0) + ($quarterData['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($columns[3] . $row, $quarterData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $quarterData['standard'] ?? 0;
        // Hitung persentase standar (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        // Set nilai persentase dalam format desimal (75% = 0.75)
        $this->sheet->setCellValue($columns[4] . $row, $percentage / 100);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle($columns[4] . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    }

    /**
     * Isi data total tahunan ke kolom yang sesuai
     */
    protected function fillTotalDataToColumns(int $row, array $totalData)
    {
        // L (Laki-laki)
        $this->sheet->setCellValue($this->totalColumns[0] . $row, $totalData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($this->totalColumns[1] . $row, $totalData['female'] ?? 0);
        
        // TOTAL
        $total = ($totalData['male'] ?? 0) + ($totalData['female'] ?? 0);
        $this->sheet->setCellValue($this->totalColumns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($this->totalColumns[3] . $row, $totalData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $totalData['standard'] ?? 0;
        // Hitung persentase standar total (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        // Set nilai persentase dalam format desimal (75% = 0.75)
        $this->sheet->setCellValue($this->totalColumns[4] . $row, $percentage / 100);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle($this->totalColumns[4] . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    }
    
    /**
     * Isi data triwulan ke kolom yang sesuai (optimized untuk quarterly)
     */
    protected function fillQuarterOnlyDataToColumns(int $row, int $quarter, array $quarterData)
    {
        if (!isset($this->quarterOnlyColumns[$quarter])) {
            return;
        }
        
        $columns = $this->quarterOnlyColumns[$quarter];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $row, $quarterData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $row, $quarterData['female'] ?? 0);
        
        // TOTAL
        $total = ($quarterData['male'] ?? 0) + ($quarterData['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($columns[3] . $row, $quarterData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $quarterData['standard'] ?? 0;
        $percentage = $this->calculateStandardPercentage($standard, $total);
        $this->sheet->setCellValue($columns[4] . $row, $percentage / 100);
        $this->sheet->getStyle($columns[4] . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    }
    
    /**
     * Isi data total tahunan ke kolom yang sesuai (optimized untuk quarterly)
     */
    protected function fillQuarterTotalDataToColumns(int $row, array $totalData)
    {
        // L (Laki-laki)
        $this->sheet->setCellValue($this->quarterTotalColumns[0] . $row, $totalData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($this->quarterTotalColumns[1] . $row, $totalData['female'] ?? 0);
        
        // TOTAL
        $total = ($totalData['male'] ?? 0) + ($totalData['female'] ?? 0);
        $this->sheet->setCellValue($this->quarterTotalColumns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($this->quarterTotalColumns[3] . $row, $totalData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $totalData['standard'] ?? 0;
        $percentage = $this->calculateStandardPercentage($standard, $total);
        $this->sheet->setCellValue($this->quarterTotalColumns[4] . $row, $percentage / 100);
        $this->sheet->getStyle($this->quarterTotalColumns[4] . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    }

    /**
     * Hitung data triwulan dari data bulanan
     */
    protected function calculateQuarterData(array $monthlyData, int $quarter): array
    {
        $quarterMonths = [
            1 => [1, 2, 3],   // Q1: Jan, Feb, Mar
            2 => [4, 5, 6],   // Q2: Apr, May, Jun
            3 => [7, 8, 9],   // Q3: Jul, Aug, Sep
            4 => [10, 11, 12] // Q4: Oct, Nov, Dec
        ];
        
        $months = $quarterMonths[$quarter] ?? [];
        $quarterData = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0
        ];
        
        foreach ($months as $month) {
            if (isset($monthlyData[$month])) {
                $quarterData['male'] += $monthlyData[$month]['male'] ?? 0;
                $quarterData['female'] += $monthlyData[$month]['female'] ?? 0;
                $quarterData['standard'] += $monthlyData[$month]['standard'] ?? 0;
                $quarterData['non_standard'] += $monthlyData[$month]['non_standard'] ?? 0;
            }
        }
        
        return $quarterData;
    }

    /**
     * Hitung total tahunan dari data bulanan
     */
    protected function calculateYearlyTotal(array $monthlyData): array
    {
        $totalData = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0
        ];
        
        for ($month = 1; $month <= 12; $month++) {
            if (isset($monthlyData[$month])) {
                $totalData['male'] += $monthlyData[$month]['male'] ?? 0;
                $totalData['female'] += $monthlyData[$month]['female'] ?? 0;
                $totalData['standard'] += $monthlyData[$month]['standard'] ?? 0;
                $totalData['non_standard'] += $monthlyData[$month]['non_standard'] ?? 0;
            }
        }
        
        return $totalData;
    }

    /**
     * Tambah baris total di akhir data
     */
    /**
     * Tambahkan baris total dengan optimasi performa dan styling yang konsisten
     */
    protected function addTotalRow(int $row, array $puskesmasData, string $reportType)
    {
        // Persiapan baris total
        $this->prepareTotalRow($row);
        
        // Isi data dasar total
        $this->fillBasicTotalInfo($row, $puskesmasData);
        
        // Isi data statistik total berdasarkan report type
        $this->fillTotalRowData($row, $puskesmasData, $reportType);
        
        // Apply styling untuk baris total
        $this->applyTotalRowStyling($row);
    }
    
    /**
     * Persiapan baris total dengan insert row baru
     */
    private function prepareTotalRow(int $row): void
    {
        $this->sheet->insertNewRowBefore($row, 1);
    }
    
    /**
     * Isi informasi dasar untuk baris total
     */
    private function fillBasicTotalInfo(int $row, array $puskesmasData): void
    {
        $totalData = $this->calculateBasicTotalData($puskesmasData);
        
        $basicTotalInfo = [
            'A' => 'TOTAL',
            'B' => 'KESELURUHAN',
            'C' => $totalData['sasaran']
        ];
        
        foreach ($basicTotalInfo as $column => $value) {
            $this->sheet->setCellValue($column . $row, $value);
        }
    }
    
    /**
     * Hitung data dasar untuk baris total
     */
    private function calculateBasicTotalData(array $puskesmasData): array
    {
        return [
            'sasaran' => array_sum(array_column($puskesmasData, 'sasaran')),
            'count' => count($puskesmasData)
        ];
    }
    
    /**
     * Apply styling untuk baris total dengan konfigurasi yang terstruktur
     */
    private function applyTotalRowStyling(int $row): void
    {
        $lastDataColumn = $this->getLastStatisticsColumn();
        $totalRowRange = 'A' . $row . ':' . $lastDataColumn . $row;
        
        // Style utama untuk seluruh baris total
        $this->applyMainTotalRowStyle($totalRowRange);
        
        // Style khusus untuk kolom identifikasi
        $this->applyTotalRowIdentificationStyle($row);
        
        // Set dimensi baris total
        $this->setTotalRowDimensions($row);
    }
    
    /**
     * Apply style utama untuk baris total
     */
    private function applyMainTotalRowStyle(string $range): void
    {
        $totalRowStyle = $this->getTotalRowStyleConfig();
        $this->sheet->getStyle($range)->applyFromArray($totalRowStyle);
    }
    
    /**
     * Get konfigurasi style untuk baris total
     */
    private function getTotalRowStyleConfig(): array
    {
        return [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['rgb' => '1F4E79']
                ]
            ]
        ];
    }
    
    /**
     * Apply style khusus untuk kolom identifikasi (NO dan NAMA)
     */
    private function applyTotalRowIdentificationStyle(int $row): void
    {
        $identificationRange = 'A' . $row . ':B' . $row;
        $this->sheet->getStyle($identificationRange)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }
    
    /**
     * Set dimensi untuk baris total
     */
    private function setTotalRowDimensions(int $row): void
    {
        $this->sheet->getRowDimension($row)->setRowHeight(28);
    }

    /**
     * Apply styling ke Excel dengan optimasi performa dan modularitas
     */
    protected function applyExcelStyling()
    {
        $styleConfig = $this->getExcelStyleConfig();
        
        // Cleanup dan persiapan
        $this->cleanupHelper->cleanupExtraAreas($styleConfig['lastDataColumn'], $styleConfig['lastDataRow']);
        
        // Apply styling bertahap
        $this->dimensionHelper->applyTitleStyling($styleConfig);
        $this->dimensionHelper->applyHeaderStyling($styleConfig);
        $this->dimensionHelper->applyDataAreaStyling($styleConfig['lastDataColumn'], $styleConfig['lastDataRow']);
        $this->applyStatisticsBorders($styleConfig);
        
        // Finalisasi
        $this->dimensionHelper->setOptimalDimensions($styleConfig['lastDataColumn'], $styleConfig['lastDataRow']);
        $this->pageSetupHelper->finalizeExcelSetup($styleConfig['lastDataColumn'], $styleConfig['lastDataRow']);
    }
    
    /**
     * Get konfigurasi styling Excel
     */
    private function getExcelStyleConfig(): array
    {
        return [
            'lastDataRow' => $this->sheet->getHighestDataRow(),
            'lastDataColumn' => $this->getLastStatisticsColumn()
        ];
    }
    
    /**
     * Persiapan worksheet untuk styling
     */
    private function prepareWorksheetForStyling(array $config): void
    {
        // Cleanup tahap awal
        $this->removeAutoTables();
        $this->cleanupExtraAreas($config['lastDataColumn'], $config['lastDataRow']);
        $this->removeBordersOutsideStatistics($config['lastDataColumn'], $config['lastDataRow']);
    }
    
    /**
     * Apply styling untuk area judul
     */
    private function applyTitleStyling(array $config): void
    {
        $titleRanges = $this->getTitleRanges($config['lastDataColumn']);
        
        // Merge cells untuk judul
        foreach ($titleRanges as $range) {
            $this->sheet->mergeCells($range['range']);
        }
        
        // Apply styling untuk setiap judul
        $this->applyTitleStyles($titleRanges);
        $this->setTitleRowDimensions();
    }
    
    /**
     * Get konfigurasi range untuk judul
     */
    private function getTitleRanges(string $lastColumn): array
    {
        return [
            'title1' => [
                'range' => 'A1:' . $lastColumn . '1',
                'style' => $this->getTitle1StyleConfig()
            ],
            'title2' => [
                'range' => 'A2:' . $lastColumn . '2',
                'style' => $this->getTitle2StyleConfig()
            ]
        ];
    }
    
    /**
     * Apply styling untuk semua judul
     */
    private function applyTitleStyles(array $titleRanges): void
    {
        foreach ($titleRanges as $titleConfig) {
            $this->sheet->getStyle($titleConfig['range'])->applyFromArray($titleConfig['style']);
        }
    }
    
    /**
     * Get konfigurasi style untuk judul utama
     */
    private function getTitle1StyleConfig(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6ED']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F4E79']]]
        ];
    }
    
    /**
     * Get konfigurasi style untuk sub judul
     */
    private function getTitle2StyleConfig(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '2F5597']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F4E79']]]
        ];
    }
    
        /**
     * Set dimensi untuk baris judul dengan ukuran yang lebih sesuai
     */
    private function setTitleRowDimensions(): void
    {
        $this->sheet->getRowDimension('1')->setRowHeight(25); // Diperkecil dari 30
        $this->sheet->getRowDimension('2')->setRowHeight(20); // Diperkecil dari 25
        $this->sheet->getRowDimension('3')->setRowHeight(5);  // Baris kosong spacing
    }
    
    /**
     * Apply styling untuk area header
     */
    private function applyHeaderStyling(array $config): void
    {
        // Header utama (kolom identifikasi) - dengan merge yang lebih rapi
        $this->applyMainHeaderStyling();
        
        // Header periode dan kategori
        $this->applyPeriodHeaderStyling($config['lastDataColumn']);
        $this->applyCategoryHeaderStyling($config['lastDataColumn']);
        
        // Set tinggi baris header yang optimal
        $this->setOptimalHeaderRowHeights();
    }
    
    /**
     * Apply styling untuk header utama dengan merge yang lebih rapi
     */
    private function applyMainHeaderStyling(): void
    {
        // Merge cells untuk header utama dengan ukuran yang lebih kompak
        $mainHeaderCells = [
            'A4:A6', // NO - merge 3 baris saja
            'B4:B6', // NAMA PUSKESMAS - merge 3 baris saja  
            'C4:C6'  // SASARAN - merge 3 baris saja
        ];
        
        foreach ($mainHeaderCells as $range) {
            $this->sheet->mergeCells($range);
        }
        
        // Apply styling dengan ukuran font yang disesuaikan
        $mainHeaderRange = 'A4:C6';
        $this->sheet->getStyle($mainHeaderRange)->applyFromArray($this->getMainHeaderStyleConfig());
    }
    
    /**
     * Get konfigurasi style untuk header utama dengan ukuran yang lebih kompak
     */
    private function getMainHeaderStyleConfig(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1F4E79']], // Ukuran font diperkecil
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E2F3']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F4E79']]]
        ];
    }
    
    /**
     * Set tinggi baris header yang optimal
     */
    private function setOptimalHeaderRowHeights(): void
    {
        // Tinggi baris header yang lebih kompak
        $this->sheet->getRowDimension('4')->setRowHeight(20); // Header utama
        $this->sheet->getRowDimension('5')->setRowHeight(18); // Sub header periode
        $this->sheet->getRowDimension('6')->setRowHeight(16); // Header kategori
        $this->sheet->getRowDimension('7')->setRowHeight(14); // Header detail (jika ada)
    }
    
    /**
     * Apply styling untuk header periode
     */
    private function applyPeriodHeaderStyling(string $lastColumn): void
    {
        $periodHeaderRange = 'D5:' . $lastColumn . '5';
        $this->sheet->getStyle($periodHeaderRange)->applyFromArray($this->getPeriodHeaderStyleConfig());
    }
    
    /**
     * Get konfigurasi style untuk header periode
     */
    private function getPeriodHeaderStyleConfig(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F243E']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F4E79']]]
        ];
    }
    
    /**
     * Apply styling untuk header kategori
     */
    private function applyCategoryHeaderStyling(string $lastColumn): void
    {
        $categoryHeaderRange = 'D6:' . $lastColumn . '7';
        $this->sheet->getStyle($categoryHeaderRange)->applyFromArray($this->getCategoryHeaderStyleConfig());
    }
    
    /**
     * Get konfigurasi style untuk header kategori
     */
    private function getCategoryHeaderStyleConfig(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '2F5597']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6ED']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '70AD47']]]
        ];
    }
    
    /**
     * Apply border untuk area statistik
     */
    private function applyStatisticsBorders(array $config): void
    {
        $statisticsRange = 'A4:' . $config['lastDataColumn'] . $config['lastDataRow'];
        $this->sheet->getStyle($statisticsRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Isi data total untuk baris TOTAL berdasarkan jenis laporan
     */
    protected function fillTotalRowData(int $row, array $puskesmasData, string $reportType)
    {
        switch ($reportType) {
            case 'all':
                $this->fillTotalAllData($row, $puskesmasData);
                break;
            case 'monthly':
                $this->fillTotalMonthlyData($row, $puskesmasData);
                break;
            case 'quarterly':
                $this->fillTotalQuarterlyData($row, $puskesmasData);
                break;
        }
    }
    
    /**
     * Isi total data untuk laporan all.xlsx - dengan perbaikan duplikasi total
     */
    protected function fillTotalAllData(int $row, array $puskesmasData)
    {
        // Hitung total untuk setiap bulan
        for ($month = 1; $month <= 12; $month++) {
            $monthTotal = $this->calculateMonthTotal($puskesmasData, $month);
            $this->fillMonthDataToColumns($row, $month, $monthTotal);
        }
        
        // Hitung dan isi total tahunan (menggabungkan triwulan dan total keseluruhan)
        $yearlyTotal = $this->calculateYearlyTotalFromAll($puskesmasData);
        
        // Isi data triwulan summary berdasarkan data tahunan terbaru
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterTotal = $this->calculateQuarterFromYearlyData($yearlyTotal, $quarter, $puskesmasData);
            $this->fillQuarterDataToColumns($row, $quarter, $quarterTotal);
        }
        
        // Isi total tahunan (hanya satu total, bukan duplikasi)
        $this->fillTotalDataToColumns($row, $yearlyTotal);
        
        // Kosongkan baris setelah total dan hapus garis tabel
        $this->clearRowsAfterTotal($row + 1, 5); // Kosongkan 5 baris setelah total
    }
    
    /**
     * Isi total data untuk laporan monthly.xlsx
     */
    protected function fillTotalMonthlyData(int $row, array $puskesmasData)
    {
        // Hitung total untuk setiap bulan
        for ($month = 1; $month <= 12; $month++) {
            $monthTotal = $this->calculateMonthTotal($puskesmasData, $month);
            $this->fillMonthDataToColumns($row, $month, $monthTotal);
        }
        
        // Hitung total tahunan
        $yearlyTotal = $this->calculateYearlyTotalFromAll($puskesmasData);
        $this->fillTotalDataToColumns($row, $yearlyTotal);
    }
    
    /**
     * Isi total data untuk laporan quarterly.xlsx (optimized)
     */
    protected function fillTotalQuarterlyData(int $row, array $puskesmasData)
    {
        // Hitung total untuk setiap triwulan (optimized)
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterTotal = $this->calculateQuarterTotal($puskesmasData, $quarter);
            $this->fillQuarterOnlyDataToColumns($row, $quarter, $quarterTotal);
        }
        
        // Hitung total tahunan (optimized)
        $yearlyTotal = $this->calculateYearlyTotalFromAll($puskesmasData);
        $this->fillQuarterTotalDataToColumns($row, $yearlyTotal);
    }
    
    /**
     * Hitung total bulanan dari semua puskesmas
     */
    protected function calculateMonthTotal(array $puskesmasData, int $month): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $monthData = $data['monthly_data'][$month] ?? [];
            $total['male'] += $monthData['male'] ?? 0;
            $total['female'] += $monthData['female'] ?? 0;
            $total['standard'] += $monthData['standard'] ?? 0;
            $total['non_standard'] += $monthData['non_standard'] ?? 0;
        }
        
        return $total;
    }
    
    /**
     * Hitung total triwulan dari semua puskesmas
     */
    protected function calculateQuarterTotal(array $puskesmasData, int $quarter): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $quarterData = $this->calculateQuarterData($data['monthly_data'] ?? [], $quarter);
            $total['male'] += $quarterData['male'] ?? 0;
            $total['female'] += $quarterData['female'] ?? 0;
            $total['standard'] += $quarterData['standard'] ?? 0;
            $total['non_standard'] += $quarterData['non_standard'] ?? 0;
        }
        
        return $total;
    }
    
    /**
     * Hitung total tahunan dari semua puskesmas
     */
    protected function calculateYearlyTotalFromAll(array $puskesmasData): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $yearlyData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
            $total['male'] += $yearlyData['male'] ?? 0;
            $total['female'] += $yearlyData['female'] ?? 0;
            $total['standard'] += $yearlyData['standard'] ?? 0;
            $total['non_standard'] += $yearlyData['non_standard'] ?? 0;
        }
        
        return $total;
    }

    /**
     * Hitung persentase standar
     */
    protected function calculateStandardPercentage(int $standard, int $total): float
    {
        if ($total == 0) {
            return 0;
        }
        
        $percentage = ($standard / $total) * 100;
        return min($percentage, 100); // Maksimal 100%
    }
    
    /**
     * Dapatkan kolom terakhir untuk area statistik
     */
    protected function getLastStatisticsColumn(): string
    {
        // Tentukan kolom terakhir berdasarkan jenis laporan yang sedang diproses
        switch ($this->reportType) {
            case 'quarterly':
                // Quarterly report: sampai kolom total quarterly (AB)
                return 'AB';
                
            case 'monthly':
                // Monthly report: sampai kolom total tahunan (CJ)
                return 'CJ';
                
            case 'all':
            default:
                // All report: sampai kolom total tahunan (CJ)
                return 'CJ';
        }
    }
    
    /**
     * Set lebar kolom yang optimal dengan konfigurasi yang terstruktur dan lebih kompak
     */
    protected function setOptimalColumnWidths(string $lastColumn): void
    {
        // Konfigurasi lebar kolom utama yang lebih kompak
        $mainColumnWidths = [
            'A' => 4,  // NO - diperkecil untuk lebih kompak
            'B' => 28, // NAMA PUSKESMAS - diperkecil sedikit
            'C' => 10  // SASARAN - diperkecil
        ];
        
        // Set lebar kolom utama
        foreach ($mainColumnWidths as $column => $width) {
            $this->sheet->getColumnDimension($column)->setWidth($width);
        }
        
        // Optimasi kolom data dengan lebar yang konsisten dan lebih kompak
        $this->optimizeDataColumns($lastColumn);
    }
    
    /**
     * Optimasi lebar kolom data dengan batasan yang wajar dan lebih kompak
     */
    private function optimizeDataColumns(string $lastColumn): void
    {
        $startColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('D');
        $lastColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);
        $dataColumnWidth = 8; // Lebar diperkecil untuk lebih kompak
        
        // Set lebar yang konsisten untuk semua kolom data
        for ($i = $startColumnIndex; $i <= $lastColumnIndex; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $this->sheet->getColumnDimension($col)->setWidth($dataColumnWidth);
        }
    }
    
    /**
     * Hitung data triwulan berdasarkan data tahunan terbaru
     */
    protected function calculateQuarterFromYearlyData(array $yearlyTotal, int $quarter, array $puskesmasData): array
    {
        // Ambil data terbaru dari puskesmas untuk triwulan tertentu
        $quarterMonths = $this->getQuarterMonths($quarter);
        $quarterData = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $monthlyData = $data['monthly_data'] ?? [];
            foreach ($quarterMonths as $month) {
                if (isset($monthlyData[$month])) {
                    $monthData = $monthlyData[$month];
                    $quarterData['male'] += $monthData['male'] ?? 0;
                    $quarterData['female'] += $monthData['female'] ?? 0;
                    $quarterData['standard'] += $monthData['standard'] ?? 0;
                    $quarterData['non_standard'] += $monthData['non_standard'] ?? 0;
                }
            }
        }
        
        return $quarterData;
    }
    
    /**
     * Dapatkan bulan-bulan dalam triwulan
     */
    protected function getQuarterMonths(int $quarter): array
    {
        $quarters = [
            1 => [1, 2, 3],   // Q1: Jan, Feb, Mar
            2 => [4, 5, 6],   // Q2: Apr, May, Jun
            3 => [7, 8, 9],   // Q3: Jul, Aug, Sep
            4 => [10, 11, 12] // Q4: Oct, Nov, Dec
        ];
        
        return $quarters[$quarter] ?? [];
    }
    
    /**
     * Kosongkan baris setelah total
     */
    protected function clearRowsAfterTotal(int $startRow, int $numRows): void
    {
        $lastColumn = $this->getLastStatisticsColumn();
        
        for ($i = 0; $i < $numRows; $i++) {
            $row = $startRow + $i;
            $range = 'A' . $row . ':' . $lastColumn . $row;
            
            // Kosongkan nilai
            for ($col = 'A'; $col <= $lastColumn; $col++) {
                $this->sheet->setCellValue($col . $row, '');
            }
            
            // Hapus semua styling termasuk border
            $this->sheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_NONE],
                    'outline' => ['borderStyle' => Border::BORDER_NONE],
                    'inside' => ['borderStyle' => Border::BORDER_NONE]
                ],
                'fill' => ['fillType' => Fill::FILL_NONE],
                'font' => [
                    'bold' => false,
                    'size' => 11,
                    'color' => ['rgb' => '000000']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_GENERAL,
                    'vertical' => Alignment::VERTICAL_BOTTOM
                ]
            ]);
        }
    }
    
    /**
     * Bersihkan area di luar range statistik
     */
    protected function cleanupExtraAreas(string $lastColumn, int $lastRow): void
    {
        $highestColumn = $this->sheet->getHighestColumn();
        $highestRow = $this->sheet->getHighestRow();
        
        $lastColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // 1. Hapus semua konten dan styling di kolom setelah lastColumn
        if ($highestColumnIndex > $lastColumnIndex) {
            for ($i = $lastColumnIndex + 1; $i <= $highestColumnIndex; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                
                // Hapus seluruh kolom sekaligus untuk efisiensi
                $columnRange = $col . '1:' . $col . $highestRow;
                
                // Kosongkan nilai
                for ($row = 1; $row <= $highestRow; $row++) {
                    $this->sheet->setCellValue($col . $row, '');
                }
                
                // Hapus semua styling sekaligus
                $this->sheet->getStyle($columnRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE]],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE],
                    'font' => ['bold' => false, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_GENERAL,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_BOTTOM,
                        'wrapText' => false
                    ]
                ]);
            }
        }
        
        // 2. Hapus konten di baris setelah footer (beri ruang 8 baris untuk footer)
        $footerEndRow = $lastRow + 8;
        if ($highestRow > $footerEndRow) {
            for ($row = $footerEndRow + 1; $row <= $highestRow; $row++) {
                $rowRange = 'A' . $row . ':' . $lastColumn . $row;
                
                // Kosongkan nilai
                for ($col = 1; $col <= $lastColumnIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $this->sheet->setCellValue($colLetter . $row, '');
                }
                
                // Hapus styling untuk seluruh baris sekaligus
                $this->sheet->getStyle($rowRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE]],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE],
                    'font' => ['bold' => false, 'size' => 11, 'color' => ['rgb' => '000000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_GENERAL,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_BOTTOM,
                        'wrapText' => false
                    ]
                ]);
            }
        }
    }
    
    /**
     * Hapus border dari area di luar statistik
     */
    protected function removeBordersOutsideStatistics(string $lastColumn, int $lastRow): void
    {
        $highestColumn = $this->sheet->getHighestColumn();
        $highestRow = $this->sheet->getHighestRow();
        
        $lastColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Hapus border dari kolom di luar area statistik
        if ($highestColumnIndex > $lastColumnIndex) {
            for ($i = $lastColumnIndex + 1; $i <= $highestColumnIndex; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $range = $col . '1:' . $col . $highestRow;
                $this->sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
            }
        }
        
        // Hapus border dari baris di luar area data (kecuali footer)
        $footerStartRow = $lastRow + 1;
        if ($highestRow > $footerStartRow + 5) {
            for ($row = $footerStartRow + 6; $row <= $highestRow; $row++) {
                $range = 'A' . $row . ':' . $lastColumn . $row;
                $this->sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
            }
        }
    }
    
    /**
     * Hapus tabel Excel otomatis yang mungkin terbentuk
     */
    protected function removeAutoTables(): void
    {
        // Hapus semua tabel yang mungkin terbentuk secara otomatis
        $tables = $this->sheet->getTableCollection();
        foreach ($tables as $table) {
            $this->sheet->removeTable($table->getName());
        }
        
        // Pastikan tidak ada autofilter yang aktif di luar area yang diinginkan
        if ($this->sheet->getAutoFilter()->getRange()) {
            $this->sheet->setAutoFilter('');
        }
        
        // Hapus semua named ranges yang mungkin terbentuk
        $namedRanges = $this->sheet->getParent()->getNamedRanges();
        foreach ($namedRanges as $namedRange) {
            $this->sheet->getParent()->removeNamedRange($namedRange->getName());
        }
    }
    
    /**
     * Final cleanup untuk memastikan tidak ada styling di luar area statistik
     */
    protected function finalCleanupOutsideStatistics(string $lastColumn, int $lastRow): void
    {
        $maxColumn = 'ZZ'; // Batas maksimal kolom untuk pembersihan
        $maxRow = 1000;    // Batas maksimal baris untuk pembersihan
        
        $lastColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn);
        $maxColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxColumn);
        
        // 1. Hapus semua tabel otomatis dan autofilter terlebih dahulu
        $this->removeAutoTables();
        
        // 2. Hapus semua merged cells di luar area statistik
        $mergedCells = $this->sheet->getMergeCells();
        foreach ($mergedCells as $mergedCell) {
            $range = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::splitRange($mergedCell);
            $startCell = $range[0][0];
            $coordinates = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($startCell);
            $startColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($coordinates[0]);
            $startRow = $coordinates[1];
            
            // Hapus merged cells di luar area yang diizinkan (kecuali judul di baris 1-2)
            if ($startColumn > $lastColumnIndex || ($startRow > $lastRow + 8 && $startRow > 2)) {
                $this->sheet->unmergeCells($mergedCell);
            }
        }

        // 3. Bersihkan styling dari kolom di luar area statistik
        for ($i = $lastColumnIndex + 1; $i <= $maxColumnIndex; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            
            // Kosongkan nilai dan reset styling untuk seluruh kolom
            for ($row = 1; $row <= $maxRow; $row++) {
                $this->sheet->setCellValue($col . $row, '');
            }
            
            $range = $col . '1:' . $col . $maxRow;
            $this->sheet->getStyle($range)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE]],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE],
                'font' => [
                    'bold' => false, 
                    'size' => 11, 
                    'color' => ['rgb' => '000000'],
                    'name' => 'Calibri'
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_GENERAL,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_BOTTOM,
                    'wrapText' => false
                ],
                'numberFormat' => ['formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL]
            ]);
        }

        // 4. Bersihkan styling dari baris di luar area data (setelah footer)
        $footerEndRow = $lastRow + 8;
        for ($row = $footerEndRow + 1; $row <= $maxRow; $row++) {
            // Kosongkan nilai untuk seluruh baris
            for ($col = 1; $col <= $maxColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $this->sheet->setCellValue($colLetter . $row, '');
            }
            
            $range = 'A' . $row . ':' . $maxColumn . $row;
            $this->sheet->getStyle($range)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE]],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE],
                'font' => [
                    'bold' => false, 
                    'size' => 11, 
                    'color' => ['rgb' => '000000'],
                    'name' => 'Calibri'
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_GENERAL,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_BOTTOM,
                    'wrapText' => false
                ],
                'numberFormat' => ['formatCode' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL]
            ]);
        }
        
        // 5. Bersihkan area di sekitar judul yang tidak diperlukan
        for ($row = 3; $row <= 6; $row++) { // Baris kosong antara judul dan header
            for ($col = $lastColumnIndex + 1; $col <= $maxColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $this->sheet->setCellValue($colLetter . $row, '');
                $this->sheet->getStyle($colLetter . $row)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE]],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE]
                ]);
            }
        }

        // 6. Hapus semua conditional formatting
        $conditionalStyles = $this->sheet->getConditionalStylesCollection();
        if (!empty($conditionalStyles)) {
            foreach ($conditionalStyles as $cellCoordinate => $conditionalStyleArray) {
                $this->sheet->removeConditionalFormatting($cellCoordinate);
            }
        }
        
        // 7. Pastikan tidak ada autofilter yang tersisa
        if ($this->sheet->getAutoFilter()->getRange()) {
            $this->sheet->setAutoFilter('');
        }

        // 8. Reset print area hanya ke area statistik dan footer
        $printArea = 'A1:' . $lastColumn . ($lastRow + 8);
        $this->sheet->getPageSetup()->setPrintArea($printArea);
        
        // 9. Set area aktif ke sel A1
        $this->sheet->setSelectedCells('A1');
        
        // 10. Pastikan tidak ada freeze panes di luar area yang diinginkan
        $this->sheet->unfreezePane();
    }
    
    /**
     * Helper function untuk increment kolom Excel
     */
    protected function incrementColumn(string $column, int $increment = 1): string
    {
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column);
        $newColumnIndex = $columnIndex + $increment;
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($newColumnIndex);
    }
    
    /**
     * Convert number to Roman numeral
     */
    protected function numberToRoman($number)
    {
        $romanNumerals = [
            1 => 'I',
            2 => 'II', 
            3 => 'III',
            4 => 'IV'
        ];
        
        return $romanNumerals[$number] ?? $number;
    }
}