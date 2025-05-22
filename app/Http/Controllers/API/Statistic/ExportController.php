<?php

namespace App\Http\Controllers\API\Statistic;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExportController extends Controller
{
    /**
     * Export statistics to PDF
     */
    public function exportPdf(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month;
        $diseaseType = $request->type ?? 'all';
        $puskesmasId = $request->puskesmas_id;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Jika user bukan admin, gunakan puskesmas user
        if (!Auth::user()->is_admin) {
            $puskesmasId = Auth::user()->puskesmas_id;
        }

        // Jika puskesmas_id tidak diisi, kembalikan error
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'Parameter puskesmas_id diperlukan.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmas = Puskesmas::find($puskesmasId);
        if (!$puskesmas) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
            ], 404);
        }

        // Ambil data statistik
        $statistics = $this->getStatisticsData($puskesmasId, $year, $month, $diseaseType);

        // Generate PDF
        $pdf = PDF::loadView('exports.statistics', [
            'statistics' => $statistics,
            'puskesmas' => $puskesmas,
            'year' => $year,
            'month' => $month,
            'diseaseType' => $diseaseType,
        ]);

        // Simpan PDF ke storage
        $filename = "statistik_{$puskesmas->name}_{$year}";
        if ($month) {
            $filename .= "_{$month}";
        }
        $filename .= "_{$diseaseType}.pdf";
        $filename = str_replace(' ', '_', $filename);
        
        $path = "exports/{$filename}";
        Storage::put("private/{$path}", $pdf->output());

        // Kembalikan URL untuk download
        $url = url("api/statistics/download/{$path}");
        
        return response()->json([
            'message' => 'PDF berhasil dibuat',
            'url' => $url,
        ]);
    }

    /**
     * Export statistics to Excel
     */
    public function exportExcel(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month;
        $diseaseType = $request->type ?? 'all';
        $puskesmasId = $request->puskesmas_id;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Jika user bukan admin, gunakan puskesmas user
        if (!Auth::user()->is_admin) {
            $puskesmasId = Auth::user()->puskesmas_id;
        }

        // Jika puskesmas_id tidak diisi, kembalikan error
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'Parameter puskesmas_id diperlukan.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmas = Puskesmas::find($puskesmasId);
        if (!$puskesmas) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
            ], 404);
        }

        // Ambil data statistik
        $statistics = $this->getStatisticsData($puskesmasId, $year, $month, $diseaseType);

        // Buat spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set judul
        $title = "Statistik Pasien {$puskesmas->name} Tahun {$year}";
        if ($month) {
            $title .= " Bulan " . Carbon::create()->month($month)->locale('id')->monthName;
        }
        if ($diseaseType !== 'all') {
            $title .= " (" . strtoupper($diseaseType) . ")";
        }
        
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:G1');
        
        // Style judul
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Header tabel
        $sheet->setCellValue('A3', 'No');
        $sheet->setCellValue('B3', 'Indikator');
        $sheet->setCellValue('C3', 'Jumlah');
        
        // Style header
        $sheet->getStyle('A3:C3')->getFont()->setBold(true);
        $sheet->getStyle('A3:C3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');
        $sheet->getStyle('A3:C3')->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Data
        $row = 4;
        $indicators = [
            'Total Pasien' => $statistics['total_patients'],
            'Pasien Laki-laki' => $statistics['male_patients'],
            'Pasien Perempuan' => $statistics['female_patients'],
            'Pasien Standar' => $statistics['standard_patients'],
            'Pasien Non-Standar' => $statistics['non_standard_patients'],
            'Pasien Terkendali' => $statistics['controlled_patients'],
        ];
        
        $i = 1;
        foreach ($indicators as $name => $value) {
            $sheet->setCellValue('A' . $row, $i);
            $sheet->setCellValue('B' . $row, $name);
            $sheet->setCellValue('C' . $row, $value);
            
            $sheet->getStyle('A' . $row . ':C' . $row)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            
            $row++;
            $i++;
        }
        
        // Data bulanan jika tidak ada filter bulan
        if (!$month) {
            $row += 2;
            $sheet->setCellValue('A' . $row, 'Data Bulanan');
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row += 1;
            $sheet->setCellValue('A' . $row, 'Bulan');
            $sheet->setCellValue('B' . $row, 'Laki-laki');
            $sheet->setCellValue('C' . $row, 'Perempuan');
            $sheet->setCellValue('D' . $row, 'Total');
            $sheet->setCellValue('E' . $row, 'Standar');
            $sheet->setCellValue('F' . $row, 'Non-Standar');
            
            $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':F' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');
            $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            
            $row++;
            
            for ($m = 1; $m <= 12; $m++) {
                $monthName = Carbon::create()->month($m)->locale('id')->monthName;
                $monthData = $statistics['monthly_data'][$m] ?? [
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                ];
                
                $sheet->setCellValue('A' . $row, $monthName);
                $sheet->setCellValue('B' . $row, $monthData['male']);
                $sheet->setCellValue('C' . $row, $monthData['female']);
                $sheet->setCellValue('D' . $row, $monthData['total']);
                $sheet->setCellValue('E' . $row, $monthData['standard']);
                $sheet->setCellValue('F' . $row, $monthData['non_standard']);
                
                $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                
                $row++;
            }
        }
        
        // Auto size kolom
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Simpan file
        $writer = new Xlsx($spreadsheet);
        $filename = "statistik_{$puskesmas->name}_{$year}";
        if ($month) {
            $filename .= "_{$month}";
        }
        $filename .= "_{$diseaseType}.xlsx";
        $filename = str_replace(' ', '_', $filename);
        
        $path = "exports/{$filename}";
        $tempPath = storage_path('app/temp/' . $filename);
        $writer->save($tempPath);
        
        // Pindahkan ke storage
        Storage::putFileAs('private/exports', $tempPath, $filename);
        unlink($tempPath);
        
        // Kembalikan URL untuk download
        $url = url("api/statistics/download/{$path}");
        
        return response()->json([
            'message' => 'Excel berhasil dibuat',
            'url' => $url,
        ]);
    }

    /**
     * Download exported file
     */
    public function download($path)
    {
        $fullPath = "private/{$path}";
        
        if (!Storage::exists($fullPath)) {
            return response()->json([
                'message' => 'File tidak ditemukan.',
            ], 404);
        }
        
        return Storage::download($fullPath);
    }

    /**
     * Get statistics data for export
     */
    private function getStatisticsData($puskesmasId, $year, $month = null, $diseaseType = 'all')
    {
        // Implementasi sama dengan metode getStatisticsData di StatisticsController
        // ...
    }
}