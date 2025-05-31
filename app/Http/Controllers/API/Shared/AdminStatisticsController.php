<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Services\StatisticsCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    protected $statisticsService;
    protected $cacheVersion = 'v1';
    protected $cacheDuration = 1800; // 30 menit

    public function __construct(StatisticsCalculationService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get admin statistics
     */
    public function index(Request $request)
    {
        try {
            $year = $request->year ?? Carbon::now()->year;
            $month = $request->month ?? Carbon::now()->month;
            $type = $request->type ?? 'all';
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;

            // Validasi nilai type
            if (!in_array($type, ['all', 'ht', 'dm'])) {
                return response()->json([
                    'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
                ], 400);
            }

            // Generate cache key untuk semua data
            $cacheKey = "admin_stats:{$this->cacheVersion}:{$year}:{$month}:{$type}";

            // Get cached data or calculate new
            $data = Cache::remember($cacheKey, $this->cacheDuration, function () use ($year, $month, $type) {
                try {
                    // Siapkan query untuk mengambil data puskesmas
                    $puskesmasQuery = Puskesmas::query();

                    // Filter berdasarkan role
                    if (!Auth::user()->is_admin) {
                        $puskesmasQuery->where('id', Auth::user()->puskesmas_id);
                    }

                    $puskesmasAll = $puskesmasQuery->get();

                    if ($puskesmasAll->isEmpty()) {
                        return [];
                    }

                    // Get all targets in one query
                    $targets = YearlyTarget::whereIn('puskesmas_id', $puskesmasAll->pluck('id'))
                        ->where('year', $year)
                        ->get()
                        ->groupBy('puskesmas_id')
                        ->map(function ($group) {
                            return $group->groupBy('disease_type')
                                ->map(function ($items) {
                                    return $items->first()->target_count ?? 0;
                                });
                        });

                    // Get all statistics in one query per disease type
                    $htStats = [];
                    $dmStats = [];

                    if ($type === 'all' || $type === 'ht') {
                        $htStats = $this->statisticsService->calculateAllHtStatistics($puskesmasAll->pluck('id'), $year);
                    }

                    if ($type === 'all' || $type === 'dm') {
                        $dmStats = $this->statisticsService->calculateAllDmStatistics($puskesmasAll->pluck('id'), $year);
                    }

                    $result = [];
                    foreach ($puskesmasAll as $puskesmas) {
                        $puskesmasData = [
                            'puskesmas_id' => $puskesmas->id,
                            'puskesmas_name' => $puskesmas->name,
                        ];

                        // Add HT data if needed
                        if ($type === 'all' || $type === 'ht') {
                            $htTarget = $targets[$puskesmas->id]['ht'] ?? 0;
                            $htData = $htStats[$puskesmas->id] ?? [
                                'total_patients' => 0,
                                'standard_patients' => 0,
                                'monthly_data' => []
                            ];

                            $puskesmasData['ht'] = [
                                'target' => $htTarget,
                                'total_patients' => $htData['total_patients'],
                                'achievement_percentage' => $htTarget > 0
                                    ? round(($htData['standard_patients'] / $htTarget) * 100, 2)
                                    : 0,
                                'standard_patients' => $htData['standard_patients'],
                                'monthly_data' => $htData['monthly_data'][$month] ?? [
                                    'total' => 0,
                                    'standard' => 0
                                ],
                            ];
                        }

                        // Add DM data if needed
                        if ($type === 'all' || $type === 'dm') {
                            $dmTarget = $targets[$puskesmas->id]['dm'] ?? 0;
                            $dmData = $dmStats[$puskesmas->id] ?? [
                                'total_patients' => 0,
                                'standard_patients' => 0,
                                'monthly_data' => []
                            ];

                            $puskesmasData['dm'] = [
                                'target' => $dmTarget,
                                'total_patients' => $dmData['total_patients'],
                                'achievement_percentage' => $dmTarget > 0
                                    ? round(($dmData['standard_patients'] / $dmTarget) * 100, 2)
                                    : 0,
                                'standard_patients' => $dmData['standard_patients'],
                                'monthly_data' => $dmData['monthly_data'][$month] ?? [
                                    'total' => 0,
                                    'standard' => 0
                                ],
                            ];
                        }

                        $result[] = $puskesmasData;
                    }

                    // Sort data by achievement percentage
                    usort($result, function ($a, $b) use ($type) {
                        $aValue = $type === 'dm' ?
                            ($a['dm']['achievement_percentage'] ?? 0) : ($a['ht']['achievement_percentage'] ?? 0);

                        $bValue = $type === 'dm' ?
                            ($b['dm']['achievement_percentage'] ?? 0) : ($b['ht']['achievement_percentage'] ?? 0);

                        return $bValue <=> $aValue;
                    });

                    // Add ranking
                    foreach ($result as $index => $item) {
                        $result[$index]['ranking'] = $index + 1;
                    }

                    return $result;
                } catch (\Exception $e) {
                    \Log::error('Error calculating statistics: ' . $e->getMessage());
                    \Log::error($e->getTraceAsString());
                    throw $e;
                }
            });

            // Pagination
            $totalData = count($data);
            $start = ($page - 1) * $perPage;
            $paginatedData = array_slice($data, $start, $perPage);

            return response()->json([
                'year' => $year,
                'month' => $month,
                'type' => $type,
                'data' => $paginatedData,
                'pagination' => [
                    'total' => $totalData,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalData / $perPage),
                    'from' => $start + 1,
                    'to' => min($start + $perPage, $totalData)
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in AdminStatisticsController@index: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data statistik.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate statistics for a puskesmas
     */
    protected function calculatePuskesmasStatistics($puskesmas, $year, $month)
    {
        // Get targets with cache
        $htTarget = Cache::remember(
            "yearly_target:{$this->cacheVersion}:{$puskesmas->id}:ht:{$year}",
            $this->cacheDuration,
            function () use ($puskesmas, $year) {
                return YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();
            }
        );

        $dmTarget = Cache::remember(
            "yearly_target:{$this->cacheVersion}:{$puskesmas->id}:dm:{$year}",
            $this->cacheDuration,
            function () use ($puskesmas, $year) {
                return YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();
            }
        );

        // Get statistics using service
        $htStats = $this->statisticsService->calculateHtStatistics($puskesmas->id, $year);
        $dmStats = $this->statisticsService->calculateDmStatistics($puskesmas->id, $year);

        return [
            'ht' => [
                'target' => $htTarget ? $htTarget->target_count : 0,
                'total_patients' => $htStats['total_patients'],
                'achievement_percentage' => $htTarget && $htTarget->target_count > 0
                    ? round(($htStats['standard_patients'] / $htTarget->target_count) * 100, 2)
                    : 0,
                'standard_patients' => $htStats['standard_patients'],
                'monthly_data' => $htStats['monthly_data'][$month] ?? [
                    'total' => 0,
                    'standard' => 0
                ],
            ],
            'dm' => [
                'target' => $dmTarget ? $dmTarget->target_count : 0,
                'total_patients' => $dmStats['total_patients'],
                'achievement_percentage' => $dmTarget && $dmTarget->target_count > 0
                    ? round(($dmStats['standard_patients'] / $dmTarget->target_count) * 100, 2)
                    : 0,
                'standard_patients' => $dmStats['standard_patients'],
                'monthly_data' => $dmStats['monthly_data'][$month] ?? [
                    'total' => 0,
                    'standard' => 0
                ],
            ]
        ];
    }

    /**
     * Clear cache for a specific puskesmas, year, and month
     */
    public function clearCache($puskesmasId, $year, $month)
    {
        $keys = [
            "admin_stats:{$this->cacheVersion}:{$puskesmasId}:{$year}:{$month}",
            "yearly_target:{$this->cacheVersion}:{$puskesmasId}:ht:{$year}",
            "yearly_target:{$this->cacheVersion}:{$puskesmasId}:dm:{$year}"
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        return true;
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

    /**
     * Format statistics data for response
     */
    protected function getStatisticsData($puskesmas, $htStats, $dmStats, $targets)
    {
        $htTarget = $targets[$puskesmas->id]['ht'] ?? 0;
        $dmTarget = $targets[$puskesmas->id]['dm'] ?? 0;

        $htAchievement = $htTarget > 0 ? ($htStats['standard_patients'] / $htTarget) * 100 : 0;
        $dmAchievement = $dmTarget > 0 ? ($dmStats['standard_patients'] / $dmTarget) * 100 : 0;

        return [
            'id' => $puskesmas->id,
            'name' => $puskesmas->name,
            'ht' => [
                'target' => $htTarget,
                'total_patients' => $htStats['total_patients'],
                'standard_patients' => $htStats['standard_patients'],
                'achievement' => round($htAchievement, 2),
                'monthly_data' => $htStats['monthly_data']
            ],
            'dm' => [
                'target' => $dmTarget,
                'total_patients' => $dmStats['total_patients'],
                'standard_patients' => $dmStats['standard_patients'],
                'achievement' => round($dmAchievement, 2),
                'monthly_data' => $dmStats['monthly_data']
            ]
        ];
    }
}
