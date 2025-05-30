<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportStatisticsController extends Controller
{
    public function export(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? Carbon::now()->month;
        $diseaseType = $request->type ?? 'all';
        $format = $request->format ?? 'excel';

        // Validasi parameter
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        if (!in_array($format, ['pdf', 'excel'])) {
            return response()->json([
                'message' => 'Format tidak valid. Gunakan pdf atau excel.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // User bukan admin hanya bisa lihat puskesmasnya sendiri
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mencetak laporan statistik.',
                ], 403);
            }
        } elseif ($request->has('puskesmas_id')) {
            // Admin bisa filter berdasarkan puskesmas_id
            $puskesmasQuery->where('id', $request->puskesmas_id);
        }

        $puskesmas = $puskesmasQuery->first();
        if (!$puskesmas) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
            ], 404);
        }

        // Ambil data statistik
        $statisticsData = $this->getStatisticsData($puskesmas, $year, $month, $diseaseType);

        // Buat nama file
        $filename = "laporan_statistik_";
        if ($diseaseType !== 'all') {
            $filename .= $diseaseType . "_";
        }

        $monthName = $this->getMonthName($month);
        $filename .= $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT) . "_";
        $filename .= str_replace(' ', '_', strtolower($puskesmas->name));

        // Export sesuai format
        if ($format === 'pdf') {
            return $this->exportStatisticsToPdf($statisticsData, $puskesmas, $year, $month, $diseaseType, $filename);
        } else {
            return $this->exportStatisticsToExcel($statisticsData, $puskesmas, $year, $month, $diseaseType, $filename);
        }
    }

    /**
     * Get statistics data for export report
     */
    protected function getStatisticsData($puskesmas, $year, $month, $diseaseType)
    {
        $result = [
            'ht' => [
                'total_patients' => 0,
                'total_visits' => 0,
                'average_visits' => 0,
                'visit_frequency' => [],
                'patient_details' => []
            ],
            'dm' => [
                'total_patients' => 0,
                'total_visits' => 0,
                'average_visits' => 0,
                'visit_frequency' => [],
                'patient_details' => []
            ]
        ];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Data Hipertensi
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htPatients = Patient::where('puskesmas_id', $puskesmas->id)
                ->whereJsonContains('ht_years', $year)
                ->get();

            $htVisits = HtExamination::whereHas('patient', function ($query) use ($puskesmas) {
                $query->where('puskesmas_id', $puskesmas->id);
            })
                ->whereBetween('examination_date', [$startDate, $endDate])
                ->count();

            $result['ht'] = [
                'total_patients' => $htPatients->count(),
                'total_visits' => $htVisits,
                'average_visits' => $htPatients->count() > 0 ? round($htVisits / $htPatients->count(), 2) : 0,
                'visit_frequency' => $this->getVisitFrequency($puskesmas->id, $startDate, $endDate, 'ht'),
                'patient_details' => $this->getPatientDetails($htPatients, $startDate, $endDate, 'ht')
            ];
        }

        // Data Diabetes
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmPatients = Patient::where('puskesmas_id', $puskesmas->id)
                ->whereJsonContains('dm_years', $year)
                ->get();

            $dmVisits = DmExamination::whereHas('patient', function ($query) use ($puskesmas) {
                $query->where('puskesmas_id', $puskesmas->id);
            })
                ->whereBetween('examination_date', [$startDate, $endDate])
                ->count();

            $result['dm'] = [
                'total_patients' => $dmPatients->count(),
                'total_visits' => $dmVisits,
                'average_visits' => $dmPatients->count() > 0 ? round($dmVisits / $dmPatients->count(), 2) : 0,
                'visit_frequency' => $this->getVisitFrequency($puskesmas->id, $startDate, $endDate, 'dm'),
                'patient_details' => $this->getPatientDetails($dmPatients, $startDate, $endDate, 'dm')
            ];
        }

        return $result;
    }

    /**
     * Get visit frequency data
     */
    protected function getVisitFrequency($puskesmasId, $startDate, $endDate, $diseaseType)
    {
        $frequency = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $date = $currentDate->format('Y-m-d');
            $count = 0;

            if ($diseaseType === 'ht') {
                $count = HtExamination::whereHas('patient', function ($query) use ($puskesmasId) {
                    $query->where('puskesmas_id', $puskesmasId);
                })
                    ->whereDate('examination_date', $date)
                    ->count();
            } else {
                $count = DmExamination::whereHas('patient', function ($query) use ($puskesmasId) {
                    $query->where('puskesmas_id', $puskesmasId);
                })
                    ->whereDate('examination_date', $date)
                    ->count();
            }

            $frequency[$date] = $count;
            $currentDate->addDay();
        }

        return $frequency;
    }

    /**
     * Get patient details with visit data
     */
    protected function getPatientDetails($patients, $startDate, $endDate, $diseaseType)
    {
        $details = [];

        foreach ($patients as $patient) {
            $visits = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $date = $currentDate->format('Y-m-d');
                $hasVisit = false;

                if ($diseaseType === 'ht') {
                    $hasVisit = HtExamination::where('patient_id', $patient->id)
                        ->whereDate('examination_date', $date)
                        ->exists();
                } else {
                    $hasVisit = DmExamination::where('patient_id', $patient->id)
                        ->whereDate('examination_date', $date)
                        ->exists();
                }

                $visits[$date] = $hasVisit;
                $currentDate->addDay();
            }

            $details[] = [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'medical_record_number' => $patient->medical_record_number,
                'gender' => $patient->gender,
                'age' => $patient->age,
                'visits' => $visits,
                'visit_count' => array_sum(array_map(function ($visit) {
                    return $visit ? 1 : 0;
                }, $visits))
            ];
        }

        return $details;
    }

    /**
     * Export statistics report to PDF
     */
    protected function exportStatisticsToPdf($statisticsData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $monthName = $this->getMonthName($month);

        // Set judul
        $title = "Laporan Statistik ";
        if ($diseaseType === 'ht') {
            $title .= "Pasien Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title .= "Pasien Diabetes Mellitus (DM)";
        } else {
            $title .= "Pasien Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }

        $data = [
            'title' => $title,
            'subtitle' => "Bulan $monthName Tahun $year",
            'puskesmas' => $puskesmas,
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'type' => $diseaseType,
            'statistics' => $statisticsData,
            'generated_at' => Carbon::now()->format('d F Y H:i'),
            'generated_by' => Auth::user()->name,
            'user_role' => Auth::user()->is_admin ? 'Admin' : 'Petugas Puskesmas',
        ];

        // Generate PDF
        $pdf = PDF::loadView('exports.statistics_pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        // Simpan PDF ke storage dan return download response
        $pdfFilename = $filename . '.pdf';
        Storage::put('public/exports/' . $pdfFilename, $pdf->output());

        return response()->download(storage_path('app/public/exports/' . $pdfFilename), $pdfFilename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export statistics report to Excel
     */
    protected function exportStatisticsToExcel($statisticsData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $monthName = $this->getMonthName($month);
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Buat spreadsheet baru
        $spreadsheet = new Spreadsheet();

        // Jika perlu, buat sheet untuk setiap jenis penyakit
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->createStatisticsSheet($spreadsheet, $statisticsData['ht'], $puskesmas, $year, $month, 'ht', $daysInMonth);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if ($diseaseType === 'all') {
                // Jika all, buat sheet baru untuk DM
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle('Statistik DM');
                $spreadsheet->setActiveSheetIndex(1);
            }
            $this->createStatisticsSheet($spreadsheet, $statisticsData['dm'], $puskesmas, $year, $month, 'dm', $daysInMonth);
        }

        // Set active sheet to first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Simpan file
        $excelFilename = $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);

        return response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Create sheet for statistics report
     */
    protected function createStatisticsSheet($spreadsheet, $statistics, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $monthName = $this->getMonthName($month);

        if ($diseaseType === 'ht') {
            $sheet->setTitle('Statistik HT');
            $title = "Laporan Statistik Pasien Hipertensi (HT)";
        } else {
            $sheet->setTitle('Statistik DM');
            $title = "Laporan Statistik Pasien Diabetes Mellitus (DM)";
        }

        $title .= " - " . $puskesmas->name;
        $subtitle = "Bulan $monthName Tahun $year";

        // Judul
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . $this->getColLetter(5 + $daysInMonth) . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Subtitle
        $sheet->setCellValue('A2', $subtitle);
        $sheet->mergeCells('A2:' . $this->getColLetter(5 + $daysInMonth) . '2');
        $sheet->getStyle('A2')->getFont()->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Info ekspor
        $exportInfo = "Diekspor oleh: " . Auth::user()->name . " (" .
            (Auth::user()->is_admin ? "Admin" : "Petugas Puskesmas") . ")";
        $sheet->setCellValue('A3', $exportInfo);
        $sheet->mergeCells('A3:' . $this->getColLetter(5 + $daysInMonth) . '3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Tanggal generate
        $sheet->setCellValue('A4', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A4:' . $this->getColLetter(5 + $daysInMonth) . '4');
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ringkasan statistik
        $sheet->setCellValue('A6', 'Ringkasan Statistik');
        $sheet->mergeCells('A6:' . $this->getColLetter(5 + $daysInMonth) . '6');
        $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A7', 'Total Pasien');
        $sheet->setCellValue('B7', $statistics['total_patients']);
        $sheet->setCellValue('A8', 'Total Kunjungan');
        $sheet->setCellValue('B8', $statistics['total_visits']);
        $sheet->setCellValue('A9', 'Rata-rata Kunjungan per Pasien');
        $sheet->setCellValue('B9', $statistics['average_visits']);

        // Style untuk ringkasan
        $summaryRange = 'A7:B9';
        $sheet->getStyle($summaryRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A7:A9')->getFont()->setBold(true);

        // Frekuensi kunjungan
        $row = 11;
        $sheet->setCellValue('A' . $row, 'Frekuensi Kunjungan per Hari');
        $sheet->mergeCells('A' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $sheet->setCellValue('A' . $row, 'Tanggal');
        $sheet->setCellValue('B' . $row, 'Jumlah Kunjungan');

        $row++;
        foreach ($statistics['visit_frequency'] as $date => $count) {
            $sheet->setCellValue('A' . $row, Carbon::parse($date)->format('d F Y'));
            $sheet->setCellValue('B' . $row, $count);
            $row++;
        }

        // Style untuk frekuensi
        $frequencyRange = 'A' . ($row - count($statistics['visit_frequency'])) . ':B' . ($row - 1);
        $sheet->getStyle($frequencyRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . ($row - count($statistics['visit_frequency']) - 1) . ':B' . ($row - count($statistics['visit_frequency']) - 1))
            ->getFont()->setBold(true);

        // Detail pasien
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Detail Pasien');
        $sheet->mergeCells('A' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'No. RM');
        $sheet->setCellValue('C' . $row, 'Nama Pasien');
        $sheet->setCellValue('D' . $row, 'JK');
        $sheet->setCellValue('E' . $row, 'Umur');
        $sheet->setCellValue('F' . $row, 'Jumlah Kunjungan');

        // Merge untuk header tanggal
        $sheet->setCellValue('G' . $row, 'Kedatangan (Tanggal)');
        $sheet->mergeCells('G' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row);

        // Style header
        $headerRange = 'A' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Header tanggal
        $row++;
        $sheet->setCellValue('A' . $row, '');
        $sheet->setCellValue('B' . $row, '');
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, '');
        $sheet->setCellValue('E' . $row, '');
        $sheet->setCellValue('F' . $row, '');

        // Isi header tanggal
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(6 + $day);
            $sheet->setCellValue($col . $row, $day);
        }

        // Style header tanggal
        $dateHeaderRange = 'A' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row;
        $sheet->getStyle($dateHeaderRange)->getFont()->setBold(true);
        $sheet->getStyle($dateHeaderRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($dateHeaderRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($dateHeaderRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Data pasien
        $row++;
        foreach ($statistics['patient_details'] as $index => $patient) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $patient['medical_record_number']);
            $sheet->setCellValue('C' . $row, $patient['patient_name']);
            $sheet->setCellValue('D' . $row, $patient['gender'] === 'male' ? 'L' : 'P');
            $sheet->setCellValue('E' . $row, $patient['age']);
            $sheet->setCellValue('F' . $row, $patient['visit_count']);

            // Isi checklist kedatangan
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $col = $this->getColLetter(6 + $day);
                $date = Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
                if (isset($patient['visits'][$date]) && $patient['visits'][$date]) {
                    $sheet->setCellValue($col . $row, '✓');
                } else {
                    $sheet->setCellValue($col . $row, '');
                }
            }

            $row++;
        }

        // Style untuk data
        $dataRange = 'A' . ($row - count($statistics['patient_details'])) . ':' . $this->getColLetter(5 + $daysInMonth) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Alignment untuk kolom tertentu
        $sheet->getStyle('A' . ($row - count($statistics['patient_details'])) . ':A' . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . ($row - count($statistics['patient_details'])) . ':D' . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . ($row - count($statistics['patient_details'])) . ':E' . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . ($row - count($statistics['patient_details'])) . ':F' . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Alignment untuk checklist kedatangan
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(6 + $day);
            $sheet->getStyle($col . ($row - count($statistics['patient_details'])) . ':' . $col . ($row - 1))
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Auto-size kolom
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setWidth(30); // Nama pasien biasanya lebih panjang
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);

        // Width untuk kolom tanggal (lebih kecil)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(6 + $day);
            $sheet->getColumnDimension($col)->setWidth(3.5);
        }

        // Freeze panes untuk memudahkan navigasi
        $sheet->freezePane('G' . ($row - count($statistics['patient_details'])));
    }

    /**
     * Helper to get Excel column letter from number
     */
    protected function getColLetter($number)
    {
        $letter = '';
        while ($number > 0) {
            $temp = ($number - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $number = (int)(($number - $temp - 1) / 26);
        }
        return $letter;
    }

    /**
     * Mendapatkan nama bulan dalam bahasa Indonesia
     */
    protected function getMonthName($month)
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        return $months[$month] ?? '';
    }
}
