<?php

namespace App\Services;

use App\Services\StatisticsAdminService;
use App\Formatters\PdfFormatter;
use App\Formatters\PdfTemplateFormatter;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfService
{
    protected $statisticsAdminService;
    protected $pdfFormatter;
    protected $pdfTemplateFormatter;

    public function __construct(
        StatisticsAdminService $statisticsAdminService,
        PdfFormatter $pdfFormatter,
        PdfTemplateFormatter $pdfTemplateFormatter
    ) {
        $this->statisticsAdminService = $statisticsAdminService;
        $this->pdfFormatter = $pdfFormatter;
        $this->pdfTemplateFormatter = $pdfTemplateFormatter;
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
            $pdf = Pdf::loadView('exports.statistics_pdf', $formattedData);
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

            $pdf = Pdf::loadView('exports.summary_pdf', $formattedData);
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

            // Format data using PdfTemplateFormatter
            $formattedData = $this->pdfTemplateFormatter->formatQuarterlyRecapData($puskesmasAll, $year, $diseaseType);

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

            // Format data using PdfTemplateFormatter
            $formattedData = $this->pdfTemplateFormatter->formatStatisticsData($puskesmasAll, $year, $month, $diseaseType, $reportType);

            Log::info('Statistics PDF generation data prepared', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
                'report_type' => $reportType,
                'data_count' => count($formattedData['statistics_data'])
            ]);

            // Determine template based on report type
            $templateName = match ($reportType) {
                'monthly' => 'monthly_statistics_pdf',
                'quarterly' => 'quarterly_statistics_pdf', 
                'yearly' => 'yearly_statistics_pdf',
                default => 'statistics_pdf'
            };

            // Check if template exists, fallback to default
            $templatePath = resource_path('pdf/' . $templateName . '.blade.php');
            if (!file_exists($templatePath)) {
                $templateName = 'all_quarters_recap_pdf'; // Fallback to existing template
                Log::warning('Template not found, using fallback', ['requested' => $templateName, 'fallback' => 'all_quarters_recap_pdf']);
            }

            // Generate PDF using template from resources/pdf
            $pdf = Pdf::loadView($templateName, $formattedData);
            $pdf->setPaper('A4', $month ? 'portrait' : 'landscape');
            
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
}