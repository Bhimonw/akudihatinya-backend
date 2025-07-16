<?php

namespace App\Formatters\Builders;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use App\Formatters\Calculators\StatisticsCalculator;

/**
 * Builder untuk membuat baris total dalam Excel
 */
class ExcelTotalRowBuilder
{
    protected $sheet;
    protected $statisticsCalculator;

    public function __construct(Worksheet $sheet, StatisticsCalculator $statisticsCalculator)
    {
        $this->sheet = $sheet;
        $this->statisticsCalculator = $statisticsCalculator;
    }

    /**
     * Tambahkan baris total ke worksheet
     */
    public function addTotalRow(int $currentRow, string $reportType, array $columnMappings): void
    {
        $totalRow = $currentRow + 1;
        
        // Persiapkan baris total
        $this->prepareTotalRow($totalRow);
        
        // Isi informasi dasar total
        $this->fillBasicTotalInfo($totalRow);
        
        // Hitung dan isi data total
        $this->calculateBasicTotalData($totalRow, $reportType, $columnMappings);
        
        // Terapkan styling
        $this->applyTotalRowStyling($totalRow, $reportType, $columnMappings);
    }

    /**
     * Persiapkan baris total dengan menyisipkan baris baru
     */
    private function prepareTotalRow(int $totalRow): void
    {
        $this->sheet->insertNewRowBefore($totalRow, 1);
    }

    /**
     * Isi informasi dasar untuk baris total
     */
    private function fillBasicTotalInfo(int $totalRow): void
    {
        $this->sheet->setCellValue('A' . $totalRow, '');
        $this->sheet->setCellValue('B' . $totalRow, 'TOTAL');
    }

    /**
     * Hitung dan isi data total berdasarkan jenis laporan
     */
    private function calculateBasicTotalData(int $totalRow, string $reportType, array $columnMappings): void
    {
        // Hitung total sasaran dari kolom C
        $targetRange = 'C8:C' . ($totalRow - 1);
        $this->sheet->setCellValue('C' . $totalRow, '=SUM(' . $targetRange . ')');
        
        // Isi data total berdasarkan jenis laporan
        $this->fillTotalRowData($totalRow, $reportType, $columnMappings);
    }

    /**
     * Isi data total berdasarkan jenis laporan
     */
    private function fillTotalRowData(int $totalRow, string $reportType, array $columnMappings): void
    {
        switch ($reportType) {
            case 'all':
                $this->fillTotalAllData($totalRow, $columnMappings);
                break;
            case 'monthly':
                $this->fillTotalMonthlyData($totalRow, $columnMappings);
                break;
            case 'quarterly':
                $this->fillTotalQuarterlyData($totalRow, $columnMappings);
                break;
            case 'puskesmas':
                $this->fillTotalPuskesmasData($totalRow, $columnMappings);
                break;
        }
    }

    /**
     * Isi total untuk laporan komprehensif (all)
     */
    private function fillTotalAllData(int $totalRow, array $columnMappings): void
    {
        $dataStartRow = 8;
        $dataEndRow = $totalRow - 1;
        
        // Total untuk setiap bulan
        for ($month = 1; $month <= 12; $month++) {
            $this->calculateMonthTotal($totalRow, $month, $dataStartRow, $dataEndRow, $columnMappings['monthColumns']);
        }
        
        // Total untuk setiap triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $this->calculateQuarterTotal($totalRow, $quarter, $dataStartRow, $dataEndRow, $columnMappings['quarterColumns']);
        }
        
        // Total tahunan
        $this->calculateYearlyTotalFromAll($totalRow, $dataStartRow, $dataEndRow, $columnMappings['totalColumns']);
    }

    /**
     * Isi total untuk laporan bulanan
     */
    private function fillTotalMonthlyData(int $totalRow, array $columnMappings): void
    {
        $dataStartRow = 8;
        $dataEndRow = $totalRow - 1;
        
        for ($month = 1; $month <= 12; $month++) {
            $this->calculateMonthTotal($totalRow, $month, $dataStartRow, $dataEndRow, $columnMappings['monthColumns']);
        }
    }

    /**
     * Isi total untuk laporan triwulan
     */
    private function fillTotalQuarterlyData(int $totalRow, array $columnMappings): void
    {
        $dataStartRow = 8;
        $dataEndRow = $totalRow - 1;
        
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $this->calculateQuarterTotal($totalRow, $quarter, $dataStartRow, $dataEndRow, $columnMappings['quarterOnlyColumns']);
        }
        
        // Total tahunan untuk quarterly
        $this->calculateYearlyTotalFromQuarterly($totalRow, $dataStartRow, $dataEndRow, $columnMappings['quarterTotalColumns']);
    }

    /**
     * Isi total untuk template puskesmas
     */
    private function fillTotalPuskesmasData(int $totalRow, array $columnMappings): void
    {
        // Template biasanya kosong, jadi isi dengan 0 atau formula kosong
        for ($month = 1; $month <= 12; $month++) {
            $columns = $columnMappings['monthColumns'][$month];
            foreach ($columns as $col) {
                $this->sheet->setCellValue($col . $totalRow, 0);
            }
        }
    }

    /**
     * Hitung total bulanan
     */
    private function calculateMonthTotal(int $totalRow, int $month, int $dataStartRow, int $dataEndRow, array $monthColumns): void
    {
        $columns = $monthColumns[$month];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $totalRow, '=SUM(' . $columns[0] . $dataStartRow . ':' . $columns[0] . $dataEndRow . ')');
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $totalRow, '=SUM(' . $columns[1] . $dataStartRow . ':' . $columns[1] . $dataEndRow . ')');
        
        // Total
        $this->sheet->setCellValue($columns[2] . $totalRow, '=SUM(' . $columns[2] . $dataStartRow . ':' . $columns[2] . $dataEndRow . ')');
        
        // TS (Target Sasaran)
        $this->sheet->setCellValue($columns[3] . $totalRow, '=SUM(' . $columns[3] . $dataStartRow . ':' . $columns[3] . $dataEndRow . ')');
        
        // %S (Persentase Sasaran)
        $this->calculateStandardPercentage($totalRow, $columns[4], $columns[2], 'C');
    }

    /**
     * Hitung total triwulan
     */
    private function calculateQuarterTotal(int $totalRow, int $quarter, int $dataStartRow, int $dataEndRow, array $quarterColumns): void
    {
        $columns = $quarterColumns[$quarter];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $totalRow, '=SUM(' . $columns[0] . $dataStartRow . ':' . $columns[0] . $dataEndRow . ')');
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $totalRow, '=SUM(' . $columns[1] . $dataStartRow . ':' . $columns[1] . $dataEndRow . ')');
        
        // Total
        $this->sheet->setCellValue($columns[2] . $totalRow, '=SUM(' . $columns[2] . $dataStartRow . ':' . $columns[2] . $dataEndRow . ')');
        
        // TS (Target Sasaran)
        $this->sheet->setCellValue($columns[3] . $totalRow, '=SUM(' . $columns[3] . $dataStartRow . ':' . $columns[3] . $dataEndRow . ')');
        
        // %S (Persentase Sasaran)
        $this->calculateStandardPercentage($totalRow, $columns[4], $columns[2], 'C');
    }

    /**
     * Hitung total tahunan dari laporan all
     */
    private function calculateYearlyTotalFromAll(int $totalRow, int $dataStartRow, int $dataEndRow, array $totalColumns): void
    {
        // L (Laki-laki)
        $this->sheet->setCellValue($totalColumns[0] . $totalRow, '=SUM(' . $totalColumns[0] . $dataStartRow . ':' . $totalColumns[0] . $dataEndRow . ')');
        
        // P (Perempuan)
        $this->sheet->setCellValue($totalColumns[1] . $totalRow, '=SUM(' . $totalColumns[1] . $dataStartRow . ':' . $totalColumns[1] . $dataEndRow . ')');
        
        // Total
        $this->sheet->setCellValue($totalColumns[2] . $totalRow, '=SUM(' . $totalColumns[2] . $dataStartRow . ':' . $totalColumns[2] . $dataEndRow . ')');
        
        // TS (Target Sasaran)
        $this->sheet->setCellValue($totalColumns[3] . $totalRow, '=SUM(' . $totalColumns[3] . $dataStartRow . ':' . $totalColumns[3] . $dataEndRow . ')');
        
        // %S (Persentase Sasaran)
        $this->calculateStandardPercentage($totalRow, $totalColumns[4], $totalColumns[2], 'C');
    }

    /**
     * Hitung total tahunan dari laporan quarterly
     */
    private function calculateYearlyTotalFromQuarterly(int $totalRow, int $dataStartRow, int $dataEndRow, array $quarterTotalColumns): void
    {
        // L (Laki-laki)
        $this->sheet->setCellValue($quarterTotalColumns[0] . $totalRow, '=SUM(' . $quarterTotalColumns[0] . $dataStartRow . ':' . $quarterTotalColumns[0] . $dataEndRow . ')');
        
        // P (Perempuan)
        $this->sheet->setCellValue($quarterTotalColumns[1] . $totalRow, '=SUM(' . $quarterTotalColumns[1] . $dataStartRow . ':' . $quarterTotalColumns[1] . $dataEndRow . ')');
        
        // Total
        $this->sheet->setCellValue($quarterTotalColumns[2] . $totalRow, '=SUM(' . $quarterTotalColumns[2] . $dataStartRow . ':' . $quarterTotalColumns[2] . $dataEndRow . ')');
        
        // TS (Target Sasaran)
        $this->sheet->setCellValue($quarterTotalColumns[3] . $totalRow, '=SUM(' . $quarterTotalColumns[3] . $dataStartRow . ':' . $quarterTotalColumns[3] . $dataEndRow . ')');
        
        // %S (Persentase Sasaran)
        $this->calculateStandardPercentage($totalRow, $quarterTotalColumns[4], $quarterTotalColumns[2], 'C');
    }

    /**
     * Hitung persentase standar
     */
    private function calculateStandardPercentage(int $row, string $percentageCol, string $totalCol, string $targetCol): void
    {
        $formula = '=IF(' . $targetCol . $row . '>0,ROUND((' . $totalCol . $row . '/' . $targetCol . $row . ')*100,2),0)';
        $this->sheet->setCellValue($percentageCol . $row, $formula);
    }

    /**
     * Terapkan styling untuk baris total
     */
    private function applyTotalRowStyling(int $totalRow, string $reportType, array $columnMappings): void
    {
        $lastColumn = $this->getLastStatisticsColumn($reportType);
        
        // Terapkan styling utama
        $this->applyMainTotalRowStyle($totalRow, $lastColumn);
        
        // Terapkan styling khusus untuk kolom identifikasi
        $this->applyTotalRowIdentificationStyle($totalRow);
        
        // Set dimensi baris
        $this->setTotalRowDimensions($totalRow);
    }

    /**
     * Terapkan styling utama untuk baris total
     */
    private function applyMainTotalRowStyle(int $totalRow, string $lastColumn): void
    {
        $range = 'A' . $totalRow . ':' . $lastColumn . $totalRow;
        $styleConfig = $this->getTotalRowStyleConfig();
        
        $this->sheet->getStyle($range)->applyFromArray($styleConfig);
    }

    /**
     * Get konfigurasi style untuk baris total
     */
    private function getTotalRowStyleConfig(): array
    {
        return [
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E86AB']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
    }

    /**
     * Terapkan styling khusus untuk kolom identifikasi
     */
    private function applyTotalRowIdentificationStyle(int $totalRow): void
    {
        $this->sheet->getStyle('B' . $totalRow)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }

    /**
     * Set dimensi untuk baris total
     */
    private function setTotalRowDimensions(int $totalRow): void
    {
        $this->sheet->getRowDimension($totalRow)->setRowHeight(28);
    }

    /**
     * Get kolom statistik terakhir berdasarkan jenis laporan
     */
    private function getLastStatisticsColumn(string $reportType): string
    {
        switch ($reportType) {
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
}