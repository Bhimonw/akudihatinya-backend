<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\MonthlyStatisticsCache;
use Illuminate\Support\Facades\Log;

class AdminStatisticsController extends Controller
{
    protected $calculationService;

    public function __construct(
        \App\Services\StatisticsCalculationService $calculationService
    ) {
        $this->calculationService = $calculationService;
    }

    /**
     * Get admin statistics
     */
    public function index(Request $request)
    {
        try {
            $year = $request->year ?? Carbon::now()->year;
            $month = $request->month ?? null;
            $diseaseType = $request->type ?? 'all';
            $perPage = $request->per_page ?? 15;

            // Validasi tahun
            if (!is_numeric($year) || $year < 2000 || $year > 2100) {
                return response()->json([
                    'message' => 'Parameter year tidak valid. Gunakan tahun antara 2000-2100.',
                ], 400);
            }

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

            // Get puskesmas with pagination
            $puskesmasQuery = Puskesmas::query();
            $puskesmas = $puskesmasQuery->paginate($perPage);

            if ($puskesmas->isEmpty()) {
                return response()->json([
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

                    $htData = $this->calculationService->getHtStatisticsWithMonthlyBreakdown($p->id, $year, $month);

                    $htTargetCount = $htTarget ? $htTarget->target_count : 0;

                    // Get the last month's data for total statistics
                    $monthlyData = $htData['monthly_data'] ?? [];
                    $lastMonthStats = !empty($monthlyData) ? end($monthlyData) : null;

                    if ($lastMonthStats) {
                        $htTotalPatients = $lastMonthStats['total_patients'];
                        $htStandardPatients = $lastMonthStats['standard_patients'];
                        $htNonStandardPatients = max(0, $htTotalPatients - $htStandardPatients);
                        $htAchievementPercentage = $htTargetCount > 0
                            ? min(round(($htStandardPatients / $htTargetCount) * 100, 2), 100)
                            : 0;

                        $data['ht'] = [
                            'target' => $htTargetCount,
                            'total_patients' => $htTotalPatients,
                            'achievement_percentage' => $htAchievementPercentage,
                            'standard_patients' => $htStandardPatients,
                            'non_standard_patients' => $htNonStandardPatients,
                            'male_patients' => $lastMonthStats['male_patients'],
                            'female_patients' => $lastMonthStats['female_patients'],
                            'standard_male_patients' => $lastMonthStats['standard_male_patients'],
                            'standard_female_patients' => $lastMonthStats['standard_female_patients'],
                            'monthly_data' => $monthlyData,
                        ];
                    } else {
                        $data['ht'] = [
                            'target' => $htTargetCount,
                            'total_patients' => 0,
                            'achievement_percentage' => 0,
                            'standard_patients' => 0,
                            'non_standard_patients' => 0,
                            'male_patients' => 0,
                            'female_patients' => 0,
                            'standard_male_patients' => 0,
                            'standard_female_patients' => 0,
                            'monthly_data' => [],
                        ];
                    }
                }

                // Get DM data if requested
                if ($diseaseType === 'all' || $diseaseType === 'dm') {
                    $dmTarget = YearlyTarget::where('puskesmas_id', $p->id)
                        ->where('disease_type', 'dm')
                        ->where('year', $year)
                        ->first();

                    $dmData = $this->calculationService->getDmStatisticsWithMonthlyBreakdown($p->id, $year, $month);

                    $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

                    // Get the last month's data for total statistics
                    $monthlyData = $dmData['monthly_data'] ?? [];
                    $lastMonthStats = !empty($monthlyData) ? end($monthlyData) : null;

                    if ($lastMonthStats) {
                        $dmTotalPatients = $lastMonthStats['total_patients'];
                        $dmStandardPatients = $lastMonthStats['standard_patients'];
                        $dmNonStandardPatients = max(0, $dmTotalPatients - $dmStandardPatients);
                        $dmAchievementPercentage = $dmTargetCount > 0
                            ? min(round(($dmStandardPatients / $dmTargetCount) * 100, 2), 100)
                            : 0;

                        $data['dm'] = [
                            'target' => $dmTargetCount,
                            'total_patients' => $dmTotalPatients,
                            'achievement_percentage' => $dmAchievementPercentage,
                            'standard_patients' => $dmStandardPatients,
                            'non_standard_patients' => $dmNonStandardPatients,
                            'male_patients' => $lastMonthStats['male_patients'],
                            'female_patients' => $lastMonthStats['female_patients'],
                            'standard_male_patients' => $lastMonthStats['standard_male_patients'],
                            'standard_female_patients' => $lastMonthStats['standard_female_patients'],
                            'monthly_data' => $monthlyData,
                        ];
                    } else {
                        $data['dm'] = [
                            'target' => $dmTargetCount,
                            'total_patients' => 0,
                            'achievement_percentage' => 0,
                            'standard_patients' => 0,
                            'non_standard_patients' => 0,
                            'male_patients' => 0,
                            'female_patients' => 0,
                            'standard_male_patients' => 0,
                            'standard_female_patients' => 0,
                            'monthly_data' => [],
                        ];
                    }
                }

                $statistics[] = $data;
            }

            // Sort statistics based on achievement percentage
            if ($diseaseType === 'ht') {
                usort($statistics, function ($a, $b) {
                    return ($b['ht']['achievement_percentage'] ?? 0) <=> ($a['ht']['achievement_percentage'] ?? 0);
                });
            } elseif ($diseaseType === 'dm') {
                usort($statistics, function ($a, $b) {
                    return ($b['dm']['achievement_percentage'] ?? 0) <=> ($a['dm']['achievement_percentage'] ?? 0);
                });
            } else {
                // Sort by combined achievement percentage (HT + DM)
                usort($statistics, function ($a, $b) {
                    $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                    $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                    return $bTotal <=> $aTotal;
                });
            }

            // Add ranking to meta
            $rankings = [];
            foreach ($statistics as $index => $stat) {
                $rankings[$stat['puskesmas_id']] = $index + 1;
            }

            return response()->json([
                'data' => $statistics,
                'meta' => [
                    'current_page' => $puskesmas->currentPage(),
                    'from' => $puskesmas->firstItem(),
                    'last_page' => $puskesmas->lastPage(),
                    'per_page' => $puskesmas->perPage(),
                    'to' => $puskesmas->lastItem(),
                    'total' => $puskesmas->total(),
                    'rankings' => $rankings
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in admin statistics: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data statistik.',
            ], 500);
        }
    }

    /**
     * Dashboard statistics API untuk frontend
     */
    public function dashboardStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all';
        $user = Auth::user();

        // Validasi tahun
        if (!is_numeric($year) || $year < 2000 || $year > 2100) {
            return response()->json([
                'message' => 'Parameter year tidak valid. Gunakan tahun antara 2000-2100.',
            ], 400);
        }

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Get puskesmas
        $puskesmas = Puskesmas::first();

        if (!$puskesmas) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
            ], 404);
        }

        $data = [];

        // Get HT data if requested
        if ($type === 'all' || $type === 'ht') {
            $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->first();

            $htData = $this->calculationService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

            $htTargetCount = $htTarget ? $htTarget->target_count : 0;

            $data['ht'] = [
                'target' => $htTargetCount,
                'total_patients' => $htData['total_patients'] ?? 0,
                'achievement_percentage' => $htTargetCount > 0
                    ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
                    : 0,
                'standard_patients' => $htData['standard_patients'] ?? 0,
                'non_standard_patients' => $htData['non_standard_patients'] ?? 0,
                'male_patients' => $htData['male_patients'] ?? 0,
                'female_patients' => $htData['female_patients'] ?? 0,
                'monthly_data' => $htData['monthly_data'] ?? [],
            ];
        }

        // Get DM data if requested
        if ($type === 'all' || $type === 'dm') {
            $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->first();

            $dmData = $this->calculationService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

            $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

            $data['dm'] = [
                'target' => $dmTargetCount,
                'total_patients' => $dmData['total_patients'] ?? 0,
                'achievement_percentage' => $dmTargetCount > 0
                    ? round(($dmData['standard_patients'] / $dmTargetCount) * 100, 2)
                    : 0,
                'standard_patients' => $dmData['standard_patients'] ?? 0,
                'non_standard_patients' => $dmData['non_standard_patients'] ?? 0,
                'male_patients' => $dmData['male_patients'] ?? 0,
                'female_patients' => $dmData['female_patients'] ?? 0,
                'monthly_data' => $dmData['monthly_data'] ?? [],
            ];
        }

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }

    public function getStatistics(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $month = $request->input('month');
            $diseaseType = $request->input('disease_type', 'ht');
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Validate input
            if (!in_array($diseaseType, ['ht', 'dm'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid disease type'
                ], 400);
            }

            // Get all puskesmas with pagination
            $puskesmas = Puskesmas::paginate($perPage, ['*'], 'page', $page);

            $statistics = [];
            foreach ($puskesmas as $puskesmas) {
                $stats = $diseaseType === 'ht'
                    ? $this->calculationService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month)
                    : $this->calculationService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                $statistics[] = [
                    'puskesmas_id' => $puskesmas->id,
                    'puskesmas_name' => $puskesmas->name,
                    'target' => $stats['target'],
                    'total_patients' => $stats['total_patients'],
                    'achievement_percentage' => $stats['achievement_percentage'],
                    'standard_patients' => $stats['standard_patients'],
                    'non_standard_patients' => $stats['non_standard_patients'],
                    'male_patients' => $stats['male_patients'],
                    'female_patients' => $stats['female_patients'],
                    'standard_male_patients' => $stats['standard_male_patients'],
                    'standard_female_patients' => $stats['standard_female_patients'],
                    'monthly_data' => $stats['monthly_data']
                ];
            }

            // Sort statistics by achievement percentage
            usort($statistics, function ($a, $b) {
                return $b['achievement_percentage'] <=> $a['achievement_percentage'];
            });

            // Add ranking
            foreach ($statistics as $index => &$stat) {
                $stat['rank'] = $index + 1;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => $statistics,
                    'pagination' => [
                        'total' => $puskesmas->total(),
                        'per_page' => $puskesmas->perPage(),
                        'current_page' => $puskesmas->currentPage(),
                        'last_page' => $puskesmas->lastPage(),
                        'from' => $puskesmas->firstItem(),
                        'to' => $puskesmas->lastItem()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getStatistics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data statistik'
            ], 500);
        }
    }

    public function getMonthlyStatistics(Request $request)
    {
        try {
            $year = $request->input('year', date('Y'));
            $month = $request->input('month', date('m'));
            $diseaseType = $request->input('disease_type', 'ht');
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Validate input
            if (!in_array($diseaseType, ['ht', 'dm'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid disease type'
                ], 400);
            }

            // Get all puskesmas with pagination
            $puskesmas = Puskesmas::paginate($perPage, ['*'], 'page', $page);

            $statistics = [];
            foreach ($puskesmas as $puskesmas) {
                $stats = $diseaseType === 'ht'
                    ? $this->calculationService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month)
                    : $this->calculationService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Get the specific month's data
                $monthlyData = collect($stats['monthly_data'])->firstWhere('month', (int)$month);

                if ($monthlyData) {
                    $statistics[] = [
                        'puskesmas_id' => $puskesmas->id,
                        'puskesmas_name' => $puskesmas->name,
                        'month' => $monthlyData['month'],
                        'total_patients' => $monthlyData['total_patients'],
                        'standard_patients' => $monthlyData['standard_patients'],
                        'non_standard_patients' => $monthlyData['total_patients'] - $monthlyData['standard_patients'],
                        'male_patients' => $monthlyData['male_patients'],
                        'female_patients' => $monthlyData['female_patients'],
                        'standard_male_patients' => $monthlyData['standard_male_patients'],
                        'standard_female_patients' => $monthlyData['standard_female_patients']
                    ];
                }
            }

            // Sort statistics by standard patients
            usort($statistics, function ($a, $b) {
                return $b['standard_patients'] <=> $a['standard_patients'];
            });

            // Add ranking
            foreach ($statistics as $index => &$stat) {
                $stat['rank'] = $index + 1;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => $statistics,
                    'pagination' => [
                        'total' => $puskesmas->total(),
                        'per_page' => $puskesmas->perPage(),
                        'current_page' => $puskesmas->currentPage(),
                        'last_page' => $puskesmas->lastPage(),
                        'from' => $puskesmas->firstItem(),
                        'to' => $puskesmas->lastItem()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMonthlyStatistics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data statistik bulanan'
            ], 500);
        }
    }
}
