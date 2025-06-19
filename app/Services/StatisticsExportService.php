<?php

namespace App\Services;

use App\Services\PdfService;
use App\Services\PuskesmasExportService;
use App\Exceptions\PuskesmasNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

    public function __construct(
        PdfService $pdfService,
        PuskesmasExportService $puskesmasExportService,
        StatisticsDataService $statisticsDataService
    ) {
        $this->pdfService = $pdfService;
        $this->puskesmasExportService = $puskesmasExportService;
        $this->statisticsDataService = $statisticsDataService;
    }

    /**
     * Export statistik berdasarkan parameter
     */
    public function exportStatistics($request)
    {
        $year = $request->year;
        $month = $request->month;
        $diseaseType = $request->disease_type ?? 'all';
        $tableType = $request->table_type ?? 'all';
        $format = $request->format ?? 'pdf';

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
            } else {
                return $this->exportToExcel($statistics, $year, $month, $diseaseType, $filename);
            }

        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export: ' . $e->getMessage()
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
            // Implementasi Excel untuk puskesmas bisa ditambahkan di sini
            return $this->puskesmasExportService->exportToExcel($puskesmasAll->first(), $year, $month, $diseaseType, $filename);
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

            return $this->pdfService->generatePdf('exports.puskesmas_statistics', $templateData, $filename);

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

        return $this->pdfService->generatePdf('exports.statistics', $templateData, $filename);
    }

    /**
     * Export ke Excel
     */
    private function exportToExcel($statistics, $year, $month, $diseaseType, $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Statistik ' . $year);

        // Header
        $monthName = $month ? $this->statisticsDataService->getMonthName($month) : 'Semua Bulan';
        $diseaseLabel = $this->getDiseaseLabel($diseaseType);
        
        $sheet->setCellValue('A1', 'LAPORAN STATISTIK ' . strtoupper($diseaseLabel));
        $sheet->setCellValue('A2', 'Tahun: ' . $year . ' | Periode: ' . $monthName);
        $sheet->setCellValue('A3', 'Digenerate pada: ' . Carbon::now()->format('d/m/Y H:i:s'));

        // Style header
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column headers
        $row = 5;
        $headers = ['No', 'Puskesmas', 'Ranking'];
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $headers = array_merge($headers, ['Target HT', 'Pasien HT', 'Standar HT', 'Pencapaian HT (%)']);
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $headers = array_merge($headers, ['Target DM', 'Pasien DM', 'Standar DM', 'Pencapaian DM (%)']);
        }

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $col++;
        }

        // Data rows
        $row++;
        foreach ($statistics as $index => $stat) {
            $col = 'A';
            $sheet->setCellValue($col++ . $row, $index + 1);
            $sheet->setCellValue($col++ . $row, $stat['puskesmas_name']);
            $sheet->setCellValue($col++ . $row, $stat['ranking'] ?? '-');
            
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $ht = $stat['ht'] ?? [];
                $sheet->setCellValue($col++ . $row, $ht['target'] ?? 0);
                $sheet->setCellValue($col++ . $row, $ht['total_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $ht['standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $ht['achievement_percentage'] ?? 0);
            }
            
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dm = $stat['dm'] ?? [];
                $sheet->setCellValue($col++ . $row, $dm['target'] ?? 0);
                $sheet->setCellValue($col++ . $row, $dm['total_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $dm['standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $dm['achievement_percentage'] ?? 0);
            }
            
            $row++;
        }

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getStyle('A5:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Save file
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'statistics_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
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

            return $this->pdfService->generatePdf('exports.puskesmas_quarterly', $templateData, $filename);

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
