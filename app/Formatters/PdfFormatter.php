<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Formatter untuk PDF menggunakan template di resources/pdf
 * Menggunakan template puskesmas_statistics_pdf.blade.php dan all_quarters_recap_pdf.blade.php
 */
class PdfFormatter
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Format data untuk PDF puskesmas statistics
     *
     * @param int $puskesmasId
     * @param int $year
     * @param string $diseaseType
     * @return array
     */
    public function formatPuskesmasStatistics(int $puskesmasId, int $year, string $diseaseType = 'ht'): array
    {
        try {
            // Ambil data puskesmas
            $puskesmas = $this->statisticsService->getAllPuskesmas();
            $currentPuskesmas = collect($puskesmas)->firstWhere('id', $puskesmasId);
            
            if (!$currentPuskesmas) {
                throw new \Exception("Puskesmas dengan ID {$puskesmasId} tidak ditemukan");
            }

            // Ambil data bulanan
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $stats = $this->statisticsService->getMonthlyStatistics(
                    $puskesmasId, 
                    $year, 
                    $diseaseType,
                    $month
                );
                
                $total = $stats['total_patients'] ?? 0;
                $standardPercentage = $total > 0 ? 
                    round(($stats['standard_patients'] / $total) * 100, 2) : 0;
                
                $monthlyData[$month] = [
                    'male' => $stats['male_patients'] ?? 0,
                    'female' => $stats['female_patients'] ?? 0,
                    'standard' => $stats['standard_patients'] ?? 0,
                    'non_standard' => $stats['non_standard_patients'] ?? 0,
                    'total' => $total,
                    'percentage' => $standardPercentage
                ];
            }

            // Ambil target tahunan
            $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
            
            // Hitung total tahunan
            $yearlyTotal = $this->calculateYearlyTotals($monthlyData);
            $achievementPercentage = $yearlyTarget['target'] > 0 ? 
                round(($yearlyTotal['standard'] / $yearlyTarget['target']) * 100, 2) : 0;

            return [
                'puskesmas_name' => $currentPuskesmas['name'],
                'year' => $year,
                'disease_type' => $diseaseType,
                'disease_label' => $this->getDiseaseLabel($diseaseType),
                'monthly_data' => $monthlyData,
                'yearly_target' => $yearlyTarget['target'],
                'yearly_total' => $yearlyTotal,
                'achievement_percentage' => $achievementPercentage,
                'generated_at' => Carbon::now()->format('d/m/Y H:i:s')
            ];
            
        } catch (\Exception $e) {
            Log::error('PdfFormatter: Error formatting puskesmas statistics', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'year' => $year,
                'disease_type' => $diseaseType
            ]);
            throw $e;
        }
    }

    /**
     * Format data untuk PDF all quarters recap
     *
     * @param int $year
     * @param string $diseaseType
     * @return array
     */
    public function formatAllQuartersRecap(int $year, string $diseaseType = 'ht'): array
    {
        try {
            // Ambil semua puskesmas
            $allPuskesmas = $this->statisticsService->getAllPuskesmas();
            
            $quarterData = [];
            
            // Data untuk setiap triwulan
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $quarterMonths = [
                    1 => ['Januari', 'Februari', 'Maret'],
                    2 => ['April', 'Mei', 'Juni'],
                    3 => ['Juli', 'Agustus', 'September'],
                    4 => ['Oktober', 'November', 'Desember']
                ];
                
                $puskesmasData = [];
                $grandTotal = [
                    'target' => 0,
                    'male' => 0,
                    'female' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                    'total' => 0
                ];
                
                foreach ($allPuskesmas as $puskesmas) {
                    $puskesmasId = $puskesmas['id'];
                    
                    // Ambil target tahunan
                    $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
                    
                    // Ambil data bulanan untuk triwulan ini
                    $monthlyData = [];
                    $quarterStats = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
                    
                    $quarterMonthNumbers = [
                        1 => [1, 2, 3],
                        2 => [4, 5, 6],
                        3 => [7, 8, 9],
                        4 => [10, 11, 12]
                    ];
                    
                    foreach ($quarterMonthNumbers[$quarter] as $month) {
                         $monthStats = $this->statisticsService->getMonthlyStatistics($puskesmasId, $year, $diseaseType, $month);
                         $monthlyData[] = $monthStats;
                        
                        $quarterStats['male'] += $monthStats['male'] ?? 0;
                        $quarterStats['female'] += $monthStats['female'] ?? 0;
                        $quarterStats['standard'] += $monthStats['standard'] ?? 0;
                        $quarterStats['non_standard'] += $monthStats['non_standard'] ?? 0;
                        $quarterStats['total'] += $monthStats['total'] ?? 0;
                    }
                    
                    $achievementPercentage = $yearlyTarget['target'] > 0 ? 
                        round(($quarterStats['standard'] / $yearlyTarget['target']) * 100, 2) : 0;
                    
                    $puskesmasData[] = [
                        'name' => $puskesmas['name'],
                        'target' => $yearlyTarget['target'],
                        'monthly' => $monthlyData,
                        'male_patients' => $quarterStats['male'],
                        'female_patients' => $quarterStats['female'],
                        'standard_patients' => $quarterStats['standard'],
                        'non_standard_patients' => $quarterStats['non_standard'],
                        'total_patients' => $quarterStats['total'],
                        'achievement_percentage' => $achievementPercentage
                    ];
                    
                    // Update grand total
                    $grandTotal['target'] += $yearlyTarget['target'];
                    $grandTotal['male'] += $quarterStats['male'];
                    $grandTotal['female'] += $quarterStats['female'];
                    $grandTotal['standard'] += $quarterStats['standard'];
                    $grandTotal['non_standard'] += $quarterStats['non_standard'];
                    $grandTotal['total'] += $quarterStats['total'];
                }
                
                $quarterData[] = [
                    'quarter' => $quarter,
                    'quarter_label' => 'TRIWULAN ' . $this->getRomanNumeral($quarter),
                    'months' => $quarterMonths[$quarter],
                    'puskesmas_data' => $puskesmasData,
                    'grand_total' => $grandTotal
                ];
            }

            return [
                'year' => $year,
                'disease_type' => $diseaseType,
                'disease_label' => $this->getDiseaseLabel($diseaseType),
                'quarter_data' => $quarterData,
                'generated_at' => Carbon::now()->format('d/m/Y H:i:s')
            ];
            
        } catch (\Exception $e) {
            Log::error('PdfFormatter: Error formatting all quarters recap', [
                'error' => $e->getMessage(),
                'year' => $year,
                'disease_type' => $diseaseType
            ]);
            throw $e;
        }
    }

    /**
     * Hitung statistik per triwulan
     */
    protected function getQuarterStatistics(int $puskesmasId, int $year, int $quarter, string $diseaseType): array
    {
        $quarterMonths = [
            1 => [1, 2, 3],   // Q1: Jan, Feb, Mar
            2 => [4, 5, 6],   // Q2: Apr, May, Jun
            3 => [7, 8, 9],   // Q3: Jul, Aug, Sep
            4 => [10, 11, 12] // Q4: Oct, Nov, Dec
        ];
        
        $months = $quarterMonths[$quarter] ?? [];
        $quarterStats = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];
        
        foreach ($months as $month) {
            $monthlyStats = $this->statisticsService->getMonthlyStatistics(
                $puskesmasId, 
                $year, 
                $diseaseType,
                $month
            );
            
            $quarterStats['male'] += $monthlyStats['male_patients'] ?? 0;
            $quarterStats['female'] += $monthlyStats['female_patients'] ?? 0;
            $quarterStats['standard'] += $monthlyStats['standard_patients'] ?? 0;
            $quarterStats['non_standard'] += $monthlyStats['non_standard_patients'] ?? 0;
        }
        
        $quarterStats['total'] = $quarterStats['male'] + $quarterStats['female'];
        
        return $quarterStats;
    }

    /**
     * Hitung total tahunan dari data bulanan
     */
    protected function calculateYearlyTotals(array $monthlyData): array
    {
        $totals = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];
        
        foreach ($monthlyData as $month => $data) {
            $totals['male'] += $data['male'] ?? 0;
            $totals['female'] += $data['female'] ?? 0;
            $totals['standard'] += $data['standard'] ?? 0;
            $totals['non_standard'] += $data['non_standard'] ?? 0;
            $totals['total'] += $data['total'] ?? 0;
        }
        
        // Hitung persentase standar tahunan
        $totals['standard_percentage'] = $totals['total'] > 0 ? 
            round(($totals['standard'] / $totals['total']) * 100, 2) : 0;
        
        return $totals;
    }

    /**
     * Get disease label
     */
    protected function getDiseaseLabel(string $diseaseType): string
    {
        switch ($diseaseType) {
            case 'ht':
                return 'Hipertensi';
            case 'dm':
                return 'Diabetes Mellitus';
            case 'both':
            case 'all':
                return 'Hipertensi & Diabetes Mellitus';
            default:
                return 'Hipertensi';
        }
    }

    /**
     * Get roman numeral for quarter
     */
    protected function getRomanNumeral(int $number): string
    {
        $romanNumerals = [
            1 => 'I',
            2 => 'II', 
            3 => 'III',
            4 => 'IV'
        ];
        
        return $romanNumerals[$number] ?? 'I';
    }

    /**
     * Generate filename untuk PDF
     */
    public function generateFilename(string $type, array $data): string
    {
        $parts = ['statistik'];
        
        if ($type === 'puskesmas') {
            $parts[] = 'puskesmas';
            $parts[] = strtolower(str_replace(' ', '_', $data['puskesmas_name'] ?? 'unknown'));
        } elseif ($type === 'all_quarters') {
            $parts[] = 'rekapitulasi_tahunan';
        }
        
        $parts[] = $data['year'] ?? date('Y');
        $parts[] = strtoupper($data['disease_type'] ?? 'ht');
        $parts[] = date('Ymd_His');
        
        return implode('_', $parts) . '.pdf';
    }
}