<?php

namespace App\Services;

use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\MonthlyStatisticsCache;
use Carbon\Carbon;
use App\Repositories\YearlyTargetRepository;

class HtStatisticsService
{
    protected $yearlyTargetRepository;

    public function __construct(YearlyTargetRepository $yearlyTargetRepository)
    {
        $this->yearlyTargetRepository = $yearlyTargetRepository;
    }

    public function getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, 'ht', $year);
        $yearlyTarget = $target ? $target->target_count : 0;
        if ($month !== null) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            $patients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereHas('htExaminations', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('examination_date', [$startDate, $endDate]);
                })
                ->get();
            $totalPatients = $patients->count();
            $malePatients = $patients->where('gender', 'male')->count();
            $femalePatients = $patients->where('gender', 'female')->count();
            $standardPatients = 0;
            foreach ($patients as $patient) {
                $firstExamMonth = HtExamination::where('patient_id', $patient->id)
                    ->where('year', $year)
                    ->min('month');
                if ($firstExamMonth === null) continue;
                $isStandard = true;
                for ($m = $firstExamMonth; $m <= $month; $m++) {
                    $hasExam = HtExamination::where('patient_id', $patient->id)
                        ->where('year', $year)
                        ->where('month', $m)
                        ->exists();
                    if (!$hasExam) {
                        $isStandard = false;
                        break;
                    }
                }
                if ($isStandard) {
                    $standardPatients++;
                }
            }
            $nonStandardPatients = $totalPatients - $standardPatients;
            $monthlyPercentage = $yearlyTarget > 0 ? round(($standardPatients / $yearlyTarget) * 100, 2) : 0;
            return [
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatients,
                'non_standard_patients' => $nonStandardPatients,
                'male_patients' => $malePatients,
                'female_patients' => $femalePatients,
                'achievement_percentage' => $monthlyPercentage,
                'standard_percentage' => $totalPatients > 0 ? round(($standardPatients / $totalPatients) * 100, 2) : 0,
                'monthly_data' => [
                    $month => [
                        'male' => $malePatients,
                        'female' => $femalePatients,
                        'total' => $totalPatients,
                        'standard' => $standardPatients,
                        'non_standard' => $nonStandardPatients,
                        'percentage' => $monthlyPercentage,
                    ]
                ],
            ];
        }
        $yearlyData = [];
        $totalUniquePatients = 0;
        $totalStandard = 0;
        $totalNonStandard = 0;
        $totalMale = 0;
        $totalFemale = 0;
        $allPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('htExaminations', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->with(['htExaminations' => function ($query) use ($year) {
                $query->where('year', $year)->orderBy('month');
            }])
            ->get();
        $totalUniquePatients = $allPatients->count();
        $totalMale = $allPatients->where('gender', 'male')->count();
        $totalFemale = $allPatients->where('gender', 'female')->count();
        for ($m = 1; $m <= 12; $m++) {
            $monthlyPatients = $allPatients->filter(function ($patient) use ($m) {
                return $patient->htExaminations->where('month', $m)->count() > 0;
            });
            $monthlyTotal = $monthlyPatients->count();
            $monthlyMale = $monthlyPatients->where('gender', 'male')->count();
            $monthlyFemale = $monthlyPatients->where('gender', 'female')->count();
            $monthlyStandard = 0;
            foreach ($monthlyPatients as $patient) {
                $firstExamMonth = $patient->htExaminations->min('month');
                if ($firstExamMonth === null) continue;
                $isStandard = true;
                for ($checkM = $firstExamMonth; $checkM <= $m; $checkM++) {
                    $hasExam = $patient->htExaminations->where('month', $checkM)->count() > 0;
                    if (!$hasExam) {
                        $isStandard = false;
                        break;
                    }
                }
                if ($isStandard) {
                    $monthlyStandard++;
                }
            }
            $monthlyNonStandard = $monthlyTotal - $monthlyStandard;
            $monthlyPercentage = $yearlyTarget > 0 ? round(($monthlyStandard / $yearlyTarget) * 100, 2) : 0;
            $yearlyData[$m] = [
                'male' => $monthlyMale,
                'female' => $monthlyFemale,
                'total' => $monthlyTotal,
                'standard' => $monthlyStandard,
                'non_standard' => $monthlyNonStandard,
                'percentage' => $monthlyPercentage,
            ];
        }
        $standardPatients = 0;
        foreach ($allPatients as $patient) {
            $firstExamMonth = $patient->htExaminations->min('month');
            if ($firstExamMonth === null) continue;
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $patient->htExaminations->where('month', $m)->count() > 0;
                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }
            if ($isStandard) {
                $standardPatients++;
            }
        }
        $nonStandardPatients = $totalUniquePatients - $standardPatients;
        $yearlyPercentage = $yearlyTarget > 0 ? round(($standardPatients / $yearlyTarget) * 100, 2) : 0;
        return [
            'total_patients' => $totalUniquePatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $totalMale,
            'female_patients' => $totalFemale,
            'achievement_percentage' => $yearlyPercentage,
            'standard_percentage' => $totalUniquePatients > 0 ? round(($standardPatients / $totalUniquePatients) * 100, 2) : 0,
            'monthly_data' => $yearlyData,
        ];
    }

    public function getHtStatistics($puskesmasId, $year, $month = null)
    {
        // ... salin isi dari StatisticsService::getHtStatistics ...
    }

    public function processHtCachedStats($statsList, $target = null)
    {
        $totalPatients = $statsList->sum('total_count');
        $standardPatients = $statsList->sum('standard_count');
        $nonStandardPatients = $statsList->sum('non_standard_count');
        $malePatients = $statsList->sum('male_count');
        $femalePatients = $statsList->sum('female_count');
        $targetCount = $target ? $target->target_count : 0;
        $achievement = $targetCount > 0 ? round(($standardPatients / $targetCount) * 100, 2) : 0;
        $monthlyData = [];
        foreach ($statsList as $stat) {
            $monthlyData[$stat->month] = [
                'target' => (string)$targetCount,
                'male' => (string)$stat->male_count,
                'female' => (string)$stat->female_count,
                'total' => (string)$stat->total_count,
                'standard' => (string)$stat->standard_count,
                'non_standard' => (string)$stat->non_standard_count,
                'percentage' => $targetCount > 0 ? round(($stat->standard_count / $targetCount) * 100, 2) : 0,
            ];
        }
        return [
            'target' => $targetCount,
            'total_patients' => $totalPatients,
            'achievement_percentage' => $achievement,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }

    public function getHtStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year);

        if ($month !== null) {
            $query->where('month', $month);
        }

        $monthlyData = $query->get()->keyBy('month');

        $totalPatients = $monthlyData->sum('total_count');
        $standardPatients = $monthlyData->sum('standard_count');
        $nonStandardPatients = $monthlyData->sum('non_standard_count');
        $malePatients = $monthlyData->sum('male_count');
        $femalePatients = $monthlyData->sum('female_count');

        $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, 'ht', $year);
        $yearlyTarget = $target ? (int)$target->target_count : 0;
        // Build monthly breakdown
        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $data = $monthlyData->get($m);
            $standard = $data ? (int)$data->standard_count : 0;
            $monthlyBreakdown[$m] = [
                'male' => $data ? $data->male_count : 0,
                'female' => $data ? $data->female_count : 0,
                'total' => $data ? $data->total_count : 0,
                'standard' => $standard,
                'non_standard' => $data ? $data->non_standard_count : 0,
                'percentage' => $yearlyTarget > 0 ? round(($standard / $yearlyTarget) * 100, 2) : 0
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyBreakdown,
        ];
    }
}
