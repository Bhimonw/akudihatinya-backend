<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\HtExamination;
use App\Models\DmExamination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatisticsCalculationService
{
    protected $cacheVersion = 'v1';
    protected $cacheDuration = 1800; // 30 menit

    /**
     * Calculate HT statistics for a puskesmas
     */
    public function calculateHtStatistics($puskesmasId, $year)
    {
        $cacheKey = "ht_stats:{$this->cacheVersion}:{$puskesmasId}:{$year}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($puskesmasId, $year) {
            // Get all patients with HT examinations in this year
            $patients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereHas('htExaminations', function ($query) use ($year) {
                    $query->where('year', $year);
                })
                ->with(['htExaminations' => function ($query) use ($year) {
                    $query->where('year', $year)->orderBy('month');
                }])
                ->get();

            $totalPatients = $patients->count();
            $standardPatients = 0;
            $monthlyData = [];

            // Initialize monthly data
            for ($m = 1; $m <= 12; $m++) {
                $monthlyData[$m] = [
                    'total' => 0,
                    'standard' => 0
                ];
            }

            foreach ($patients as $patient) {
                $firstExamMonth = $patient->htExaminations->min('month');

                if ($firstExamMonth === null) continue;

                // Check if patient has examinations every month since first exam
                $isStandard = true;
                for ($m = $firstExamMonth; $m <= 12; $m++) {
                    $hasExam = $patient->htExaminations
                        ->where('month', $m)
                        ->count() > 0;

                    if (!$hasExam) {
                        $isStandard = false;
                        break;
                    }
                }

                if ($isStandard) {
                    $standardPatients++;
                }

                // Count monthly visits
                foreach ($patient->htExaminations as $exam) {
                    $month = $exam->month;
                    $monthlyData[$month]['total']++;
                    if ($isStandard) {
                        $monthlyData[$month]['standard']++;
                    }
                }
            }

            return [
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatients,
                'monthly_data' => $monthlyData
            ];
        });
    }

    /**
     * Calculate DM statistics for a puskesmas
     */
    public function calculateDmStatistics($puskesmasId, $year)
    {
        $cacheKey = "dm_stats:{$this->cacheVersion}:{$puskesmasId}:{$year}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($puskesmasId, $year) {
            // Get all patients with DM examinations in this year
            $patients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereHas('dmExaminations', function ($query) use ($year) {
                    $query->where('year', $year);
                })
                ->with(['dmExaminations' => function ($query) use ($year) {
                    $query->where('year', $year)->orderBy('month');
                }])
                ->get();

            $totalPatients = $patients->count();
            $standardPatients = 0;
            $monthlyData = [];

            // Initialize monthly data
            for ($m = 1; $m <= 12; $m++) {
                $monthlyData[$m] = [
                    'total' => 0,
                    'standard' => 0
                ];
            }

            foreach ($patients as $patient) {
                $firstExamMonth = $patient->dmExaminations->min('month');

                if ($firstExamMonth === null) continue;

                // Check if patient has examinations every month since first exam
                $isStandard = true;
                for ($m = $firstExamMonth; $m <= 12; $m++) {
                    $hasExam = $patient->dmExaminations
                        ->where('month', $m)
                        ->count() > 0;

                    if (!$hasExam) {
                        $isStandard = false;
                        break;
                    }
                }

                if ($isStandard) {
                    $standardPatients++;
                }

                // Count monthly visits
                foreach ($patient->dmExaminations as $exam) {
                    $month = $exam->month;
                    $monthlyData[$month]['total']++;
                    if ($isStandard) {
                        $monthlyData[$month]['standard']++;
                    }
                }
            }

            return [
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatients,
                'monthly_data' => $monthlyData
            ];
        });
    }

    /**
     * Get patient attendance data for monitoring report
     */
    public function getPatientAttendanceData($puskesmasId, $year, $month, $diseaseType)
    {
        $cacheKey = "patient_attendance:{$this->cacheVersion}:{$puskesmasId}:{$year}:{$month}:{$diseaseType}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($puskesmasId, $year, $month, $diseaseType) {
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
        });
    }

    /**
     * Clear all cache for a specific puskesmas
     */
    public function clearCache($puskesmasId, $year = null, $month = null)
    {
        $keys = [];

        // Clear HT stats
        if ($year) {
            $keys[] = "ht_stats:{$this->cacheVersion}:{$puskesmasId}:{$year}";
        }

        // Clear DM stats
        if ($year) {
            $keys[] = "dm_stats:{$this->cacheVersion}:{$puskesmasId}:{$year}";
        }

        // Clear patient attendance
        if ($year && $month) {
            $diseaseTypes = ['all', 'ht', 'dm'];
            foreach ($diseaseTypes as $type) {
                $keys[] = "patient_attendance:{$this->cacheVersion}:{$puskesmasId}:{$year}:{$month}:{$type}";
            }
        }

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        return true;
    }

    /**
     * Calculate HT statistics for multiple puskesmas
     */
    public function calculateAllHtStatistics($puskesmasIds, $year)
    {
        try {
            $cacheKey = "ht_stats_all:{$this->cacheVersion}:" . implode(',', $puskesmasIds) . ":{$year}";

            return Cache::remember($cacheKey, $this->cacheDuration, function () use ($puskesmasIds, $year) {
                try {
                    $result = [];

                    // Get all patients with HT examinations in this year
                    $patients = Patient::whereIn('puskesmas_id', $puskesmasIds)
                        ->whereHas('htExaminations', function ($query) use ($year) {
                            $query->where('year', $year);
                        })
                        ->with(['htExaminations' => function ($query) use ($year) {
                            $query->where('year', $year)
                                ->orderBy('month')
                                ->select(['id', 'patient_id', 'month', 'year']);
                        }])
                        ->select(['id', 'puskesmas_id', 'name'])
                        ->get()
                        ->groupBy('puskesmas_id');

                    foreach ($puskesmasIds as $puskesmasId) {
                        $puskesmasPatients = $patients[$puskesmasId] ?? collect();
                        $totalPatients = $puskesmasPatients->count();
                        $standardPatients = 0;
                        $monthlyData = [];

                        // Initialize monthly data
                        for ($m = 1; $m <= 12; $m++) {
                            $monthlyData[$m] = [
                                'total' => 0,
                                'standard' => 0
                            ];
                        }

                        foreach ($puskesmasPatients as $patient) {
                            $firstExamMonth = $patient->htExaminations->min('month');

                            if ($firstExamMonth === null) continue;

                            // Check if patient has examinations every month since first exam
                            $isStandard = true;
                            for ($m = $firstExamMonth; $m <= 12; $m++) {
                                $hasExam = $patient->htExaminations
                                    ->where('month', $m)
                                    ->count() > 0;

                                if (!$hasExam) {
                                    $isStandard = false;
                                    break;
                                }
                            }

                            if ($isStandard) {
                                $standardPatients++;
                            }

                            // Count monthly visits
                            foreach ($patient->htExaminations as $exam) {
                                $month = $exam->month;
                                $monthlyData[$month]['total']++;
                                if ($isStandard) {
                                    $monthlyData[$month]['standard']++;
                                }
                            }
                        }

                        $result[$puskesmasId] = [
                            'total_patients' => $totalPatients,
                            'standard_patients' => $standardPatients,
                            'monthly_data' => $monthlyData
                        ];
                    }

                    return $result;
                } catch (\Exception $e) {
                    \Log::error('Error calculating HT statistics: ' . $e->getMessage());
                    \Log::error($e->getTraceAsString());
                    throw $e;
                }
            });
        } catch (\Exception $e) {
            \Log::error('Error in calculateAllHtStatistics: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate DM statistics for multiple puskesmas
     */
    public function calculateAllDmStatistics($puskesmasIds, $year)
    {
        try {
            $cacheKey = "dm_stats_all:{$this->cacheVersion}:" . implode(',', $puskesmasIds) . ":{$year}";

            return Cache::remember($cacheKey, $this->cacheDuration, function () use ($puskesmasIds, $year) {
                try {
                    $result = [];

                    // Get all patients with DM examinations in this year
                    $patients = Patient::whereIn('puskesmas_id', $puskesmasIds)
                        ->whereHas('dmExaminations', function ($query) use ($year) {
                            $query->where('year', $year);
                        })
                        ->with(['dmExaminations' => function ($query) use ($year) {
                            $query->where('year', $year)
                                ->orderBy('month')
                                ->select(['id', 'patient_id', 'month', 'year']);
                        }])
                        ->select(['id', 'puskesmas_id', 'name'])
                        ->get()
                        ->groupBy('puskesmas_id');

                    foreach ($puskesmasIds as $puskesmasId) {
                        $puskesmasPatients = $patients[$puskesmasId] ?? collect();
                        $totalPatients = $puskesmasPatients->count();
                        $standardPatients = 0;
                        $monthlyData = [];

                        // Initialize monthly data
                        for ($m = 1; $m <= 12; $m++) {
                            $monthlyData[$m] = [
                                'total' => 0,
                                'standard' => 0
                            ];
                        }

                        foreach ($puskesmasPatients as $patient) {
                            $firstExamMonth = $patient->dmExaminations->min('month');

                            if ($firstExamMonth === null) continue;

                            // Check if patient has examinations every month since first exam
                            $isStandard = true;
                            for ($m = $firstExamMonth; $m <= 12; $m++) {
                                $hasExam = $patient->dmExaminations
                                    ->where('month', $m)
                                    ->count() > 0;

                                if (!$hasExam) {
                                    $isStandard = false;
                                    break;
                                }
                            }

                            if ($isStandard) {
                                $standardPatients++;
                            }

                            // Count monthly visits
                            foreach ($patient->dmExaminations as $exam) {
                                $month = $exam->month;
                                $monthlyData[$month]['total']++;
                                if ($isStandard) {
                                    $monthlyData[$month]['standard']++;
                                }
                            }
                        }

                        $result[$puskesmasId] = [
                            'total_patients' => $totalPatients,
                            'standard_patients' => $standardPatients,
                            'monthly_data' => $monthlyData
                        ];
                    }

                    return $result;
                } catch (\Exception $e) {
                    \Log::error('Error calculating DM statistics: ' . $e->getMessage());
                    \Log::error($e->getTraceAsString());
                    throw $e;
                }
            });
        } catch (\Exception $e) {
            \Log::error('Error in calculateAllDmStatistics: ' . $e->getMessage());
            throw $e;
        }
    }
}
