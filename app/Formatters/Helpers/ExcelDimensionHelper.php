<?php

namespace App\Formatters\Helpers;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Helper untuk mengatur dimensi dan styling Excel worksheet
 */
class ExcelDimensionHelper
{
    protected $sheet;
    protected $reportType;

    public function __construct(Worksheet $sheet, string $reportType = 'all')
    {
        $this->sheet = $sheet;
        $this->reportType = $reportType;
    }

    /**
     * Set optimal dimensions untuk worksheet
     */
    public function setOptimalDimensions(): void
    {
        $this->setOptimalRowHeights();
        $this->setOptimalColumnWidths();
    }

    /**
     * Set optimal row heights
     */
    public function setOptimalRowHeights(): void
    {
        $this->setHeaderRowHeights();
        $this->setDataRowHeights();
    }

    /**
     * Set tinggi baris header dengan ukuran yang lebih kompak
     */
    public function setHeaderRowHeights(): void
    {
        $config = $this->getHeaderRowHeightConfig();
        
        foreach ($config as $row => $height) {
            $this->sheet->getRowDimension($row)->setRowHeight($height);
        }
    }

    /**
     * Get konfigurasi tinggi baris header dengan ukuran yang lebih kompak
     */
    public function getHeaderRowHeightConfig(): array
    {
        return [
            4 => 20,  // Header utama - diperkecil
            5 => 18,  // Sub header - diperkecil
            6 => 16,  // Header kategori - diperkecil
            7 => 14   // Header detail - diperkecil
        ];
    }

    /**
     * Set tinggi baris data dengan ukuran yang sesuai konten
     */
    public function setDataRowHeights(): void
    {
        $startRow = 8;
        $endRow = $this->sheet->getHighestRow();
        $dataHeight = $this->getDataRowHeight();
        
        for ($row = $startRow; $row <= $endRow; $row++) {
            $this->sheet->getRowDimension($row)->setRowHeight($dataHeight);
        }
    }

    /**
     * Get tinggi standar untuk baris data yang lebih kompak
     */
    public function getDataRowHeight(): float
    {
        return 14;
    }

    /**
     * Set optimal column widths berdasarkan jenis laporan dengan ukuran yang lebih kompak
     */
    public function setOptimalColumnWidths(): void
    {
        $widths = $this->getColumnWidthConfig();
        
        foreach ($widths as $column => $width) {
            $this->sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * Get konfigurasi lebar kolom berdasarkan jenis laporan
     */
    public function getColumnWidthConfig(): array
    {
        $baseWidths = [
            'A' => 4,   // No - diperkecil
            'B' => 22,  // Nama Puskesmas - diperkecil
        ];

        switch ($this->reportType) {
            case 'monthly':
                return array_merge($baseWidths, $this->getMonthlyColumnWidths());
            case 'quarterly':
                return array_merge($baseWidths, $this->getQuarterlyColumnWidths());
            case 'puskesmas':
                return array_merge($baseWidths, $this->getPuskesmasColumnWidths());
            default: // 'all'
                return array_merge($baseWidths, $this->getAllColumnWidths());
        }
    }

    /**
     * Get lebar kolom untuk laporan bulanan dengan ukuran yang lebih kompak
     */
    private function getMonthlyColumnWidths(): array
    {
        $widths = [];
        $columns = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
        
        foreach ($columns as $col) {
            $widths[$col] = 7; // Lebar standar untuk kolom bulan - diperkecil
        }
        
        return $widths;
    }

    /**
     * Get lebar kolom untuk laporan triwulanan dengan ukuran yang lebih kompak
     */
    private function getQuarterlyColumnWidths(): array
    {
        return [
            'C' => 8, 'D' => 8, 'E' => 8, 'F' => 8, 'G' => 8, // TW1 - diperkecil
            'H' => 8, 'I' => 8, 'J' => 8, 'K' => 8, 'L' => 8, // TW2 - diperkecil
            'M' => 8, 'N' => 8, 'O' => 8, 'P' => 8, 'Q' => 8, // TW3 - diperkecil
            'R' => 8, 'S' => 8, 'T' => 8, 'U' => 8, 'V' => 8, // TW4 - diperkecil
        ];
    }

    /**
     * Get lebar kolom untuk laporan puskesmas dengan ukuran yang lebih kompak
     */
    private function getPuskesmasColumnWidths(): array
    {
        return [
            'C' => 10, 'D' => 10, 'E' => 10, 'F' => 10, 'G' => 10, // Data utama - diperkecil
        ];
    }

    /**
     * Get lebar kolom untuk laporan lengkap (all)
     */
    private function getAllColumnWidths(): array
    {
        $widths = [];
        
        // Kolom bulanan (C-N) - diperkecil
        $monthlyColumns = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
        foreach ($monthlyColumns as $col) {
            $widths[$col] = 6;
        }
        
        // Kolom triwulanan (O-V) - diperkecil
        $quarterlyColumns = ['O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V'];
        foreach ($quarterlyColumns as $col) {
            $widths[$col] = 8;
        }
        
        // Kolom total tahunan (W-AA) - diperkecil
        $yearlyColumns = ['W', 'X', 'Y', 'Z', 'AA'];
        foreach ($yearlyColumns as $col) {
            $widths[$col] = 9;
        }
        
        return $widths;
    }

    /**
     * Apply alternating row colors untuk data area
     */
    public function applyAlternatingRowColors(int $startRow, int $endRow, string $lastColumn): void
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $fillColor = ($row % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            
            $range = 'A' . $row . ':' . $lastColumn . $row;
            $this->sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($fillColor);
        }
    }

    /**
     * Apply data area styling
     */
    public function applyDataAreaStyling(int $startRow, int $endRow, string $lastColumn): void
    {
        $range = 'A' . $startRow . ':' . $lastColumn . $endRow;
        
        $this->sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'font' => [
                'size' => 10,
                'name' => 'Calibri'
            ]
        ]);
        
        // Apply alternating colors
        $this->applyAlternatingRowColors($startRow, $endRow, $lastColumn);
        
        // Special styling untuk kolom nama puskesmas
        $nameColumnRange = 'B' . $startRow . ':B' . $endRow;
        $this->sheet->getStyle($nameColumnRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    /**
     * Apply header styling
     */
    public function applyHeaderStyling(int $headerStartRow, int $headerEndRow, string $lastColumn): void
    {
        $range = 'A' . $headerStartRow . ':' . $lastColumn . $headerEndRow;
        
        $this->sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E3F2FD']
            ],
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Calibri',
                'color' => ['rgb' => '1565C0']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ]);
    }

    /**
     * Apply title styling
     */
    public function applyTitleStyling(string $titleRange): void
    {
        $this->sheet->getStyle($titleRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'name' => 'Calibri',
                'color' => ['rgb' => '1565C0']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }

    /**
     * Apply total row styling
     */
    public function applyTotalRowStyling(int $totalRow, string $lastColumn): void
    {
        $range = 'A' . $totalRow . ':' . $lastColumn . $totalRow;
        
        $this->sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF3E0']
            ],
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Calibri',
                'color' => ['rgb' => 'E65100']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        
        // Set tinggi baris total
        $this->sheet->getRowDimension($totalRow)->setRowHeight(20);
    }
}