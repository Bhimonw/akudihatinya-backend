<?php

namespace App\Formatters;

use App\Services\Statistics\StatisticsService;
use App\Models\Puskesmas;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Formatter untuk PDF reports
 * Menangani formatting data untuk template PDF
 */
class PdfFormatter
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Format data untuk quarterly recap PDF
     *
     * @param int $year
     * @param string $diseaseType
     * @param int|null $puskesmasId
     * @return array
     */
    public function formatAllQuartersRecap(int $year, string $diseaseType, ?int $puskesmasId = null): array
    {
        try {
            // Get statistics data
            $statisticsData = $this->statisticsService->getStatisticsData($diseaseType, $year, $puskesmasId);
            
            // Format quarterly data
            $quarterData = [];
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $quarterData[$quarter] = $this->formatQuarterData($statisticsData, $quarter, $diseaseType);
            }
            
            // Calculate yearly totals
            $yearlyTotals = $this->calculateYearlyTotals($quarterData, $diseaseType);
            
            $formattedData = [
                'year' => $year,
                'disease_type' => $diseaseType,
                'disease_label' => $this->getDiseaseLabel($diseaseType),
                'quarter_data' => $quarterData,
                'yearly_totals' => $yearlyTotals,
                'generated_date' => Carbon::now()->format('d/m/Y'),
                'generated_time' => Carbon::now()->format('H:i:s'),
                'report_title' => $this->getReportTitle($diseaseType, $year)
            ];
            
            // Add puskesmas info if specific puskesmas
            if ($puskesmasId) {
                $puskesmas = Puskesmas::find($puskesmasId);
                if ($puskesmas) {
                    $formattedData['puskesmas_name'] = $puskesmas->name;
                    $formattedData['puskesmas_id'] = $puskesmasId;
                }
            }
            
            Log::info('PdfFormatter: Successfully formatted quarterly recap data', [
                'year' => $year,
                'disease_type' => $diseaseType,
                'puskesmas_id' => $puskesmasId,
                'quarters_count' => count($quarterData)
            ]);
            
            return $formattedData;
            
        } catch (\Exception $e) {
            Log::error('PdfFormatter: Error formatting quarterly recap data', [
                'error' => $e->getMessage(),
                'year' => $year,
                'disease_type' => $diseaseType,
                'puskesmas_id' => $puskesmasId
            ]);
            throw $e;
        }
    }

    /**
     * Format data untuk puskesmas statistics PDF
     *
     * @param int $puskesmasId
     * @param int $year
     * @param string $diseaseType
     * @return array
     */
    public function formatPuskesmasStatistics(int $puskesmasId, int $year, string $diseaseType): array
    {
        try {
            $puskesmas = Puskesmas::find($puskesmasId);
            if (!$puskesmas) {
                throw new \Exception('Puskesmas not found');
            }
            
            // Get statistics data for specific puskesmas
            $statisticsData = $this->statisticsService->getStatisticsData($diseaseType, $year, $puskesmasId);
            
            // Format monthly data
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyData[$month] = $this->formatMonthData($statisticsData, $month, $diseaseType);
            }
            
            // Calculate yearly summary
            $yearlySummary = $this->calculateYearlySummary($monthlyData, $diseaseType);
            
            $formattedData = [
                'puskesmas_id' => $puskesmasId,
                'puskesmas_name' => $puskesmas->name,
                'year' => $year,
                'disease_type' => $diseaseType,
                'disease_label' => $this->getDiseaseLabel($diseaseType),
                'monthly_data' => $monthlyData,
                'yearly_summary' => $yearlySummary,
                'generated_date' => Carbon::now()->format('d/m/Y'),
                'generated_time' => Carbon::now()->format('H:i:s'),
                'report_title' => $this->getPuskesmasReportTitle($puskesmas->name, $diseaseType, $year)
            ];
            
            Log::info('PdfFormatter: Successfully formatted puskesmas statistics', [
                'puskesmas_id' => $puskesmasId,
                'puskesmas_name' => $puskesmas->name,
                'year' => $year,
                'disease_type' => $diseaseType
            ]);
            
            return $formattedData;
            
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
     * Generate filename untuk puskesmas PDF
     *
     * @param string $diseaseType
     * @param int $year
     * @param array $data
     * @return string
     */
    public function generatePuskesmasFilename(string $diseaseType, int $year, array $data): string
    {
        $diseaseLabel = $diseaseType === 'dm' ? 'DM' : 'HT';
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $puskesmasName = $data['puskesmas_name'] ?? 'Unknown';
        $cleanPuskesmasName = preg_replace('/[^A-Za-z0-9_-]/', '_', $puskesmasName);

        return "Laporan_SPM_{$cleanPuskesmasName}_{$diseaseLabel}_{$year}_{$timestamp}.pdf";
    }

    /**
     * Format data untuk quarter tertentu
     */
    private function formatQuarterData(array $statisticsData, int $quarter, string $diseaseType): array
    {
        $quarterMonths = $this->getQuarterMonths($quarter);
        $quarterData = [
            'quarter' => $quarter,
            'months' => $quarterMonths,
            'puskesmas_data' => [],
            'totals' => $this->initializeTotals($diseaseType)
        ];
        
        // Process each puskesmas data for this quarter
        foreach ($statisticsData as $puskesmasData) {
            $puskesmasQuarterData = $this->calculatePuskesmasQuarterData($puskesmasData, $quarterMonths, $diseaseType);
            $quarterData['puskesmas_data'][] = $puskesmasQuarterData;
            
            // Add to quarter totals
            $this->addToTotals($quarterData['totals'], $puskesmasQuarterData, $diseaseType);
        }
        
        return $quarterData;
    }

    /**
     * Format data untuk month tertentu
     */
    private function formatMonthData(array $statisticsData, int $month, string $diseaseType): array
    {
        $monthData = [
            'month' => $month,
            'month_name' => $this->getMonthName($month),
            'data' => $this->initializeTotals($diseaseType)
        ];
        
        // Process statistics data for this month
        foreach ($statisticsData as $data) {
            if (isset($data['monthly_data'][$month])) {
                $monthlyData = $data['monthly_data'][$month];
                $this->addMonthlyDataToTotals($monthData['data'], $monthlyData, $diseaseType);
            }
        }
        
        return $monthData;
    }

    /**
     * Calculate yearly totals dari quarter data
     */
    private function calculateYearlyTotals(array $quarterData, string $diseaseType): array
    {
        $yearlyTotals = $this->initializeTotals($diseaseType);
        
        foreach ($quarterData as $quarter) {
            $this->addToTotals($yearlyTotals, $quarter['totals'], $diseaseType);
        }
        
        return $yearlyTotals;
    }

    /**
     * Calculate yearly summary dari monthly data
     */
    private function calculateYearlySummary(array $monthlyData, string $diseaseType): array
    {
        $yearlySummary = $this->initializeTotals($diseaseType);
        
        foreach ($monthlyData as $month) {
            $this->addToTotals($yearlySummary, $month['data'], $diseaseType);
        }
        
        return $yearlySummary;
    }

    /**
     * Get months dalam quarter
     */
    protected function getQuarterMonths(int $quarter): array
    {
        switch ($quarter) {
            case 1: return [1, 2, 3];
            case 2: return [4, 5, 6];
            case 3: return [7, 8, 9];
            case 4: return [10, 11, 12];
            default: return [];
        }
    }

    /**
     * Get nama bulan
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $months[$month] ?? '';
    }

    /**
     * Get disease label
     */
    protected function getDiseaseLabel(string $diseaseType): string
    {
        switch ($diseaseType) {
            case 'ht': return 'Hipertensi';
            case 'dm': return 'Diabetes Melitus';
            case 'all': return 'Hipertensi & Diabetes Melitus';
            default: return 'Hipertensi & Diabetes Melitus';
        }
    }

    /**
     * Get report title
     */
    private function getReportTitle(string $diseaseType, int $year): string
    {
        $diseaseLabel = $this->getDiseaseLabel($diseaseType);
        return "Laporan Rekapitulasi {$diseaseLabel} Tahun {$year}";
    }

    /**
     * Get puskesmas report title
     */
    private function getPuskesmasReportTitle(string $puskesmasName, string $diseaseType, int $year): string
    {
        $diseaseLabel = $this->getDiseaseLabel($diseaseType);
        return "Laporan {$diseaseLabel} {$puskesmasName} Tahun {$year}";
    }

    /**
     * Initialize totals structure
     */
    private function initializeTotals(string $diseaseType): array
    {
        $totals = [];
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $totals['ht'] = [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'non_standard_patients' => 0,
                'male_patients' => 0,
                'female_patients' => 0,
                'achievement_percentage' => 0
            ];
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $totals['dm'] = [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'non_standard_patients' => 0,
                'male_patients' => 0,
                'female_patients' => 0,
                'achievement_percentage' => 0
            ];
        }
        
        return $totals;
    }

    /**
     * Add data to totals
     */
    private function addToTotals(array &$totals, array $data, string $diseaseType): void
    {
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            if (isset($data['ht'])) {
                foreach ($data['ht'] as $key => $value) {
                    if ($key !== 'achievement_percentage' && is_numeric($value)) {
                        $totals['ht'][$key] += $value;
                    }
                }
                // Recalculate achievement percentage
                $totals['ht']['achievement_percentage'] = $this->calculatePercentage(
                    $totals['ht']['standard_patients'],
                    $totals['ht']['target']
                );
            }
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if (isset($data['dm'])) {
                foreach ($data['dm'] as $key => $value) {
                    if ($key !== 'achievement_percentage' && is_numeric($value)) {
                        $totals['dm'][$key] += $value;
                    }
                }
                // Recalculate achievement percentage
                $totals['dm']['achievement_percentage'] = $this->calculatePercentage(
                    $totals['dm']['standard_patients'],
                    $totals['dm']['target']
                );
            }
        }
    }

    /**
     * Add monthly data to totals
     */
    private function addMonthlyDataToTotals(array &$totals, array $monthlyData, string $diseaseType): void
    {
        // Implementation depends on monthly data structure
        // This is a placeholder - adjust based on actual monthly data format
        $this->addToTotals($totals, $monthlyData, $diseaseType);
    }

    /**
     * Calculate puskesmas quarter data
     */
    private function calculatePuskesmasQuarterData(array $puskesmasData, array $quarterMonths, string $diseaseType): array
    {
        $quarterData = [
            'puskesmas_name' => $puskesmasData['puskesmas_name'] ?? '',
            'puskesmas_id' => $puskesmasData['puskesmas_id'] ?? null
        ];
        
        // Initialize disease data
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $quarterData['ht'] = $this->initializeTotals('ht')['ht'];
        }
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $quarterData['dm'] = $this->initializeTotals('dm')['dm'];
        }
        
        // Sum data for quarter months
        foreach ($quarterMonths as $month) {
            if (isset($puskesmasData['monthly_data'][$month])) {
                $monthData = $puskesmasData['monthly_data'][$month];
                $this->addToTotals($quarterData, $monthData, $diseaseType);
            }
        }
        
        return $quarterData;
    }

    /**
     * Calculate percentage dengan handling division by zero
     */
    protected function calculatePercentage($numerator, $denominator): float
    {
        if ($denominator == 0) {
            return 0;
        }
        
        return round(($numerator / $denominator) * 100, 2);
    }
}