<?php

namespace App\Services;

use App\Models\MonthlyStatisticsCache;
use App\Models\HtExamination;
use App\Models\DmExamination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsCalculationService
{
    /**
     * Get HT statistics with monthly breakdown
     */
    public function getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        $query = HtExamination::where('puskesmas_id', $puskesmasId)
            ->whereYear('examination_date', $year);

        if ($month) {
            $query->whereMonth('examination_date', $month);
        }

        $examinations = $query->get();

        $totalPatients = $examinations->count();
        $standardPatients = $examinations->where('is_standard', true)->count();
        $nonStandardPatients = $examinations->where('is_standard', false)->count();
        $malePatients = $examinations->where('patient_gender', 'L')->count();
        $femalePatients = $examinations->where('patient_gender', 'P')->count();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthExaminations = $examinations->filter(function ($exam) use ($m) {
                return Carbon::parse($exam->examination_date)->month === $m;
            });

            $monthlyData[] = [
                'month' => $m,
                'total_patients' => $monthExaminations->count(),
                'standard_patients' => $monthExaminations->where('is_standard', true)->count(),
                'non_standard_patients' => $monthExaminations->where('is_standard', false)->count(),
                'male_patients' => $monthExaminations->where('patient_gender', 'L')->count(),
                'female_patients' => $monthExaminations->where('patient_gender', 'P')->count(),
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData,
        ];
    }

    /**
     * Get DM statistics with monthly breakdown
     */
    public function getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        $query = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereYear('examination_date', $year);

        if ($month) {
            $query->whereMonth('examination_date', $month);
        }

        $examinations = $query->get();

        $totalPatients = $examinations->count();
        $standardPatients = $examinations->where('is_standard', true)->count();
        $nonStandardPatients = $examinations->where('is_standard', false)->count();
        $malePatients = $examinations->where('patient_gender', 'L')->count();
        $femalePatients = $examinations->where('patient_gender', 'P')->count();

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthExaminations = $examinations->filter(function ($exam) use ($m) {
                return Carbon::parse($exam->examination_date)->month === $m;
            });

            $monthlyData[] = [
                'month' => $m,
                'total_patients' => $monthExaminations->count(),
                'standard_patients' => $monthExaminations->where('is_standard', true)->count(),
                'non_standard_patients' => $monthExaminations->where('is_standard', false)->count(),
                'male_patients' => $monthExaminations->where('patient_gender', 'L')->count(),
                'female_patients' => $monthExaminations->where('patient_gender', 'P')->count(),
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData,
        ];
    }

    /**
     * Get patient attendance data
     */
    public function getPatientAttendanceData($puskesmasId, $year, $month, $diseaseType)
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = Carbon::create($year, $month, $daysInMonth)->endOfDay();

        $query = DB::table('patients')
            ->join('ht_examinations', 'patients.id', '=', 'ht_examinations.patient_id')
            ->where('ht_examinations.puskesmas_id', $puskesmasId)
            ->whereBetween('ht_examinations.examination_date', [$startDate, $endDate]);

        if ($diseaseType === 'dm') {
            $query = DB::table('patients')
                ->join('dm_examinations', 'patients.id', '=', 'dm_examinations.patient_id')
                ->where('dm_examinations.puskesmas_id', $puskesmasId)
                ->whereBetween('dm_examinations.examination_date', [$startDate, $endDate]);
        }

        $patients = $query->select(
            'patients.id',
            'patients.name',
            'patients.gender',
            'patients.birth_date',
            'patients.address',
            'patients.phone',
            DB::raw('DATE(ht_examinations.examination_date) as examination_date')
        )->get();

        return [
            'patients' => $patients,
            'days_in_month' => $daysInMonth,
        ];
    }
}
