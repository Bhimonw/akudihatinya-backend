<?php

namespace App\Services\System;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NewYearSetupService
{
    /**
     * Setup new year by clearing examination data while preserving patient data
     */
    public function setupNewYear($year)
    {
        return DB::transaction(function () use ($year) {
            // Count data before clearing
            $htCount = HtExamination::count();
            $dmCount = DmExamination::count();
            $patientCount = Patient::count();
            
            // Clear all examination data
            HtExamination::truncate();
            DmExamination::truncate();
            
            // Clear monthly statistics cache
            MonthlyStatisticsCache::truncate();
            
            // Create yearly targets for new year
            $createdTargets = $this->createYearlyTargets($year);
            
            // Reset patient examination years
            $this->resetPatientExaminationYears();
            
            return [
                'cleared_ht' => $htCount,
                'cleared_dm' => $dmCount,
                'preserved_patients' => $patientCount,
                'created_targets' => $createdTargets,
                'year' => $year
            ];
        });
    }
    
    /**
     * Create yearly targets for all puskesmas for the new year
     */
    private function createYearlyTargets($year)
    {
        $puskesmas = Puskesmas::all();
        $createdCount = 0;
        
        foreach ($puskesmas as $puskesmasItem) {
            // Create HT target
            YearlyTarget::updateOrCreate(
                [
                    'puskesmas_id' => $puskesmasItem->id,
                    'disease_type' => 'ht',
                    'year' => $year
                ],
                [
                    'target_count' => $this->getDefaultTarget($puskesmasItem->name, 'ht')
                ]
            );
            $createdCount++;
            
            // Create DM target
            YearlyTarget::updateOrCreate(
                [
                    'puskesmas_id' => $puskesmasItem->id,
                    'disease_type' => 'dm',
                    'year' => $year
                ],
                [
                    'target_count' => $this->getDefaultTarget($puskesmasItem->name, 'dm')
                ]
            );
            $createdCount++;
        }
        
        return $createdCount;
    }
    
    /**
     * Reset patient examination years to empty arrays
     */
    private function resetPatientExaminationYears()
    {
        Patient::query()->update([
            'ht_years' => json_encode([]),
            'dm_years' => json_encode([])
        ]);
    }
    
    /**
     * Get default target based on puskesmas name and disease type
     */
    private function getDefaultTarget($puskesmasName, $diseaseType)
    {
        // Default target values based on existing seeder logic
        $targetValues = [
            'Puskesmas 4' => 137,
            'Puskesmas 6' => 97,
            'default' => rand(100, 300)
        ];
        
        return $targetValues[$puskesmasName] ?? $targetValues['default'];
    }
    
    /**
     * Archive current year data before setup (optional)
     */
    public function archiveCurrentYear()
    {
        $currentYear = Carbon::now()->year;
        
        // Archive HT examinations
        HtExamination::where('year', $currentYear)
            ->where('is_archived', false)
            ->update(['is_archived' => true]);
        
        // Archive DM examinations
        DmExamination::where('year', $currentYear)
            ->where('is_archived', false)
            ->update(['is_archived' => true]);
        
        return [
            'archived_ht' => HtExamination::where('year', $currentYear)->where('is_archived', true)->count(),
            'archived_dm' => DmExamination::where('year', $currentYear)->where('is_archived', true)->count(),
        ];
    }
}