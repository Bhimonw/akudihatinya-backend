<?php

namespace App\Services;

use App\Services\StatisticsAdminService;
use App\Formatters\PdfFormatter;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

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
}