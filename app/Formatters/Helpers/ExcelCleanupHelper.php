<?php

namespace App\Formatters\Helpers;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Helper untuk membersihkan dan mengoptimalkan Excel worksheet
 */
class ExcelCleanupHelper
{
    protected $sheet;

    public function __construct(Worksheet $sheet)
    {
        $this->sheet = $sheet;
    }

    /**
     * Final cleanup untuk memastikan tidak ada styling di luar area statistik
     */
    public function finalCleanupOutsideStatistics(string $lastColumn, int $lastRow): void
    {
        $maxColumn = 'ZZ'; // Batas maksimal kolom untuk pembersihan
        $maxRow = 1000;    // Batas maksimal baris untuk pembersihan
        
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        $maxColumnIndex = Coordinate::columnIndexFromString($maxColumn);
        
        // 1. Hapus semua tabel otomatis dan autofilter terlebih dahulu
        $this->removeAutoTables();
        
        // 2. Hapus semua merged cells di luar area statistik
        $this->cleanupMergedCells($lastColumnIndex, $lastRow);

        // 3. Bersihkan styling dari kolom di luar area statistik
        $this->cleanupColumnsOutsideArea($lastColumnIndex, $maxColumnIndex, $maxRow);

        // 4. Bersihkan styling dari baris di luar area data
        $this->cleanupRowsOutsideArea($lastRow, $maxRow, $maxColumn, $maxColumnIndex);
        
        // 5. Bersihkan area di sekitar judul yang tidak diperlukan
        $this->cleanupAroundTitle($lastColumnIndex, $maxColumnIndex);

        // 6. Hapus semua conditional formatting
        $this->removeConditionalFormatting();
        
        // 7. Finalisasi pengaturan
        $this->finalizeSettings($lastColumn, $lastRow);
    }

    /**
     * Hapus tabel Excel otomatis yang mungkin terbentuk
     */
    private function removeAutoTables(): void
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
     * Bersihkan merged cells di luar area yang diizinkan
     */
    private function cleanupMergedCells(int $lastColumnIndex, int $lastRow): void
    {
        $mergedCells = $this->sheet->getMergeCells();
        foreach ($mergedCells as $mergedCell) {
            $range = Coordinate::splitRange($mergedCell);
            $startCell = $range[0][0];
            $coordinates = Coordinate::coordinateFromString($startCell);
            $startColumn = Coordinate::columnIndexFromString($coordinates[0]);
            $startRow = $coordinates[1];
            
            // Hapus merged cells di luar area yang diizinkan (kecuali judul di baris 1-2)
            if ($startColumn > $lastColumnIndex || ($startRow > $lastRow + 8 && $startRow > 2)) {
                $this->sheet->unmergeCells($mergedCell);
            }
        }
    }

    /**
     * Bersihkan kolom di luar area statistik
     */
    private function cleanupColumnsOutsideArea(int $lastColumnIndex, int $maxColumnIndex, int $maxRow): void
    {
        for ($i = $lastColumnIndex + 1; $i <= $maxColumnIndex; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            
            // Kosongkan nilai dan reset styling untuk seluruh kolom
            for ($row = 1; $row <= $maxRow; $row++) {
                $this->sheet->setCellValue($col . $row, '');
            }
            
            $range = $col . '1:' . $col . $maxRow;
            $this->sheet->getStyle($range)->applyFromArray($this->getDefaultCellStyle());
        }
    }

    /**
     * Bersihkan baris di luar area data
     */
    private function cleanupRowsOutsideArea(int $lastRow, int $maxRow, string $maxColumn, int $maxColumnIndex): void
    {
        $footerEndRow = $lastRow + 8;
        for ($row = $footerEndRow + 1; $row <= $maxRow; $row++) {
            // Kosongkan nilai untuk seluruh baris
            for ($col = 1; $col <= $maxColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $this->sheet->setCellValue($colLetter . $row, '');
            }
            
            $range = 'A' . $row . ':' . $maxColumn . $row;
            $this->sheet->getStyle($range)->applyFromArray($this->getDefaultCellStyle());
        }
    }

    /**
     * Bersihkan area di sekitar judul
     */
    private function cleanupAroundTitle(int $lastColumnIndex, int $maxColumnIndex): void
    {
        for ($row = 3; $row <= 6; $row++) { // Baris kosong antara judul dan header
            for ($col = $lastColumnIndex + 1; $col <= $maxColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $this->sheet->setCellValue($colLetter . $row, '');
                $this->sheet->getStyle($colLetter . $row)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
                    'fill' => ['fillType' => Fill::FILL_NONE]
                ]);
            }
        }
    }

    /**
     * Hapus semua conditional formatting
     */
    private function removeConditionalFormatting(): void
    {
        $conditionalStyles = $this->sheet->getConditionalStylesCollection();
        if (!empty($conditionalStyles)) {
            foreach ($conditionalStyles as $cellCoordinate => $conditionalStyleArray) {
                $this->sheet->removeConditionalFormatting($cellCoordinate);
            }
        }
    }

    /**
     * Finalisasi pengaturan worksheet
     */
    private function finalizeSettings(string $lastColumn, int $lastRow): void
    {
        // Pastikan tidak ada autofilter yang tersisa
        if ($this->sheet->getAutoFilter()->getRange()) {
            $this->sheet->setAutoFilter('');
        }

        // Reset print area hanya ke area statistik dan footer
        $printArea = 'A1:' . $lastColumn . ($lastRow + 8);
        $this->sheet->getPageSetup()->setPrintArea($printArea);
        
        // Set area aktif ke sel A1
        $this->sheet->setSelectedCells('A1');
        
        // Pastikan tidak ada freeze panes di luar area yang diinginkan
        $this->sheet->unfreezePane();
    }

    /**
     * Get default cell style untuk reset
     */
    private function getDefaultCellStyle(): array
    {
        return [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            'fill' => ['fillType' => Fill::FILL_NONE],
            'font' => [
                'bold' => false, 
                'size' => 11, 
                'color' => ['rgb' => '000000'],
                'name' => 'Calibri'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_GENERAL,
                'vertical' => Alignment::VERTICAL_BOTTOM,
                'wrapText' => false
            ],
            'numberFormat' => ['formatCode' => NumberFormat::FORMAT_GENERAL]
        ];
    }

    /**
     * Hapus border dari area di luar statistik
     */
    public function removeBordersOutsideStatistics(string $lastColumn, int $lastRow): void
    {
        $highestColumn = $this->sheet->getHighestColumn();
        $highestRow = $this->sheet->getHighestRow();
        
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        
        // Hapus border dari kolom di luar area statistik
        if ($highestColumnIndex > $lastColumnIndex) {
            for ($i = $lastColumnIndex + 1; $i <= $highestColumnIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $range = $col . '1:' . $col . $highestRow;
                $this->sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_NONE);
            }
        }
        
        // Hapus border dari baris di luar area data (kecuali footer)
        $footerStartRow = $lastRow + 1;
        if ($highestRow > $footerStartRow + 5) {
            for ($row = $footerStartRow + 6; $row <= $highestRow; $row++) {
                $range = 'A' . $row . ':' . $lastColumn . $row;
                $this->sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_NONE);
            }
        }
    }

    /**
     * Bersihkan area ekstra yang tidak diperlukan
     */
    public function cleanupExtraAreas(string $lastColumn, int $lastRow): void
    {
        $this->removeBordersOutsideStatistics($lastColumn, $lastRow);
        $this->removeAutoTables();
        $this->finalCleanupOutsideStatistics($lastColumn, $lastRow);
    }
}