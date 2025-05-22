<?php

namespace App\Services\Statistics;

use App\Models\DmExamination;
use App\Models\Patient;
use App\Models\YearlyTarget;
use Carbon\Carbon;

class DmStatisticsService
{
    /**
     * Mendapatkan statistik DM dengan breakdown bulanan 
     * yang sesuai dengan logika standar dan tidak standar yang baru
     */
    public function getStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        // Get yearly target
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
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
            ->whereHas('dmExaminations', function ($query) use ($startDate, $endDate) {
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
            $firstExamMonth = DmExamination::where('patient_id', $patient->id)
                ->where('year', $year)
                ->min('month');

            if ($firstExamMonth === null) continue;

            // Cek apakah pasien hadir setiap bulan sejak pertama sampai bulan ini
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= $month; $m++) {
                $hasExam = DmExamination::where('patient_id', $patient->id)
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
        // Implementasi mirip dengan HtStatisticsService tetapi untuk DM
        // Logika sama, hanya model yang berbeda
        
        // Kode implementasi...
        
        return [
            'total_patients' => 0,
            'standard_patients' => 0,
            'non_standard_patients' => 0,
            'male_patients' => 0,
            'female_patients' => 0,
            'achievement_percentage' => 0,
            'standard_percentage' => 0,
            'monthly_data' => [],
        ];
    }
}