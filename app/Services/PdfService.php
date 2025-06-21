<?php

namespace App\Services;

use App\Services\StatisticsAdminService;
use App\Formatters\PdfFormatter;
use App\Formatters\PdfTemplateFormatter;
use App\Formatters\PuskesmasPdfFormatter;
use App\Formatters\AllQuartersRecapPdfFormatter;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfService
{
    protected $statisticsAdminService;
    protected $pdfFormatter;
    protected $pdfTemplateFormatter;
    protected $puskesmasPdfFormatter;
    protected $allQuartersRecapPdfFormatter;

    public function __construct(
        StatisticsAdminService $statisticsAdminService,
        PdfFormatter $pdfFormatter,
        PdfTemplateFormatter $pdfTemplateFormatter,
        PuskesmasPdfFormatter $puskesmasPdfFormatter,
        AllQuartersRecapPdfFormatter $allQuartersRecapPdfFormatter
    ) {
        $this->statisticsAdminService = $statisticsAdminService;
        $this->pdfFormatter = $pdfFormatter;
        $this->pdfTemplateFormatter = $pdfTemplateFormatter;
        $this->puskesmasPdfFormatter = $puskesmasPdfFormatter;
        $this->allQuartersRecapPdfFormatter = $allQuartersRecapPdfFormatter;
    }

    /**
     * Generate PDF report for statistics data
     *
     * @param string $diseaseType
     * @param int $year
     * @param int|null $puskesmasId
     * @param string $reportType
     * @return \Illuminate\Http\Response
     */
    public function generateStatisticsPdf($diseaseType = 'dm', $year = null, $puskesmasId = null, $reportType = 'all')
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $year = $year ?? date('Y');

            // Create request object for StatisticsAdminService
            $request = new Request([
                'year' => $year,
                'type' => $diseaseType,
                'per_page' => 1000 // Get all data for PDF
            ]);

            if ($puskesmasId) {
                $request->merge(['puskesmas_id' => $puskesmasId]);
            }

            // Get statistics data
            $statisticsData = $this->statisticsAdminService->getAdminStatistics($request);

            if (isset($statisticsData['error']) && $statisticsData['error']) {
                throw new \Exception($statisticsData['message']);
            }

            // Format data for PDF
            $formattedData = $this->pdfFormatter->formatForPdf($statisticsData, $diseaseType, $year, $reportType);

            Log::info('PDF generation data prepared', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'report_type' => $reportType,
                'data_count' => count($formattedData['puskesmas_data'])
            ]);

            // Generate PDF
            $pdf = Pdf::loadView('all_quarters_recap_pdf', $formattedData);
            $pdf->setPaper('A4', 'landscape');

            // Create filename
            $filename = $this->generateFilename($diseaseType, $year, $reportType, $puskesmasId);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year,
                'report_type' => $reportType
            ]);

            return response()->json([
                'error' => 'PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate filename for PDF
     */
    private function generateFilename($diseaseType, $year, $reportType, $puskesmasId = null)
    {
        $diseaseLabel = $diseaseType === 'dm' ? 'DM' : 'HT';
        $timestamp = date('Y-m-d_H-i-s');

        if ($puskesmasId) {
            return "Laporan_{$diseaseLabel}_Puskesmas_{$puskesmasId}_{$year}_{$timestamp}.pdf";
        }

        return "Laporan_{$diseaseLabel}_Semua_Puskesmas_{$year}_{$timestamp}.pdf";
    }

    /**
     * Generate summary PDF for dashboard
     */
    public function generateSummaryPdf($year = null, $diseaseType = 'all')
    {
        try {
            $year = $year ?? date('Y');

            $request = new Request([
                'year' => $year,
                'type' => $diseaseType,
                'per_page' => 1000
            ]);

            $statisticsData = $this->statisticsAdminService->getAdminStatistics($request);

            if (isset($statisticsData['error']) && $statisticsData['error']) {
                throw new \Exception($statisticsData['message']);
            }

            $formattedData = $this->pdfFormatter->formatSummaryForPdf($statisticsData, $diseaseType, $year);

            $pdf = Pdf::loadView('all_quarters_recap_pdf', $formattedData);
            $pdf->setPaper('A4', 'portrait');

            $filename = "Ringkasan_Statistik_{$year}_" . date('Y-m-d_H-i-s') . ".pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Summary PDF generation failed', [
                'error' => $e->getMessage(),
                'year' => $year,
                'disease_type' => $diseaseType
            ]);

            return response()->json([
                'error' => 'Summary PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate quarterly recap PDF using resources/pdf template
     */
    public function generateQuarterlyRecapPdf($puskesmasAll, $year, $diseaseType, $filename)
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Format data using AllQuartersRecapPdfFormatter
            $formattedData = $this->allQuartersRecapPdfFormatter->formatAllQuartersRecapData($year, $diseaseType);

            Log::info('Quarterly recap PDF generation data prepared', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'quarters_count' => count($formattedData['quarters_data'])
            ]);

            // Load template from resources/pdf
            $templatePath = resource_path('pdf/all_quarters_recap_pdf.blade.php');

            if (!file_exists($templatePath)) {
                throw new \Exception('PDF template not found: ' . $templatePath);
            }

            // Generate PDF using the template from resources/pdf
            $pdf = Pdf::loadView('all_quarters_recap_pdf', $formattedData);
            $pdf->setPaper('A4', 'landscape');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Quarterly recap PDF generation failed', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);

            return response()->json([
                'error' => 'Quarterly recap PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate statistics PDF using resources/pdf template
     */
    public function generateStatisticsPdfFromTemplate($puskesmasAll, $year, $month, $diseaseType, $filename, $reportType = 'statistics')
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Choose template and formatter based on report type
            if ($reportType === 'puskesmas') {
                // For puskesmas reports, use puskesmas template and formatter
                $puskesmasId = $puskesmasAll ? $puskesmasAll->first()->id : null;
                if (!$puskesmasId) {
                    throw new \Exception('Puskesmas ID is required for puskesmas reports');
                }
                return $this->generatePuskesmasPdf($puskesmasId, $diseaseType, $year);
            }

            // For admin reports, use AllQuartersRecapPdfFormatter
            $formattedData = $this->allQuartersRecapPdfFormatter->formatAllQuartersRecapData($year, $diseaseType);

            Log::info('Statistics PDF generation data prepared', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
                'report_type' => $reportType,
                'quarters_count' => count($formattedData['quarters_data'])
            ]);

            // Use all_quarters_recap_pdf template for admin reports
            $templateName = 'all_quarters_recap_pdf';

            // Check if template exists
            $templatePath = resource_path('pdf/' . $templateName . '.blade.php');
            if (!file_exists($templatePath)) {
                throw new \Exception('PDF template not found: ' . $templatePath);
            }

            // Generate PDF using template from resources/pdf
            $pdf = Pdf::loadView($templateName, $formattedData);
            $pdf->setPaper('A4', 'landscape');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Statistics PDF generation failed', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
                'report_type' => $reportType
            ]);

            return response()->json([
                'error' => 'Statistics PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate puskesmas-specific PDF report using custom template
     *
     * @param int $puskesmasId
     * @param string $diseaseType
     * @param int $year
     * @return \Illuminate\Http\Response
     */
    public function generatePuskesmasPdf($puskesmasId, $diseaseType = 'dm', $year = null)
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $year = $year ?? date('Y');

            // Format data using PuskesmasPdfFormatter
            $formattedData = $this->puskesmasPdfFormatter->formatPuskesmasData($puskesmasId, $diseaseType, $year);

            Log::info('Puskesmas PDF generation data prepared', [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'puskesmas_name' => $formattedData['puskesmas_name']
            ]);

            // Generate PDF using puskesmas template
            $pdf = Pdf::loadView('puskesmas_statistics_pdf', $formattedData);
            $pdf->setPaper('A4', 'portrait');

            // Create filename
            $filename = $this->generatePuskesmasFilename($formattedData['puskesmas_name'], $diseaseType, $year);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Puskesmas PDF generation failed', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);

            return response()->json([
                'error' => 'Puskesmas PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate quarterly puskesmas PDF report
     *
     * @param int $puskesmasId
     * @param string $diseaseType
     * @param int $year
     * @param int $quarter
     * @return \Illuminate\Http\Response
     */
    public function generatePuskesmasQuarterlyPdf($puskesmasId, $diseaseType, $year, $quarter)
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Format quarterly data
            $formattedData = $this->puskesmasPdfFormatter->formatQuarterlyData($puskesmasId, $diseaseType, $year, $quarter);

            Log::info('Puskesmas quarterly PDF generation data prepared', [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'quarter' => $quarter,
                'puskesmas_name' => $formattedData['puskesmas_name']
            ]);

            // Generate PDF using puskesmas template
            $pdf = Pdf::loadView('puskesmas_statistics_pdf', $formattedData);
            $pdf->setPaper('A4', 'portrait');

            // Create filename
            $filename = $this->generatePuskesmasQuarterlyFilename(
                $formattedData['puskesmas_name'],
                $diseaseType,
                $year,
                $quarter
            );

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Puskesmas quarterly PDF generation failed', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'quarter' => $quarter
            ]);

            return response()->json([
                'error' => 'Puskesmas quarterly PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate filename for puskesmas PDF
     */
    private function generatePuskesmasFilename($puskesmasName, $diseaseType, $year)
    {
        $diseaseLabel = $diseaseType === 'dm' ? 'DM' : 'HT';
        $timestamp = date('Y-m-d_H-i-s');
        $cleanPuskesmasName = preg_replace('/[^A-Za-z0-9_-]/', '_', $puskesmasName);

        return "Rekapitulasi_SPM_{$cleanPuskesmasName}_{$diseaseLabel}_{$year}_{$timestamp}.pdf";
    }

    /**
     * Generate filename for puskesmas quarterly PDF
     */
    private function generatePuskesmasQuarterlyFilename($puskesmasName, $diseaseType, $year, $quarter)
    {
        $diseaseLabel = $diseaseType === 'dm' ? 'DM' : 'HT';
        $timestamp = date('Y-m-d_H-i-s');
        $cleanPuskesmasName = preg_replace('/[^A-Za-z0-9_-]/', '_', $puskesmasName);

        return "Rekapitulasi_SPM_{$cleanPuskesmasName}_{$diseaseLabel}_Q{$quarter}_{$year}_{$timestamp}.pdf";
    }

    /**
     * Generic PDF generation method for various templates
     *
     * @param string $template
     * @param array $data
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function generatePdf($template, $data, $filename)
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $pdf = Pdf::loadView($template, $data)
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled' => true,
                    'defaultFont' => 'sans-serif'
                ]);

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'template' => $template,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'PDF generation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
