<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class StandardPatientCalculationService
{
    /**
     * Calculate standard patients for a specific month
     */
    public function calculateStandardPatientsForMonth($examinations, $month)
    {
        $standardPatients = [
            'total' => 0,
            'male' => 0,
            'female' => 0
        ];

        // Group examinations by patient
        $patientExaminations = $this->groupExaminationsByPatient($examinations);

        foreach ($patientExaminations as $patientId => $data) {
            if ($this->isStandardForMonth($data['dates'], $month)) {
                $standardPatients['total']++;
                if ($data['gender'] === 'male') {
                    $standardPatients['male']++;
                } else {
                    $standardPatients['female']++;
                }
            }
        }

        return $standardPatients;
    }

    /**
     * Group examinations by patient
     */
    private function groupExaminationsByPatient($examinations)
    {
        $grouped = [];
        foreach ($examinations as $exam) {
            $patientId = $exam->patient_id;
            if (!isset($grouped[$patientId])) {
                $grouped[$patientId] = [
                    'dates' => [],
                    'gender' => $exam->patient->gender
                ];
            }
            $grouped[$patientId]['dates'][] = Carbon::parse($exam->examination_date);
        }
        return $grouped;
    }

    /**
     * Check if a patient is standard for a specific month
     */
    private function isStandardForMonth($dates, $month)
    {
        if (empty($dates)) {
            return false;
        }

        // Sort dates
        usort($dates, function ($a, $b) {
            return $a->timestamp - $b->timestamp;
        });

        // Find the first visit
        $firstVisit = $dates[0];

        // If first visit is after the current month, patient is not standard
        if ($firstVisit->month > $month) {
            return false;
        }

        // If this is the first visit month, patient is standard
        if ($firstVisit->month === $month) {
            return true;
        }

        // Check if patient has visited in current month and previous month
        $hasCurrentMonth = false;
        $hasPreviousMonth = false;

        foreach ($dates as $date) {
            if ($date->month === $month) {
                $hasCurrentMonth = true;
            }
            if ($date->month === $month - 1) {
                $hasPreviousMonth = true;
            }
        }

        // If patient has visited in both current and previous month, they are standard
        return $hasCurrentMonth && $hasPreviousMonth;
    }

    /**
     * Calculate monthly statistics for standard patients
     */
    public function calculateMonthlyStatistics($examinations, $year)
    {
        $monthlyStats = [];
        $currentMonth = Carbon::now()->month;

        // Group examinations by month
        $monthlyExaminations = $this->groupExaminationsByMonth($examinations);

        // Calculate statistics for each month
        for ($m = 1; $m <= 12; $m++) {
            $monthKey = sprintf('%04d-%02d', $year, $m);
            $monthExaminations = $monthlyExaminations[$monthKey] ?? [];

            $monthStats = [
                'month' => $m,
                'total_patients' => count($monthExaminations),
                'male_patients' => 0,
                'female_patients' => 0,
                'standard_patients' => 0,
                'standard_male_patients' => 0,
                'standard_female_patients' => 0
            ];

            // Calculate standard patients for this month
            $standardStats = $this->calculateStandardPatientsForMonth($examinations, $m);

            // Update statistics
            $monthStats['standard_patients'] = $standardStats['total'];
            $monthStats['standard_male_patients'] = $standardStats['male'];
            $monthStats['standard_female_patients'] = $standardStats['female'];

            // Calculate gender distribution
            foreach ($monthExaminations as $exam) {
                if ($exam->patient->gender === 'male') {
                    $monthStats['male_patients']++;
                } else {
                    $monthStats['female_patients']++;
                }
            }

            $monthlyStats[] = $monthStats;

            // Stop if we've reached the current month
            if ($m === $currentMonth) {
                break;
            }
        }

        return $monthlyStats;
    }

    /**
     * Group examinations by month
     */
    private function groupExaminationsByMonth($examinations)
    {
        $grouped = [];
        foreach ($examinations as $exam) {
            $monthKey = Carbon::parse($exam->examination_date)->format('Y-m');
            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [];
            }
            $grouped[$monthKey][] = $exam;
        }
        return $grouped;
    }
}
