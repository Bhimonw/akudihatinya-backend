<?php

namespace App\Formatters\Strategies\Export;

use App\Formatters\Strategies\BaseFormatterStrategy;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Strategy untuk Excel export
 * Menangani semua logika ekspor data ke format Excel dengan template dan styling
 */
class ExcelExportStrategy extends BaseFormatterStrategy
{
    protected string $name = 'ExcelExportStrategy';

    protected array $defaultOptions = [
        'template_path' => null,
        'output_filename' => null,
        'include_headers' => true,
        'include_totals' => true,
        'apply_styling' => true,
        'auto_size_columns' => true,
        'freeze_header_row' => true,
        'add_borders' => true,
        'format_numbers' => true,
        'disease_type' => 'all',
        'export_type' => 'data', // data, summary, detailed
        'year' => null
    ];

    private ?Spreadsheet $spreadsheet = null;
    private ?string $templatePath = null;

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Starting Excel export', [
            'data_count' => count($data),
            'export_type' => $options['export_type'],
            'template_path' => $options['template_path']
        ]);

        try {
            // Initialize spreadsheet
            $this->initializeSpreadsheet($options);

            // Process data based on export type
            switch ($options['export_type']) {
                case 'summary':
                    $this->exportSummaryData($data, $options);
                    break;
                case 'detailed':
                    $this->exportDetailedData($data, $options);
                    break;
                default:
                    $this->exportRegularData($data, $options);
                    break;
            }

            // Apply styling if requested
            if ($options['apply_styling']) {
                $this->applyExcelStyling($options);
            }

            // Generate output file
            $outputPath = $this->generateOutputFile($options);

            return [
                'success' => true,
                'message' => 'Excel export berhasil',
                'file_path' => $outputPath,
                'filename' => basename($outputPath),
                'export_type' => $options['export_type'],
                'data_count' => count($data),
                'generated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            $this->log('Excel export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException('Excel export gagal: ' . $e->getMessage());
        }
    }

    /**
     * Initialize spreadsheet from template or create new
     *
     * @param array $options Export options
     * @throws \Exception
     */
    private function initializeSpreadsheet(array $options): void
    {
        if (!empty($options['template_path']) && file_exists($options['template_path'])) {
            $this->templatePath = $options['template_path'];
            $this->spreadsheet = IOFactory::load($this->templatePath);
            $this->log('Loaded Excel template', ['template' => $this->templatePath]);
        } else {
            $this->spreadsheet = new Spreadsheet();
            $this->setupNewSpreadsheet($options);
            $this->log('Created new Excel spreadsheet');
        }
    }

    /**
     * Setup new spreadsheet with basic configuration
     *
     * @param array $options Export options
     */
    private function setupNewSpreadsheet(array $options): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        
        // Set sheet name
        $sheetName = $this->generateSheetName($options);
        $sheet->setTitle($sheetName);

        // Set document properties
        $this->spreadsheet->getProperties()
            ->setCreator('Akudihatinya System')
            ->setLastModifiedBy('Akudihatinya System')
            ->setTitle('Data Export - ' . $sheetName)
            ->setSubject('Health Data Export')
            ->setDescription('Exported health data from Akudihatinya system')
            ->setKeywords('health data export excel')
            ->setCategory('Health Data');
    }

    /**
     * Export regular data
     *
     * @param array $data Data to export
     * @param array $options Export options
     */
    private function exportRegularData(array $data, array $options): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $currentRow = 1;

        // Add headers if requested
        if ($options['include_headers']) {
            $headers = $this->generateHeaders($data, $options);
            $this->writeHeaders($sheet, $headers, $currentRow);
            $currentRow++;
        }

        // Add data rows
        foreach ($data as $item) {
            $this->writeDataRow($sheet, $item, $currentRow, $options);
            $currentRow++;
        }

        // Add totals if requested
        if ($options['include_totals'] && count($data) > 0) {
            $this->writeTotalRow($sheet, $data, $currentRow, $options);
        }

        // Freeze header row if requested
        if ($options['freeze_header_row'] && $options['include_headers']) {
            $sheet->freezePane('A2');
        }
    }

    /**
     * Export summary data
     *
     * @param array $data Data to export
     * @param array $options Export options
     */
    private function exportSummaryData(array $data, array $options): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $currentRow = 1;

        // Add title
        $title = 'Ringkasan Data ' . $this->getDiseaseTypeLabel($options['disease_type']) . ' Tahun ' . ($options['year'] ?? date('Y'));
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:F1');
        $currentRow += 2;

        // Add summary statistics
        $summary = $this->calculateSummaryStatistics($data, $options);
        $this->writeSummarySection($sheet, $summary, $currentRow, $options);
        $currentRow += count($summary) + 2;

        // Add top performers
        $topPerformers = $this->getTopPerformers($data, 5);
        $this->writeTopPerformersSection($sheet, $topPerformers, $currentRow, $options);
    }

    /**
     * Export detailed data with multiple sheets
     *
     * @param array $data Data to export
     * @param array $options Export options
     */
    private function exportDetailedData(array $data, array $options): void
    {
        // Main summary sheet
        $this->exportSummaryData($data, $options);

        // Add monthly breakdown sheet
        $this->addMonthlyBreakdownSheet($data, $options);

        // Add quarterly summary sheet
        $this->addQuarterlySummarySheet($data, $options);

        // Add individual puskesmas sheets if data is not too large
        if (count($data) <= 10) {
            $this->addIndividualPuskesmasSheets($data, $options);
        }
    }

    /**
     * Generate headers based on data structure
     *
     * @param array $data Data array
     * @param array $options Export options
     * @return array Headers
     */
    private function generateHeaders(array $data, array $options): array
    {
        $headers = ['No', 'Nama Puskesmas', 'Kode'];
        $diseaseType = $options['disease_type'];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $headers = array_merge($headers, [
                'Target HT',
                'Total Pasien HT',
                'Pasien HT Standar',
                'Pencapaian HT (%)'
            ]);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $headers = array_merge($headers, [
                'Target DM',
                'Total Pasien DM',
                'Pasien DM Standar',
                'Pencapaian DM (%)'
            ]);
        }

        $headers[] = 'Status';
        $headers[] = 'Terakhir Update';

        return $headers;
    }

    /**
     * Write headers to sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     * @param array $headers Headers array
     * @param int $row Row number
     */
    private function writeHeaders($sheet, array $headers, int $row): void
    {
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $col++;
        }
    }

    /**
     * Write data row to sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     * @param array $item Data item
     * @param int $row Row number
     * @param array $options Export options
     */
    private function writeDataRow($sheet, array $item, int $row, array $options): void
    {
        $col = 1;
        $diseaseType = $options['disease_type'];

        // Basic information
        $sheet->setCellValueByColumnAndRow($col++, $row, $row - 1); // No
        $sheet->setCellValueByColumnAndRow($col++, $row, $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? 'Unknown');
        $sheet->setCellValueByColumnAndRow($col++, $row, $item['code'] ?? $item['kode_puskesmas'] ?? '');

        // HT data
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = $item['ht'] ?? $item['ht_data'] ?? [];
            $sheet->setCellValueByColumnAndRow($col++, $row, $htData['target'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $htData['total_patients'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $htData['standard_patients'] ?? 0);
            
            $htAchievement = $this->calculateAchievementPercentage($htData);
            $sheet->setCellValueByColumnAndRow($col++, $row, $htAchievement);
        }

        // DM data
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
            $sheet->setCellValueByColumnAndRow($col++, $row, $dmData['target'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $dmData['total_patients'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $dmData['standard_patients'] ?? 0);
            
            $dmAchievement = $this->calculateAchievementPercentage($dmData);
            $sheet->setCellValueByColumnAndRow($col++, $row, $dmAchievement);
        }

        // Status and last update
        $status = $this->determineOverallStatus($item, $options);
        $sheet->setCellValueByColumnAndRow($col++, $row, $status);
        $sheet->setCellValueByColumnAndRow($col++, $row, $item['last_updated'] ?? $item['updated_at'] ?? '');
    }

    /**
     * Write total row to sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     * @param array $data All data
     * @param int $row Row number
     * @param array $options Export options
     */
    private function writeTotalRow($sheet, array $data, int $row, array $options): void
    {
        $totals = $this->calculateTotals($data, $options);
        $col = 1;

        $sheet->setCellValueByColumnAndRow($col++, $row, '');
        $sheet->setCellValueByColumnAndRow($col++, $row, 'TOTAL');
        $sheet->setCellValueByColumnAndRow($col++, $row, '');

        $diseaseType = $options['disease_type'];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['ht']['target'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['ht']['total_patients'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['ht']['standard_patients'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['ht']['achievement_percentage'] ?? 0);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['dm']['target'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['dm']['total_patients'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['dm']['standard_patients'] ?? 0);
            $sheet->setCellValueByColumnAndRow($col++, $row, $totals['dm']['achievement_percentage'] ?? 0);
        }
    }

    /**
     * Apply Excel styling
     *
     * @param array $options Export options
     */
    private function applyExcelStyling(array $options): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        
        // Auto-size columns if requested
        if ($options['auto_size_columns']) {
            $this->autoSizeColumns($sheet);
        }

        // Add borders if requested
        if ($options['add_borders']) {
            $this->addBorders($sheet);
        }

        // Style headers
        if ($options['include_headers']) {
            $this->styleHeaders($sheet);
        }

        // Style total row
        if ($options['include_totals']) {
            $this->styleTotalRow($sheet);
        }
    }

    /**
     * Auto-size columns
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     */
    private function autoSizeColumns($sheet): void
    {
        $highestColumn = $sheet->getHighestColumn();
        $columnIterator = $sheet->getColumnIterator('A', $highestColumn);
        
        foreach ($columnIterator as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
    }

    /**
     * Add borders to data range
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     */
    private function addBorders($sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        
        $range = 'A1:' . $highestColumn . $highestRow;
        
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Style header row
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     */
    private function styleHeaders($sheet): void
    {
        $highestColumn = $sheet->getHighestColumn();
        $headerRange = 'A1:' . $highestColumn . '1';
        
        $sheet->getStyle($headerRange)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF4472C4');
            
        $sheet->getStyle($headerRange)
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFFFF');
            
        $sheet->getStyle($headerRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Style total row
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     */
    private function styleTotalRow($sheet): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $totalRange = 'A' . $highestRow . ':' . $highestColumn . $highestRow;
        
        $sheet->getStyle($totalRange)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE7E6E6');
            
        $sheet->getStyle($totalRange)
            ->getFont()
            ->setBold(true);
    }

    /**
     * Calculate achievement percentage
     *
     * @param array $diseaseData Disease data
     * @return float Achievement percentage
     */
    private function calculateAchievementPercentage(array $diseaseData): float
    {
        $total = $diseaseData['total_patients'] ?? 0;
        $target = $diseaseData['target'] ?? 0;
        
        return $target > 0 ? ($total / $target) * 100 : 0;
    }

    /**
     * Determine overall status
     *
     * @param array $item Data item
     * @param array $options Export options
     * @return string Status
     */
    private function determineOverallStatus(array $item, array $options): string
    {
        $diseaseType = $options['disease_type'];
        $achievements = [];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = $item['ht'] ?? $item['ht_data'] ?? [];
            $achievements[] = $this->calculateAchievementPercentage($htData);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
            $achievements[] = $this->calculateAchievementPercentage($dmData);
        }

        if (empty($achievements)) {
            return 'Tidak Ada Data';
        }

        $averageAchievement = array_sum($achievements) / count($achievements);

        if ($averageAchievement >= 100) {
            return 'Sangat Baik';
        } elseif ($averageAchievement >= 80) {
            return 'Baik';
        } elseif ($averageAchievement >= 60) {
            return 'Cukup';
        } else {
            return 'Kurang';
        }
    }

    /**
     * Calculate totals for all data
     *
     * @param array $data All data
     * @param array $options Export options
     * @return array Totals
     */
    private function calculateTotals(array $data, array $options): array
    {
        $totals = [];
        $diseaseType = $options['disease_type'];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $totals['ht'] = $this->calculateDiseaseTotals($data, 'ht');
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $totals['dm'] = $this->calculateDiseaseTotals($data, 'dm');
        }

        return $totals;
    }

    /**
     * Calculate totals for specific disease
     *
     * @param array $data All data
     * @param string $diseaseType Disease type
     * @return array Disease totals
     */
    private function calculateDiseaseTotals(array $data, string $diseaseType): array
    {
        $totals = [
            'target' => 0,
            'total_patients' => 0,
            'standard_patients' => 0,
            'achievement_percentage' => 0
        ];

        foreach ($data as $item) {
            $diseaseData = $item[$diseaseType] ?? $item[$diseaseType . '_data'] ?? [];
            
            $totals['target'] += $diseaseData['target'] ?? 0;
            $totals['total_patients'] += $diseaseData['total_patients'] ?? 0;
            $totals['standard_patients'] += $diseaseData['standard_patients'] ?? 0;
        }

        if ($totals['target'] > 0) {
            $totals['achievement_percentage'] = ($totals['total_patients'] / $totals['target']) * 100;
        }

        return $totals;
    }

    /**
     * Generate output file
     *
     * @param array $options Export options
     * @return string Output file path
     * @throws \Exception
     */
    private function generateOutputFile(array $options): string
    {
        $filename = $options['output_filename'] ?? $this->generateDefaultFilename($options);
        $outputPath = storage_path('app/exports/' . $filename);
        
        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Xlsx($this->spreadsheet);
        $writer->save($outputPath);

        $this->log('Excel file generated', ['path' => $outputPath]);

        return $outputPath;
    }

    /**
     * Generate default filename
     *
     * @param array $options Export options
     * @return string Filename
     */
    private function generateDefaultFilename(array $options): string
    {
        $diseaseLabel = $this->getDiseaseTypeLabel($options['disease_type']);
        $year = $options['year'] ?? date('Y');
        $exportType = $options['export_type'];
        $timestamp = date('Y-m-d_H-i-s');

        return "export_{$exportType}_{$diseaseLabel}_{$year}_{$timestamp}.xlsx";
    }

    /**
     * Generate sheet name
     *
     * @param array $options Export options
     * @return string Sheet name
     */
    private function generateSheetName(array $options): string
    {
        $diseaseLabel = $this->getDiseaseTypeLabel($options['disease_type']);
        $year = $options['year'] ?? date('Y');
        
        return "{$diseaseLabel} {$year}";
    }

    /**
     * Calculate summary statistics
     *
     * @param array $data All data
     * @param array $options Export options
     * @return array Summary statistics
     */
    private function calculateSummaryStatistics(array $data, array $options): array
    {
        $summary = [
            'Total Puskesmas' => count($data),
            'Puskesmas Aktif' => 0,
            'Pencapaian Baik' => 0,
            'Perlu Perhatian' => 0
        ];

        foreach ($data as $item) {
            $status = $this->determineOverallStatus($item, $options);
            
            if ($status !== 'Tidak Ada Data') {
                $summary['Puskesmas Aktif']++;
            }
            
            if (in_array($status, ['Sangat Baik', 'Baik'])) {
                $summary['Pencapaian Baik']++;
            } elseif (in_array($status, ['Cukup', 'Kurang'])) {
                $summary['Perlu Perhatian']++;
            }
        }

        return $summary;
    }

    /**
     * Get top performers
     *
     * @param array $data All data
     * @param int $limit Number of top performers
     * @return array Top performers
     */
    private function getTopPerformers(array $data, int $limit = 5): array
    {
        $performers = [];
        
        foreach ($data as $item) {
            $achievements = [];
            
            if (isset($item['ht']) || isset($item['ht_data'])) {
                $htData = $item['ht'] ?? $item['ht_data'] ?? [];
                $achievements[] = $this->calculateAchievementPercentage($htData);
            }
            
            if (isset($item['dm']) || isset($item['dm_data'])) {
                $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
                $achievements[] = $this->calculateAchievementPercentage($dmData);
            }
            
            if (!empty($achievements)) {
                $averageAchievement = array_sum($achievements) / count($achievements);
                $performers[] = [
                    'name' => $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? 'Unknown',
                    'achievement' => $averageAchievement
                ];
            }
        }
        
        // Sort by achievement descending
        usort($performers, function($a, $b) {
            return $b['achievement'] <=> $a['achievement'];
        });
        
        return array_slice($performers, 0, $limit);
    }

    /**
     * Write summary section to sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     * @param array $summary Summary data
     * @param int $startRow Starting row
     * @param array $options Export options
     */
    private function writeSummarySection($sheet, array $summary, int &$startRow, array $options): void
    {
        $sheet->setCellValue('A' . $startRow, 'RINGKASAN STATISTIK');
        $sheet->mergeCells('A' . $startRow . ':B' . $startRow);
        $startRow++;
        
        foreach ($summary as $label => $value) {
            $sheet->setCellValue('A' . $startRow, $label);
            $sheet->setCellValue('B' . $startRow, $value);
            $startRow++;
        }
    }

    /**
     * Write top performers section to sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     * @param array $topPerformers Top performers data
     * @param int $startRow Starting row
     * @param array $options Export options
     */
    private function writeTopPerformersSection($sheet, array $topPerformers, int &$startRow, array $options): void
    {
        $sheet->setCellValue('A' . $startRow, 'TOP PERFORMERS');
        $sheet->mergeCells('A' . $startRow . ':C' . $startRow);
        $startRow++;
        
        $sheet->setCellValue('A' . $startRow, 'Ranking');
        $sheet->setCellValue('B' . $startRow, 'Nama Puskesmas');
        $sheet->setCellValue('C' . $startRow, 'Pencapaian (%)');
        $startRow++;
        
        $rank = 1;
        foreach ($topPerformers as $performer) {
            $sheet->setCellValue('A' . $startRow, $rank);
            $sheet->setCellValue('B' . $startRow, $performer['name']);
            $sheet->setCellValue('C' . $startRow, round($performer['achievement'], 2));
            $startRow++;
            $rank++;
        }
    }

    /**
     * Add monthly breakdown sheet
     *
     * @param array $data All data
     * @param array $options Export options
     */
    private function addMonthlyBreakdownSheet(array $data, array $options): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Breakdown Bulanan');
        
        // Implementation for monthly breakdown
        // This would be similar to exportRegularData but with monthly columns
    }

    /**
     * Add quarterly summary sheet
     *
     * @param array $data All data
     * @param array $options Export options
     */
    private function addQuarterlySummarySheet(array $data, array $options): void
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan Triwulan');
        
        // Implementation for quarterly summary
        // This would show quarterly aggregated data
    }

    /**
     * Add individual puskesmas sheets
     *
     * @param array $data All data
     * @param array $options Export options
     */
    private function addIndividualPuskesmasSheets(array $data, array $options): void
    {
        foreach ($data as $item) {
            $puskesmasName = $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? 'Unknown';
            $sheet = $this->spreadsheet->createSheet();
            $sheet->setTitle(substr($puskesmasName, 0, 31)); // Excel sheet name limit
            
            // Implementation for individual puskesmas detailed data
            // This would show detailed monthly/quarterly data for single puskesmas
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);

        // Validate template path if provided
        if (!empty($options['template_path']) && !file_exists($options['template_path'])) {
            throw new \InvalidArgumentException('Template file not found: ' . $options['template_path']);
        }

        // Validate export type
        $validExportTypes = ['data', 'summary', 'detailed'];
        if (isset($options['export_type']) && !in_array($options['export_type'], $validExportTypes)) {
            throw new \InvalidArgumentException('Invalid export type. Must be one of: ' . implode(', ', $validExportTypes));
        }

        // Validate disease type
        if (isset($options['disease_type'])) {
            $this->validateDiseaseType($options['disease_type']);
        }

        // Validate year
        if (isset($options['year'])) {
            $this->validateYear($options['year']);
        }

        return true;
    }
}