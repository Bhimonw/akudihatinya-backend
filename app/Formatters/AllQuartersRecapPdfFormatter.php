<?php

namespace App\Formatters;

use App\Helpers\QuarterHelper;
use App\Services\StatisticsAdminService;
use App\Models\YearlyTarget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AllQuartersRecapPdfFormatter
{
    protected $statisticsAdminService;

    public function __construct(StatisticsAdminService $statisticsAdminService)
    {
        $this->statisticsAdminService = $statisticsAdminService;
    }

    /**
     * Format data for all quarters recap PDF
     */
    public function formatAllQuartersRecapData($year, $diseaseType)
    {
        $quarters = [
            1 => ['months' => ['Januari', 'Februari', 'Maret'], 'quarter' => 'I'],
            2 => ['months' => ['April', 'Mei', 'Juni'], 'quarter' => 'II'],
            3 => ['months' => ['Juli', 'Agustus', 'September'], 'quarter' => 'III'],
            4 => ['months' => ['Oktober', 'November', 'Desember'], 'quarter' => 'IV']
        ];

        $quartersData = [];
        $diseaseTypes = $diseaseType === 'all' ? ['ht', 'dm'] : [$diseaseType];

        foreach ($diseaseTypes as $type) {
            foreach ($quarters as $quarterNum => $quarterInfo) {
                $quarterData = $this->formatQuarterData($quarterNum, $quarterInfo, $year, $type);
                $quartersData[] = $quarterData;
            }
        }

        return [
            'quarters_data' => $quartersData,
            'year' => $year,
            'disease_type' => $diseaseType,
            'disease_label' => $this->getDiseaseLabel($diseaseType),
            'generated_at' => now()->format('d/m/Y H:i:s')
        ];
    }

    /**
     * Format data for a specific quarter
     */
    protected function formatQuarterData($quarterNum, $quarterInfo, $year, $diseaseType)
    {
        // Create request for statistics service
        $request = new Request([
            'year' => $year,
            'disease_type' => $diseaseType,
            'table_type' => 'quarterly'
        ]);

        // Get statistics data from service
        $statisticsData = $this->statisticsAdminService->getAdminStatistics($request);
        
        if (isset($statisticsData['error']) && $statisticsData['error']) {
            Log::error('Failed to get statistics data for quarter', [
                'quarter' => $quarterNum,
                'year' => $year,
                'disease_type' => $diseaseType,
                'error' => $statisticsData['message'] ?? 'Unknown error'
            ]);
            
            return $this->getEmptyQuarterData($quarterInfo, $year, $diseaseType);
        }

        $puskesmasData = [];
        $grandTotal = [
            'target' => 0,
            'monthly' => [],
            'quarterly' => [[
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'total' => 0,
                'achievement_percentage' => 0
            ]],
            'total_patients' => 0,
            'achievement_percentage' => 0
        ];

        // Initialize monthly grand totals
        for ($i = 0; $i < 3; $i++) {
            $grandTotal['monthly'][] = [
                'standard' => 0,
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        // Process each puskesmas data
        if (isset($statisticsData['data']) && is_array($statisticsData['data'])) {
            foreach ($statisticsData['data'] as $puskesmas) {
                $puskesmasFormatted = $this->formatPuskesmasData($puskesmas, $quarterNum, $year, $diseaseType);
                $puskesmasData[] = $puskesmasFormatted;
                
                // Add to grand totals
                $this->addToGrandTotal($grandTotal, $puskesmasFormatted);
            }
        }

        // Calculate grand total percentages
        $this->calculateGrandTotalPercentages($grandTotal);

        return [
            'quarter' => $quarterInfo['quarter'],
            'months' => $quarterInfo['months'],
            'year' => $year,
            'disease_type' => $diseaseType,
            'disease_label' => $this->getDiseaseLabel($diseaseType),
            'puskesmas_data' => $puskesmasData,
            'grand_total' => $grandTotal
        ];
    }

    /**
     * Format individual puskesmas data
     */
    protected function formatPuskesmasData($puskesmas, $quarterNum, $year, $diseaseType)
    {
        $monthlyData = [];
        $quarterlyData = [[
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'total' => 0,
            'achievement_percentage' => 0
        ]];
        
        $totalPatients = 0;
        
        // Get disease-specific data from the nested structure
        $diseaseData = $puskesmas[$diseaseType] ?? [];
        $target = $diseaseData['target'] ?? 0;

        // Get months for this quarter
        $quarterMonths = $this->getQuarterMonths($quarterNum);
        
        // Process monthly data for this quarter
        $lastMonthData = null;
        foreach ($quarterMonths as $month) {
            $monthData = $this->getMonthlyData($puskesmas, $month, $diseaseType, $target);
            $monthlyData[] = $monthData;
            
            // Keep track of the last month with data for quarterly totals
            if ($monthData['total'] > 0 || $monthData['standard'] > 0) {
                $lastMonthData = $monthData;
            }
        }
        
        // Use data from the last month of the quarter that has data
        if ($lastMonthData !== null) {
            $quarterlyData[0]['male'] = $lastMonthData['male'];
            $quarterlyData[0]['female'] = $lastMonthData['female'];
            $quarterlyData[0]['standard'] = $lastMonthData['standard'];
            $quarterlyData[0]['total'] = $lastMonthData['total'];
            $quarterlyData[0]['achievement_percentage'] = $lastMonthData['percentage'];
        }

        // Calculate total patients and achievement percentage from last month data
        $totalPatients = $quarterlyData[0]['total'];
        $achievementPercentage = $quarterlyData[0]['achievement_percentage'];

        return [
            'name' => $puskesmas['puskesmas_name'] ?? 'Unknown',
            'target' => $target,
            'monthly' => $monthlyData,
            'quarterly' => $quarterlyData,
            'total_patients' => $totalPatients,
            'achievement_percentage' => $achievementPercentage
        ];
    }

    /**
     * Get monthly data for a specific month
     */
    protected function getMonthlyData($puskesmas, $month, $diseaseType, $target = 0)
    {
        // Get disease-specific data from the nested structure
        $diseaseData = $puskesmas[$diseaseType] ?? [];
        $monthlyData = $diseaseData['monthly_data'][$month] ?? [];
        
        $standard = $monthlyData['standard'] ?? 0;
        $male = $monthlyData['male'] ?? 0;
        $female = $monthlyData['female'] ?? 0;
        $total = $monthlyData['total'] ?? ($male + $female);
        $nonStandard = $monthlyData['non_standard'] ?? max(0, $total - $standard);
        // Calculate percentage based on standard/target (same as AdminAllFormatter)
        $percentage = $monthlyData['percentage'] ?? ($target > 0 ? round(($standard / $target) * 100, 2) : 0);

        return [
            'standard' => $standard,
            'male' => $male,
            'female' => $female,
            'total' => $total,
            'non_standard' => $nonStandard,
            'percentage' => $percentage
        ];
    }

    /**
     * Get months for a specific quarter
     */
    protected function getQuarterMonths($quarterNum)
    {
        $quarters = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12]
        ];

        return $quarters[$quarterNum] ?? [1, 2, 3];
    }

    /**
     * Add puskesmas data to grand total
     */
    protected function addToGrandTotal(&$grandTotal, $puskesmasData)
    {
        $grandTotal['target'] += $puskesmasData['target'];
        $grandTotal['total_patients'] += $puskesmasData['total_patients'];
        
        // Add monthly data
        foreach ($puskesmasData['monthly'] as $index => $monthData) {
            if (isset($grandTotal['monthly'][$index])) {
                $grandTotal['monthly'][$index]['standard'] += $monthData['standard'];
                $grandTotal['monthly'][$index]['male'] += $monthData['male'];
                $grandTotal['monthly'][$index]['female'] += $monthData['female'];
                $grandTotal['monthly'][$index]['total'] += $monthData['total'];
                $grandTotal['monthly'][$index]['non_standard'] += $monthData['non_standard'];
                $grandTotal['monthly'][$index]['percentage'] += $monthData['percentage'];
            }
        }
        
        // Use quarterly data from last month instead of accumulation
        if (isset($puskesmasData['quarterly'][0])) {
            // Find the last month with data from this puskesmas
            $lastMonthData = null;
            if (isset($puskesmasData['monthly']) && is_array($puskesmasData['monthly'])) {
                for ($i = count($puskesmasData['monthly']) - 1; $i >= 0; $i--) {
                    if ($puskesmasData['monthly'][$i]['total'] > 0 || $puskesmasData['monthly'][$i]['standard'] > 0) {
                        $lastMonthData = $puskesmasData['monthly'][$i];
                        break;
                    }
                }
            }
            
            // Use data from last month if available, otherwise use quarterly data
            if ($lastMonthData !== null) {
                $grandTotal['quarterly'][0]['male'] = $lastMonthData['male'];
                $grandTotal['quarterly'][0]['female'] = $lastMonthData['female'];
                $grandTotal['quarterly'][0]['standard'] = $lastMonthData['standard'];
                $grandTotal['quarterly'][0]['total'] = $lastMonthData['total'];
                $grandTotal['quarterly'][0]['achievement_percentage'] = $lastMonthData['percentage'];
            } else {
                $grandTotal['quarterly'][0]['male'] = $puskesmasData['quarterly'][0]['male'];
                $grandTotal['quarterly'][0]['female'] = $puskesmasData['quarterly'][0]['female'];
                $grandTotal['quarterly'][0]['standard'] = $puskesmasData['quarterly'][0]['standard'];
                $grandTotal['quarterly'][0]['total'] = $puskesmasData['quarterly'][0]['total'];
                $grandTotal['quarterly'][0]['achievement_percentage'] = $puskesmasData['quarterly'][0]['achievement_percentage'];
            }
        }
    }

    /**
     * Calculate grand total percentages
     */
    protected function calculateGrandTotalPercentages(&$grandTotal)
    {
        // Calculate monthly percentages based on standard/target (same as AdminAllFormatter)
        foreach ($grandTotal['monthly'] as &$monthData) {
            $monthData['percentage'] = $grandTotal['target'] > 0 
                ? round(($monthData['standard'] / $grandTotal['target']) * 100, 2) 
                : 0;
        }
        
        // For TOTAL STANDAR TW: use standard from previous month (last month with data)
        // For % CAPAIAN TW: use percentage from last month
        $lastMonthIndex = count($grandTotal['monthly']) - 1;
        $lastMonthWithData = null;
        
        // Find the last month that has data
        for ($i = $lastMonthIndex; $i >= 0; $i--) {
            if ($grandTotal['monthly'][$i]['standard'] > 0 || $grandTotal['monthly'][$i]['total'] > 0) {
                $lastMonthWithData = $grandTotal['monthly'][$i];
                break;
            }
        }
        
        // Set achievement percentage from last month's percentage
        if ($lastMonthWithData !== null) {
            $grandTotal['achievement_percentage'] = $lastMonthWithData['percentage'];
        } else {
            $grandTotal['achievement_percentage'] = 0;
        }
    }

    /**
     * Get empty quarter data structure
     */
    protected function getEmptyQuarterData($quarterInfo, $year, $diseaseType)
    {
        return [
            'quarter' => $quarterInfo['quarter'],
            'months' => $quarterInfo['months'],
            'year' => $year,
            'disease_type' => $diseaseType,
            'disease_label' => $this->getDiseaseLabel($diseaseType),
            'puskesmas_data' => [],
            'grand_total' => [
                'target' => 0,
                'monthly' => array_fill(0, 3, [
                    'standard' => 0,
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                    'non_standard' => 0,
                    'percentage' => 0
                ]),
                'quarterly' => [[
                    'male' => 0,
                    'female' => 0,
                    'total' => 0
                ]],
                'total_patients' => 0,
                'achievement_percentage' => 0
            ]
        ];
    }

    /**
     * Get disease label
     */
    protected function getDiseaseLabel($diseaseType)
    {
        return match ($diseaseType) {
            'ht' => 'HIPERTENSI',
            'dm' => 'DIABETES MELLITUS',
            'all' => 'HIPERTENSI & DIABETES MELLITUS',
            default => 'STATISTIK KESEHATAN'
        };
    }
}