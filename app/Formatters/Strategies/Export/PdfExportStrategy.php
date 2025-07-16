<?php

namespace App\Formatters\Strategies\Export;

use App\Formatters\Strategies\BaseFormatterStrategy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Strategy untuk PDF export
 * Menangani semua logika ekspor data ke format PDF dengan template dan styling
 */
class PdfExportStrategy extends BaseFormatterStrategy
{
    protected string $name = 'PdfExportStrategy';

    protected array $defaultOptions = [
        'template' => 'default',
        'orientation' => 'portrait', // portrait, landscape
        'paper_size' => 'a4',
        'include_header' => true,
        'include_footer' => true,
        'include_summary' => true,
        'include_charts' => false,
        'output_filename' => null,
        'disease_type' => 'all',
        'export_type' => 'report', // report, summary, detailed
        'year' => null,
        'month' => null,
        'quarter' => null,
        'logo_path' => null,
        'organization_name' => 'Dinas Kesehatan',
        'report_title' => null
    ];

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Starting PDF export', [
            'data_count' => count($data),
            'export_type' => $options['export_type'],
            'template' => $options['template']
        ]);

        try {
            // Prepare data for PDF
            $pdfData = $this->preparePdfData($data, $options);

            // Generate PDF content based on export type
            $htmlContent = $this->generateHtmlContent($pdfData, $options);

            // Create PDF
            $pdf = $this->createPdf($htmlContent, $options);

            // Save PDF file
            $outputPath = $this->savePdfFile($pdf, $options);

            return [
                'success' => true,
                'message' => 'PDF export berhasil',
                'file_path' => $outputPath,
                'filename' => basename($outputPath),
                'export_type' => $options['export_type'],
                'data_count' => count($data),
                'generated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            $this->log('PDF export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException('PDF export gagal: ' . $e->getMessage());
        }
    }

    /**
     * Prepare data for PDF generation
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Prepared data
     */
    private function preparePdfData(array $data, array $options): array
    {
        $pdfData = [
            'data' => $data,
            'options' => $options,
            'metadata' => $this->generateMetadata($data, $options),
            'summary' => [],
            'charts_data' => []
        ];

        // Add summary if requested
        if ($options['include_summary']) {
            $pdfData['summary'] = $this->generateSummaryData($data, $options);
        }

        // Add charts data if requested
        if ($options['include_charts']) {
            $pdfData['charts_data'] = $this->generateChartsData($data, $options);
        }

        // Process data based on export type
        switch ($options['export_type']) {
            case 'summary':
                $pdfData['processed_data'] = $this->prepareSummaryData($data, $options);
                break;
            case 'detailed':
                $pdfData['processed_data'] = $this->prepareDetailedData($data, $options);
                break;
            default:
                $pdfData['processed_data'] = $this->prepareReportData($data, $options);
                break;
        }

        return $pdfData;
    }

    /**
     * Generate HTML content for PDF
     *
     * @param array $pdfData Prepared PDF data
     * @param array $options Export options
     * @return string HTML content
     */
    private function generateHtmlContent(array $pdfData, array $options): string
    {
        $templateName = $this->getTemplateName($options);
        
        // Check if custom template exists
        if (View::exists("pdf.{$templateName}")) {
            return View::make("pdf.{$templateName}", $pdfData)->render();
        }
        
        // Use default template based on export type
        return $this->generateDefaultTemplate($pdfData, $options);
    }

    /**
     * Generate default HTML template
     *
     * @param array $pdfData PDF data
     * @param array $options Export options
     * @return string HTML content
     */
    private function generateDefaultTemplate(array $pdfData, array $options): string
    {
        $html = $this->getBaseHtmlStructure($options);
        
        // Add header
        if ($options['include_header']) {
            $html .= $this->generateHeader($pdfData, $options);
        }
        
        // Add main content based on export type
        switch ($options['export_type']) {
            case 'summary':
                $html .= $this->generateSummaryContent($pdfData, $options);
                break;
            case 'detailed':
                $html .= $this->generateDetailedContent($pdfData, $options);
                break;
            default:
                $html .= $this->generateReportContent($pdfData, $options);
                break;
        }
        
        // Add footer
        if ($options['include_footer']) {
            $html .= $this->generateFooter($pdfData, $options);
        }
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Get base HTML structure with CSS
     *
     * @param array $options Export options
     * @return string Base HTML
     */
    private function getBaseHtmlStructure(array $options): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . ($options['report_title'] ?? 'Laporan Data Kesehatan') . '</title>
            <style>
                ' . $this->getCssStyles($options) . '
            </style>
        </head>
        <body>
        ';
    }

    /**
     * Get CSS styles for PDF
     *
     * @param array $options Export options
     * @return string CSS styles
     */
    private function getCssStyles(array $options): string
    {
        return '
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                line-height: 1.4;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #4472C4;
                padding-bottom: 15px;
            }
            
            .header h1 {
                font-size: 18px;
                margin: 0 0 5px 0;
                color: #4472C4;
            }
            
            .header h2 {
                font-size: 14px;
                margin: 0 0 5px 0;
                color: #666;
            }
            
            .header .meta {
                font-size: 9px;
                color: #888;
            }
            
            .summary-section {
                margin-bottom: 25px;
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
            }
            
            .summary-title {
                font-size: 12px;
                font-weight: bold;
                margin-bottom: 10px;
                color: #4472C4;
            }
            
            .summary-grid {
                display: table;
                width: 100%;
            }
            
            .summary-item {
                display: table-cell;
                width: 25%;
                text-align: center;
                padding: 10px;
            }
            
            .summary-value {
                font-size: 16px;
                font-weight: bold;
                color: #4472C4;
            }
            
            .summary-label {
                font-size: 9px;
                color: #666;
                margin-top: 3px;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 9px;
            }
            
            .data-table th {
                background-color: #4472C4;
                color: white;
                padding: 8px 4px;
                text-align: center;
                font-weight: bold;
                border: 1px solid #ddd;
            }
            
            .data-table td {
                padding: 6px 4px;
                text-align: center;
                border: 1px solid #ddd;
            }
            
            .data-table tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .data-table .total-row {
                background-color: #e7e6e6;
                font-weight: bold;
            }
            
            .status-excellent { color: #28a745; font-weight: bold; }
            .status-good { color: #17a2b8; font-weight: bold; }
            .status-fair { color: #ffc107; font-weight: bold; }
            .status-poor { color: #dc3545; font-weight: bold; }
            
            .footer {
                position: fixed;
                bottom: 20px;
                left: 20px;
                right: 20px;
                text-align: center;
                font-size: 8px;
                color: #888;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .text-right { text-align: right; }
            .font-bold { font-weight: bold; }
            .mb-10 { margin-bottom: 10px; }
            .mb-15 { margin-bottom: 15px; }
            .mb-20 { margin-bottom: 20px; }
        ';
    }

    /**
     * Generate header section
     *
     * @param array $pdfData PDF data
     * @param array $options Export options
     * @return string Header HTML
     */
    private function generateHeader(array $pdfData, array $options): string
    {
        $metadata = $pdfData['metadata'];
        $title = $options['report_title'] ?? $this->generateReportTitle($options);
        
        $html = '<div class="header">';
        
        // Logo if provided
        if (!empty($options['logo_path']) && file_exists($options['logo_path'])) {
            $html .= '<img src="' . $options['logo_path'] . '" style="height: 50px; margin-bottom: 10px;">';
        }
        
        $html .= '<h1>' . ($options['organization_name'] ?? 'Dinas Kesehatan') . '</h1>';
        $html .= '<h2>' . $title . '</h2>';
        
        $html .= '<div class="meta">';
        $html .= 'Periode: ' . $metadata['period_label'] . ' | ';
        $html .= 'Jenis Penyakit: ' . $metadata['disease_label'] . ' | ';
        $html .= 'Dibuat: ' . $metadata['generated_at_formatted'];
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate summary content
     *
     * @param array $pdfData PDF data
     * @param array $options Export options
     * @return string Summary HTML
     */
    private function generateSummaryContent(array $pdfData, array $options): string
    {
        $html = '<div class="summary-section">';
        $html .= '<div class="summary-title">RINGKASAN STATISTIK</div>';
        
        $summary = $pdfData['summary'];
        
        $html .= '<div class="summary-grid">';
        foreach ($summary['statistics'] as $stat) {
            $html .= '<div class="summary-item">';
            $html .= '<div class="summary-value">' . $stat['value'] . '</div>';
            $html .= '<div class="summary-label">' . $stat['label'] . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add top performers table
        if (!empty($summary['top_performers'])) {
            $html .= $this->generateTopPerformersTable($summary['top_performers']);
        }
        
        return $html;
    }

    /**
     * Generate report content
     *
     * @param array $pdfData PDF data
     * @param array $options Export options
     * @return string Report HTML
     */
    private function generateReportContent(array $pdfData, array $options): string
    {
        $html = '';
        
        // Add summary section if included
        if ($options['include_summary'] && !empty($pdfData['summary'])) {
            $html .= $this->generateSummarySection($pdfData['summary']);
        }
        
        // Add main data table
        $html .= $this->generateDataTable($pdfData['processed_data'], $options);
        
        return $html;
    }

    /**
     * Generate detailed content
     *
     * @param array $pdfData PDF data
     * @param array $options Export options
     * @return string Detailed HTML
     */
    private function generateDetailedContent(array $pdfData, array $options): string
    {
        $html = '';
        
        // Add summary section
        if (!empty($pdfData['summary'])) {
            $html .= $this->generateSummarySection($pdfData['summary']);
        }
        
        // Add detailed breakdown by puskesmas
        foreach ($pdfData['processed_data'] as $puskesmasData) {
            $html .= $this->generatePuskesmasDetailSection($puskesmasData, $options);
        }
        
        return $html;
    }

    /**
     * Generate data table
     *
     * @param array $data Table data
     * @param array $options Export options
     * @return string Table HTML
     */
    private function generateDataTable(array $data, array $options): string
    {
        if (empty($data)) {
            return '<p class="text-center">Tidak ada data untuk ditampilkan.</p>';
        }
        
        $html = '<table class="data-table">';
        
        // Generate headers
        $html .= '<thead><tr>';
        $headers = $this->getTableHeaders($options);
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Generate data rows
        $html .= '<tbody>';
        $rowNumber = 1;
        foreach ($data as $item) {
            $html .= $this->generateDataRow($item, $rowNumber, $options);
            $rowNumber++;
        }
        
        // Add total row if applicable
        if (count($data) > 1) {
            $html .= $this->generateTotalRow($data, $options);
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }

    /**
     * Generate single data row
     *
     * @param array $item Data item
     * @param int $rowNumber Row number
     * @param array $options Export options
     * @return string Row HTML
     */
    private function generateDataRow(array $item, int $rowNumber, array $options): string
    {
        $html = '<tr>';
        $diseaseType = $options['disease_type'];
        
        // Basic columns
        $html .= '<td>' . $rowNumber . '</td>';
        $html .= '<td class="text-left">' . ($item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? 'Unknown') . '</td>';
        $html .= '<td>' . ($item['code'] ?? $item['kode_puskesmas'] ?? '') . '</td>';
        
        // HT columns
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = $item['ht'] ?? $item['ht_data'] ?? [];
            $html .= '<td>' . $this->formatNumber($htData['target'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($htData['total_patients'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($htData['standard_patients'] ?? 0) . '</td>';
            
            $htAchievement = $this->calculateAchievementPercentage($htData);
            $html .= '<td>' . $this->formatPercentage($htAchievement) . '</td>';
        }
        
        // DM columns
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
            $html .= '<td>' . $this->formatNumber($dmData['target'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($dmData['total_patients'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($dmData['standard_patients'] ?? 0) . '</td>';
            
            $dmAchievement = $this->calculateAchievementPercentage($dmData);
            $html .= '<td>' . $this->formatPercentage($dmAchievement) . '</td>';
        }
        
        // Status column
        $status = $this->determineOverallStatus($item, $options);
        $statusClass = $this->getStatusClass($status);
        $html .= '<td class="' . $statusClass . '">' . $status . '</td>';
        
        $html .= '</tr>';
        
        return $html;
    }

    /**
     * Generate total row
     *
     * @param array $data All data
     * @param array $options Export options
     * @return string Total row HTML
     */
    private function generateTotalRow(array $data, array $options): string
    {
        $totals = $this->calculateTotals($data, $options);
        $diseaseType = $options['disease_type'];
        
        $html = '<tr class="total-row">';
        $html .= '<td></td>';
        $html .= '<td class="text-left font-bold">TOTAL</td>';
        $html .= '<td></td>';
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $html .= '<td>' . $this->formatNumber($totals['ht']['target'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($totals['ht']['total_patients'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($totals['ht']['standard_patients'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatPercentage($totals['ht']['achievement_percentage'] ?? 0) . '</td>';
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $html .= '<td>' . $this->formatNumber($totals['dm']['target'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($totals['dm']['total_patients'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatNumber($totals['dm']['standard_patients'] ?? 0) . '</td>';
            $html .= '<td>' . $this->formatPercentage($totals['dm']['achievement_percentage'] ?? 0) . '</td>';
        }
        
        $html .= '<td></td>';
        $html .= '</tr>';
        
        return $html;
    }

    /**
     * Generate footer section
     *
     * @param array $pdfData PDF data
     * @param array $options Export options
     * @return string Footer HTML
     */
    private function generateFooter(array $pdfData, array $options): string
    {
        $metadata = $pdfData['metadata'];
        
        return '<div class="footer">' .
               'Laporan dibuat pada ' . $metadata['generated_at_formatted'] . ' | ' .
               'Halaman {PAGE_NUM} dari {PAGE_COUNT}' .
               '</div>';
    }

    /**
     * Get table headers based on options
     *
     * @param array $options Export options
     * @return array Headers
     */
    private function getTableHeaders(array $options): array
    {
        $headers = ['No', 'Nama Puskesmas', 'Kode'];
        $diseaseType = $options['disease_type'];
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $headers = array_merge($headers, [
                'Target HT',
                'Total HT',
                'Standar HT',
                'Pencapaian HT (%)'
            ]);
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $headers = array_merge($headers, [
                'Target DM',
                'Total DM',
                'Standar DM',
                'Pencapaian DM (%)'
            ]);
        }
        
        $headers[] = 'Status';
        
        return $headers;
    }

    /**
     * Create PDF from HTML content
     *
     * @param string $htmlContent HTML content
     * @param array $options Export options
     * @return \Barryvdh\DomPDF\PDF PDF instance
     */
    private function createPdf(string $htmlContent, array $options): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadHTML($htmlContent);
        
        // Set paper size and orientation
        $pdf->setPaper($options['paper_size'], $options['orientation']);
        
        // Set additional options
        $pdf->setOptions([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true
        ]);
        
        return $pdf;
    }

    /**
     * Save PDF file
     *
     * @param \Barryvdh\DomPDF\PDF $pdf PDF instance
     * @param array $options Export options
     * @return string Output file path
     */
    private function savePdfFile(\Barryvdh\DomPDF\PDF $pdf, array $options): string
    {
        $filename = $options['output_filename'] ?? $this->generateDefaultFilename($options);
        $outputPath = storage_path('app/exports/' . $filename);
        
        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $pdf->save($outputPath);
        
        $this->log('PDF file generated', ['path' => $outputPath]);
        
        return $outputPath;
    }

    /**
     * Generate metadata for PDF
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Metadata
     */
    private function generateMetadata(array $data, array $options): array
    {
        $now = now();
        
        return [
            'generated_at' => $now->toISOString(),
            'generated_at_formatted' => $now->format('d/m/Y H:i:s'),
            'data_count' => count($data),
            'disease_type' => $options['disease_type'],
            'disease_label' => $this->getDiseaseTypeLabel($options['disease_type']),
            'export_type' => $options['export_type'],
            'period_label' => $this->generatePeriodLabel($options),
            'year' => $options['year'] ?? date('Y'),
            'month' => $options['month'] ?? null,
            'quarter' => $options['quarter'] ?? null
        ];
    }

    /**
     * Generate period label
     *
     * @param array $options Export options
     * @return string Period label
     */
    private function generatePeriodLabel(array $options): string
    {
        $year = $options['year'] ?? date('Y');
        
        if (!empty($options['month'])) {
            return $this->getMonthName($options['month']) . ' ' . $year;
        }
        
        if (!empty($options['quarter'])) {
            return 'Triwulan ' . $options['quarter'] . ' ' . $year;
        }
        
        return 'Tahun ' . $year;
    }

    /**
     * Generate report title
     *
     * @param array $options Export options
     * @return string Report title
     */
    private function generateReportTitle(array $options): string
    {
        $diseaseLabel = $this->getDiseaseTypeLabel($options['disease_type']);
        $periodLabel = $this->generatePeriodLabel($options);
        
        switch ($options['export_type']) {
            case 'summary':
                return "Ringkasan Laporan {$diseaseLabel} {$periodLabel}";
            case 'detailed':
                return "Laporan Detail {$diseaseLabel} {$periodLabel}";
            default:
                return "Laporan {$diseaseLabel} {$periodLabel}";
        }
    }

    /**
     * Get template name based on options
     *
     * @param array $options Export options
     * @return string Template name
     */
    private function getTemplateName(array $options): string
    {
        $template = $options['template'];
        $exportType = $options['export_type'];
        
        return "{$template}_{$exportType}";
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
     * Get status CSS class
     *
     * @param string $status Status
     * @return string CSS class
     */
    private function getStatusClass(string $status): string
    {
        switch ($status) {
            case 'Sangat Baik':
                return 'status-excellent';
            case 'Baik':
                return 'status-good';
            case 'Cukup':
                return 'status-fair';
            case 'Kurang':
            default:
                return 'status-poor';
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
     * Generate summary data
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Summary data
     */
    private function generateSummaryData(array $data, array $options): array
    {
        $summary = [
            'statistics' => [],
            'top_performers' => []
        ];
        
        // Calculate basic statistics
        $totalPuskesmas = count($data);
        $activePuskesmas = 0;
        $goodPerformance = 0;
        
        foreach ($data as $item) {
            $status = $this->determineOverallStatus($item, $options);
            
            if ($status !== 'Tidak Ada Data') {
                $activePuskesmas++;
            }
            
            if (in_array($status, ['Sangat Baik', 'Baik'])) {
                $goodPerformance++;
            }
        }
        
        $summary['statistics'] = [
            ['label' => 'Total Puskesmas', 'value' => $totalPuskesmas],
            ['label' => 'Puskesmas Aktif', 'value' => $activePuskesmas],
            ['label' => 'Pencapaian Baik', 'value' => $goodPerformance],
            ['label' => 'Perlu Perhatian', 'value' => $activePuskesmas - $goodPerformance]
        ];
        
        // Get top performers
        $summary['top_performers'] = $this->getTopPerformers($data, 5);
        
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
     * Prepare summary data
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Prepared summary data
     */
    private function prepareSummaryData(array $data, array $options): array
    {
        // For summary export, we just need aggregated statistics
        return $this->generateSummaryData($data, $options);
    }

    /**
     * Prepare report data
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Prepared report data
     */
    private function prepareReportData(array $data, array $options): array
    {
        // For regular report, return data as-is with some formatting
        return $data;
    }

    /**
     * Prepare detailed data
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Prepared detailed data
     */
    private function prepareDetailedData(array $data, array $options): array
    {
        // For detailed export, add monthly/quarterly breakdown if available
        $detailedData = [];
        
        foreach ($data as $item) {
            $detailed = $item;
            
            // Add calculated fields
            $detailed['calculated_achievements'] = [];
            
            if (isset($item['ht']) || isset($item['ht_data'])) {
                $htData = $item['ht'] ?? $item['ht_data'] ?? [];
                $detailed['calculated_achievements']['ht'] = $this->calculateAchievementPercentage($htData);
            }
            
            if (isset($item['dm']) || isset($item['dm_data'])) {
                $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
                $detailed['calculated_achievements']['dm'] = $this->calculateAchievementPercentage($dmData);
            }
            
            $detailedData[] = $detailed;
        }
        
        return $detailedData;
    }

    /**
     * Generate default filename
     *
     * @param array $options Export options
     * @return string Filename
     */
    private function generateDefaultFilename(array $options): string
    {
        $diseaseLabel = strtolower($this->getDiseaseTypeLabel($options['disease_type']));
        $year = $options['year'] ?? date('Y');
        $exportType = $options['export_type'];
        $timestamp = date('Y-m-d_H-i-s');
        
        return "laporan_{$exportType}_{$diseaseLabel}_{$year}_{$timestamp}.pdf";
    }

    /**
     * Generate summary section HTML
     *
     * @param array $summary Summary data
     * @return string Summary HTML
     */
    private function generateSummarySection(array $summary): string
    {
        $html = '<div class="summary-section">';
        $html .= '<div class="summary-title">RINGKASAN STATISTIK</div>';
        
        $html .= '<div class="summary-grid">';
        foreach ($summary['statistics'] as $stat) {
            $html .= '<div class="summary-item">';
            $html .= '<div class="summary-value">' . $stat['value'] . '</div>';
            $html .= '<div class="summary-label">' . $stat['label'] . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate top performers table
     *
     * @param array $topPerformers Top performers data
     * @return string Table HTML
     */
    private function generateTopPerformersTable(array $topPerformers): string
    {
        if (empty($topPerformers)) {
            return '';
        }
        
        $html = '<div class="mb-20">';
        $html .= '<div class="summary-title">TOP PERFORMERS</div>';
        $html .= '<table class="data-table">';
        $html .= '<thead><tr><th>Ranking</th><th>Nama Puskesmas</th><th>Pencapaian (%)</th></tr></thead>';
        $html .= '<tbody>';
        
        $rank = 1;
        foreach ($topPerformers as $performer) {
            $html .= '<tr>';
            $html .= '<td>' . $rank . '</td>';
            $html .= '<td class="text-left">' . $performer['name'] . '</td>';
            $html .= '<td>' . $this->formatPercentage($performer['achievement']) . '</td>';
            $html .= '</tr>';
            $rank++;
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate puskesmas detail section
     *
     * @param array $puskesmasData Puskesmas data
     * @param array $options Export options
     * @return string Detail HTML
     */
    private function generatePuskesmasDetailSection(array $puskesmasData, array $options): string
    {
        $html = '<div class="page-break">';
        $html .= '<h3>' . ($puskesmasData['puskesmas_name'] ?? $puskesmasData['nama_puskesmas'] ?? 'Unknown') . '</h3>';
        
        // Add detailed breakdown for this puskesmas
        // This would include monthly data, quarterly summaries, etc.
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate charts data (placeholder for future chart integration)
     *
     * @param array $data Original data
     * @param array $options Export options
     * @return array Charts data
     */
    private function generateChartsData(array $data, array $options): array
    {
        // Placeholder for chart data generation
        // This would prepare data for charts if chart library is integrated
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);
        
        // Validate orientation
        $validOrientations = ['portrait', 'landscape'];
        if (isset($options['orientation']) && !in_array($options['orientation'], $validOrientations)) {
            throw new \InvalidArgumentException('Invalid orientation. Must be one of: ' . implode(', ', $validOrientations));
        }
        
        // Validate paper size
        $validPaperSizes = ['a4', 'a3', 'letter', 'legal'];
        if (isset($options['paper_size']) && !in_array($options['paper_size'], $validPaperSizes)) {
            throw new \InvalidArgumentException('Invalid paper size. Must be one of: ' . implode(', ', $validPaperSizes));
        }
        
        // Validate export type
        $validExportTypes = ['report', 'summary', 'detailed'];
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
        
        // Validate month
        if (isset($options['month']) && ($options['month'] < 1 || $options['month'] > 12)) {
            throw new \InvalidArgumentException('Month must be between 1 and 12');
        }
        
        // Validate quarter
        if (isset($options['quarter']) && ($options['quarter'] < 1 || $options['quarter'] > 4)) {
            throw new \InvalidArgumentException('Quarter must be between 1 and 4');
        }
        
        // Validate logo path if provided
        if (!empty($options['logo_path']) && !file_exists($options['logo_path'])) {
            throw new \InvalidArgumentException('Logo file not found: ' . $options['logo_path']);
        }
        
        return true;
    }
}