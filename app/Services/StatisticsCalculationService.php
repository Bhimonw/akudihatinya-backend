<?php

namespace App\Services;

use App\Models\MonthlyStatisticsCache;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Models\YearlyTarget;
use App\Models\Examination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\StandardPatientCalculationService;

class StatisticsCalculationService
{
    const CACHE_TTL = 3600; // 1 hour cache

    protected $standardPatientService;

    public function __construct(StandardPatientCalculationService $standardPatientService)
    {
        $this->standardPatientService = $standardPatientService;
    }

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

                $examinations = $query->with('patient')->get();

                // Calculate monthly statistics
                $monthlyStats = $this->standardPatientService->calculateMonthlyStatistics($examinations, $year);

                // Get the last month's data for total statistics
                $lastMonthStats = end($monthlyStats);

                $totalPatients = $lastMonthStats['total_patients'];
                $malePatients = $lastMonthStats['male_patients'];
                $femalePatients = $lastMonthStats['female_patients'];
                $standardPatients = $lastMonthStats['standard_patients'];
                $standardMalePatients = $lastMonthStats['standard_male_patients'];
                $standardFemalePatients = $lastMonthStats['standard_female_patients'];
                $nonStandardPatients = $totalPatients - $standardPatients;

                // Calculate achievement percentage
                $targetCount = $target ? $target->target_count : 0;
                $achievementPercentage = $targetCount > 0
                    ? min(round(($standardPatients / $targetCount) * 100, 2), 100)
                    : 0;

                return [
                    'target' => $targetCount,
                    'total_patients' => $totalPatients,
                    'achievement_percentage' => $achievementPercentage,
                    'standard_patients' => $standardPatients,
                    'non_standard_patients' => $nonStandardPatients,
                    'male_patients' => $malePatients,
                    'female_patients' => $femalePatients,
                    'standard_male_patients' => $standardMalePatients,
                    'standard_female_patients' => $standardFemalePatients,
                    'monthly_data' => $monthlyStats
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error in HT statistics: ' . $e->getMessage());
            return $this->getDefaultStatistics();
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

                $examinations = $query->with('patient')->get();

                // Calculate monthly statistics
                $monthlyStats = $this->standardPatientService->calculateMonthlyStatistics($examinations, $year);

                // Get the last month's data for total statistics
                $lastMonthStats = end($monthlyStats);

                $totalPatients = $lastMonthStats['total_patients'];
                $malePatients = $lastMonthStats['male_patients'];
                $femalePatients = $lastMonthStats['female_patients'];
                $standardPatients = $lastMonthStats['standard_patients'];
                $standardMalePatients = $lastMonthStats['standard_male_patients'];
                $standardFemalePatients = $lastMonthStats['standard_female_patients'];
                $nonStandardPatients = $totalPatients - $standardPatients;

                // Calculate achievement percentage
                $targetCount = $target ? $target->target_count : 0;
                $achievementPercentage = $targetCount > 0
                    ? min(round(($standardPatients / $targetCount) * 100, 2), 100)
                    : 0;

                return [
                    'target' => $targetCount,
                    'total_patients' => $totalPatients,
                    'achievement_percentage' => $achievementPercentage,
                    'standard_patients' => $standardPatients,
                    'non_standard_patients' => $nonStandardPatients,
                    'male_patients' => $malePatients,
                    'female_patients' => $femalePatients,
                    'standard_male_patients' => $standardMalePatients,
                    'standard_female_patients' => $standardFemalePatients,
                    'monthly_data' => $monthlyStats
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error in DM statistics: ' . $e->getMessage());
            return $this->getDefaultStatistics();
        }
    }

    /**
     * Get default statistics when an error occurs
     */
    private function getDefaultStatistics()
    {
        return [
            'target' => 0,
            'total_patients' => 0,
            'achievement_percentage' => 0,
            'standard_patients' => 0,
            'non_standard_patients' => 0,
            'male_patients' => 0,
            'female_patients' => 0,
            'standard_male_patients' => 0,
            'standard_female_patients' => 0,
            'monthly_data' => []
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

                $model = $diseaseType === 'dm' ? DmExamination::class : HtExamination::class;
                $examinations = $model::where('puskesmas_id', $puskesmasId)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->with('patient')
                    ->get();

                $patients = [];
                foreach ($examinations as $exam) {
                    $patientId = $exam->patient_id;
                    if (!isset($patients[$patientId])) {
                        $patients[$patientId] = [
                            'id' => $exam->patient->id,
                            'name' => $exam->patient->name,
                            'gender' => $exam->patient->gender,
                            'birth_date' => $exam->patient->birth_date,
                            'address' => $exam->patient->address,
                            'phone' => $exam->patient->phone,
                            'examination_date' => $exam->examination_date->format('Y-m-d')
                        ];
                    }
                }

                return [
                    'patients' => array_values($patients),
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
