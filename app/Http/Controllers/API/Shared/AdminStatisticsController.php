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
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $p->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->calculationService->getDmStatisticsWithMonthlyBreakdown($p->id, $year, $month);

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
}
