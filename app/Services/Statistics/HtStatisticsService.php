<?php

namespace App\Services\Statistics;

use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\YearlyTarget;
use Carbon\Carbon;

class HtStatisticsService
{
    /**
     * Mendapatkan statistik HT dengan breakdown bulanan 
     * yang sesuai dengan logika standar dan tidak standar yang baru
     */
    public function getStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        // Get yearly target
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->first();

        $yearlyTarget = $target ? $target->target_count : 0;

        // Jika filter bulan digunakan, ambil data untuk bulan tersebut saja
        if ($month !== null) {
            return $this->getMonthlyStatistics($puskesmasId, $year, $month, $yearlyTarget);
        }

        // Untuk laporan tahunan, ambil semua bulan
        return $this->getYearlyStatistics($puskesmasId, $year, $yearlyTarget);
    }

    /**
     * Get monthly statistics
     */
    private function getMonthlyStatistics($puskesmasId, $year, $month, $yearlyTarget)
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Ambil data pasien unik yang melakukan pemeriksaan di bulan ini
        $patients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('htExaminations', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('examination_date', [$startDate, $endDate]);
            })
            ->get();

        $totalPatients = $patients->count();
        $malePatients = $patients->where('gender', 'male')->count();
        $femalePatients = $patients->where('gender', 'female')->count();

        // Untuk laporan bulanan, cek apakah pasien telah melakukan pemeriksaan
        // setiap bulan sejak pemeriksaan pertama sampai bulan ini
        $standardPatients = 0;

        foreach ($patients as $patient) {
            // Ambil bulan pertama pemeriksaan tahun ini
            $firstExamMonth = HtExamination::where('patient_id', $patient->id)
                ->where('year', $year)
                ->min('month');

            if ($firstExamMonth === null) continue;

            // Cek apakah pasien hadir setiap bulan sejak pertama sampai bulan ini
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

    /**
     * Get yearly statistics
     */
    private function getYearlyStatistics($puskesmasId, $year, $yearlyTarget)
    {
        $yearlyData = [];
        $totalUniquePatients = 0;
        $totalStandard = 0;
        $totalNonStandard = 0;
        $totalMale = 0;
        $totalFemale = 0;

        // Ambil semua pasien yang memiliki pemeriksaan HT dalam tahun ini
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

        // Untuk setiap bulan, hitung statistik
        for ($m = 1; $m <= 12; $m++) {
            $monthlyPatients = $allPatients->filter(function ($patient) use ($m) {
                return $patient->htExaminations->where('month', $m)->count() > 0;
            });

            $monthlyTotal = $monthlyPatients->count();
            $monthlyMale = $monthlyPatients->where('gender', 'male')->count();
            $monthlyFemale = $monthlyPatients->where('gender', 'female')->count();

            // Hitung pasien standar untuk bulan ini
            $monthlyStandard = 0;
            foreach ($monthlyPatients as $patient) {
                // Ambil bulan pertama pemeriksaan tahun ini
                $firstExamMonth = $patient->htExaminations
                    ->min('month');

                if ($firstExamMonth === null) continue;

                // Cek apakah pasien hadir setiap bulan sejak pertama sampai bulan ini
                $isStandard = true;
                for ($checkM = $firstExamMonth; $checkM <= $m; $checkM++) {
                    $hasExam = $patient->htExaminations
                        ->where('month', $checkM)
                        ->count() > 0;

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

        // Hitung pasien standar untuk tahun ini
        // Pasien standar = hadir setiap bulan sejak pertama pemeriksaan
        $standardPatients = 0;
        foreach ($allPatients as $patient) {
            $firstExamMonth = $patient->htExaminations->min('month');

            if ($firstExamMonth === null) continue;

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
}