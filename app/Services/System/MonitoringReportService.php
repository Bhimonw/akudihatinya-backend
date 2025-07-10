<?php

namespace App\Services\System;

use App\Models\Puskesmas;
use App\Models\Patient;
use App\Models\PatientAttendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

/**
 * Service untuk menangani monitoring report
 * Memisahkan logika monitoring dari controller
 */
class MonitoringReportService
{
    private $pdfService;
    private $statisticsDataService;

    public function __construct(
        PdfService $pdfService,
        StatisticsDataService $statisticsDataService
    ) {
        $this->pdfService = $pdfService;
        $this->statisticsDataService = $statisticsDataService;
    }

    /**
     * Export monitoring report
     */
    public function exportMonitoringReport($request)
    {
        try {
            $format = $request->format;

            // Validasi akses user
            if (!Auth::user()->isAdmin() && !Auth::user()->puskesmas_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. User harus memiliki puskesmas atau menjadi admin.'
                ], 403);
            }

            // Ambil data puskesmas berdasarkan role
            $puskesmasQuery = $this->statisticsDataService->getPuskesmasQuery($request);
            $puskesmas = $puskesmasQuery->first();

            if (!$puskesmas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data puskesmas tidak ditemukan'
                ], 404);
            }

            // Ambil data kehadiran pasien
            $attendanceData = $this->getPatientAttendanceData($puskesmas->id);

            // Generate filename
            $filename = 'monitoring_report_' . strtolower(str_replace(' ', '_', $puskesmas->name)) . '_' . date('Ymd_His') . '.' . $format;

            if ($format === 'pdf') {
                return $this->exportMonitoringToPdf($puskesmas, $attendanceData, $filename);
            } else {
                return $this->exportMonitoringToExcel($puskesmas, $attendanceData, $filename);
            }

        } catch (\Exception $e) {
            Log::error('Error during monitoring report export', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat laporan monitoring: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan data kehadiran pasien
     */
    private function getPatientAttendanceData($puskesmasId)
    {
        // Ambil data pasien dan kehadiran mereka
        $patients = Patient::where('puskesmas_id', $puskesmasId)
            ->with(['attendances' => function ($query) {
                $query->orderBy('attendance_date', 'desc')
                      ->limit(12); // 12 kunjungan terakhir
            }])
            ->get();

        $attendanceData = [];
        
        foreach ($patients as $patient) {
            $lastAttendance = $patient->attendances->first();
            $totalAttendances = $patient->attendances->count();
            
            // Hitung konsistensi kunjungan (berdasarkan 6 bulan terakhir)
            $sixMonthsAgo = Carbon::now()->subMonths(6);
            $recentAttendances = $patient->attendances->where('attendance_date', '>=', $sixMonthsAgo)->count();
            $consistencyPercentage = $recentAttendances > 0 ? round(($recentAttendances / 6) * 100, 2) : 0;
            
            $attendanceData[] = [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'patient_nik' => $patient->nik,
                'disease_type' => $patient->disease_type,
                'gender' => $patient->gender,
                'age' => $patient->age,
                'phone' => $patient->phone,
                'address' => $patient->address,
                'last_attendance' => $lastAttendance ? $lastAttendance->attendance_date : null,
                'last_blood_pressure' => $lastAttendance ? $lastAttendance->blood_pressure : null,
                'last_blood_sugar' => $lastAttendance ? $lastAttendance->blood_sugar : null,
                'last_weight' => $lastAttendance ? $lastAttendance->weight : null,
                'total_attendances' => $totalAttendances,
                'consistency_percentage' => $consistencyPercentage,
                'status' => $this->determinePatientStatus($lastAttendance, $consistencyPercentage),
                'recent_attendances' => $patient->attendances->take(5)->map(function ($attendance) {
                    return [
                        'date' => $attendance->attendance_date,
                        'blood_pressure' => $attendance->blood_pressure,
                        'blood_sugar' => $attendance->blood_sugar,
                        'weight' => $attendance->weight,
                        'notes' => $attendance->notes
                    ];
                })->toArray()
            ];
        }

        // Urutkan berdasarkan status (prioritas: perlu perhatian, tidak aktif, aktif)
        usort($attendanceData, function ($a, $b) {
            $statusPriority = ['perlu_perhatian' => 1, 'tidak_aktif' => 2, 'aktif' => 3];
            return ($statusPriority[$a['status']] ?? 4) <=> ($statusPriority[$b['status']] ?? 4);
        });

        return $attendanceData;
    }

    /**
     * Menentukan status pasien berdasarkan kehadiran
     */
    private function determinePatientStatus($lastAttendance, $consistencyPercentage)
    {
        if (!$lastAttendance) {
            return 'tidak_aktif';
        }

        $daysSinceLastVisit = Carbon::parse($lastAttendance->attendance_date)->diffInDays(Carbon::now());
        
        if ($daysSinceLastVisit > 90) {
            return 'tidak_aktif';
        } elseif ($daysSinceLastVisit > 30 || $consistencyPercentage < 50) {
            return 'perlu_perhatian';
        } else {
            return 'aktif';
        }
    }

    /**
     * Export monitoring ke PDF
     */
    private function exportMonitoringToPdf($puskesmas, $attendanceData, $filename)
    {
        // Kelompokkan data berdasarkan status
        $groupedData = [
            'aktif' => array_filter($attendanceData, fn($item) => $item['status'] === 'aktif'),
            'perlu_perhatian' => array_filter($attendanceData, fn($item) => $item['status'] === 'perlu_perhatian'),
            'tidak_aktif' => array_filter($attendanceData, fn($item) => $item['status'] === 'tidak_aktif')
        ];

        // Hitung statistik
        $totalPatients = count($attendanceData);
        $activePatients = count($groupedData['aktif']);
        $needAttentionPatients = count($groupedData['perlu_perhatian']);
        $inactivePatients = count($groupedData['tidak_aktif']);

        $templateData = [
            'puskesmas' => $puskesmas,
            'attendance_data' => $attendanceData,
            'grouped_data' => $groupedData,
            'statistics' => [
                'total_patients' => $totalPatients,
                'active_patients' => $activePatients,
                'need_attention_patients' => $needAttentionPatients,
                'inactive_patients' => $inactivePatients,
                'active_percentage' => $totalPatients > 0 ? round(($activePatients / $totalPatients) * 100, 2) : 0,
                'need_attention_percentage' => $totalPatients > 0 ? round(($needAttentionPatients / $totalPatients) * 100, 2) : 0,
                'inactive_percentage' => $totalPatients > 0 ? round(($inactivePatients / $totalPatients) * 100, 2) : 0
            ],
            'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
            'generated_by' => Auth::user()->name ?? 'System'
        ];

        return $this->pdfService->generatePdf('exports.monitoring_report', $templateData, $filename);
    }

    /**
     * Export monitoring ke Excel
     */
    private function exportMonitoringToExcel($puskesmas, $attendanceData, $filename)
    {
        $spreadsheet = new Spreadsheet();
        
        // Sheet 1: Summary
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Ringkasan');
        $this->createSummarySheet($summarySheet, $puskesmas, $attendanceData);
        
        // Sheet 2: Detail Pasien
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Detail Pasien');
        $this->createDetailSheet($detailSheet, $attendanceData);
        
        // Sheet 3: Pasien Perlu Perhatian
        $attentionSheet = $spreadsheet->createSheet();
        $attentionSheet->setTitle('Perlu Perhatian');
        $needAttentionData = array_filter($attendanceData, fn($item) => $item['status'] === 'perlu_perhatian');
        $this->createAttentionSheet($attentionSheet, $needAttentionData);

        // Set active sheet ke summary
        $spreadsheet->setActiveSheetIndex(0);

        // Save file
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'monitoring_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Membuat sheet ringkasan
     */
    private function createSummarySheet($sheet, $puskesmas, $attendanceData)
    {
        // Header
        $sheet->setCellValue('A1', 'LAPORAN MONITORING PASIEN');
        $sheet->setCellValue('A2', 'Puskesmas: ' . $puskesmas->name);
        $sheet->setCellValue('A3', 'Tanggal: ' . Carbon::now()->format('d/m/Y H:i:s'));

        // Style header
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Statistik
        $totalPatients = count($attendanceData);
        $activePatients = count(array_filter($attendanceData, fn($item) => $item['status'] === 'aktif'));
        $needAttentionPatients = count(array_filter($attendanceData, fn($item) => $item['status'] === 'perlu_perhatian'));
        $inactivePatients = count(array_filter($attendanceData, fn($item) => $item['status'] === 'tidak_aktif'));

        $row = 5;
        $sheet->setCellValue('A' . $row, 'STATISTIK PASIEN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Pasien:');
        $sheet->setCellValue('B' . $row, $totalPatients);
        $row++;

        $sheet->setCellValue('A' . $row, 'Pasien Aktif:');
        $sheet->setCellValue('B' . $row, $activePatients);
        $sheet->setCellValue('C' . $row, $totalPatients > 0 ? round(($activePatients / $totalPatients) * 100, 2) . '%' : '0%');
        $row++;

        $sheet->setCellValue('A' . $row, 'Perlu Perhatian:');
        $sheet->setCellValue('B' . $row, $needAttentionPatients);
        $sheet->setCellValue('C' . $row, $totalPatients > 0 ? round(($needAttentionPatients / $totalPatients) * 100, 2) . '%' : '0%');
        $row++;

        $sheet->setCellValue('A' . $row, 'Tidak Aktif:');
        $sheet->setCellValue('B' . $row, $inactivePatients);
        $sheet->setCellValue('C' . $row, $totalPatients > 0 ? round(($inactivePatients / $totalPatients) * 100, 2) . '%' : '0%');

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Membuat sheet detail pasien
     */
    private function createDetailSheet($sheet, $attendanceData)
    {
        // Headers
        $headers = [
            'No', 'Nama Pasien', 'NIK', 'Jenis Penyakit', 'Gender', 'Umur',
            'Telepon', 'Kunjungan Terakhir', 'Total Kunjungan', 'Konsistensi (%)', 'Status'
        ];

        $row = 1;
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $col++;
        }

        // Data
        $row = 2;
        foreach ($attendanceData as $index => $data) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $data['patient_name']);
            $sheet->setCellValue('C' . $row, $data['patient_nik']);
            $sheet->setCellValue('D' . $row, strtoupper($data['disease_type']));
            $sheet->setCellValue('E' . $row, $data['gender'] === 'male' ? 'Laki-laki' : 'Perempuan');
            $sheet->setCellValue('F' . $row, $data['age']);
            $sheet->setCellValue('G' . $row, $data['phone']);
            $sheet->setCellValue('H' . $row, $data['last_attendance'] ? Carbon::parse($data['last_attendance'])->format('d/m/Y') : '-');
            $sheet->setCellValue('I' . $row, $data['total_attendances']);
            $sheet->setCellValue('J' . $row, $data['consistency_percentage']);
            $sheet->setCellValue('K' . $row, ucfirst(str_replace('_', ' ', $data['status'])));
            
            // Color coding berdasarkan status
            $statusColor = $this->getStatusColor($data['status']);
            if ($statusColor) {
                $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);
            }
            
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:K' . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Membuat sheet pasien yang perlu perhatian
     */
    private function createAttentionSheet($sheet, $needAttentionData)
    {
        $sheet->setCellValue('A1', 'PASIEN YANG PERLU PERHATIAN');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Headers
        $headers = [
            'No', 'Nama Pasien', 'NIK', 'Telepon', 'Kunjungan Terakhir',
            'Hari Sejak Kunjungan', 'Konsistensi (%)', 'Alasan'
        ];

        $row = 3;
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFE0E0');
            $col++;
        }

        // Data
        $row = 4;
        foreach ($needAttentionData as $index => $data) {
            $daysSinceLastVisit = $data['last_attendance'] ? 
                Carbon::parse($data['last_attendance'])->diffInDays(Carbon::now()) : null;
            
            $reason = '';
            if (!$data['last_attendance']) {
                $reason = 'Belum pernah berkunjung';
            } elseif ($daysSinceLastVisit > 30) {
                $reason = 'Tidak berkunjung > 30 hari';
            } elseif ($data['consistency_percentage'] < 50) {
                $reason = 'Konsistensi rendah';
            }

            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $data['patient_name']);
            $sheet->setCellValue('C' . $row, $data['patient_nik']);
            $sheet->setCellValue('D' . $row, $data['phone']);
            $sheet->setCellValue('E' . $row, $data['last_attendance'] ? Carbon::parse($data['last_attendance'])->format('d/m/Y') : '-');
            $sheet->setCellValue('F' . $row, $daysSinceLastVisit ?? '-');
            $sheet->setCellValue('G' . $row, $data['consistency_percentage']);
            $sheet->setCellValue('H' . $row, $reason);
            
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A3:H' . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Mendapatkan warna berdasarkan status
     */
    private function getStatusColor($status)
    {
        switch ($status) {
            case 'aktif':
                return 'C8E6C9'; // Light green
            case 'perlu_perhatian':
                return 'FFE0B2'; // Light orange
            case 'tidak_aktif':
                return 'FFCDD2'; // Light red
            default:
                return null;
        }
    }
}