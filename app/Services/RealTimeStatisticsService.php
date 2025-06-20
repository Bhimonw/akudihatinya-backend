<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RealTimeStatisticsService
{
    /**
     * Process examination data and update statistics in real-time
     */
    public function processExaminationData($examination, string $diseaseType): void
    {
        DB::transaction(function () use ($examination, $diseaseType) {
            // Calculate and save pre-calculated statistics
            $examination->calculateStatistics();
            $examination->save();
            
            // Update monthly cache if this is first visit this month
            if ($examination->is_first_visit_this_month) {
                $this->updateMonthlyCache($examination, $diseaseType);
            }
            
            // Update patient standard status for all examinations in current year
            $this->updatePatientStandardStatus($examination, $diseaseType);
        });
    }
    
    /**
     * Update monthly statistics cache
     */
    private function updateMonthlyCache($examination, string $diseaseType): void
    {
        $cache = MonthlyStatisticsCache::firstOrNew([
            'puskesmas_id' => $examination->puskesmas_id,
            'disease_type' => $diseaseType,
            'year' => $examination->year,
            'month' => $examination->month,
        ]);
        
        if (!$cache->exists) {
            $cache->fill([
                'male_count' => 0,
                'female_count' => 0,
                'total_count' => 0,
                'standard_count' => 0,
                'non_standard_count' => 0,
                'standard_percentage' => 0.00,
            ]);
        }
        
        // Increment counters based on pre-calculated data
        if ($examination->is_standard_patient) {
            $cache->increment('standard_count');
            
            // Only count gender for standard patients
            if ($examination->patient_gender === 'male') {
                $cache->increment('male_count');
            } else {
                $cache->increment('female_count');
            }
        } else {
            $cache->increment('non_standard_count');
        }
        
        $cache->increment('total_count');
        
        // Recalculate percentage
        if ($cache->total_count > 0) {
            $cache->standard_percentage = round(($cache->standard_count / $cache->total_count) * 100, 2);
        }
        
        $cache->save();
    }
    
    /**
     * Update patient standard status for all examinations in current year
     * This is needed because adding a new visit might change the standard status
     */
    private function updatePatientStandardStatus($examination, string $diseaseType): void
    {
        $modelClass = $diseaseType === 'ht' ? HtExamination::class : DmExamination::class;
        
        // Get all examinations for this patient in current year
        $examinations = $modelClass::where('patient_id', $examination->patient_id)
            ->where('year', $examination->year)
            ->orderBy('examination_date')
            ->get();
            
        if ($examinations->isEmpty()) {
            return;
        }
        
        $firstMonth = $examinations->first()->month;
        
        // Check each month and update standard status
        foreach ($examinations as $exam) {
            $isStandard = $this->calculateStandardStatus(
                $examination->patient_id,
                $examination->year,
                $exam->month,
                $firstMonth,
                $diseaseType
            );
            
            // Update if changed
            if ($exam->is_standard_patient !== $isStandard) {
                $exam->is_standard_patient = $isStandard;
                $exam->save();
                
                // Update cache for this month if this was first visit
                if ($exam->is_first_visit_this_month) {
                    $this->recalculateMonthlyCache(
                        $exam->puskesmas_id,
                        $diseaseType,
                        $exam->year,
                        $exam->month
                    );
                }
            }
        }
    }
    
    /**
     * Calculate if patient is standard for specific month
     */
    private function calculateStandardStatus(
        int $patientId,
        int $year,
        int $currentMonth,
        int $firstMonth,
        string $diseaseType
    ): bool {
        $modelClass = $diseaseType === 'ht' ? HtExamination::class : DmExamination::class;
        
        // Check if patient has visits for every month from first visit until current month
        for ($month = $firstMonth; $month <= $currentMonth; $month++) {
            $hasVisit = $modelClass::where('patient_id', $patientId)
                ->where('year', $year)
                ->where('month', $month)
                ->exists();
                
            if (!$hasVisit) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Recalculate monthly cache from pre-calculated examination data
     */
    private function recalculateMonthlyCache(
        int $puskesmasId,
        string $diseaseType,
        int $year,
        int $month
    ): void {
        $modelClass = $diseaseType === 'ht' ? HtExamination::class : DmExamination::class;
        
        // Get aggregated data from pre-calculated examination data
        $stats = $modelClass::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('is_first_visit_this_month', true)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN is_standard_patient = 1 THEN 1 ELSE 0 END) as standard_count,
                SUM(CASE WHEN is_standard_patient = 0 THEN 1 ELSE 0 END) as non_standard_count,
                SUM(CASE WHEN is_standard_patient = 1 AND patient_gender = "male" THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN is_standard_patient = 1 AND patient_gender = "female" THEN 1 ELSE 0 END) as female_count
            ')
            ->first();
            
        if (!$stats || $stats->total_count == 0) {
            return;
        }
        
        $standardPercentage = $stats->total_count > 0 
            ? round(($stats->standard_count / $stats->total_count) * 100, 2) 
            : 0;
            
        MonthlyStatisticsCache::updateOrCreate(
            [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
            ],
            [
                'male_count' => $stats->male_count,
                'female_count' => $stats->female_count,
                'total_count' => $stats->total_count,
                'standard_count' => $stats->standard_count,
                'non_standard_count' => $stats->non_standard_count,
                'standard_percentage' => $standardPercentage,
            ]
        );
    }
    
    /**
     * Get fast dashboard statistics using pre-calculated data
     */
    public function getFastDashboardStats(int $puskesmasId, string $diseaseType, int $year): array
    {
        // Get data from cache table (much faster)
        $cacheData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->get();
            
        $monthlyData = [];
        $totalMale = 0;
        $totalFemale = 0;
        $totalCount = 0;
        $totalStandard = 0;
        $totalNonStandard = 0;
        
        // Initialize 12 months with zero data
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = [
                'male_count' => 0,
                'female_count' => 0,
                'total_count' => 0,
                'standard_count' => 0,
                'non_standard_count' => 0,
                'standard_percentage' => 0,
            ];
        }
        
        // Fill with actual data
        foreach ($cacheData as $data) {
            $monthlyData[$data->month] = [
                'male_count' => $data->male_count,
                'female_count' => $data->female_count,
                'total_count' => $data->total_count,
                'standard_count' => $data->standard_count,
                'non_standard_count' => $data->non_standard_count,
                'standard_percentage' => $data->standard_percentage,
            ];
            
            $totalMale += $data->male_count;
            $totalFemale += $data->female_count;
            $totalCount += $data->total_count;
            $totalStandard += $data->standard_count;
            $totalNonStandard += $data->non_standard_count;
        }
        
        // Find last month with data for summary
        $lastMonthWithData = null;
        for ($month = 12; $month >= 1; $month--) {
            if ($monthlyData[$month]['total_count'] > 0) {
                $lastMonthWithData = $monthlyData[$month];
                break;
            }
        }
        
        return [
            'monthly_data' => $monthlyData,
            'summary' => $lastMonthWithData ?: [
                'male_count' => 0,
                'female_count' => 0,
                'total_count' => 0,
                'standard_count' => 0,
                'non_standard_count' => 0,
                'standard_percentage' => 0,
            ],
            'yearly_total' => [
                'male_count' => $totalMale,
                'female_count' => $totalFemale,
                'total_count' => $totalCount,
                'standard_count' => $totalStandard,
                'non_standard_count' => $totalNonStandard,
                'standard_percentage' => $totalCount > 0 ? round(($totalStandard / $totalCount) * 100, 2) : 0,
            ]
        ];
    }
}