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
     * Set tinggi baris header
     */
    public function setHeaderRowHeights(): void
    {
        $config = $this->getHeaderRowHeightConfig();
        
        foreach ($config as $row => $height) {
            $this->sheet->getRowDimension($row)->setRowHeight($height);
        }
    }

    /**
     * Get konfigurasi tinggi baris header
     */
    public function getHeaderRowHeightConfig(): array
    {
        return [
            4 => 25,  // Header utama
            5 => 20,  // Sub header
            6 => 18,  // Header kategori
            7 => 16   // Header detail
        ];
    }

    /**
     * Set tinggi baris data
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
     * Get tinggi standar untuk baris data
     */
    public function getDataRowHeight(): float
    {
        return 16;
    }

    /**
     * Set optimal column widths berdasarkan jenis laporan
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
            'A' => 5,   // No
            'B' => 25,  // Nama Puskesmas
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
     * Get lebar kolom untuk laporan bulanan
     */
    private function getMonthlyColumnWidths(): array
    {
        $widths = [];
        $columns = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
        
        foreach ($columns as $col) {
            $widths[$col] = 8; // Lebar standar untuk kolom bulan
        }
        
        return $widths;
    }

    /**
     * Get lebar kolom untuk laporan triwulanan
     */
    private function getQuarterlyColumnWidths(): array
    {
        return [
            'C' => 10, 'D' => 10, 'E' => 10, 'F' => 10, 'G' => 10, // TW1
            'H' => 10, 'I' => 10, 'J' => 10, 'K' => 10, 'L' => 10, // TW2
            'M' => 10, 'N' => 10, 'O' => 10, 'P' => 10, 'Q' => 10, // TW3
            'R' => 10, 'S' => 10, 'T' => 10, 'U' => 10, 'V' => 10, // TW4
        ];
    }

    /**
     * Get lebar kolom untuk laporan puskesmas
     */
    private function getPuskesmasColumnWidths(): array
    {
        return [
            'C' => 12, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12, // Data utama
        ];
    }

    /**
     * Get lebar kolom untuk laporan lengkap (all)
     */
    private function getAllColumnWidths(): array
    {
        $widths = [];
        
        // Kolom bulanan (C-N)
        $monthlyColumns = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
        foreach ($monthlyColumns as $col) {
            $widths[$col] = 7;
        }
        
        // Kolom triwulanan (O-V)
        $quarterlyColumns = ['O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V'];
        foreach ($quarterlyColumns as $col) {
            $widths[$col] = 9;
        }
        
        // Kolom total tahunan (W-AA)
        $yearlyColumns = ['W', 'X', 'Y', 'Z', 'AA'];
        foreach ($yearlyColumns as $col) {
            $widths[$col] = 10;
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