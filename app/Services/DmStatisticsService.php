<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\Patient;
use App\Models\MonthlyStatisticsCache;
use Carbon\Carbon;
use App\Repositories\YearlyTargetRepository;
use Illuminate\Support\Facades\DB;

class DmStatisticsService
{
    protected $yearlyTargetRepository;

    public function __construct(YearlyTargetRepository $yearlyTargetRepository)
    {
        $this->yearlyTargetRepository = $yearlyTargetRepository;
    }

    public function getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, 'dm', $year);
        $target = $target ? $target->target_count : 0;
        $monthly_data = [];
        $allPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('dmExaminations', function ($query) use ($year) {
                $query->whereYear('examination_date', $year);
            })
            ->with(['dmExaminations' => function ($query) use ($year) {
                $query->whereYear('examination_date', $year)->orderBy(DB::raw('MONTH(examination_date)'));
            }])
            ->get();
        $total_patients = $allPatients->count();
        $male_patients = $allPatients->where('gender', 'male')->count();
        $female_patients = $allPatients->where('gender', 'female')->count();
        for ($m = 1; $m <= 12; $m++) {
            $monthlyPatients = $allPatients->filter(function ($patient) use ($m) {
                return $patient->dmExaminations->filter(function ($exam) use ($m) {
                    return Carbon::parse($exam->examination_date)->month == $m;
                })->count() > 0;
            });
            $monthly_total = $monthlyPatients->count();
            $monthly_male = $monthlyPatients->where('gender', 'male')->count();
            $monthly_female = $monthlyPatients->where('gender', 'female')->count();
            $monthly_standard = 0;
            foreach ($monthlyPatients as $patient) {
                $firstExamMonth = $patient->dmExaminations->min(function ($exam) {
                    return Carbon::parse($exam->examination_date)->month;
                });
                if ($firstExamMonth === null) continue;
                if ($m == $firstExamMonth) {
                    $monthly_standard++;
                } else {
                    $isStandard = true;
                    for ($checkM = $firstExamMonth; $checkM <= $m; $checkM++) {
                        $hasExam = $patient->dmExaminations->filter(function ($exam) use ($checkM) {
                            return Carbon::parse($exam->examination_date)->month == $checkM;
                        })->count() > 0;
                        if (!$hasExam) {
                            $isStandard = false;
                            break;
                        }
                    }
                    if ($isStandard) {
                        $monthly_standard++;
                    }
                }
            }
            $monthly_non_standard = $monthly_total - $monthly_standard;
            $monthly_percentage = $target > 0 ? round(($monthly_standard / $target) * 100, 2) : 0;
            $monthly_data[$m] = [
                'target' => (string)$target,
                'male' => (string)$monthly_male,
                'female' => (string)$monthly_female,
                'total' => (string)$monthly_total,
                'standard' => (string)$monthly_standard,
                'non_standard' => (string)$monthly_non_standard,
                'percentage' => $monthly_percentage,
            ];
        }
        $standard_patients = 0;
        foreach ($allPatients as $patient) {
            $firstExamMonth = $patient->dmExaminations->min(function ($exam) {
                return Carbon::parse($exam->examination_date)->month;
            });
            if ($firstExamMonth === null) continue;
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $patient->dmExaminations->filter(function ($exam) use ($m) {
                    return Carbon::parse($exam->examination_date)->month == $m;
                })->count() > 0;
                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }
            if ($isStandard) {
                $standard_patients++;
            }
        }
        $non_standard_patients = $total_patients - $standard_patients;
        $achievement_percentage = $target > 0 ? round(($standard_patients / $target) * 100, 2) : 0;
        $standard_percentage = $total_patients > 0 ? round(($standard_patients / $total_patients) * 100, 2) : 0;
        return [
            'target' => (string)$target,
            'total_patients' => (string)$total_patients,
            'standard_patients' => (string)$standard_patients,
            'non_standard_patients' => (string)$non_standard_patients,
            'male_patients' => (string)$male_patients,
            'female_patients' => (string)$female_patients,
            'achievement_percentage' => $achievement_percentage,
            'standard_percentage' => $standard_percentage,
            'monthly_data' => $monthly_data,
        ];
    }

    public function getDmStatistics($puskesmasId, $year, $month = null)
    {
        return $this->getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month);
    }

    public function processDmCachedStats($statsList, $target = null)
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

    public function getDmStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, 'dm', $year);
        $target = $target ? $target->target_count : 0;
        $monthlyData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->get()
            ->keyBy('month');
        $total_patients = $monthlyData->sum('total_count');
        $standard_patients = $monthlyData->sum('standard_count');
        $non_standard_patients = $monthlyData->sum('non_standard_count');
        $male_patients = $monthlyData->sum('male_count');
        $female_patients = $monthlyData->sum('female_count');
        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $data = $monthlyData->get($m);
            $standard = $data ? (int)$data->standard_count : 0;
            $percentage = $target > 0 ? round($standard / $target * 100, 2) : 0;
            $monthlyBreakdown[$m] = [
                'target' => (string)$target,
                'male' => (string)($data ? $data->male_count : 0),
                'female' => (string)($data ? $data->female_count : 0),
                'total' => (string)($data ? $data->total_count : 0),
                'standard' => (string)$standard,
                'non_standard' => (string)($data ? $data->non_standard_count : 0),
                'percentage' => $percentage
            ];
        }
        return [
            'target' => (string)$target,
            'total_patients' => (string)$total_patients,
            'standard_patients' => (string)$standard_patients,
            'non_standard_patients' => (string)$non_standard_patients,
            'male_patients' => (string)$male_patients,
            'female_patients' => (string)$female_patients,
            'monthly_data' => $monthlyBreakdown,
        ];
    }
}
