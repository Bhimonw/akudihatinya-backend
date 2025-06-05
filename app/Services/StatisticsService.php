<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Repositories\PuskesmasRepository;
use App\Repositories\YearlyTargetRepository;
use App\Services\HtStatisticsService;
use App\Services\DmStatisticsService;

class StatisticsService
{
    protected $puskesmasRepository;
    protected $yearlyTargetRepository;
    protected $htStatisticsService;
    protected $dmStatisticsService;

    public function __construct(PuskesmasRepository $puskesmasRepository, YearlyTargetRepository $yearlyTargetRepository, HtStatisticsService $htStatisticsService, DmStatisticsService $dmStatisticsService)
    {
        $this->puskesmasRepository = $puskesmasRepository;
        $this->yearlyTargetRepository = $yearlyTargetRepository;
        $this->htStatisticsService = $htStatisticsService;
        $this->dmStatisticsService = $dmStatisticsService;
    }

    public function getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        return $this->htStatisticsService->getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month);
    }

    public function getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        return $this->dmStatisticsService->getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month);
    }

    public function getHtStatistics($puskesmasId, $year, $month = null)
    {
        return $this->htStatisticsService->getHtStatistics($puskesmasId, $year, $month);
    }

    public function getDmStatistics($puskesmasId, $year, $month = null)
    {
        return $this->dmStatisticsService->getDmStatistics($puskesmasId, $year, $month);
    }

    public function processHtCachedStats($statsList, $target = null)
    {
        return $this->htStatisticsService->processHtCachedStats($statsList, $target);
    }

    public function processDmCachedStats($statsList, $target = null)
    {
        return $this->dmStatisticsService->processDmCachedStats($statsList, $target);
    }

    public function getHtStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        return $this->htStatisticsService->getHtStatisticsFromCache($puskesmasId, $year, $month);
    }

    public function getDmStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        return $this->dmStatisticsService->getDmStatisticsFromCache($puskesmasId, $year, $month);
    }

    public function calculateSummaryStatistics($puskesmasIds, $year, $month, $diseaseType)
    {
        $summary = [];
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            // First try to get month 12 data
            $htStats = DB::table('monthly_statistics_cache')
                ->select(
                    DB::raw('SUM(total_count) as total_patients'),
                    DB::raw('SUM(standard_count) as standard_patients'),
                    DB::raw('SUM(non_standard_count) as non_standard_patients'),
                    DB::raw('SUM(male_count) as male_patients'),
                    DB::raw('SUM(female_count) as female_patients')
                )
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->where('month', 12)
                ->whereIn('puskesmas_id', $puskesmasIds)
                ->first();

            // If month 12 data is not found or has no patients, get the latest month's data
            if (!$htStats || ($htStats->total_patients ?? 0) == 0) {
                $latestMonth = DB::table('monthly_statistics_cache')
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->whereIn('puskesmas_id', $puskesmasIds)
                    ->where('total_count', '>', 0)
                    ->max('month');

                if ($latestMonth) {
                    $htStats = DB::table('monthly_statistics_cache')
                        ->select(
                            DB::raw('SUM(total_count) as total_patients'),
                            DB::raw('SUM(standard_count) as standard_patients'),
                            DB::raw('SUM(non_standard_count) as non_standard_patients'),
                            DB::raw('SUM(male_count) as male_patients'),
                            DB::raw('SUM(female_count) as female_patients')
                        )
                        ->where('disease_type', 'ht')
                        ->where('year', $year)
                        ->where('month', $latestMonth)
                        ->whereIn('puskesmas_id', $puskesmasIds)
                        ->first();
                }
            }

            $htTargetTotal = $this->yearlyTargetRepository->getTotalTargetCount($puskesmasIds, 'ht', $year);
            $htMonthlyData = $this->getMonthlyAggregatedStats('ht', $puskesmasIds, $year, $htTargetTotal);

            $summary['ht'] = [
                'target' => $htTargetTotal,
                'total_patients' => $htStats->total_patients ?? 0,
                'standard_patients' => $htStats->standard_patients ?? 0,
                'non_standard_patients' => $htStats->non_standard_patients ?? 0,
                'male_patients' => $htStats->male_patients ?? 0,
                'female_patients' => $htStats->female_patients ?? 0,
                'achievement_percentage' => $htTargetTotal > 0
                    ? round((($htStats->standard_patients ?? 0) / $htTargetTotal) * 100, 2)
                    : 0,
                'monthly_data' => $htMonthlyData,
            ];
        }
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // First try to get month 12 data
            $dmStats = DB::table('monthly_statistics_cache')
                ->select(
                    DB::raw('SUM(total_count) as total_patients'),
                    DB::raw('SUM(standard_count) as standard_patients'),
                    DB::raw('SUM(non_standard_count) as non_standard_patients'),
                    DB::raw('SUM(male_count) as male_patients'),
                    DB::raw('SUM(female_count) as female_patients')
                )
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->where('month', 12)
                ->whereIn('puskesmas_id', $puskesmasIds)
                ->first();

            // If month 12 data is not found or has no patients, get the latest month's data
            if (!$dmStats || ($dmStats->total_patients ?? 0) == 0) {
                $latestMonth = DB::table('monthly_statistics_cache')
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->whereIn('puskesmas_id', $puskesmasIds)
                    ->where('total_count', '>', 0)
                    ->max('month');

                if ($latestMonth) {
                    $dmStats = DB::table('monthly_statistics_cache')
                        ->select(
                            DB::raw('SUM(total_count) as total_patients'),
                            DB::raw('SUM(standard_count) as standard_patients'),
                            DB::raw('SUM(non_standard_count) as non_standard_patients'),
                            DB::raw('SUM(male_count) as male_patients'),
                            DB::raw('SUM(female_count) as female_patients')
                        )
                        ->where('disease_type', 'dm')
                        ->where('year', $year)
                        ->where('month', $latestMonth)
                        ->whereIn('puskesmas_id', $puskesmasIds)
                        ->first();
                }
            }

            $dmTargetTotal = $this->yearlyTargetRepository->getTotalTargetCount($puskesmasIds, 'dm', $year);
            $dmMonthlyData = $this->getMonthlyAggregatedStats('dm', $puskesmasIds, $year, $dmTargetTotal);

            $summary['dm'] = [
                'target' => $dmTargetTotal,
                'total_patients' => $dmStats->total_patients ?? 0,
                'standard_patients' => $dmStats->standard_patients ?? 0,
                'non_standard_patients' => $dmStats->non_standard_patients ?? 0,
                'male_patients' => $dmStats->male_patients ?? 0,
                'female_patients' => $dmStats->female_patients ?? 0,
                'achievement_percentage' => $dmTargetTotal > 0
                    ? round((($dmStats->standard_patients ?? 0) / $dmTargetTotal) * 100, 2)
                    : 0,
                'monthly_data' => $dmMonthlyData,
            ];
        }
        return $summary;
    }

    public function getMonthlyAggregatedStats($diseaseType, $puskesmasIds, $year, $targetTotal)
    {
        $monthlyData = [];
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
        $monthlySummary = DB::table('monthly_statistics_cache')
            ->select(
                'month',
                DB::raw('SUM(total_count) as total_count'),
                DB::raw('SUM(standard_count) as standard_count'),
                DB::raw('SUM(non_standard_count) as non_standard_count'),
                DB::raw('SUM(male_count) as male_count'),
                DB::raw('SUM(female_count) as female_count')
            )
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->groupBy('month')
            ->get();
        foreach ($monthlySummary as $summary) {
            $month = $summary->month;
            $monthlyData[$month] = [
                'male' => $summary->male_count,
                'female' => $summary->female_count,
                'total' => $summary->total_count,
                'standard' => $summary->standard_count,
                'non_standard' => $summary->non_standard_count,
                'percentage' => $targetTotal > 0
                    ? round(($summary->standard_count / $targetTotal) * 100, 2)
                    : 0
            ];
        }
        return $monthlyData;
    }

    public function getTopAndBottomPuskesmas($year, $diseaseType)
    {
        $allPuskesmas = \App\Models\Puskesmas::select('id', 'name')->get()->keyBy('id');
        $puskesmasIds = $allPuskesmas->pluck('id')->toArray();
        $rankings = [];
        $htTargets = $this->yearlyTargetRepository->getByYearAndTypeAndIds($year, 'ht', $puskesmasIds)->keyBy('puskesmas_id');
        $dmTargets = $this->yearlyTargetRepository->getByYearAndTypeAndIds($year, 'dm', $puskesmasIds)->keyBy('puskesmas_id');
        $htStats = DB::table('monthly_statistics_cache')
            ->select(
                'puskesmas_id',
                DB::raw('SUM(total_count) as total_patients'),
                DB::raw('SUM(standard_count) as standard_patients'),
                DB::raw('SUM(male_count) as male_patients'),
                DB::raw('SUM(female_count) as female_patients')
            )
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->groupBy('puskesmas_id')
            ->get()
            ->keyBy('puskesmas_id');
        $dmStats = DB::table('monthly_statistics_cache')
            ->select(
                'puskesmas_id',
                DB::raw('SUM(total_count) as total_patients'),
                DB::raw('SUM(standard_count) as standard_patients'),
                DB::raw('SUM(male_count) as male_patients'),
                DB::raw('SUM(female_count) as female_patients')
            )
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->groupBy('puskesmas_id')
            ->get()
            ->keyBy('puskesmas_id');
        foreach ($allPuskesmas as $id => $puskesmas) {
            $htTarget = $htTargets->get($id);
            $dmTarget = $dmTargets->get($id);
            $htStat = $htStats->get($id);
            $dmStat = $dmStats->get($id);
            $htTargetCount = $htTarget ? $htTarget->target_count : 0;
            $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;
            $htStandardPatients = $htStat ? $htStat->standard_patients : 0;
            $dmStandardPatients = $dmStat ? $dmStat->standard_patients : 0;
            $htAchievement = $htTargetCount > 0 ? round(($htStandardPatients / $htTargetCount) * 100, 2) : 0;
            $dmAchievement = $dmTargetCount > 0 ? round(($dmStandardPatients / $dmTargetCount) * 100, 2) : 0;
            $ranking = [
                'puskesmas_id' => $id,
                'puskesmas_name' => $puskesmas->name,
            ];
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $ranking['ht'] = [
                    'target' => $htTargetCount,
                    'total_patients' => $htStat ? $htStat->total_patients : 0,
                    'achievement_percentage' => $htAchievement,
                    'standard_patients' => $htStandardPatients,
                ];
            }
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $ranking['dm'] = [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmStat ? $dmStat->total_patients : 0,
                    'achievement_percentage' => $dmAchievement,
                    'standard_patients' => $dmStandardPatients,
                ];
            }
            if ($diseaseType === 'all') {
                $ranking['combined_achievement'] = $htAchievement + $dmAchievement;
            } elseif ($diseaseType === 'ht') {
                $ranking['combined_achievement'] = $htAchievement;
            } else {
                $ranking['combined_achievement'] = $dmAchievement;
            }
            $rankings[] = $ranking;
        }
        usort($rankings, function ($a, $b) {
            return $b['combined_achievement'] <=> $a['combined_achievement'];
        });
        foreach ($rankings as $index => $rank) {
            $rankings[$index]['ranking'] = $index + 1;
        }
        return [
            'top_puskesmas' => array_slice($rankings, 0, 5),
            'bottom_puskesmas' => count($rankings) > 5 ? array_slice($rankings, -5) : []
        ];
    }

    public function prepareChartData($diseaseType, $htMonthlyData, $dmMonthlyData)
    {
        $shortMonths = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'Mei',
            'Jun',
            'Jul',
            'Ags',
            'Sep',
            'Okt',
            'Nov',
            'Des'
        ];
        $chartData = [
            'labels' => $shortMonths,
            'datasets' => []
        ];
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = [];
            for ($m = 1; $m <= 12; $m++) {
                $htData[] = $htMonthlyData[$m]['total'] ?? 0;
            }
            $chartData['datasets'][] = [
                'label' => 'Hipertensi (HT)',
                'data' => $htData,
                'borderColor' => '#3490dc',
                'backgroundColor' => 'rgba(52, 144, 220, 0.1)',
                'borderWidth' => 2,
                'tension' => 0.4
            ];
        }
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = [];
            for ($m = 1; $m <= 12; $m++) {
                $dmData[] = $dmMonthlyData[$m]['total'] ?? 0;
            }
            $chartData['datasets'][] = [
                'label' => 'Diabetes Mellitus (DM)',
                'data' => $dmData,
                'borderColor' => '#f6993f',
                'backgroundColor' => 'rgba(246, 153, 63, 0.1)',
                'borderWidth' => 2,
                'tension' => 0.4
            ];
        }
        return $chartData;
    }
}
