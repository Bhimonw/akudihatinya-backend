<?php

namespace App\Services\Statistics;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use App\Models\YearlyTarget;
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
        
        // Recalculate percentage using yearly target
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $examination->puskesmas_id)
            ->where('disease_type', $diseaseType)
            ->where('year', $examination->year)
            ->value('target_count') ?? 0;
            
        if ($yearlyTarget > 0) {
            $cache->standard_percentage = round(($cache->standard_count / $yearlyTarget) * 100, 2);
        } else {
            $cache->standard_percentage = 0;
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
        
        // Get yearly target for correct percentage calculation
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->value('target_count') ?? 0;
        
        $standardPercentage = $yearlyTarget > 0 
            ? round(($stats->standard_count / $yearlyTarget) * 100, 2) 
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
        // Get yearly target
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->value('target_count') ?? 0;
            
        // Get data from cache table (much faster)
        $cacheData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->get();
            
        $monthlyData = [];
        
        // Initialize 12 months with zero data
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = [
                'male' => '0',
                'female' => '0',
                'total' => '0',
                'standard' => '0',
                'non_standard' => '0',
                'percentage' => 0,
            ];
        }
        
        // Fill with actual data
        foreach ($cacheData as $data) {
            $monthlyData[$data->month] = [
                'male' => (string)$data->male_count,
                'female' => (string)$data->female_count,
                'total' => (string)$data->total_count,
                'standard' => (string)$data->standard_count,
                'non_standard' => (string)$data->non_standard_count,
                'percentage' => $data->standard_percentage,
            ];
        }
        
        // Find last month with data for summary
        $lastMonthWithData = null;
        for ($month = 12; $month >= 1; $month--) {
            if ((int)$monthlyData[$month]['total'] > 0) {
                $lastMonthWithData = $monthlyData[$month];
                break;
            }
        }
        
        // Use last month data for both summary and yearly_total to avoid accumulation
        $summaryData = $lastMonthWithData ?: [
            'male' => '0',
            'female' => '0',
            'total' => '0',
            'standard' => '0',
            'non_standard' => '0',
            'percentage' => 0,
        ];
        
        return [
            'monthly_data' => $monthlyData,
            'summary' => $summaryData,
            'yearly_total' => [
                'male' => $summaryData['male'],
                'female' => $summaryData['female'],
                'total' => $summaryData['total'],
                'standard' => $summaryData['standard'],
                'non_standard' => $summaryData['non_standard'],
                'percentage' => $yearlyTarget > 0 ? round(((int)$summaryData['standard'] / $yearlyTarget) * 100, 2) : 0,
            ]
        ];
    }
}