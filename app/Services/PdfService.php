<?php

namespace App\Services;

use App\Services\StatisticsAdminService;
use App\Formatters\PdfFormatter;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PdfService
{
    protected $statisticsAdminService;
    protected $pdfFormatter;

    public function __construct(
        StatisticsAdminService $statisticsAdminService,
        PdfFormatter $pdfFormatter
    ) {
        $this->statisticsAdminService = $statisticsAdminService;
        $this->pdfFormatter = $pdfFormatter;
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
            $formattedData = $this->formatDataForPdf($statisticsData, $diseaseType, $year, $reportType);

            Log::info('PDF generation data prepared', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'report_type' => $reportType,
                'data_count' => isset($formattedData['puskesmas_data']) ? count($formattedData['puskesmas_data']) : 0
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

            $formattedData = $this->formatDataForPdf($statisticsData, $diseaseType, $year, 'summary');

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
    public function generateQuarterlyRecapPdf($year, $diseaseType, $filename)
    {
        try {
            // Format data menggunakan PdfFormatter
            $data = $this->pdfFormatter->formatAllQuartersRecap($year, $diseaseType);
            
            // Generate PDF menggunakan template all_quarters_recap_pdf
            $pdf = Pdf::loadView('pdf.all_quarters_recap_pdf', $data);
            $pdf->setPaper('A4', 'landscape');
            
            Log::info('PdfService: Successfully generated quarterly recap PDF', [
                'year' => $year,
                'disease_type' => $diseaseType,
                'quarters_count' => count($data['quarter_data'] ?? [])
            ]);
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            Log::error('PdfService: Error generating quarterly recap PDF', [
                'error' => $e->getMessage(),
                'year' => $year,
                'disease_type' => $diseaseType
            ]);
            throw $e;
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

            // For admin reports, format data for quarterly recap
            $formattedData = $this->formatQuarterlyRecapData($year, $diseaseType);

            Log::info('Statistics PDF generation data prepared', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
                'report_type' => $reportType,
                'quarters_count' => isset($formattedData['quarters_data']) ? count($formattedData['quarters_data']) : 0
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
    public function generatePuskesmasPdf($puskesmasId, $diseaseType, $year)
    {
        try {
            // Format data menggunakan PdfFormatter
            $data = $this->pdfFormatter->formatPuskesmasStatistics($puskesmasId, $year, $diseaseType);
            
            // Generate filename
            $filename = $this->pdfFormatter->generatePuskesmasFilename($diseaseType, $year, $data);
            
            // Generate PDF menggunakan template puskesmas_statistics_pdf
            $pdf = Pdf::loadView('pdf.puskesmas_statistics_pdf', $data);
            $pdf->setPaper('A4', 'portrait');
            
            Log::info('PdfService: Successfully generated puskesmas PDF', [
                'puskesmas_id' => $puskesmasId,
                'year' => $year,
                'disease_type' => $diseaseType,
                'puskesmas_name' => isset($data['puskesmas_name']) ? $data['puskesmas_name'] : 'unknown'
            ]);
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            Log::error('PdfService: Error generating puskesmas PDF', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'year' => $year,
                'disease_type' => $diseaseType
            ]);
            throw $e;
        }
    }

    /**
     * Generate quarterly puskesmas PDF report
     *
     * @param int $puskesmasId
     * @param int $year
     * @param string $diseaseType
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function generatePuskesmasQuarterlyPdf($puskesmasId, $year, $diseaseType, $filename)
    {
        try {
            // Set memory and time limits
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Format quarterly data for puskesmas using PdfFormatter
            $data = $this->pdfFormatter->formatAllQuartersRecap($year, $diseaseType, $puskesmasId);

            Log::info('Puskesmas quarterly PDF generation data prepared', [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'puskesmas_name' => isset($data['puskesmas_name']) ? $data['puskesmas_name'] : 'Unknown'
            ]);

            // Generate PDF using all_quarters_recap_pdf template
            $pdf = Pdf::loadView('pdf.all_quarters_recap_pdf', $data);
            $pdf->setPaper('A4', 'landscape');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Puskesmas quarterly PDF generation failed', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
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
