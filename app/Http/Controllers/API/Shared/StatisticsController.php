<?php

namespace App\Http\Controllers\API\Shared;

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
use App\Services\StatisticsService;
use App\Services\ExportService;

class StatisticsController extends Controller
{
    private $statisticsService;
    private $exportService;

    public function __construct(StatisticsService $statisticsService, ExportService $exportService)
    {
        $this->statisticsService = $statisticsService;
        $this->exportService = $exportService;
    }

    /**
     * Display a listing of statistics.
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null;
        $diseaseType = $request->type ?? 'all';
        $perPage = $request->per_page ?? 15;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Validasi bulan jika diisi
        if ($month !== null) {
            $month = intval($month);
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
                ], 400);
            }
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Jika ada filter nama puskesmas (hanya untuk admin)
        if (Auth::user()->is_admin && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Jika user bukan admin, filter data ke puskesmas user
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Log this issue to debug
                Log::warning('Puskesmas user without puskesmas_id: ' . Auth::user()->id);

                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . Auth::user()->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);

                    // Update the user with the correct puskesmas_id for future requests
                    Auth::user()->update(['puskesmas_id' => $puskesmasWithSameName->id]);

                    Log::info('Updated user ' . Auth::user()->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    // Kembalikan data kosong dengan pesan
                    return response()->json([
                        'message' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
                        'data' => [],
                        'meta' => [
                            'current_page' => 1,
                            'from' => 0,
                            'last_page' => 1,
                            'per_page' => $perPage,
                            'to' => 0,
                            'total' => 0,
                        ],
                    ], 400);
                }
            }
        }

        $puskesmasAll = $puskesmasQuery->get();

        // If no puskesmas found, return specific error
        if ($puskesmasAll->isEmpty()) {
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

        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Ambil data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->statisticsService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                $htTargetCount = $htTarget ? $htTarget->target_count : 0;

                $data['ht'] = [
                    'target' => $htTargetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTargetCount > 0
                        ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'non_standard_patients' => $htData['non_standard_patients'],
                    'male_patients' => $htData['male_patients'],
                    'female_patients' => $htData['female_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Ambil data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->statisticsService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

                $data['dm'] = [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTargetCount > 0
                        ? round(($dmData['standard_patients'] / $dmTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'non_standard_patients' => $dmData['non_standard_patients'],
                    'male_patients' => $dmData['male_patients'],
                    'female_patients' => $dmData['female_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $statistics[] = $data;
        }

        // Sort by achievement percentage berdasarkan jenis penyakit
        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            // Sort by combined achievement percentage (HT + DM) for ranking
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

        // Paginate the results
        $page = $request->page ?? 1;
        $offset = ($page - 1) * $perPage;

        $paginatedItems = array_slice($statistics, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            count($statistics),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Dashboard statistics API untuk frontend
     */
    public function dashboardStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all'; // Default 'all', bisa juga 'ht' atau 'dm'
        $user = Auth::user();

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Siapkan query untuk mengambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!$user->isAdmin()) {
            $puskesmasQuery->where('id', $user->puskesmas_id);
        }

        $puskesmasAll = $puskesmasQuery->get();

        // Siapkan data untuk dikirim ke frontend
        $data = [];

        foreach ($puskesmasAll as $puskesmas) {
            $puskesmasData = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Tambahkan data HT jika diperlukan
            if ($type === 'all' || $type === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->statisticsService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

                $targetCount = $htTarget ? $htTarget->target_count : 0;

                $puskesmasData['ht'] = [
                    'target' => $targetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($htData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Tambahkan data DM jika diperlukan
            if ($type === 'all' || $type === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->statisticsService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

                $targetCount = $dmTarget ? $dmTarget->target_count : 0;

                $puskesmasData['dm'] = [
                    'target' => $targetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($dmData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $data[] = $puskesmasData;
        }

        // Urutkan data berdasarkan achievement_percentage
        usort($data, function ($a, $b) use ($type) {
            $aValue = $type === 'dm' ?
                ($a['dm']['achievement_percentage'] ?? 0) : ($a['ht']['achievement_percentage'] ?? 0);

            $bValue = $type === 'dm' ?
                ($b['dm']['achievement_percentage'] ?? 0) : ($b['ht']['achievement_percentage'] ?? 0);

            return $bValue <=> $aValue;
        });

        // Tambahkan ranking
        foreach ($data as $index => $item) {
            $data[$index]['ranking'] = $index + 1;
        }

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }

    /**
     * Mendapatkan statistik HT dengan breakdown bulanan 
     * yang sesuai dengan logika standar dan tidak standar yang baru
     */
    private function getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        return $this->statisticsService->getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month);
    }

    /**
     * Mendapatkan statistik DM dengan breakdown bulanan
     * yang sesuai dengan logika standar dan tidak standar yang baru
     */
    private function getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        return $this->statisticsService->getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month);
    }

    /**
     * Export statistik bulanan atau tahunan ke format PDF atau Excel
     */
    public function exportStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null; // null = laporan tahunan
        $diseaseType = $request->type ?? 'all'; // Nilai default: 'all', bisa juga 'ht' atau 'dm'

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Validasi bulan jika diisi
        if ($month !== null) {
            $month = intval($month);
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
                ], 400);
            }
        }

        // Format export (pdf atau excel)
        $format = $request->format ?? 'excel';
        if (!in_array($format, ['pdf', 'excel'])) {
            return response()->json([
                'message' => 'Format tidak valid. Gunakan pdf atau excel.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Jika ada filter nama puskesmas (hanya untuk admin)
        if (Auth::user()->is_admin && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Implementasi logika ekspor:
        // - Admin dapat mencetak rekap atau laporan puskesmas tertentu
        // - User hanya dapat mencetak data miliknya sendiri

        // Jika user bukan admin, HARUS filter data ke puskesmas user
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Jika user bukan admin dan tidak terkait dengan puskesmas, kembalikan error
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mencetak statistik.',
                ], 403);
            }
        }

        // Cek apakah ini permintaan rekap (hanya untuk admin)
        $isRecap = Auth::user()->is_admin && (!$request->has('puskesmas_id') || $puskesmasQuery->count() > 1);

        $puskesmasAll = $puskesmasQuery->get();

        // Jika tidak ada puskesmas yang ditemukan
        if ($puskesmasAll->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang sesuai dengan filter.',
            ], 404);
        }

        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Ambil data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->statisticsService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $htTargetCount = $htTarget ? $htTarget->target_count : 0;
                if ($month !== null && $htTargetCount > 0) {
                    // Perkiraan target bulanan = target tahunan / 12
                    $htTargetCount = ceil($htTargetCount / 12);
                }

                $data['ht'] = [
                    'target' => $htTargetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTargetCount > 0
                        ? round(($htData['total_patients'] / $htTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'non_standard_patients' => $htData['non_standard_patients'],
                    'male_patients' => $htData['male_patients'],
                    'female_patients' => $htData['female_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Ambil data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->statisticsService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;
                if ($month !== null && $dmTargetCount > 0) {
                    // Perkiraan target bulanan = target tahunan / 12
                    $dmTargetCount = ceil($dmTargetCount / 12);
                }

                $data['dm'] = [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTargetCount > 0
                        ? round(($dmData['total_patients'] / $dmTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'non_standard_patients' => $dmData['non_standard_patients'],
                    'male_patients' => $dmData['male_patients'],
                    'female_patients' => $dmData['female_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $statistics[] = $data;
        }

        // Sort by achievement percentage berdasarkan jenis penyakit
        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            // Sort by combined achievement percentage (HT + DM) for ranking
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

        // Buat nama file
        $filename = "";

        // Tentukan jenis laporan berdasarkan parameter
        if ($month === null) {
            // Laporan tahunan
            $reportType = "laporan_tahunan";
        } else {
            // Laporan bulanan
            $reportType = "laporan_bulanan";
        }

        // Tambahkan prefix "rekap" jika ini adalah rekap (untuk admin)
        if (Auth::user()->is_admin && $isRecap) {
            $filename .= "rekap_";
        }

        $filename .= $reportType . "_";

        if ($diseaseType !== 'all') {
            $filename .= $diseaseType . "_";
        }

        $filename .= $year;

        if ($month !== null) {
            $filename .= "_" . str_pad($month, 2, '0', STR_PAD_LEFT);
        }

        // Jika user bukan admin ATAU admin yang mencetak laporan spesifik puskesmas,
        // tambahkan nama puskesmas pada filename
        if (!Auth::user()->is_admin) {
            $puskesmasName = Puskesmas::find(Auth::user()->puskesmas_id)->name ?? '';
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmasName));
        } elseif (Auth::user()->is_admin && !$isRecap) {
            // Admin mencetak laporan untuk satu puskesmas spesifik
            $puskesmasName = $puskesmasAll->first()->name ?? '';
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmasName));
        }

        // Proses export sesuai format
        if ($format === 'pdf') {
            return $this->exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
        } else {
            return $this->exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
        }
    }

    /**
     * Endpoint khusus untuk export data HT
     */
    public function exportHtStatistics(Request $request)
    {
        $request->merge(['type' => 'ht']);
        return $this->exportStatistics($request);
    }

    /**
     * Endpoint khusus untuk export data DM
     */
    public function exportDmStatistics(Request $request)
    {
        $request->merge(['type' => 'dm']);
        return $this->exportStatistics($request);
    }

    /**
     * Endpoint untuk export laporan pemantauan pasien (attendance)
     */
    public function exportMonitoringReport(Request $request)
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
                    'message' => 'Anda tidak memiliki akses untuk mencetak laporan pemantauan.',
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

        // Ambil data pasien dan kedatangan
        $patientData = $this->getPatientAttendanceData($puskesmas->id, $year, $month, $diseaseType);

        // Buat nama file
        $filename = "laporan_pemantauan_";
        if ($diseaseType !== 'all') {
            $filename .= $diseaseType . "_";
        }

        $monthName = $this->getMonthName($month);
        $filename .= $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT) . "_";
        $filename .= str_replace(' ', '_', strtolower($puskesmas->name));

        // Export sesuai format
        if ($format === 'pdf') {
            return $this->exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
        } else {
            return $this->exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
        }
    }

    /**
     * Export laporan statistik ke format PDF menggunakan Dompdf
     */
    protected function exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        return $this->exportService->exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
    }

    /**
     * Export laporan statistik ke format Excel menggunakan PhpSpreadsheet
     */
    protected function exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        return $this->exportService->exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
    }

    /**
     * Tambahkan sheet data bulanan ke spreadsheet untuk laporan tahunan
     */
    protected function addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap = false)
    {
        return $this->exportService->addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap);
    }

    /**
     * Get patient attendance data for monitoring report
     */
    protected function getPatientAttendanceData($puskesmasId, $year, $month, $diseaseType)
    {
        $result = [
            'ht' => [],
            'dm' => []
        ];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $daysInMonth = $endDate->day;

        // Ambil data pasien hipertensi jika diperlukan
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereJsonContains('ht_years', $year)
                ->orderBy('name')
                ->get();

            foreach ($htPatients as $patient) {
                // Ambil pemeriksaan HT untuk pasien di bulan ini
                $examinations = HtExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get()
                    ->pluck('examination_date')
                    ->map(function ($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();

                // Buat data kehadiran per hari
                $attendance = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $attendance[$day] = in_array($day, $examinations);
                }

                $result['ht'][] = [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }

        // Ambil data pasien diabetes jika diperlukan
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereJsonContains('dm_years', $year)
                ->orderBy('name')
                ->get();

            foreach ($dmPatients as $patient) {
                // Ambil pemeriksaan DM untuk pasien di bulan ini
                $examinations = DmExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->distinct('examination_date')
                    ->pluck('examination_date')
                    ->map(function ($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();

                // Buat data kehadiran per hari
                $attendance = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $attendance[$day] = in_array($day, $examinations);
                }

                $result['dm'][] = [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }

        return $result;
    }

    /**
     * Export monitoring report to PDF
     */
    protected function exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        return $this->exportService->exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
    }

    /**
     * Export monitoring report to Excel
     */
    protected function exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        return $this->exportService->exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
    }

    /**
     * Create sheet for monitoring report
     */
    protected function createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        return $this->exportService->createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth);
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

    /**
     * Mendapatkan statistik HT (Hipertensi) secara lengkap
     */
    private function getHtStatistics($puskesmasId, $year, $month = null)
    {
        return $this->statisticsService->getHtStatistics($puskesmasId, $year, $month);
    }

    /**
     * Mendapatkan statistik DM (Diabetes) secara lengkap
     */
    private function getDmStatistics($puskesmasId, $year, $month = null)
    {
        return $this->statisticsService->getDmStatistics($puskesmasId, $year, $month);
    }

    /**
     * Display aggregated statistics for admin (all puskesmas)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function adminStatistics(Request $request)
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

        // Get puskesmas with pagination directly from database
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

        // Get all puskesmas IDs for summary calculations
        $allPuskesmasIds = Puskesmas::pluck('id')->toArray();

        // Get statistics for paginated puskesmas only
        $statistics = [];
        $puskesmasIds = $puskesmas->pluck('id')->toArray();

        // Get targets for all puskesmas in one query to reduce DB calls
        $htTargets = YearlyTarget::where('year', $year)
            ->where('disease_type', 'ht')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get()
            ->keyBy('puskesmas_id');

        $dmTargets = YearlyTarget::where('year', $year)
            ->where('disease_type', 'dm')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get()
            ->keyBy('puskesmas_id');

        // Pre-fetch statistics from cache
        $monthlyStats = MonthlyStatisticsCache::where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->when($month, function ($query) use ($month) {
                return $query->where('month', $month);
            })
            ->get();

        $htStats = $monthlyStats->where('disease_type', 'ht')->groupBy('puskesmas_id');
        $dmStats = $monthlyStats->where('disease_type', 'dm')->groupBy('puskesmas_id');

        foreach ($puskesmas as $p) {
            $data = [
                'puskesmas_id' => $p->id,
                'puskesmas_name' => $p->name,
            ];

            // Get HT data if requested
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                // Use cached data if available
                if (isset($htStats[$p->id])) {
                    $data['ht'] = $this->processHtCachedStats($htStats[$p->id], $htTargets->get($p->id));
                } else {
                    // Fallback to direct calculation if cache not available
                    $htTarget = $htTargets->get($p->id);
                    $htTargetCount = $htTarget ? $htTarget->target_count : 0;

                    // Use shorter function that reads from cache instead of recalculating
                    $htData = $this->getHtStatisticsFromCache($p->id, $year, $month);

                    $data['ht'] = [
                        'target' => $htTargetCount,
                        'total_patients' => $htData['total_patients'],
                        'achievement_percentage' => $htTargetCount > 0
                            ? round(($htData['total_standard'] / $htTargetCount) * 100, 2)
                            : 0,
                        'standard_patients' => $htData['total_standard'],
                        'non_standard_patients' => $htData['total_patients'] - $htData['total_standard'],
                        'male_patients' => $htData['male_patients'] ?? 0,
                        'female_patients' => $htData['female_patients'] ?? 0,
                        'monthly_data' => $htData['monthly_data'],
                    ];
                }
            }

            // Get DM data if requested
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                // Use cached data if available
                if (isset($dmStats[$p->id])) {
                    $data['dm'] = $this->processDmCachedStats($dmStats[$p->id], $dmTargets->get($p->id));
                } else {
                    // Fallback to direct calculation if cache not available
                    $dmTarget = $dmTargets->get($p->id);
                    $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

                    // Use shorter function that reads from cache instead of recalculating
                    $dmData = $this->getDmStatisticsFromCache($p->id, $year, $month);

                    $data['dm'] = [
                        'target' => $dmTargetCount,
                        'total_patients' => $dmData['total_patients'],
                        'achievement_percentage' => $dmTargetCount > 0
                            ? round(($dmData['total_standard'] / $dmTargetCount) * 100, 2)
                            : 0,
                        'standard_patients' => $dmData['total_standard'],
                        'non_standard_patients' => $dmData['total_patients'] - $dmData['total_standard'],
                        'male_patients' => $dmData['male_patients'] ?? 0,
                        'female_patients' => $dmData['female_patients'] ?? 0,
                        'monthly_data' => $dmData['monthly_data'],
                    ];
                }
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

        // Calculate summary data for all puskesmas
        $summary = $this->calculateSummaryStatistics($allPuskesmasIds, $year, $month, $diseaseType);

        // Prepare response with summary data
        $response = [
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
        ];

        // Add chart data for visualization (from summary)
        $response['chart_data'] = $this->prepareChartData(
            $diseaseType,
            $summary['ht']['monthly_data'] ?? [],
            $summary['dm']['monthly_data'] ?? []
        );

        // Add top and bottom performers
        // This requires getting top and bottom performers from database instead of full puskesmas list
        $response['rankings'] = $this->getTopAndBottomPuskesmas($year, $diseaseType);

        return response()->json($response);
    }

    /**
     * Process cached HT statistics for admin view
     */
    private function processHtCachedStats($statsList, $target = null)
    {
        return $this->statisticsService->processHtCachedStats($statsList, $target);
    }

    /**
     * Process cached DM statistics for admin view
     */
    private function processDmCachedStats($statsList, $target = null)
    {
        return $this->statisticsService->processDmCachedStats($statsList, $target);
    }

    /**
     * Calculate summary statistics using efficient queries
     */
    private function calculateSummaryStatistics($puskesmasIds, $year, $month, $diseaseType)
    {
        return $this->statisticsService->calculateSummaryStatistics($puskesmasIds, $year, $month, $diseaseType);
    }

    /**
     * Get monthly aggregated statistics
     */
    private function getMonthlyAggregatedStats($diseaseType, $puskesmasIds, $year, $targetTotal)
    {
        return $this->statisticsService->getMonthlyAggregatedStats($diseaseType, $puskesmasIds, $year, $targetTotal);
    }

    /**
     * Get top and bottom performers efficiently
     */
    private function getTopAndBottomPuskesmas($year, $diseaseType)
    {
        return $this->statisticsService->getTopAndBottomPuskesmas($year, $diseaseType);
    }

    /**
     * Prepare chart data for frontend visualization
     */
    private function prepareChartData($diseaseType, $htMonthlyData, $dmMonthlyData)
    {
        return $this->statisticsService->prepareChartData($diseaseType, $htMonthlyData, $dmMonthlyData);
    }

    private function getHtStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        return $this->statisticsService->getHtStatisticsFromCache($puskesmasId, $year, $month);
    }

    private function getDmStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        return $this->statisticsService->getDmStatisticsFromCache($puskesmasId, $year, $month);
    }
}
