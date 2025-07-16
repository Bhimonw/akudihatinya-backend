<?php

namespace App\Formatters\Helpers;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Helper untuk mengatur page setup dan konfigurasi Excel
 */
class ExcelPageSetupHelper
{
    protected $sheet;

    public function __construct(Worksheet $sheet)
    {
        $this->sheet = $sheet;
    }

    /**
     * Finalisasi setup Excel dengan konfigurasi optimal
     */
    public function finalizeExcelSetup(string $lastDataColumn, int $lastDataRow): void
    {
        $this->configurePageSettings($lastDataColumn, $lastDataRow);
        $this->configureDisplaySettings();
        $this->performFinalCleanup($lastDataColumn, $lastDataRow);
    }

    /**
     * Konfigurasi pengaturan halaman untuk pencetakan
     */
    private function configurePageSettings(string $lastDataColumn, int $lastDataRow): void
    {
        $this->setPageMargins();
        $this->setupPageLayout($lastDataColumn, $lastDataRow);
    }

    /**
     * Set margin halaman yang optimal
     */
    private function setPageMargins(): void
    {
        $margins = $this->getOptimalMargins();
        
        $this->sheet->getPageMargins()
            ->setTop($margins['top'])
            ->setRight($margins['right'])
            ->setBottom($margins['bottom'])
            ->setLeft($margins['left'])
            ->setHeader($margins['header'])
            ->setFooter($margins['footer']);
    }

    /**
     * Get konfigurasi margin yang optimal
     */
    private function getOptimalMargins(): array
    {
        return [
            'top' => 0.75,
            'right' => 0.5,
            'bottom' => 0.75,
            'left' => 0.5,
            'header' => 0.3,
            'footer' => 0.3
        ];
    }

    /**
     * Setup layout halaman untuk pencetakan
     */
    private function setupPageLayout(string $lastDataColumn, int $lastDataRow): void
    {
        $pageSetup = $this->sheet->getPageSetup();
        $layoutConfig = $this->getPageLayoutConfig($lastDataColumn, $lastDataRow);
        
        $pageSetup->setOrientation($layoutConfig['orientation'])
                  ->setPaperSize($layoutConfig['paperSize'])
                  ->setFitToPage($layoutConfig['fitToPage'])
                  ->setFitToWidth($layoutConfig['fitToWidth'])
                  ->setFitToHeight($layoutConfig['fitToHeight'])
                  ->setPrintArea($layoutConfig['printArea']);
    }

    /**
     * Get konfigurasi layout halaman
     */
    private function getPageLayoutConfig(string $lastDataColumn, int $lastDataRow): array
    {
        return [
            'orientation' => PageSetup::ORIENTATION_LANDSCAPE,
            'paperSize' => PageSetup::PAPERSIZE_A4,
            'fitToPage' => true,
            'fitToWidth' => 1,
            'fitToHeight' => 0,
            'printArea' => 'A1:' . $lastDataColumn . $lastDataRow
        ];
    }

    /**
     * Konfigurasi pengaturan tampilan dan navigasi
     */
    private function configureDisplaySettings(): void
    {
        $displayConfig = $this->getDisplayConfig();
        
        $this->sheet->freezePane($displayConfig['freezePane']);
        $this->sheet->setShowGridlines($displayConfig['showGridlines']);
        $this->sheet->setPrintGridlines($displayConfig['printGridlines']);
    }

    /**
     * Get konfigurasi tampilan
     */
    private function getDisplayConfig(): array
    {
        return [
            'freezePane' => 'D8',
            'showGridlines' => true,
            'printGridlines' => true
        ];
    }

    /**
     * Lakukan pembersihan final
     */
    private function performFinalCleanup(string $lastDataColumn, int $lastDataRow): void
    {
        $cleanupHelper = new ExcelCleanupHelper($this->sheet);
        $cleanupHelper->finalCleanupOutsideStatistics($lastDataColumn, $lastDataRow);
    }

    /**
     * Set dimensi optimal untuk baris dan kolom
     */
    public function setOptimalDimensions(string $lastDataColumn, int $lastDataRow): void
    {
        $this->setOptimalRowHeights($lastDataRow);
        $this->setOptimalColumnWidths($lastDataColumn);
    }

    /**
     * Set tinggi baris yang optimal
     */
    private function setOptimalRowHeights(int $lastDataRow): void
    {
        $this->setHeaderRowHeights();
        $this->setDataRowHeights($lastDataRow);
    }

    /**
     * Set tinggi baris header
     */
    private function setHeaderRowHeights(): void
    {
        $headerHeights = $this->getHeaderRowHeightConfig();
        
        foreach ($headerHeights as $row => $height) {
            $this->sheet->getRowDimension($row)->setRowHeight($height);
        }
    }

    /**
     * Get konfigurasi tinggi baris header
     */
    private function getHeaderRowHeightConfig(): array
    {
        return [
            '4' => 35, // Header utama yang di-merge
            '5' => 25, // Header periode
            '6' => 22, // Header kategori
            '7' => 22  // Header kategori
        ];
    }

    /**
     * Set tinggi baris data
     */
    private function setDataRowHeights(int $lastDataRow): void
    {
        $dataRowHeight = $this->getDataRowHeight();
        
        for ($row = 8; $row <= $lastDataRow; $row++) {
            $this->sheet->getRowDimension($row)->setRowHeight($dataRowHeight);
        }
    }

    /**
     * Get tinggi standar untuk baris data
     */
    private function getDataRowHeight(): int
    {
        return 24;
    }

    /**
     * Set lebar kolom yang optimal
     */
    private function setOptimalColumnWidths(string $lastDataColumn): void
    {
        $columnWidths = $this->getColumnWidthConfig();
        
        // Set lebar untuk kolom identitas
        $this->sheet->getColumnDimension('A')->setWidth($columnWidths['number']);
        $this->sheet->getColumnDimension('B')->setWidth($columnWidths['name']);
        $this->sheet->getColumnDimension('C')->setWidth($columnWidths['target']);
        
        // Set lebar untuk kolom data statistik
        $this->optimizeDataColumns($lastDataColumn, $columnWidths['data']);
    }

    /**
     * Get konfigurasi lebar kolom
     */
    private function getColumnWidthConfig(): array
    {
        return [
            'number' => 5,   // Kolom nomor
            'name' => 25,    // Kolom nama puskesmas
            'target' => 10,  // Kolom sasaran
            'data' => 8      // Kolom data statistik
        ];
    }

    /**
     * Optimasi lebar kolom data
     */
    private function optimizeDataColumns(string $lastDataColumn, float $dataWidth): void
    {
        $startColumnIndex = 4; // Mulai dari kolom D
        $lastColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastDataColumn);
        
        for ($i = $startColumnIndex; $i <= $lastColumnIndex; $i++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $this->sheet->getColumnDimension($columnLetter)->setWidth($dataWidth);
        }
    }

    /**
     * Set document properties
     */
    public function setDocumentProperties(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $diseaseType, int $year): void
    {
        $diseaseLabel = $diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes Melitus';
        
        $spreadsheet->getProperties()
            ->setCreator('Sistem Akudihatinya')
            ->setLastModifiedBy('Sistem Akudihatinya')
            ->setTitle('Laporan Statistik Kesehatan')
            ->setSubject('Laporan ' . $diseaseLabel)
            ->setDescription('Laporan statistik kesehatan tahun ' . $year)
            ->setKeywords('laporan,statistik,kesehatan')
            ->setCategory('Laporan');
    }
}