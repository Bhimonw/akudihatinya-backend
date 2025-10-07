<?php

namespace App\Services\Export;

use App\Services\Export\PdfService;
use App\Services\Export\PuskesmasExportService;
use App\Services\Statistics\StatisticsDataService;
use App\Exceptions\PuskesmasNotFoundException;
use App\Formatters\AdminAllFormatter;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\DynamicFormatterFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

/**
 * Service untuk menangani export statistik dalam berbagai format
 * Memisahkan logika export dari controller
 */
class StatisticsExportService
{
    private $pdfService;
    private $puskesmasExportService;
    private $statisticsDataService;
    private $adminAllFormatter;
    private $adminMonthlyFormatter;
    private $adminQuarterlyFormatter;

    public function __construct(
        PdfService $pdfService,
        PuskesmasExportService $puskesmasExportService,
        StatisticsDataService $statisticsDataService,
        AdminAllFormatter $adminAllFormatter,
        AdminMonthlyFormatter $adminMonthlyFormatter,
        AdminQuarterlyFormatter $adminQuarterlyFormatter
    ) {
        $this->pdfService = $pdfService;
        $this->puskesmasExportService = $puskesmasExportService;
        $this->statisticsDataService = $statisticsDataService;
        $this->adminAllFormatter = $adminAllFormatter;
        $this->adminMonthlyFormatter = $adminMonthlyFormatter;
        $this->adminQuarterlyFormatter = $adminQuarterlyFormatter;
    }

    /**
     * Export statistik berdasarkan parameter
     */
    public function exportStatistics($request)
    {
        $year = $request->year ?? date('Y');
        $month = $request->month;
        $diseaseType = $request->disease_type ?? $request->type ?? 'all';
        $tableType = $request->table_type ?? 'all';
        $format = $request->format ?? 'pdf';

        // Whitelist validation
        $allowedFormats = ['pdf','excel'];
        if (!in_array($format, $allowedFormats, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Format export tidak didukung',
                'allowed_formats' => $allowedFormats
            ], 422);
        }

        $allowedTableTypes = ['all','puskesmas'];
        if (!in_array($tableType, $allowedTableTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'table_type tidak valid',
                'allowed_table_types' => $allowedTableTypes
            ], 422);
        }

        // Force scope untuk non-admin (hanya boleh puskesmas)
        $user = Auth::user();
        if (!$user->isAdmin()) {
            $tableType = 'puskesmas';
        }

        // Validasi parameter
        $validationErrors = $this->statisticsDataService->validateParameters($request);
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validationErrors
            ], 400);
        }

        // Filter puskesmas berdasarkan role
        $puskesmasQuery = $this->statisticsDataService->getPuskesmasQuery($request);
        $puskesmasAll = $puskesmasQuery->get();

        // Hard size cap to prevent memory exhaustion on large aggregate export
        if ($puskesmasAll->count() > 150 && $tableType === 'all') {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah puskesmas terlalu besar untuk sekali export. Gunakan filter atau export per puskesmas.'
            ], 422);
        }

        if ($puskesmasAll->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data puskesmas yang ditemukan'
            ], 404);
        }

        // Tentukan jenis laporan
        $reportType = $month ? 'single' : 'recap';
        $filename = $this->generateFilename($year, $month, $diseaseType, $tableType, $format, $reportType);

        try {
            // Handle export khusus untuk puskesmas
            if ($tableType === 'puskesmas') {
                return $this->exportPuskesmasData($puskesmasAll, $year, $month, $diseaseType, $format, $filename);
            }

            // Export umum
            $statistics = $this->statisticsDataService->getConsistentStatisticsData(
                $puskesmasAll, 
                $year, 
                $month, 
                $diseaseType
            );

            if ($format === 'pdf') {
                return $this->exportToPdf($statistics, $year, $month, $diseaseType, $filename);
            }
            return $this->exportToExcel($statistics, $year, $month, $diseaseType, $filename, $tableType);

        } catch (\Exception $e) {
            Log::error('Export error', [
                'error' => $e->getMessage(),
                'trace_hash' => substr(sha1($e->getTraceAsString()),0,12)
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export',
                'error_code' => 'EXPORT_FAILURE'
            ], 500);
        }
    }

    /**
     * Export data puskesmas khusus
     */
    private function exportPuskesmasData($puskesmasAll, $year, $month, $diseaseType, $format, $filename)
    {
        if ($format === 'pdf') {
            return $this->exportPuskesmasPdf($puskesmasAll->first(), $year, $month, $diseaseType, $filename);
        } else {
            // Use PuskesmasFormatter for puskesmas-specific Excel exports
            return $this->puskesmasExportService->exportPuskesmasStatistics($diseaseType, $year, $puskesmasAll->first()->id);
        }
    }

    /**
     * Export puskesmas ke PDF
     */
    public function exportPuskesmasPdf($puskesmas, $year, $month = null, $diseaseType = 'all', $filename = null)
    {
        try {
            Log::info('Starting PDF export for puskesmas', [
                'puskesmas_id' => $puskesmas->id,
                'year' => $year,
                'month' => $month,
                'disease_type' => $diseaseType
            ]);

            if (!$puskesmas) {
                throw new PuskesmasNotFoundException('Puskesmas tidak ditemukan');
            }

            $statistics = $this->statisticsDataService->getConsistentStatisticsData(
                collect([$puskesmas]), 
                $year, 
                $month, 
                $diseaseType
            );

            if (empty($statistics)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data statistik untuk puskesmas ini'
                ], 404);
            }

            $data = $statistics[0];
            $monthName = $month ? $this->statisticsDataService->getMonthName($month) : null;

            $templateData = [
                'puskesmas' => $puskesmas,
                'year' => $year,
                'month' => $month,
                'month_name' => $monthName,
                'disease_type' => $diseaseType,
                'data' => $data,
                'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
                'generated_by' => Auth::user()->name ?? 'System'
            ];

            $filename = $filename ?: $this->generateFilename($year, $month, $diseaseType, 'puskesmas', 'pdf', $month ? 'single' : 'recap');

            // Use the correct method from PdfService for puskesmas
            return $this->pdfService->generatePuskesmasPdf(
                $puskesmas->id,
                $diseaseType,
                $year
            );

        } catch (PuskesmasNotFoundException $e) {
            Log::error('Puskesmas not found during PDF export', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error during puskesmas PDF export', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export ke PDF
     */
    private function exportToPdf($statistics, $year, $month, $diseaseType, $filename)
    {
        $monthName = $month ? $this->statisticsDataService->getMonthName($month) : null;
        
        $templateData = [
            'statistics' => $statistics,
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'disease_type' => $diseaseType,
            'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
            'generated_by' => Auth::user()->name ?? 'System'
        ];

        // Use the correct method from PdfService
        return $this->pdfService->generateStatisticsPdfFromTemplate(
            null, // puskesmasAll - not needed for this call
            $templateData['year'],
            null, // month
            $templateData['disease_type'],
            $filename,
            'statistics'
        );
    }

    /**
     * Export ke Excel menggunakan template dinamis
     * Menggunakan DynamicFormatterFactory untuk menentukan formatter yang sesuai
     */
    private function exportToExcel($statistics, $year, $month, $diseaseType, $filename, $tableType = 'all')
    {
        $user = Auth::user();
        
        // Untuk puskesmas, gunakan service khusus
        if (!$user->isAdmin() && $user->puskesmas_id) {
            return $this->puskesmasExportService->exportPuskesmasStatistics($diseaseType, $year, $user->puskesmas_id);
        }
        
        // Tentukan quarter jika ada
        $quarter = null;
        if ($month) {
            $quarter = ceil($month / 3);
        }
        
        // Gunakan factory untuk mendapatkan template path dan formatter
        $templatePath = DynamicFormatterFactory::getTemplatePath($tableType, $diseaseType, $month, $quarter);
        $formatter = DynamicFormatterFactory::createFormatter($tableType, $diseaseType, $month, $quarter);

        // Validasi keberadaan template Excel
        if (!DynamicFormatterFactory::validateTemplate($templatePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Template Excel tidak ditemukan: ' . basename($templatePath)
            ], 500);
        }

        try {
            // Load template Excel
            $spreadsheet = IOFactory::load($templatePath);

            // Format spreadsheet menggunakan formatter yang sesuai
            if ($month && method_exists($formatter, 'format')) {
                // Untuk monthly formatter yang memerlukan parameter month
                $spreadsheet = $formatter->format($spreadsheet, $diseaseType, $year, $statistics, $month);
            } else {
                // Untuk formatter lainnya
                $spreadsheet = $formatter->format($spreadsheet, $diseaseType, $year, $statistics);
            }

            // Save file
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'statistics_') . '.xlsx';
            $writer->save($tempFile);

            // Save file to storage for later access if needed
            $storagePath = 'exports/excel/' . date('Y/m') . '/' . $filename;
            Storage::put($storagePath, file_get_contents($tempFile));
            
            // Return download response
            return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            Log::error('Error saat export Excel', [
                'error' => $e->getMessage(),
                'template_path' => $templatePath,
                'disease_type' => $diseaseType,
                'table_type' => $tableType
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat file Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate nama file
     */
    private function generateFilename($year, $month, $diseaseType, $tableType, $format, $reportType)
    {
        $parts = ['statistik'];
        
        if ($diseaseType !== 'all') {
            $parts[] = strtoupper($diseaseType);
        }
        
        $parts[] = $year;
        
        if ($month) {
            $parts[] = 'bulan-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        }
        
        if ($tableType !== 'all') {
            $parts[] = $tableType;
        }
        
        $parts[] = $reportType;
        $parts[] = date('Ymd-His');
        
        return implode('_', $parts) . '.' . $format;
    }

    /**
     * Mendapatkan label penyakit
     */
    private function getDiseaseLabel($diseaseType)
    {
        switch ($diseaseType) {
            case 'ht':
                return 'Hipertensi';
            case 'dm':
                return 'Diabetes Melitus';
            default:
                return 'Hipertensi & Diabetes Melitus';
        }
    }

    /**
     * Export quarterly statistics untuk puskesmas
     */
    public function exportPuskesmasQuarterlyPdf($puskesmas, $year, $diseaseType = 'all')
    {
        try {
            // Validasi input
            if (!$puskesmas) {
                throw new PuskesmasNotFoundException('Puskesmas tidak ditemukan');
            }

            if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.'
                ], 400);
            }

            // Ambil data quarterly (Q1, Q2, Q3, Q4)
            $quarterlyData = [];
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $startMonth = ($quarter - 1) * 3 + 1;
                $endMonth = $quarter * 3;
                
                $quarterData = [];
                for ($month = $startMonth; $month <= $endMonth; $month++) {
                    $monthlyStats = $this->statisticsDataService->getConsistentStatisticsData(
                        collect([$puskesmas]), 
                        $year, 
                        $month, 
                        $diseaseType
                    );
                    
                    if (!empty($monthlyStats)) {
                        $quarterData[$month] = $monthlyStats[0];
                    }
                }
                
                $quarterlyData['Q' . $quarter] = $quarterData;
            }

            $templateData = [
                'puskesmas' => $puskesmas,
                'year' => $year,
                'disease_type' => $diseaseType,
                'quarterly_data' => $quarterlyData,
                'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
                'generated_by' => Auth::user()->name ?? 'System'
            ];

            $filename = 'statistik_quarterly_' . strtolower($puskesmas->name) . '_' . $year . '_' . $diseaseType . '_' . date('Ymd-His') . '.pdf';

            // Use the correct method from PdfService
            return $this->pdfService->generatePuskesmasQuarterlyPdf(
                $puskesmas->id,
                $year,
                $diseaseType,
                $filename
            );

        } catch (PuskesmasNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error during quarterly PDF export', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmas->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat PDF quarterly: ' . $e->getMessage()
            ], 500);
        }
    }
}
