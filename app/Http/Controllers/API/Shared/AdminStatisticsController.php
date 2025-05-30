<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
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
use Illuminate\Support\Facades\DB;

class AdminStatisticsController extends Controller
{
    /**
     * Get admin statistics
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month; // Optional: null for yearly view, 1-12 for monthly view
        $diseaseType = $request->type ?? 'all'; // all, ht, dm

        // Validate disease type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Get puskesmas with pagination
        $perPage = $request->per_page ?? 15;
        $page = $request->page ?? 1;

        $puskesmasQuery = Puskesmas::query();

        // Filter by puskesmas name if provided
        if ($request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Get paginated puskesmas
        $puskesmas = $puskesmasQuery->paginate($perPage);

        if ($puskesmas->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'to' => 0,
                    'total' => 0,
                ],
            ]);
        }

        // Get statistics for paginated puskesmas
        $statistics = [];
        foreach ($puskesmas as $p) {
            $data = [
                'puskesmas_id' => $p->id,
                'puskesmas_name' => $p->name,
            ];

            // Get HT data if requested
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $p->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->getStatisticsData($p, $year, $month, 'ht');

                $data['ht'] = [
                    'target' => $htTarget ? $htTarget->target_count : 0,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTarget && $htTarget->target_count > 0
                        ? round(($htData['standard_patients'] / $htTarget->target_count) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'non_standard_patients' => $htData['total_patients'] - $htData['standard_patients'],
                    'male_patients' => $htData['male_patients'],
                    'female_patients' => $htData['female_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Get DM data if requested
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $p->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getStatisticsData($p, $year, $month, 'dm');

                $data['dm'] = [
                    'target' => $dmTarget ? $dmTarget->target_count : 0,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTarget && $dmTarget->target_count > 0
                        ? round(($dmData['standard_patients'] / $dmTarget->target_count) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'non_standard_patients' => $dmData['total_patients'] - $dmData['standard_patients'],
                    'male_patients' => $dmData['male_patients'],
                    'female_patients' => $dmData['female_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $statistics[] = $data;
        }

        // Sort statistics based on achievement percentage
        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            // Sort by combined achievement percentage (HT + DM)
            usort($statistics, function ($a, $b) {
                $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                return $bTotal <=> $aTotal;
            });
        }

        // Add ranking
        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        // Calculate summary data
        $summary = $this->calculateSummaryStatistics($statistics, $diseaseType);

        return response()->json([
            'year' => $year,
            'type' => $diseaseType,
            'month' => $month,
            'month_name' => $month ? $this->getMonthName($month) : null,
            'total_puskesmas' => Puskesmas::count(),
            'summary' => $summary,
            'data' => $statistics,
            'meta' => [
                'current_page' => $puskesmas->currentPage(),
                'from' => $puskesmas->firstItem(),
                'last_page' => $puskesmas->lastPage(),
                'per_page' => $puskesmas->perPage(),
                'to' => $puskesmas->lastItem(),
                'total' => $puskesmas->total(),
            ],
        ]);
    }

    /**
     * Get statistics data for a puskesmas
     */
    protected function getStatisticsData($puskesmas, $year, $month, $diseaseType)
    {
        $model = $diseaseType === 'ht' ? HtExamination::class : DmExamination::class;
        $relation = $diseaseType === 'ht' ? 'htExaminations' : 'dmExaminations';

        $query = Patient::where('puskesmas_id', $puskesmas->id)
            ->whereHas($relation, function ($query) use ($year, $month) {
                $query->where('year', $year);
                if ($month) {
                    $query->where('month', $month);
                }
            })
            ->with([$relation => function ($query) use ($year, $month) {
                $query->where('year', $year);
                if ($month) {
                    $query->where('month', $month);
                }
                $query->orderBy('month');
            }]);

        $patients = $query->get();

        $totalPatients = $patients->count();
        $standardPatients = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0
            ];
        }

        foreach ($patients as $patient) {
            // Count by gender
            if ($patient->gender === 'male') {
                $malePatients++;
            } else {
                $femalePatients++;
            }

            $examinations = $patient->$relation;
            $firstExamMonth = $examinations->min('month');

            if ($firstExamMonth === null) continue;

            // Check if patient has examinations every month since first exam
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $examinations->where('month', $m)->count() > 0;
                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }

            if ($isStandard) {
                $standardPatients++;
            }

            // Count monthly visits
            foreach ($examinations as $exam) {
                $month = $exam->month;
                $monthlyData[$month]['total']++;
                if ($patient->gender === 'male') {
                    $monthlyData[$month]['male']++;
                } else {
                    $monthlyData[$month]['female']++;
                }
                if ($isStandard) {
                    $monthlyData[$month]['standard']++;
                } else {
                    $monthlyData[$month]['non_standard']++;
                }
            }
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Calculate summary statistics
     */
    protected function calculateSummaryStatistics($statistics, $diseaseType)
    {
        $summary = [
            'ht' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'non_standard_patients' => 0,
                'male_patients' => 0,
                'female_patients' => 0,
                'achievement_percentage' => 0,
                'monthly_data' => []
            ],
            'dm' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'non_standard_patients' => 0,
                'male_patients' => 0,
                'female_patients' => 0,
                'achievement_percentage' => 0,
                'monthly_data' => []
            ]
        ];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $summary['ht']['monthly_data'][$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0
            ];
            $summary['dm']['monthly_data'][$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0
            ];
        }

        foreach ($statistics as $stat) {
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $summary['ht']['target'] += $stat['ht']['target'];
                $summary['ht']['total_patients'] += $stat['ht']['total_patients'];
                $summary['ht']['standard_patients'] += $stat['ht']['standard_patients'];
                $summary['ht']['non_standard_patients'] += $stat['ht']['non_standard_patients'];
                $summary['ht']['male_patients'] += $stat['ht']['male_patients'];
                $summary['ht']['female_patients'] += $stat['ht']['female_patients'];

                foreach ($stat['ht']['monthly_data'] as $month => $data) {
                    $summary['ht']['monthly_data'][$month]['male'] += $data['male'];
                    $summary['ht']['monthly_data'][$month]['female'] += $data['female'];
                    $summary['ht']['monthly_data'][$month]['total'] += $data['total'];
                    $summary['ht']['monthly_data'][$month]['standard'] += $data['standard'];
                    $summary['ht']['monthly_data'][$month]['non_standard'] += $data['non_standard'];
                }
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $summary['dm']['target'] += $stat['dm']['target'];
                $summary['dm']['total_patients'] += $stat['dm']['total_patients'];
                $summary['dm']['standard_patients'] += $stat['dm']['standard_patients'];
                $summary['dm']['non_standard_patients'] += $stat['dm']['non_standard_patients'];
                $summary['dm']['male_patients'] += $stat['dm']['male_patients'];
                $summary['dm']['female_patients'] += $stat['dm']['female_patients'];

                foreach ($stat['dm']['monthly_data'] as $month => $data) {
                    $summary['dm']['monthly_data'][$month]['male'] += $data['male'];
                    $summary['dm']['monthly_data'][$month]['female'] += $data['female'];
                    $summary['dm']['monthly_data'][$month]['total'] += $data['total'];
                    $summary['dm']['monthly_data'][$month]['standard'] += $data['standard'];
                    $summary['dm']['monthly_data'][$month]['non_standard'] += $data['non_standard'];
                }
            }
        }

        // Calculate achievement percentages
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $summary['ht']['achievement_percentage'] = $summary['ht']['target'] > 0
                ? round(($summary['ht']['standard_patients'] / $summary['ht']['target']) * 100, 2)
                : 0;
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $summary['dm']['achievement_percentage'] = $summary['dm']['target'] > 0
                ? round(($summary['dm']['standard_patients'] / $summary['dm']['target']) * 100, 2)
                : 0;
        }

        return $summary;
    }

    /**
     * Get month name in Indonesian
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

        // Filter berdasarkan puskesmas_id jika ada
        if ($request->has('puskesmas_id')) {
            $puskesmasQuery->where('id', $request->puskesmas_id);
        }

        $puskesmas = $puskesmasQuery->get();
        if ($puskesmas->isEmpty()) {
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
        $filename .= $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT);

        // Export sesuai format
        if ($format === 'pdf') {
            return $this->exportStatisticsToPdf($statisticsData, $year, $month, $diseaseType, $filename);
        } else {
            return $this->exportStatisticsToExcel($statisticsData, $year, $month, $diseaseType, $filename);
        }
    }

    /**
     * Export statistics report to PDF
     */
    protected function exportStatisticsToPdf($statisticsData, $year, $month, $diseaseType, $filename)
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
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'type' => $diseaseType,
            'statistics' => $statisticsData,
            'generated_at' => Carbon::now()->format('d F Y H:i'),
            'generated_by' => Auth::user()->name,
            'user_role' => 'Admin',
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
    protected function exportStatisticsToExcel($statisticsData, $year, $month, $diseaseType, $filename)
    {
        $monthName = $this->getMonthName($month);
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Buat spreadsheet baru
        $spreadsheet = new Spreadsheet();

        // Jika perlu, buat sheet untuk setiap jenis penyakit
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->createStatisticsSheet($spreadsheet, $statisticsData['ht'], $year, $month, 'ht', $daysInMonth);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if ($diseaseType === 'all') {
                // Jika all, buat sheet baru untuk DM
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle('Statistik DM');
                $spreadsheet->setActiveSheetIndex(1);
            }
            $this->createStatisticsSheet($spreadsheet, $statisticsData['dm'], $year, $month, 'dm', $daysInMonth);
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
    protected function createStatisticsSheet($spreadsheet, $statistics, $year, $month, $diseaseType, $daysInMonth)
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
        $exportInfo = "Diekspor oleh: " . Auth::user()->name . " (Admin)";
        $sheet->setCellValue('A3', $exportInfo);
        $sheet->mergeCells('A3:' . $this->getColLetter(5 + $daysInMonth) . '3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Tanggal generate
        $sheet->setCellValue('A4', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A4:' . $this->getColLetter(5 + $daysInMonth) . '4');
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header baris 1
        $row = 6;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'Puskesmas');
        $sheet->setCellValue('C' . $row, 'Total Pasien');
        $sheet->setCellValue('D' . $row, 'Total Kunjungan');
        $sheet->setCellValue('E' . $row, 'Rata-rata Kunjungan');

        // Merge untuk header frekuensi kunjungan
        $sheet->setCellValue('F' . $row, 'Frekuensi Kunjungan per Hari');
        $sheet->mergeCells('F' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row);

        // Header baris 2 (tanggal)
        $row++;
        $sheet->setCellValue('A' . $row, '');
        $sheet->setCellValue('B' . $row, '');
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, '');
        $sheet->setCellValue('E' . $row, '');

        // Isi header tanggal
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(5 + $day);
            $sheet->setCellValue($col . $row, $day);
        }

        // Style header
        $headerRange1 = 'A6:' . $this->getColLetter(5 + $daysInMonth) . '6';
        $headerRange2 = 'A7:' . $this->getColLetter(5 + $daysInMonth) . '7';

        $sheet->getStyle($headerRange1)->getFont()->setBold(true);
        $sheet->getStyle($headerRange2)->getFont()->setBold(true);

        $sheet->getStyle($headerRange1)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange2)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');

        $sheet->getStyle($headerRange1)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange2)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle($headerRange1)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange2)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Data statistik
        $row = 7;
        foreach ($statistics as $index => $data) {
            $row++;

            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $data['name']);
            $sheet->setCellValue('C' . $row, $data[$diseaseType]['total_patients']);
            $sheet->setCellValue('D' . $row, $data[$diseaseType]['total_visits']);
            $sheet->setCellValue('E' . $row, $data[$diseaseType]['average_visits']);

            // Isi frekuensi kunjungan
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $col = $this->getColLetter(5 + $day);
                $date = Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
                $count = $data[$diseaseType]['visit_frequency'][$date] ?? 0;
                $sheet->setCellValue($col . $row, $count);
            }
        }

        // Style untuk data
        $dataRange = 'A8:' . $this->getColLetter(5 + $daysInMonth) . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Alignment untuk kolom tertentu
        $sheet->getStyle('A8:A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C8:E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Alignment untuk frekuensi kunjungan
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(5 + $day);
            $sheet->getStyle($col . '8:' . $col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Auto-size kolom
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setWidth(30); // Nama puskesmas biasanya lebih panjang
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);

        // Width untuk kolom tanggal (lebih kecil)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(5 + $day);
            $sheet->getColumnDimension($col)->setWidth(3.5);
        }

        // Freeze panes untuk memudahkan navigasi
        $sheet->freezePane('F8');
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
     * Get HT statistics from cache
     */
    protected function getHtStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = DB::table('monthly_statistics_cache')
            ->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', 'ht');

        if ($month) {
            $query->where('month', $month);
        }

        $stats = $query->get();

        $totalPatients = 0;
        $totalStandard = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($stats as $stat) {
            $totalPatients += $stat->total_count;
            $totalStandard += $stat->standard_count;
            $malePatients += $stat->male_count;
            $femalePatients += $stat->female_count;

            $monthlyData[$stat->month] = [
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => 0 // Will be calculated later with target
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'total_standard' => $totalStandard,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get DM statistics from cache
     */
    protected function getDmStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = DB::table('monthly_statistics_cache')
            ->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', 'dm');

        if ($month) {
            $query->where('month', $month);
        }

        $stats = $query->get();

        $totalPatients = 0;
        $totalStandard = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($stats as $stat) {
            $totalPatients += $stat->total_count;
            $totalStandard += $stat->standard_count;
            $malePatients += $stat->male_count;
            $femalePatients += $stat->female_count;

            $monthlyData[$stat->month] = [
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => 0 // Will be calculated later with target
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'total_standard' => $totalStandard,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }
}
