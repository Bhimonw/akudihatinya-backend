<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Get all puskesmas IDs
        $puskesmasIds = Puskesmas::pluck('id')->toArray();

        // Calculate summary statistics
        $summaryStats = $this->calculateSummaryStatistics($puskesmasIds, $year, $month, $diseaseType);

        // Get top and bottom performing puskesmas
        $performanceData = $this->getTopAndBottomPuskesmas($year, $diseaseType);

        // Get monthly aggregated stats
        $monthlyStats = $this->getMonthlyAggregatedStats($diseaseType, $puskesmasIds, $year, $summaryStats['total_target']);

        // Prepare chart data
        $chartData = $this->prepareChartData($diseaseType, $monthlyStats['ht'] ?? [], $monthlyStats['dm'] ?? []);

        return response()->json([
            'data' => [
                'summary' => $summaryStats,
                'performance' => $performanceData,
                'monthly' => $monthlyStats,
                'charts' => $chartData,
            ],
        ]);
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummaryStatistics($puskesmasIds, $year, $month, $diseaseType)
    {
        $stats = [
            'total_target' => 0,
            'total_achievement' => 0,
            'total_percentage' => 0,
            'total_puskesmas' => count($puskesmasIds),
            'achieving_puskesmas' => 0,
            'not_achieving_puskesmas' => 0,
        ];

        // Get total target
        $targetQuery = YearlyTarget::whereIn('puskesmas_id', $puskesmasIds)
            ->where('year', $year);

        if ($diseaseType !== 'all') {
            $targetQuery->where('disease_type', $diseaseType);
        }

        $totalTarget = $targetQuery->sum('target_count');
        $stats['total_target'] = $totalTarget;

        // Get achievement data
        $achievementData = DB::table('monthly_statistics_cache')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->where('year', $year);

        if ($month) {
            $achievementData->where('month', $month);
        }

        if ($diseaseType !== 'all') {
            $achievementData->where('disease_type', $diseaseType);
        }

        $achievementData = $achievementData->get();

        // Calculate total achievement
        $totalAchievement = 0;
        $achievingCount = 0;

        foreach ($achievementData as $data) {
            $totalAchievement += $data->standard_patients;
            if ($data->achievement_percentage >= 100) {
                $achievingCount++;
            }
        }

        $stats['total_achievement'] = $totalAchievement;
        $stats['total_percentage'] = $totalTarget > 0 ? round(($totalAchievement / $totalTarget) * 100, 2) : 0;
        $stats['achieving_puskesmas'] = $achievingCount;
        $stats['not_achieving_puskesmas'] = count($puskesmasIds) - $achievingCount;

        return $stats;
    }

    /**
     * Get top and bottom performing puskesmas
     */
    private function getTopAndBottomPuskesmas($year, $diseaseType)
    {
        $query = DB::table('monthly_statistics_cache')
            ->join('puskesmas', 'monthly_statistics_cache.puskesmas_id', '=', 'puskesmas.id')
            ->select(
                'puskesmas.id',
                'puskesmas.name',
                DB::raw('AVG(achievement_percentage) as avg_achievement')
            )
            ->where('year', $year);

        if ($diseaseType !== 'all') {
            $query->where('disease_type', $diseaseType);
        }

        $query->groupBy('puskesmas.id', 'puskesmas.name')
            ->orderBy('avg_achievement', 'desc');

        $allPuskesmas = $query->get();

        return [
            'top' => $allPuskesmas->take(5)->values(),
            'bottom' => $allPuskesmas->reverse()->take(5)->values(),
        ];
    }

    /**
     * Get monthly aggregated stats
     */
    private function getMonthlyAggregatedStats($diseaseType, $puskesmasIds, $year, $targetTotal)
    {
        $stats = [];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htStats = DB::table('monthly_statistics_cache')
                ->whereIn('puskesmas_id', $puskesmasIds)
                ->where('year', $year)
                ->where('disease_type', 'ht')
                ->select(
                    'month',
                    DB::raw('SUM(standard_patients) as total_patients'),
                    DB::raw('AVG(achievement_percentage) as avg_achievement')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $stats['ht'] = $htStats->map(function ($item) use ($targetTotal) {
                return [
                    'month' => $item->month,
                    'total_patients' => $item->total_patients,
                    'achievement_percentage' => $item->avg_achievement,
                    'target' => $targetTotal / 12, // Monthly target
                ];
            })->values();
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmStats = DB::table('monthly_statistics_cache')
                ->whereIn('puskesmas_id', $puskesmasIds)
                ->where('year', $year)
                ->where('disease_type', 'dm')
                ->select(
                    'month',
                    DB::raw('SUM(standard_patients) as total_patients'),
                    DB::raw('AVG(achievement_percentage) as avg_achievement')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $stats['dm'] = $dmStats->map(function ($item) use ($targetTotal) {
                return [
                    'month' => $item->month,
                    'total_patients' => $item->total_patients,
                    'achievement_percentage' => $item->avg_achievement,
                    'target' => $targetTotal / 12, // Monthly target
                ];
            })->values();
        }

        return $stats;
    }

    /**
     * Prepare chart data
     */
    private function prepareChartData($diseaseType, $htMonthlyData, $dmMonthlyData)
    {
        $chartData = [
            'labels' => [],
            'datasets' => [],
        ];

        // Prepare labels (months)
        for ($i = 1; $i <= 12; $i++) {
            $chartData['labels'][] = Carbon::create()->month($i)->format('M');
        }

        // Prepare datasets
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $chartData['datasets'][] = [
                'label' => 'HT Achievement',
                'data' => $htMonthlyData->pluck('achievement_percentage')->toArray(),
                'borderColor' => '#4CAF50',
                'backgroundColor' => 'rgba(76, 175, 80, 0.1)',
            ];
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $chartData['datasets'][] = [
                'label' => 'DM Achievement',
                'data' => $dmMonthlyData->pluck('achievement_percentage')->toArray(),
                'borderColor' => '#2196F3',
                'backgroundColor' => 'rgba(33, 150, 243, 0.1)',
            ];
        }

        return $chartData;
    }
}
