<?php

namespace App\Services;

use App\Models\MonthlyStatisticsCache;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StatisticsCalculationService
{
    const CACHE_TTL = 3600; // 1 hour cache

    /**
     * Get HT statistics with monthly breakdown for a specific puskesmas
     */
    public function getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        try {
            $cacheKey = "ht_stats_{$puskesmasId}_{$year}" . ($month ? "_{$month}" : "");

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($puskesmasId, $year, $month) {
                // Get target for the year
                $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                // Get all examinations for the year
                $query = HtExamination::where('puskesmas_id', $puskesmasId)
                    ->whereYear('examination_date', $year);

                if ($month) {
                    $query->whereMonth('examination_date', $month);
                }

                // Get all examinations for the year
                $examinations = $query->get();

                // Get unique patients
                $uniquePatients = $examinations->pluck('patient_id')->unique()->toArray();

                // Get standard patients (those who attended all months after their first visit)
                $standardPatients = $this->getStandardPatients($examinations, $year);

                // Get monthly statistics
                $monthlyData = $this->getMonthlyStatistics($query, $standardPatients);

                // Calculate gender statistics
                $genderStats = $this->getGenderStatistics($examinations, $standardPatients);

                // Calculate overall statistics
                $totalPatients = count($uniquePatients);
                $standardPatientsCount = count($standardPatients);
                $nonStandardPatientsCount = $totalPatients - $standardPatientsCount;
                $achievementPercentage = $target && $target->target_count > 0
                    ? min(round(($standardPatientsCount / $target->target_count) * 100, 2), 100)
                    : 0;

                $stats = [
                    'target' => $target ? $target->target_count : 0,
                    'total_patients' => $totalPatients,
                    'standard_patients' => $standardPatientsCount,
                    'non_standard_patients' => max(0, $nonStandardPatientsCount),
                    'achievement_percentage' => $achievementPercentage,
                    'male_patients' => $genderStats['male_patients'],
                    'female_patients' => $genderStats['female_patients'],
                    'standard_male_patients' => $genderStats['standard_male_patients'],
                    'standard_female_patients' => $genderStats['standard_female_patients'],
                    'monthly_data' => $monthlyData
                ];

                return $stats;
            });
        } catch (\Exception $e) {
            Log::error('Error calculating HT statistics: ' . $e->getMessage());
            throw new \Exception('Terjadi kesalahan saat menghitung statistik HT');
        }
    }

    /**
     * Get DM statistics with monthly breakdown for a specific puskesmas
     */
    public function getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        try {
            $cacheKey = "dm_stats_{$puskesmasId}_{$year}" . ($month ? "_{$month}" : "");

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($puskesmasId, $year, $month) {
                // Get target for the year
                $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                // Get all examinations for the year
                $query = DmExamination::where('puskesmas_id', $puskesmasId)
                    ->whereYear('examination_date', $year);

                if ($month) {
                    $query->whereMonth('examination_date', $month);
                }

                // Get all examinations for the year
                $examinations = $query->get();

                // Get unique patients
                $uniquePatients = $examinations->pluck('patient_id')->unique()->toArray();

                // Get standard patients (those who attended all months after their first visit)
                $standardPatients = $this->getStandardPatients($examinations, $year);

                // Get monthly statistics
                $monthlyData = $this->getMonthlyStatistics($query, $standardPatients);

                // Calculate gender statistics
                $genderStats = $this->getGenderStatistics($examinations, $standardPatients);

                // Calculate overall statistics
                $totalPatients = count($uniquePatients);
                $standardPatientsCount = count($standardPatients);
                $nonStandardPatientsCount = $totalPatients - $standardPatientsCount;
                $achievementPercentage = $target && $target->target_count > 0
                    ? min(round(($standardPatientsCount / $target->target_count) * 100, 2), 100)
                    : 0;

                $stats = [
                    'target' => $target ? $target->target_count : 0,
                    'total_patients' => $totalPatients,
                    'standard_patients' => $standardPatientsCount,
                    'non_standard_patients' => max(0, $nonStandardPatientsCount),
                    'achievement_percentage' => $achievementPercentage,
                    'male_patients' => $genderStats['male_patients'],
                    'female_patients' => $genderStats['female_patients'],
                    'standard_male_patients' => $genderStats['standard_male_patients'],
                    'standard_female_patients' => $genderStats['standard_female_patients'],
                    'monthly_data' => $monthlyData
                ];

                return $stats;
            });
        } catch (\Exception $e) {
            Log::error('Error calculating DM statistics: ' . $e->getMessage());
            throw new \Exception('Terjadi kesalahan saat menghitung statistik DM');
        }
    }

    /**
     * Get standard patients (those who attended all months after their first visit)
     */
    private function getStandardPatients($examinations, $year)
    {
        $standardPatients = [];
        $patientVisits = [];

        // Group visits by patient
        foreach ($examinations as $exam) {
            $patientId = $exam->patient_id;
            $visitMonth = Carbon::parse($exam->examination_date)->month;

            if (!isset($patientVisits[$patientId])) {
                $patientVisits[$patientId] = [];
            }
            $patientVisits[$patientId][] = $visitMonth;
        }

        // Check each patient's attendance
        foreach ($patientVisits as $patientId => $visits) {
            sort($visits);
            $firstMonth = min($visits);
            $isStandard = true;

            // Check if patient attended all months after their first visit
            for ($month = $firstMonth; $month <= 12; $month++) {
                if (!in_array($month, $visits)) {
                    $isStandard = false;
                    break;
                }
            }

            if ($isStandard) {
                $standardPatients[] = $patientId;
            }
        }

        return $standardPatients;
    }

    /**
     * Get monthly statistics for a specific puskesmas
     */
    private function getMonthlyStatistics($query, $standardPatients)
    {
        $monthlyData = [];

        for ($m = 1; $m <= 12; $m++) {
            $monthExams = $query->whereMonth('examination_date', $m)->get();
            $uniquePatients = $monthExams->pluck('patient_id')->unique()->toArray();
            $monthStandardPatients = array_intersect($uniquePatients, $standardPatients);

            $maleStandardPatients = $monthExams->whereIn('patient_id', $monthStandardPatients)
                ->where('patient_gender', 'L')
                ->pluck('patient_id')
                ->unique()
                ->count();

            $femaleStandardPatients = $monthExams->whereIn('patient_id', $monthStandardPatients)
                ->where('patient_gender', 'P')
                ->pluck('patient_id')
                ->unique()
                ->count();

            $totalPatients = count($uniquePatients);
            $standardPatientsCount = count($monthStandardPatients);
            $nonStandardPatientsCount = $totalPatients - $standardPatientsCount;

            $monthlyData[] = [
                'month' => $m,
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatientsCount,
                'non_standard_patients' => max(0, $nonStandardPatientsCount),
                'male_patients' => $monthExams->where('patient_gender', 'L')->pluck('patient_id')->unique()->count(),
                'female_patients' => $monthExams->where('patient_gender', 'P')->pluck('patient_id')->unique()->count(),
                'standard_male_patients' => $maleStandardPatients,
                'standard_female_patients' => $femaleStandardPatients
            ];
        }

        return $monthlyData;
    }

    /**
     * Get gender statistics for a specific query
     */
    private function getGenderStatistics($examinations, $standardPatients)
    {
        $malePatients = $examinations->where('patient_gender', 'L')->pluck('patient_id')->unique();
        $femalePatients = $examinations->where('patient_gender', 'P')->pluck('patient_id')->unique();

        $standardMalePatients = $malePatients->intersect($standardPatients)->count();
        $standardFemalePatients = $femalePatients->intersect($standardPatients)->count();

        return [
            'male_patients' => $malePatients->count(),
            'female_patients' => $femalePatients->count(),
            'standard_male_patients' => $standardMalePatients,
            'standard_female_patients' => $standardFemalePatients
        ];
    }

    /**
     * Get patient attendance data for a specific puskesmas
     */
    public function getPatientAttendanceData($puskesmasId, $year, $month, $diseaseType)
    {
        try {
            $cacheKey = "attendance_{$puskesmasId}_{$year}_{$month}_{$diseaseType}";

            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($puskesmasId, $year, $month, $diseaseType) {
                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                $startDate = Carbon::create($year, $month, 1)->startOfDay();
                $endDate = Carbon::create($year, $month, $daysInMonth)->endOfDay();

                $examinationTable = $diseaseType === 'dm' ? 'dm_examinations' : 'ht_examinations';
                $examinationDateColumn = "{$examinationTable}.examination_date";

                $patients = DB::table('patients')
                    ->join($examinationTable, 'patients.id', '=', "{$examinationTable}.patient_id")
                    ->where("{$examinationTable}.puskesmas_id", $puskesmasId)
                    ->whereBetween($examinationDateColumn, [$startDate, $endDate])
                    ->select([
                        'patients.id',
                        'patients.name',
                        'patients.gender',
                        'patients.birth_date',
                        'patients.address',
                        'patients.phone',
                        DB::raw("DATE({$examinationDateColumn}) as examination_date")
                    ])
                    ->orderBy('examination_date')
                    ->get();

                return [
                    'patients' => $patients,
                    'days_in_month' => $daysInMonth,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error getting patient attendance data: ' . $e->getMessage());
            throw new \Exception('Terjadi kesalahan saat mengambil data kehadiran pasien');
        }
    }

    /**
     * Clear statistics cache for a specific puskesmas and year
     */
    public function clearStatisticsCache($puskesmasId, $year, $month = null)
    {
        $patterns = [
            "ht_stats_{$puskesmasId}_{$year}*",
            "dm_stats_{$puskesmasId}_{$year}*",
            "attendance_{$puskesmasId}_{$year}*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
