<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use App\Http\Requests\Puskesmas\PuskesmasPdfRequest;
use App\Exceptions\PuskesmasNotFoundException;
use App\Repositories\PuskesmasRepositoryInterface;
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
use App\Services\PuskesmasExportService;
use App\Services\PdfService;

class StatisticsController extends Controller
{
    private $statisticsService;
    private $exportService;
    private $puskesmasExportService;
    private $pdfService;
    private $puskesmasRepository;

    public function __construct(
        StatisticsService $statisticsService,
        ExportService $exportService,
        PuskesmasExportService $puskesmasExportService,
        PdfService $pdfService,
        PuskesmasRepositoryInterface $puskesmasRepository
    ) {
        $this->statisticsService = $statisticsService;
        $this->exportService = $exportService;
        $this->puskesmasExportService = $puskesmasExportService;
        $this->pdfService = $pdfService;
        $this->puskesmasRepository = $puskesmasRepository;
    }

    /**
     * Display a listing of statistics.
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null;
        $diseaseType = $request->disease_type ?? 'all';
        $perPage = $request->per_page ?? 15;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.',
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
        if (Auth::user()->isAdmin() && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Jika user bukan admin, filter data ke puskesmas user
        if (!Auth::user()->isAdmin()) {
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

        // OPTIMASI: Ambil semua cache statistik bulanan sekaligus
        $puskesmasIds = $puskesmasAll->pluck('id')->toArray();
        $monthlyStats = \App\Models\MonthlyStatisticsCache::where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get();
        $htStats = $monthlyStats->where('disease_type', 'ht')->groupBy('puskesmas_id');
        $dmStats = $monthlyStats->where('disease_type', 'dm')->groupBy('puskesmas_id');
        $statistics = [];
        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htArr = [
                    'target' => 0,
                    'total_patients' => 0,
                    'achievement_percentage' => 0,
                    'standard_patients' => 0,
                    'non_standard_patients' => 0,
                    'male_patients' => 0,
                    'female_patients' => 0,
                    'monthly_data' => [],
                ];
                $target = \App\Models\YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();
                $targetCount = $target ? $target->target_count : 0;
                $htArr['target'] = $targetCount;
                if (isset($htStats[$puskesmas->id])) {
                    $totalPatients = $htStats[$puskesmas->id]->sum('total_count');
                    $standardPatients = $htStats[$puskesmas->id]->sum('standard_count');
                    $nonStandardPatients = $htStats[$puskesmas->id]->sum('non_standard_count');
                    $malePatients = $htStats[$puskesmas->id]->sum('male_count');
                    $femalePatients = $htStats[$puskesmas->id]->sum('female_count');
                    $htArr['total_patients'] = $totalPatients;
                    $htArr['standard_patients'] = $standardPatients;
                    $htArr['non_standard_patients'] = $nonStandardPatients;
                    $htArr['male_patients'] = $malePatients;
                    $htArr['female_patients'] = $femalePatients;
                    $htArr['achievement_percentage'] = $targetCount > 0 ? round(($standardPatients / $targetCount) * 100, 2) : 0;
                    $monthlyData = [];
                    foreach ($htStats[$puskesmas->id] as $stat) {
                        $monthlyData[$stat->month] = [
                            'male' => $stat->male_count,
                            'female' => $stat->female_count,
                            'total' => $stat->total_count,
                            'standard' => $stat->standard_count,
                            'non_standard' => $stat->non_standard_count,
                            'percentage' => $targetCount > 0 ? round(($stat->standard_count / $targetCount) * 100, 2) : 0,
                        ];
                    }
                    $htArr['monthly_data'] = $monthlyData;
                }
                $data['ht'] = $htArr;
            }
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmArr = [
                    'target' => 0,
                    'total_patients' => 0,
                    'achievement_percentage' => 0,
                    'standard_patients' => 0,
                    'non_standard_patients' => 0,
                    'male_patients' => 0,
                    'female_patients' => 0,
                    'monthly_data' => [],
                ];
                $target = \App\Models\YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();
                $targetCount = $target ? $target->target_count : 0;
                $dmArr['target'] = $targetCount;
                if (isset($dmStats[$puskesmas->id])) {
                    $totalPatients = $dmStats[$puskesmas->id]->sum('total_count');
                    $standardPatients = $dmStats[$puskesmas->id]->sum('standard_count');
                    $nonStandardPatients = $dmStats[$puskesmas->id]->sum('non_standard_count');
                    $malePatients = $dmStats[$puskesmas->id]->sum('male_count');
                    $femalePatients = $dmStats[$puskesmas->id]->sum('female_count');
                    $dmArr['total_patients'] = $totalPatients;
                    $dmArr['standard_patients'] = $standardPatients;
                    $dmArr['non_standard_patients'] = $nonStandardPatients;
                    $dmArr['male_patients'] = $malePatients;
                    $dmArr['female_patients'] = $femalePatients;
                    $dmArr['achievement_percentage'] = $targetCount > 0 ? round(($standardPatients / $targetCount) * 100, 2) : 0;
                    $monthlyData = [];
                    foreach ($dmStats[$puskesmas->id] as $stat) {
                        $monthlyData[$stat->month] = [
                            'male' => $stat->male_count,
                            'female' => $stat->female_count,
                            'total' => $stat->total_count,
                            'standard' => $stat->standard_count,
                            'non_standard' => $stat->non_standard_count,
                            'percentage' => $targetCount > 0 ? round(($stat->standard_count / $targetCount) * 100, 2) : 0,
                        ];
                    }
                    $dmArr['monthly_data'] = $monthlyData;
                }
                $data['dm'] = $dmArr;
            }
            $statistics[] = $data;
        }

        // Urutkan ranking DM/HT jika disease_type=dm/ht
        if ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return ($b['dm']['achievement_percentage'] ?? 0) <=> ($a['dm']['achievement_percentage'] ?? 0);
            });
        } elseif ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return ($b['ht']['achievement_percentage'] ?? 0) <=> ($a['ht']['achievement_percentage'] ?? 0);
            });
        }
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
        $diseaseType = $request->disease_type ?? 'all'; // Default 'all', bisa juga 'ht' atau 'dm'
        $user = Auth::user();

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Siapkan query untuk mengambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!Auth::user()->isAdmin()) {
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
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->statisticsService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year);
                // Provide default values if $htData is null
                $htData = $htData ?? [
                    'total_patients' => 0,
                    'standard_patients' => 0,
                    'monthly_data' => [],
                ];

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
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->statisticsService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year);
                // Provide default values if $dmData is null
                $dmData = $dmData ?? [
                    'total_patients' => 0,
                    'standard_patients' => 0,
                    'monthly_data' => [],
                ];

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

        // Tidak perlu mengurutkan data, ranking diisi sesuai urutan default
        foreach ($data as $index => $item) {
            $data[$index]['ranking'] = $index + 1;
        }

        return response()->json([
            'year' => $year,
            'disease_type' => $diseaseType,
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
        $diseaseType = $request->disease_type ?? 'all'; // Nilai default: 'all', bisa juga 'ht' atau 'dm'
        $tableType = $request->table_type ?? 'all'; // Nilai default: 'all', bisa juga 'quarterly' atau 'monthly'

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Validasi nilai table_type
        if (!in_array($tableType, ['all', 'quarterly', 'monthly', 'puskesmas'])) {
            return response()->json([
                'message' => 'Parameter table_type tidak valid. Gunakan all, quarterly, monthly, atau puskesmas.',
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
        if (Auth::user()->isAdmin() && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Jika user bukan admin, filter data ke puskesmas user
        if (!Auth::user()->isAdmin()) {
            $puskesmasQuery->where('id', Auth::user()->puskesmas_id);
        }

        $puskesmasAll = $puskesmasQuery->get();

        // Tentukan apakah ini laporan rekap (admin) atau laporan puskesmas
        $isRecap = Auth::user()->isAdmin() && !$request->has('puskesmas_id');
        $reportType = $isRecap ? 'recap' : 'single';

        // Buat nama file
        $filename = "laporan_" . ($diseaseType === 'all' ? 'ht_dm' : $diseaseType) . "_" . $year;
        if ($month) {
            $filename .= "_" . str_pad($month, 2, '0', STR_PAD_LEFT);
        }

        // Jika user bukan admin ATAU admin yang mencetak laporan spesifik puskesmas,
        // tambahkan nama puskesmas pada filename
        if (!Auth::user()->isAdmin()) {
            $puskesmasName = Puskesmas::find(Auth::user()->puskesmas_id)->name ?? '';
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmasName));
        } elseif (Auth::user()->isAdmin() && !$isRecap) {
            // Admin mencetak laporan untuk satu puskesmas spesifik
            $puskesmasName = $puskesmasAll->first()->name ?? '';
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmasName));
        }

        // Tambahkan table_type ke filename jika admin
        if (Auth::user()->isAdmin()) {
            $filename .= "_" . $tableType;
        }

        // Proses export sesuai format
        if ($format === 'pdf') {
            // Handle table_type 'puskesmas' dengan template PDF khusus
            if ($tableType === 'puskesmas') {
                // Tentukan puskesmas_id berdasarkan user role
                $puskesmasId = null;
                if (Auth::user()->isAdmin()) {
                    // Admin harus memilih puskesmas atau gunakan yang pertama sebagai default
                    $puskesmasId = $request->puskesmas_id;
                    if (!$puskesmasId) {
                        $firstPuskesmas = \App\Models\Puskesmas::first();
                        $puskesmasId = $firstPuskesmas ? $firstPuskesmas->id : null;
                    }
                } else {
                    // User puskesmas menggunakan puskesmas mereka sendiri
                    $puskesmasId = Auth::user()->puskesmas_id;
                }

                if (!$puskesmasId) {
                    return response()->json([
                        'error' => 'Puskesmas tidak ditemukan untuk export PDF'
                    ], 404);
                }

                // Gunakan PdfService untuk generate PDF puskesmas
                try {
                    return $this->pdfService->generatePuskesmasPdf($puskesmasId, $diseaseType, $year);
                } catch (\Exception $e) {
                    \Log::error('Export puskesmas PDF failed', [
                        'error' => $e->getMessage(),
                        'disease_type' => $diseaseType,
                        'year' => $year,
                        'puskesmas_id' => $puskesmasId
                    ]);
                    return response()->json([
                        'error' => 'Gagal mengexport PDF: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Panggil metode exportToPdf dari service untuk format lainnya
            return $this->exportService->exportToPdf(
                $puskesmasAll,
                $year,
                $month,
                $diseaseType,
                $filename,
                $isRecap,
                $reportType
            );
        } else {
            // Handle table_type 'puskesmas' dengan PuskesmasExportService
            if ($tableType === 'puskesmas') {
                // Jika admin tanpa puskesmas_id spesifik, gunakan puskesmas pertama sebagai default
                $puskesmasId = $request->puskesmas_id;
                if (!$puskesmasId && Auth::user()->isAdmin()) {
                    $firstPuskesmas = \App\Models\Puskesmas::first();
                    $puskesmasId = $firstPuskesmas ? $firstPuskesmas->id : null;
                }
                return $this->puskesmasExportService->exportPuskesmasStatistics($diseaseType, $year, $puskesmasId);
            }

            if (Auth::user()->isAdmin()) {
                return $this->exportService->exportToExcel($diseaseType, $year, $request->puskesmas_id, $tableType);
            } else {
                return $this->exportToExcel($puskesmasAll, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
            }
        }
    }



    /**
     * Endpoint untuk export laporan pemantauan pasien (attendance)
     */
    public function exportMonitoringReport(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? Carbon::now()->month;
        $diseaseType = $request->disease_type ?? 'all';
        $format = $request->format ?? 'excel';

        // Validasi parameter
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.',
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
        if (!Auth::user()->isAdmin()) {
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
    protected function exportToPdf($puskesmasAll, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        return $this->exportService->exportToPdf($puskesmasAll, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
    }

    /**
     * Export laporan statistik ke format Excel menggunakan PhpSpreadsheet
     */
    protected function exportToExcel($puskesmasAll, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        return $this->exportService->exportToExcel($puskesmasAll, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
    }

    /**
     * Tambahkan sheet data bulanan ke spreadsheet untuk laporan tahunan
     */
    protected function addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap = false)
    {
        return $this->exportService->createMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap);
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
        return $this->exportService->exportToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename, 'monitoring');
    }

    /**
     * Export monitoring report to Excel
     */
    protected function exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        return $this->exportService->exportToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
    }

    /**
     * Create sheet for monitoring report
     */
    protected function createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        return $this->exportService->exportMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth);
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
        $diseaseType = $request->disease_type ?? 'all'; // all, ht, dm

        // Validate disease type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.',
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
                    $htArr = $this->processHtCachedStats($htStats[$p->id], $htTargets->get($p->id));
                } else {
                    $htTarget = $htTargets->get($p->id);
                    $htTargetCount = $htTarget ? $htTarget->target_count : 0;
                    $htData = $this->getHtStatisticsFromCache($p->id, $year, $month);
                    $htArr = [
                        'target' => $htTargetCount,
                        'total_patients' => $htData['total_patients'],
                        'achievement_percentage' => $htTargetCount > 0
                            ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
                            : 0,
                        'standard_patients' => $htData['standard_patients'],
                        'non_standard_patients' => $htData['total_patients'] - $htData['standard_patients'],
                        'male_patients' => $htData['male_patients'] ?? 0,
                        'female_patients' => $htData['female_patients'] ?? 0,
                        'monthly_data' => $htData['monthly_data'],
                    ];
                }
                // Format ke string dan monthly_data sesuai permintaan
                $htArr = [
                    'target' => (string)($htArr['target'] ?? 0),
                    'total_patients' => (string)($htArr['total_patients'] ?? 0),
                    'standard_patients' => (string)($htArr['standard_patients'] ?? 0),
                    'non_standard_patients' => (string)($htArr['non_standard_patients'] ?? 0),
                    'male_patients' => (string)($htArr['male_patients'] ?? 0),
                    'female_patients' => (string)($htArr['female_patients'] ?? 0),
                    'achievement_percentage' => $htArr['achievement_percentage'] ?? 0,
                    'monthly_data' => array_map(function ($m) {
                        return [
                            'male' => (string)($m['male'] ?? 0),
                            'female' => (string)($m['female'] ?? 0),
                            'total' => (string)($m['total'] ?? 0),
                            'standard' => (string)($m['standard'] ?? 0),
                            'non_standard' => (string)($m['non_standard'] ?? 0),
                            'percentage' => isset($m['standard'], $m['target']) && $m['target'] > 0 ? round(($m['standard'] / $m['target']) * 100, 2) : 0,
                        ];
                    }, $htArr['monthly_data'] ?? []),
                ];
                $data['ht'] = $htArr;
            }

            // Get DM data if requested
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $this->statisticsService->getDmStatisticsWithMonthlyBreakdown($p->id, $year, $month);
                $dmArr = $dmData;
                $dmArr = [
                    'target' => (string)($dmArr['target'] ?? 0),
                    'total_patients' => (string)($dmArr['total_patients'] ?? 0),
                    'standard_patients' => (string)($dmArr['standard_patients'] ?? 0),
                    'non_standard_patients' => (string)($dmArr['non_standard_patients'] ?? 0),
                    'male_patients' => (string)($dmArr['male_patients'] ?? 0),
                    'female_patients' => (string)($dmArr['female_patients'] ?? 0),
                    'achievement_percentage' => $dmArr['achievement_percentage'] ?? 0,
                    'monthly_data' => array_map(function ($m) {
                        return [
                            'male' => (string)($m['male'] ?? 0),
                            'female' => (string)($m['female'] ?? 0),
                            'total' => (string)($m['total'] ?? 0),
                            'standard' => (string)($m['standard'] ?? 0),
                            'non_standard' => (string)($m['non_standard'] ?? 0),
                            'percentage' => isset($m['standard'], $m['target']) && $m['target'] > 0 ? round(($m['standard'] / $m['target']) * 100, 2) : 0,
                        ];
                    }, $dmArr['monthly_data'] ?? []),
                ];
                $data['dm'] = $dmArr;
            }

            // Hapus field yang tidak perlu
            if ($diseaseType === 'dm') {
                unset($data['ht']);
            }
            if ($diseaseType === 'ht') {
                unset($data['dm']);
            }

            $statistics[] = $data;
        }

        // Urutkan ranking DM/HT jika disease_type=dm/ht
        if ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return ($b['dm']['achievement_percentage'] ?? 0) <=> ($a['dm']['achievement_percentage'] ?? 0);
            });
        } elseif ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return ($b['ht']['achievement_percentage'] ?? 0) <=> ($a['ht']['achievement_percentage'] ?? 0);
            });
        }
        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        // Calculate summary data for all puskesmas
        $summary = $this->calculateSummaryStatistics($allPuskesmasIds, $year, $month, $diseaseType);

        // Prepare response with summary data
        $response = [
            'year' => $year,
            'disease_type' => $diseaseType,
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

        // Tambahkan data seluruh puskesmas (tanpa paginasi)
        $allPuskesmas = Puskesmas::all(['id', 'name']);
        $response['all_puskesmas'] = $allPuskesmas;

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

    /**
     * Get available years for export
     */
    public function getAvailableYears()
    {
        $puskesmasId = Auth::user()->isAdmin() ? null : Auth::user()->puskesmas_id;
        $years = $this->puskesmasExportService->getAvailableYears($puskesmasId);

        return response()->json([
            'success' => true,
            'data' => ['years' => $years]
        ]);
    }

    /**
     * Get list of puskesmas (admin only)
     */
    public function getPuskesmasList()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya admin yang dapat mengakses daftar puskesmas.'
            ], 403);
        }

        $puskesmas = $this->puskesmasExportService->getPuskesmasList();

        return response()->json([
            'success' => true,
            'data' => ['puskesmas' => $puskesmas]
        ]);
    }

    /**
     * Get export options based on user role
     */
    public function getExportOptions()
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $exportOptions = [
            'user_role' => $isAdmin ? 'admin' : 'puskesmas',
            'available_exports' => [
                [
                    'type' => 'ht',
                    'name' => 'Hipertensi',
                    'description' => 'Export data statistik Hipertensi'
                ],
                [
                    'type' => 'dm',
                    'name' => 'Diabetes Melitus',
                    'description' => 'Export data statistik Diabetes Melitus'
                ]
            ],
            'can_select_puskesmas' => $isAdmin
        ];

        if (!$isAdmin) {
            $exportOptions['puskesmas_info'] = [
                'id' => $user->puskesmas_id,
                'name' => $user->puskesmas->name ?? 'Unknown'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $exportOptions
        ]);
    }

    /**
     * Export puskesmas statistics to PDF using custom template
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportPuskesmasPdf(PuskesmasPdfRequest $request)
    {
        $correlationId = uniqid('pdf_export_', true);

        try {
            $validatedData = $request->getValidatedData();

            Log::info('Starting Puskesmas PDF export', [
                'correlation_id' => $correlationId,
                'user_id' => Auth::id(),
                'user_role' => Auth::user()->isAdmin() ? 'admin' : 'puskesmas',
                'request_data' => $validatedData
            ]);

            // Validate puskesmas exists using repository
            $puskesmas = $this->puskesmasRepository->findOrFail($validatedData['puskesmas_id']);

            Log::info('Puskesmas validated successfully', [
                'correlation_id' => $correlationId,
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name
            ]);

            // Generate PDF menggunakan PdfService
            $result = $this->pdfService->generatePuskesmasPdf(
                $validatedData['puskesmas_id'],
                $validatedData['disease_type'],
                $validatedData['year']
            );

            Log::info('Puskesmas PDF export completed successfully', [
                'correlation_id' => $correlationId,
                'puskesmas_id' => $validatedData['puskesmas_id']
            ]);

            return $result;
        } catch (PuskesmasNotFoundException $e) {
            Log::warning('Puskesmas PDF export failed - Puskesmas not found', [
                'correlation_id' => $correlationId,
                'user_id' => Auth::id(),
                'context' => $e->getContext()
            ]);

            return $e->render($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Puskesmas PDF export failed - Validation error', [
                'correlation_id' => $correlationId,
                'user_id' => Auth::id(),
                'validation_errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => 'Data yang dikirim tidak valid',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Puskesmas PDF export failed - Unexpected error', [
                'correlation_id' => $correlationId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'pdf_generation_failed',
                'message' => 'Gagal menggenerate PDF. Silakan coba lagi atau hubungi administrator.',
                'correlation_id' => $correlationId
            ], 500);
        }
    }

    /**
     * Export puskesmas quarterly statistics to PDF
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportPuskesmasQuarterlyPdf(Request $request)
    {
        try {
            // Validasi input
            $request->validate([
                'disease_type' => 'required|in:ht,dm',
                'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
                'quarter' => 'required|integer|min:1|max:4',
                'puskesmas_id' => 'nullable|integer|exists:puskesmas,id'
            ]);

            $diseaseType = $request->disease_type;
            $year = $request->year ?? date('Y');
            $quarter = $request->quarter;

            // Tentukan puskesmas ID
            $puskesmasId = null;
            if (Auth::user()->isAdmin()) {
                // Admin bisa pilih puskesmas
                $puskesmasId = $request->puskesmas_id;
                if (!$puskesmasId) {
                    return response()->json([
                        'error' => 'Admin harus memilih puskesmas untuk export PDF'
                    ], 400);
                }
            } else {
                // User puskesmas hanya bisa export data puskesmasnya sendiri
                $puskesmasId = Auth::user()->puskesmas_id;
            }

            // Validasi puskesmas exists
            $puskesmas = Puskesmas::find($puskesmasId);
            if (!$puskesmas) {
                return response()->json([
                    'error' => 'Puskesmas tidak ditemukan'
                ], 404);
            }

            // Generate quarterly PDF menggunakan PdfService
            return $this->pdfService->generatePuskesmasQuarterlyPdf($puskesmasId, $diseaseType, $year, $quarter);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validasi gagal',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Export puskesmas quarterly PDF failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Gagal mengexport PDF triwulanan: ' . $e->getMessage()
            ], 500);
        }
    }
}
