<?php

namespace App\Formatters\Strategies\Calculation;

use App\Formatters\Strategies\BaseFormatterStrategy;

/**
 * Strategy untuk perhitungan data
 * Menangani semua perhitungan statistik, persentase, total, dan analisis data
 */
class CalculationStrategy extends BaseFormatterStrategy
{
    protected string $name = 'CalculationStrategy';

    protected array $defaultOptions = [
        'calculation_type' => 'comprehensive', // comprehensive, basic, advanced
        'include_percentages' => true,
        'include_totals' => true,
        'include_averages' => true,
        'include_quarterly_data' => true,
        'include_yearly_data' => true,
        'include_achievement_analysis' => true,
        'include_trend_analysis' => false,
        'include_comparison' => false,
        'precision' => 2,
        'percentage_precision' => 2,
        'target_threshold' => 80, // Default achievement threshold
        'warning_threshold' => 60,
        'critical_threshold' => 40
    ];

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Starting calculation processing', [
            'data_count' => count($data),
            'calculation_type' => $options['calculation_type']
        ]);

        try {
            $results = [];
            $summary = [];
            $metadata = [];

            // Process each data item
            foreach ($data as $index => $item) {
                $itemResult = $this->calculateSingleItem($item, $index, $options);
                $results[] = $itemResult;
            }

            // Calculate summary statistics
            if ($options['include_totals'] || $options['include_averages']) {
                $summary = $this->calculateSummaryStatistics($results, $options);
            }

            // Calculate quarterly data
            if ($options['include_quarterly_data']) {
                $quarterlyData = $this->calculateQuarterlyData($results, $options);
                $summary['quarterly'] = $quarterlyData;
            }

            // Calculate yearly data
            if ($options['include_yearly_data']) {
                $yearlyData = $this->calculateYearlyData($results, $options);
                $summary['yearly'] = $yearlyData;
            }

            // Achievement analysis
            if ($options['include_achievement_analysis']) {
                $achievementAnalysis = $this->calculateAchievementAnalysis($results, $options);
                $summary['achievement'] = $achievementAnalysis;
            }

            // Trend analysis
            if ($options['include_trend_analysis']) {
                $trendAnalysis = $this->calculateTrendAnalysis($results, $options);
                $summary['trends'] = $trendAnalysis;
            }

            // Comparison analysis
            if ($options['include_comparison']) {
                $comparisonAnalysis = $this->calculateComparisonAnalysis($results, $options);
                $summary['comparison'] = $comparisonAnalysis;
            }

            // Generate metadata
            $metadata = $this->generateCalculationMetadata($results, $summary, $options);

            return [
                'success' => true,
                'calculation_type' => $options['calculation_type'],
                'results' => $results,
                'summary' => $summary,
                'metadata' => $metadata
            ];

        } catch (\Exception $e) {
            $this->log('Calculation processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException('Calculation processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Calculate single data item
     *
     * @param array $item Data item
     * @param int $index Item index
     * @param array $options Calculation options
     * @return array Calculated result
     */
    private function calculateSingleItem(array $item, int $index, array $options): array
    {
        $result = [
            'index' => $index,
            'original_data' => $item,
            'calculated_data' => []
        ];

        // Calculate HT data if present
        if (isset($item['ht']) || isset($item['ht_data'])) {
            $htData = $item['ht'] ?? $item['ht_data'] ?? [];
            $result['calculated_data']['ht'] = $this->calculateDiseaseData($htData, 'ht', $options);
        }

        // Calculate DM data if present
        if (isset($item['dm']) || isset($item['dm_data'])) {
            $dmData = $item['dm'] ?? $item['dm_data'] ?? [];
            $result['calculated_data']['dm'] = $this->calculateDiseaseData($dmData, 'dm', $options);
        }

        // Calculate monthly data if present
        if (isset($item['monthly_data'])) {
            $result['calculated_data']['monthly'] = $this->calculateMonthlyData($item['monthly_data'], $options);
        }

        // Calculate combined totals
        $result['calculated_data']['totals'] = $this->calculateCombinedTotals($result['calculated_data'], $options);

        // Calculate overall achievement
        $result['calculated_data']['achievement'] = $this->calculateOverallAchievement($result['calculated_data'], $options);

        return $result;
    }

    /**
     * Calculate disease-specific data (HT or DM)
     *
     * @param array $diseaseData Disease data
     * @param string $diseaseType Disease type (ht/dm)
     * @param array $options Calculation options
     * @return array Calculated disease data
     */
    private function calculateDiseaseData(array $diseaseData, string $diseaseType, array $options): array
    {
        $result = [
            'disease_type' => $diseaseType,
            'raw_data' => $diseaseData
        ];

        // Extract basic values
        $target = $diseaseData['target'] ?? 0;
        $totalPatients = $diseaseData['total_patients'] ?? $diseaseData['total'] ?? 0;
        $standardPatients = $diseaseData['standard_patients'] ?? $diseaseData['standard'] ?? 0;

        $result['target'] = $target;
        $result['total_patients'] = $totalPatients;
        $result['standard_patients'] = $standardPatients;

        // Calculate percentages
        if ($options['include_percentages']) {
            $result['percentages'] = $this->calculateDiseasePercentages(
                $target, $totalPatients, $standardPatients, $options
            );
        }

        // Calculate achievement status
        $result['achievement_status'] = $this->calculateAchievementStatus(
            $target, $totalPatients, $options
        );

        // Calculate monthly breakdown if available
        if (isset($diseaseData['monthly']) && is_array($diseaseData['monthly'])) {
            $result['monthly_breakdown'] = $this->calculateMonthlyBreakdown(
                $diseaseData['monthly'], $target, $options
            );
        }

        // Calculate quarterly breakdown if available
        if (isset($diseaseData['quarterly']) && is_array($diseaseData['quarterly'])) {
            $result['quarterly_breakdown'] = $this->calculateQuarterlyBreakdown(
                $diseaseData['quarterly'], $target, $options
            );
        }

        return $result;
    }

    /**
     * Calculate disease percentages
     *
     * @param float $target Target value
     * @param float $totalPatients Total patients
     * @param float $standardPatients Standard patients
     * @param array $options Calculation options
     * @return array Percentage calculations
     */
    private function calculateDiseasePercentages(float $target, float $totalPatients, float $standardPatients, array $options): array
    {
        $precision = $options['percentage_precision'];
        
        $percentages = [
            'achievement_percentage' => 0,
            'standard_percentage' => 0,
            'target_completion' => 0
        ];

        // Calculate achievement percentage (total patients vs target)
        if ($target > 0) {
            $percentages['achievement_percentage'] = round(($totalPatients / $target) * 100, $precision);
            $percentages['target_completion'] = round(($totalPatients / $target) * 100, $precision);
        }

        // Calculate standard percentage (standard patients vs total patients)
        if ($totalPatients > 0) {
            $percentages['standard_percentage'] = round(($standardPatients / $totalPatients) * 100, $precision);
        }

        // Calculate standard vs target
        if ($target > 0) {
            $percentages['standard_vs_target'] = round(($standardPatients / $target) * 100, $precision);
        }

        return $percentages;
    }

    /**
     * Calculate achievement status
     *
     * @param float $target Target value
     * @param float $totalPatients Total patients
     * @param array $options Calculation options
     * @return array Achievement status
     */
    private function calculateAchievementStatus(float $target, float $totalPatients, array $options): array
    {
        $achievementPercentage = $target > 0 ? ($totalPatients / $target) * 100 : 0;
        
        $status = [
            'percentage' => round($achievementPercentage, $options['percentage_precision']),
            'level' => $this->getAchievementLevel($achievementPercentage, $options),
            'status' => $this->getAchievementStatus($achievementPercentage, $options),
            'target_met' => $achievementPercentage >= 100,
            'above_threshold' => $achievementPercentage >= $options['target_threshold']
        ];

        return $status;
    }

    /**
     * Get achievement level
     *
     * @param float $percentage Achievement percentage
     * @param array $options Calculation options
     * @return string Achievement level
     */
    private function getAchievementLevel(float $percentage, array $options): string
    {
        if ($percentage >= 100) {
            return 'excellent';
        } elseif ($percentage >= $options['target_threshold']) {
            return 'good';
        } elseif ($percentage >= $options['warning_threshold']) {
            return 'fair';
        } elseif ($percentage >= $options['critical_threshold']) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    /**
     * Get achievement status
     *
     * @param float $percentage Achievement percentage
     * @param array $options Calculation options
     * @return string Achievement status
     */
    private function getAchievementStatus(float $percentage, array $options): string
    {
        if ($percentage >= 100) {
            return 'Target Tercapai';
        } elseif ($percentage >= $options['target_threshold']) {
            return 'Hampir Tercapai';
        } elseif ($percentage >= $options['warning_threshold']) {
            return 'Perlu Peningkatan';
        } elseif ($percentage >= $options['critical_threshold']) {
            return 'Kurang';
        } else {
            return 'Sangat Kurang';
        }
    }

    /**
     * Calculate monthly breakdown
     *
     * @param array $monthlyData Monthly data
     * @param float $yearlyTarget Yearly target
     * @param array $options Calculation options
     * @return array Monthly breakdown
     */
    private function calculateMonthlyBreakdown(array $monthlyData, float $yearlyTarget, array $options): array
    {
        $breakdown = [
            'months' => [],
            'totals' => [
                'total_patients' => 0,
                'standard_patients' => 0
            ],
            'averages' => [],
            'trends' => []
        ];

        $monthlyTarget = $yearlyTarget > 0 ? $yearlyTarget / 12 : 0;
        $cumulativeTotal = 0;
        $cumulativeStandard = 0;

        foreach ($monthlyData as $month => $data) {
            $totalPatients = $data['total_patients'] ?? $data['total'] ?? 0;
            $standardPatients = $data['standard_patients'] ?? $data['standard'] ?? 0;
            
            $cumulativeTotal += $totalPatients;
            $cumulativeStandard += $standardPatients;

            $monthResult = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatients,
                'monthly_target' => $monthlyTarget,
                'cumulative_total' => $cumulativeTotal,
                'cumulative_standard' => $cumulativeStandard
            ];

            // Calculate monthly percentages
            if ($options['include_percentages']) {
                $monthResult['percentages'] = [
                    'monthly_achievement' => $monthlyTarget > 0 ? round(($totalPatients / $monthlyTarget) * 100, $options['percentage_precision']) : 0,
                    'cumulative_achievement' => $yearlyTarget > 0 ? round(($cumulativeTotal / $yearlyTarget) * 100, $options['percentage_precision']) : 0,
                    'standard_rate' => $totalPatients > 0 ? round(($standardPatients / $totalPatients) * 100, $options['percentage_precision']) : 0
                ];
            }

            $breakdown['months'][$month] = $monthResult;
        }

        // Calculate totals
        $breakdown['totals']['total_patients'] = $cumulativeTotal;
        $breakdown['totals']['standard_patients'] = $cumulativeStandard;

        // Calculate averages
        if ($options['include_averages'] && count($monthlyData) > 0) {
            $breakdown['averages'] = [
                'avg_monthly_total' => round($cumulativeTotal / count($monthlyData), $options['precision']),
                'avg_monthly_standard' => round($cumulativeStandard / count($monthlyData), $options['precision'])
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate quarterly breakdown
     *
     * @param array $quarterlyData Quarterly data
     * @param float $yearlyTarget Yearly target
     * @param array $options Calculation options
     * @return array Quarterly breakdown
     */
    private function calculateQuarterlyBreakdown(array $quarterlyData, float $yearlyTarget, array $options): array
    {
        $breakdown = [
            'quarters' => [],
            'totals' => [
                'total_patients' => 0,
                'standard_patients' => 0
            ],
            'averages' => []
        ];

        $quarterlyTarget = $yearlyTarget > 0 ? $yearlyTarget / 4 : 0;
        $cumulativeTotal = 0;
        $cumulativeStandard = 0;

        foreach ($quarterlyData as $quarter => $data) {
            $totalPatients = $data['total_patients'] ?? $data['total'] ?? 0;
            $standardPatients = $data['standard_patients'] ?? $data['standard'] ?? 0;
            
            $cumulativeTotal += $totalPatients;
            $cumulativeStandard += $standardPatients;

            $quarterResult = [
                'quarter' => $quarter,
                'quarter_name' => 'Triwulan ' . $this->getRomanNumeral($quarter),
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatients,
                'quarterly_target' => $quarterlyTarget,
                'cumulative_total' => $cumulativeTotal,
                'cumulative_standard' => $cumulativeStandard
            ];

            // Calculate quarterly percentages
            if ($options['include_percentages']) {
                $quarterResult['percentages'] = [
                    'quarterly_achievement' => $quarterlyTarget > 0 ? round(($totalPatients / $quarterlyTarget) * 100, $options['percentage_precision']) : 0,
                    'cumulative_achievement' => $yearlyTarget > 0 ? round(($cumulativeTotal / $yearlyTarget) * 100, $options['percentage_precision']) : 0,
                    'standard_rate' => $totalPatients > 0 ? round(($standardPatients / $totalPatients) * 100, $options['percentage_precision']) : 0
                ];
            }

            $breakdown['quarters'][$quarter] = $quarterResult;
        }

        // Calculate totals
        $breakdown['totals']['total_patients'] = $cumulativeTotal;
        $breakdown['totals']['standard_patients'] = $cumulativeStandard;

        // Calculate averages
        if ($options['include_averages'] && count($quarterlyData) > 0) {
            $breakdown['averages'] = [
                'avg_quarterly_total' => round($cumulativeTotal / count($quarterlyData), $options['precision']),
                'avg_quarterly_standard' => round($cumulativeStandard / count($quarterlyData), $options['precision'])
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate monthly data
     *
     * @param array $monthlyData Monthly data
     * @param array $options Calculation options
     * @return array Calculated monthly data
     */
    private function calculateMonthlyData(array $monthlyData, array $options): array
    {
        $result = [
            'months' => [],
            'summary' => [
                'total_months' => count($monthlyData),
                'ht_total' => 0,
                'dm_total' => 0,
                'combined_total' => 0
            ]
        ];

        foreach ($monthlyData as $month => $data) {
            $monthResult = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'data' => $data
            ];

            // Calculate HT data for this month
            if (isset($data['ht'])) {
                $monthResult['ht'] = $this->calculateDiseaseData($data['ht'], 'ht', $options);
                $result['summary']['ht_total'] += $data['ht']['total_patients'] ?? $data['ht']['total'] ?? 0;
            }

            // Calculate DM data for this month
            if (isset($data['dm'])) {
                $monthResult['dm'] = $this->calculateDiseaseData($data['dm'], 'dm', $options);
                $result['summary']['dm_total'] += $data['dm']['total_patients'] ?? $data['dm']['total'] ?? 0;
            }

            $result['months'][$month] = $monthResult;
        }

        $result['summary']['combined_total'] = $result['summary']['ht_total'] + $result['summary']['dm_total'];

        return $result;
    }

    /**
     * Calculate combined totals
     *
     * @param array $calculatedData Calculated data
     * @param array $options Calculation options
     * @return array Combined totals
     */
    private function calculateCombinedTotals(array $calculatedData, array $options): array
    {
        $totals = [
            'ht' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0
            ],
            'dm' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0
            ],
            'combined' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0
            ]
        ];

        // Sum HT data
        if (isset($calculatedData['ht'])) {
            $totals['ht']['target'] = $calculatedData['ht']['target'] ?? 0;
            $totals['ht']['total_patients'] = $calculatedData['ht']['total_patients'] ?? 0;
            $totals['ht']['standard_patients'] = $calculatedData['ht']['standard_patients'] ?? 0;
        }

        // Sum DM data
        if (isset($calculatedData['dm'])) {
            $totals['dm']['target'] = $calculatedData['dm']['target'] ?? 0;
            $totals['dm']['total_patients'] = $calculatedData['dm']['total_patients'] ?? 0;
            $totals['dm']['standard_patients'] = $calculatedData['dm']['standard_patients'] ?? 0;
        }

        // Calculate combined totals
        $totals['combined']['target'] = $totals['ht']['target'] + $totals['dm']['target'];
        $totals['combined']['total_patients'] = $totals['ht']['total_patients'] + $totals['dm']['total_patients'];
        $totals['combined']['standard_patients'] = $totals['ht']['standard_patients'] + $totals['dm']['standard_patients'];

        // Calculate percentages for combined data
        if ($options['include_percentages']) {
            $totals['combined']['percentages'] = $this->calculateDiseasePercentages(
                $totals['combined']['target'],
                $totals['combined']['total_patients'],
                $totals['combined']['standard_patients'],
                $options
            );
        }

        return $totals;
    }

    /**
     * Calculate overall achievement
     *
     * @param array $calculatedData Calculated data
     * @param array $options Calculation options
     * @return array Overall achievement
     */
    private function calculateOverallAchievement(array $calculatedData, array $options): array
    {
        $achievement = [
            'ht_achievement' => 0,
            'dm_achievement' => 0,
            'combined_achievement' => 0,
            'overall_status' => 'unknown',
            'overall_level' => 'unknown'
        ];

        // Calculate HT achievement
        if (isset($calculatedData['ht']['achievement_status'])) {
            $achievement['ht_achievement'] = $calculatedData['ht']['achievement_status']['percentage'] ?? 0;
        }

        // Calculate DM achievement
        if (isset($calculatedData['dm']['achievement_status'])) {
            $achievement['dm_achievement'] = $calculatedData['dm']['achievement_status']['percentage'] ?? 0;
        }

        // Calculate combined achievement (average of HT and DM)
        if ($achievement['ht_achievement'] > 0 && $achievement['dm_achievement'] > 0) {
            $achievement['combined_achievement'] = ($achievement['ht_achievement'] + $achievement['dm_achievement']) / 2;
        } elseif ($achievement['ht_achievement'] > 0) {
            $achievement['combined_achievement'] = $achievement['ht_achievement'];
        } elseif ($achievement['dm_achievement'] > 0) {
            $achievement['combined_achievement'] = $achievement['dm_achievement'];
        }

        // Determine overall status and level
        $achievement['overall_status'] = $this->getAchievementStatus($achievement['combined_achievement'], $options);
        $achievement['overall_level'] = $this->getAchievementLevel($achievement['combined_achievement'], $options);

        return $achievement;
    }

    /**
     * Calculate summary statistics
     *
     * @param array $results All calculated results
     * @param array $options Calculation options
     * @return array Summary statistics
     */
    private function calculateSummaryStatistics(array $results, array $options): array
    {
        $summary = [
            'total_items' => count($results),
            'ht_summary' => [
                'total_target' => 0,
                'total_patients' => 0,
                'total_standard' => 0,
                'avg_achievement' => 0
            ],
            'dm_summary' => [
                'total_target' => 0,
                'total_patients' => 0,
                'total_standard' => 0,
                'avg_achievement' => 0
            ],
            'combined_summary' => [
                'total_target' => 0,
                'total_patients' => 0,
                'total_standard' => 0,
                'avg_achievement' => 0
            ],
            'achievement_distribution' => [
                'excellent' => 0,
                'good' => 0,
                'fair' => 0,
                'poor' => 0,
                'critical' => 0
            ]
        ];

        $htAchievements = [];
        $dmAchievements = [];
        $combinedAchievements = [];

        foreach ($results as $result) {
            $calculatedData = $result['calculated_data'] ?? [];

            // Sum HT data
            if (isset($calculatedData['ht'])) {
                $summary['ht_summary']['total_target'] += $calculatedData['ht']['target'] ?? 0;
                $summary['ht_summary']['total_patients'] += $calculatedData['ht']['total_patients'] ?? 0;
                $summary['ht_summary']['total_standard'] += $calculatedData['ht']['standard_patients'] ?? 0;
                
                if (isset($calculatedData['ht']['achievement_status']['percentage'])) {
                    $htAchievements[] = $calculatedData['ht']['achievement_status']['percentage'];
                }
            }

            // Sum DM data
            if (isset($calculatedData['dm'])) {
                $summary['dm_summary']['total_target'] += $calculatedData['dm']['target'] ?? 0;
                $summary['dm_summary']['total_patients'] += $calculatedData['dm']['total_patients'] ?? 0;
                $summary['dm_summary']['total_standard'] += $calculatedData['dm']['standard_patients'] ?? 0;
                
                if (isset($calculatedData['dm']['achievement_status']['percentage'])) {
                    $dmAchievements[] = $calculatedData['dm']['achievement_status']['percentage'];
                }
            }

            // Track achievement distribution
            if (isset($calculatedData['achievement']['overall_level'])) {
                $level = $calculatedData['achievement']['overall_level'];
                if (isset($summary['achievement_distribution'][$level])) {
                    $summary['achievement_distribution'][$level]++;
                }
                
                if (isset($calculatedData['achievement']['combined_achievement'])) {
                    $combinedAchievements[] = $calculatedData['achievement']['combined_achievement'];
                }
            }
        }

        // Calculate combined summary
        $summary['combined_summary']['total_target'] = $summary['ht_summary']['total_target'] + $summary['dm_summary']['total_target'];
        $summary['combined_summary']['total_patients'] = $summary['ht_summary']['total_patients'] + $summary['dm_summary']['total_patients'];
        $summary['combined_summary']['total_standard'] = $summary['ht_summary']['total_standard'] + $summary['dm_summary']['total_standard'];

        // Calculate average achievements
        if (count($htAchievements) > 0) {
            $summary['ht_summary']['avg_achievement'] = round(array_sum($htAchievements) / count($htAchievements), $options['percentage_precision']);
        }
        
        if (count($dmAchievements) > 0) {
            $summary['dm_summary']['avg_achievement'] = round(array_sum($dmAchievements) / count($dmAchievements), $options['percentage_precision']);
        }
        
        if (count($combinedAchievements) > 0) {
            $summary['combined_summary']['avg_achievement'] = round(array_sum($combinedAchievements) / count($combinedAchievements), $options['percentage_precision']);
        }

        // Calculate overall percentages
        if ($options['include_percentages']) {
            $summary['ht_summary']['percentages'] = $this->calculateDiseasePercentages(
                $summary['ht_summary']['total_target'],
                $summary['ht_summary']['total_patients'],
                $summary['ht_summary']['total_standard'],
                $options
            );
            
            $summary['dm_summary']['percentages'] = $this->calculateDiseasePercentages(
                $summary['dm_summary']['total_target'],
                $summary['dm_summary']['total_patients'],
                $summary['dm_summary']['total_standard'],
                $options
            );
            
            $summary['combined_summary']['percentages'] = $this->calculateDiseasePercentages(
                $summary['combined_summary']['total_target'],
                $summary['combined_summary']['total_patients'],
                $summary['combined_summary']['total_standard'],
                $options
            );
        }

        return $summary;
    }

    /**
     * Calculate quarterly data
     *
     * @param array $results All calculated results
     * @param array $options Calculation options
     * @return array Quarterly data
     */
    private function calculateQuarterlyData(array $results, array $options): array
    {
        $quarterlyData = [
            'quarters' => [
                1 => ['ht' => ['total' => 0, 'standard' => 0], 'dm' => ['total' => 0, 'standard' => 0]],
                2 => ['ht' => ['total' => 0, 'standard' => 0], 'dm' => ['total' => 0, 'standard' => 0]],
                3 => ['ht' => ['total' => 0, 'standard' => 0], 'dm' => ['total' => 0, 'standard' => 0]],
                4 => ['ht' => ['total' => 0, 'standard' => 0], 'dm' => ['total' => 0, 'standard' => 0]]
            ],
            'summary' => []
        ];

        // This is a simplified quarterly calculation
        // In a real implementation, you would need monthly data to properly calculate quarters
        foreach ($results as $result) {
            $calculatedData = $result['calculated_data'] ?? [];
            
            // For now, distribute yearly data evenly across quarters
            if (isset($calculatedData['ht'])) {
                $htTotal = $calculatedData['ht']['total_patients'] ?? 0;
                $htStandard = $calculatedData['ht']['standard_patients'] ?? 0;
                
                for ($q = 1; $q <= 4; $q++) {
                    $quarterlyData['quarters'][$q]['ht']['total'] += $htTotal / 4;
                    $quarterlyData['quarters'][$q]['ht']['standard'] += $htStandard / 4;
                }
            }
            
            if (isset($calculatedData['dm'])) {
                $dmTotal = $calculatedData['dm']['total_patients'] ?? 0;
                $dmStandard = $calculatedData['dm']['standard_patients'] ?? 0;
                
                for ($q = 1; $q <= 4; $q++) {
                    $quarterlyData['quarters'][$q]['dm']['total'] += $dmTotal / 4;
                    $quarterlyData['quarters'][$q]['dm']['standard'] += $dmStandard / 4;
                }
            }
        }

        // Calculate quarterly percentages and summaries
        foreach ($quarterlyData['quarters'] as $quarter => &$data) {
            $data['quarter_name'] = 'Triwulan ' . $this->getRomanNumeral($quarter);
            $data['combined'] = [
                'total' => $data['ht']['total'] + $data['dm']['total'],
                'standard' => $data['ht']['standard'] + $data['dm']['standard']
            ];
            
            // Round values
            $data['ht']['total'] = round($data['ht']['total'], $options['precision']);
            $data['ht']['standard'] = round($data['ht']['standard'], $options['precision']);
            $data['dm']['total'] = round($data['dm']['total'], $options['precision']);
            $data['dm']['standard'] = round($data['dm']['standard'], $options['precision']);
            $data['combined']['total'] = round($data['combined']['total'], $options['precision']);
            $data['combined']['standard'] = round($data['combined']['standard'], $options['precision']);
        }

        return $quarterlyData;
    }

    /**
     * Calculate yearly data
     *
     * @param array $results All calculated results
     * @param array $options Calculation options
     * @return array Yearly data
     */
    private function calculateYearlyData(array $results, array $options): array
    {
        $yearlyData = [
            'ht' => ['target' => 0, 'total' => 0, 'standard' => 0],
            'dm' => ['target' => 0, 'total' => 0, 'standard' => 0],
            'combined' => ['target' => 0, 'total' => 0, 'standard' => 0]
        ];

        foreach ($results as $result) {
            $calculatedData = $result['calculated_data'] ?? [];
            
            if (isset($calculatedData['ht'])) {
                $yearlyData['ht']['target'] += $calculatedData['ht']['target'] ?? 0;
                $yearlyData['ht']['total'] += $calculatedData['ht']['total_patients'] ?? 0;
                $yearlyData['ht']['standard'] += $calculatedData['ht']['standard_patients'] ?? 0;
            }
            
            if (isset($calculatedData['dm'])) {
                $yearlyData['dm']['target'] += $calculatedData['dm']['target'] ?? 0;
                $yearlyData['dm']['total'] += $calculatedData['dm']['total_patients'] ?? 0;
                $yearlyData['dm']['standard'] += $calculatedData['dm']['standard_patients'] ?? 0;
            }
        }

        // Calculate combined yearly data
        $yearlyData['combined']['target'] = $yearlyData['ht']['target'] + $yearlyData['dm']['target'];
        $yearlyData['combined']['total'] = $yearlyData['ht']['total'] + $yearlyData['dm']['total'];
        $yearlyData['combined']['standard'] = $yearlyData['ht']['standard'] + $yearlyData['dm']['standard'];

        // Calculate yearly percentages
        if ($options['include_percentages']) {
            foreach (['ht', 'dm', 'combined'] as $type) {
                $yearlyData[$type]['percentages'] = $this->calculateDiseasePercentages(
                    $yearlyData[$type]['target'],
                    $yearlyData[$type]['total'],
                    $yearlyData[$type]['standard'],
                    $options
                );
            }
        }

        return $yearlyData;
    }

    /**
     * Calculate achievement analysis
     *
     * @param array $results All calculated results
     * @param array $options Calculation options
     * @return array Achievement analysis
     */
    private function calculateAchievementAnalysis(array $results, array $options): array
    {
        $analysis = [
            'overall_performance' => 'unknown',
            'top_performers' => [],
            'underperformers' => [],
            'achievement_stats' => [
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'median' => 0
            ],
            'recommendations' => []
        ];

        $achievements = [];
        $performanceData = [];

        foreach ($results as $index => $result) {
            $calculatedData = $result['calculated_data'] ?? [];
            $originalData = $result['original_data'] ?? [];
            
            if (isset($calculatedData['achievement']['combined_achievement'])) {
                $achievement = $calculatedData['achievement']['combined_achievement'];
                $achievements[] = $achievement;
                
                $performanceData[] = [
                    'index' => $index,
                    'puskesmas_id' => $originalData['puskesmas_id'] ?? $originalData['id'] ?? null,
                    'puskesmas_name' => $originalData['puskesmas_name'] ?? $originalData['nama_puskesmas'] ?? 'Unknown',
                    'achievement' => $achievement,
                    'level' => $calculatedData['achievement']['overall_level'] ?? 'unknown'
                ];
            }
        }

        if (!empty($achievements)) {
            // Calculate achievement statistics
            $analysis['achievement_stats']['min'] = round(min($achievements), $options['percentage_precision']);
            $analysis['achievement_stats']['max'] = round(max($achievements), $options['percentage_precision']);
            $analysis['achievement_stats']['avg'] = round(array_sum($achievements) / count($achievements), $options['percentage_precision']);
            
            sort($achievements);
            $count = count($achievements);
            $analysis['achievement_stats']['median'] = $count % 2 === 0 
                ? round(($achievements[$count/2 - 1] + $achievements[$count/2]) / 2, $options['percentage_precision'])
                : round($achievements[floor($count/2)], $options['percentage_precision']);

            // Determine overall performance
            $avgAchievement = $analysis['achievement_stats']['avg'];
            $analysis['overall_performance'] = $this->getAchievementLevel($avgAchievement, $options);

            // Sort performance data by achievement
            usort($performanceData, function($a, $b) {
                return $b['achievement'] <=> $a['achievement'];
            });

            // Identify top performers (top 20% or those above target threshold)
            $topCount = max(1, ceil(count($performanceData) * 0.2));
            $analysis['top_performers'] = array_slice($performanceData, 0, $topCount);

            // Identify underperformers (below warning threshold)
            $analysis['underperformers'] = array_filter($performanceData, function($item) use ($options) {
                return $item['achievement'] < $options['warning_threshold'];
            });

            // Generate recommendations
            $analysis['recommendations'] = $this->generateRecommendations($analysis, $options);
        }

        return $analysis;
    }

    /**
     * Calculate trend analysis
     *
     * @param array $results All calculated results
     * @param array $options Calculation options
     * @return array Trend analysis
     */
    private function calculateTrendAnalysis(array $results, array $options): array
    {
        // This is a simplified trend analysis
        // In a real implementation, you would need historical data
        return [
            'trend_direction' => 'stable',
            'trend_strength' => 'moderate',
            'projected_achievement' => 0,
            'seasonal_patterns' => [],
            'growth_rate' => 0
        ];
    }

    /**
     * Calculate comparison analysis
     *
     * @param array $results All calculated results
     * @param array $options Calculation options
     * @return array Comparison analysis
     */
    private function calculateComparisonAnalysis(array $results, array $options): array
    {
        // This is a simplified comparison analysis
        // In a real implementation, you would compare with previous periods or benchmarks
        return [
            'vs_previous_period' => [
                'change_percentage' => 0,
                'direction' => 'stable'
            ],
            'vs_benchmark' => [
                'difference' => 0,
                'status' => 'on_par'
            ],
            'ranking' => []
        ];
    }

    /**
     * Generate recommendations based on analysis
     *
     * @param array $analysis Achievement analysis
     * @param array $options Calculation options
     * @return array Recommendations
     */
    private function generateRecommendations(array $analysis, array $options): array
    {
        $recommendations = [];

        $avgAchievement = $analysis['achievement_stats']['avg'];
        $underperformerCount = count($analysis['underperformers']);
        $totalItems = count($analysis['top_performers']) + $underperformerCount;

        if ($avgAchievement < $options['critical_threshold']) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'critical_improvement',
                'message' => 'Capaian rata-rata sangat rendah. Diperlukan intervensi segera dan evaluasi menyeluruh program.',
                'actions' => [
                    'Evaluasi ulang strategi program',
                    'Tingkatkan kapasitas SDM',
                    'Perbaiki sistem monitoring'
                ]
            ];
        } elseif ($avgAchievement < $options['warning_threshold']) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'improvement_needed',
                'message' => 'Capaian rata-rata di bawah target. Perlu peningkatan kinerja.',
                'actions' => [
                    'Identifikasi hambatan utama',
                    'Tingkatkan koordinasi antar unit',
                    'Optimalkan alokasi sumber daya'
                ]
            ];
        }

        if ($underperformerCount > ($totalItems * 0.3)) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'widespread_underperformance',
                'message' => 'Banyak unit dengan capaian rendah. Perlu pendampingan intensif.',
                'actions' => [
                    'Program mentoring untuk unit berkinerja rendah',
                    'Sharing best practices dari top performers',
                    'Pelatihan tambahan untuk petugas'
                ]
            ];
        }

        if ($analysis['achievement_stats']['max'] - $analysis['achievement_stats']['min'] > 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'performance_gap',
                'message' => 'Kesenjangan kinerja antar unit cukup besar. Perlu standardisasi.',
                'actions' => [
                    'Standardisasi prosedur operasional',
                    'Pemerataan distribusi sumber daya',
                    'Program peer learning antar unit'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Generate calculation metadata
     *
     * @param array $results Calculation results
     * @param array $summary Summary data
     * @param array $options Calculation options
     * @return array Metadata
     */
    private function generateCalculationMetadata(array $results, array $summary, array $options): array
    {
        return [
            'calculated_at' => now()->toISOString(),
            'calculation_type' => $options['calculation_type'],
            'total_items_processed' => count($results),
            'precision' => $options['precision'],
            'percentage_precision' => $options['percentage_precision'],
            'thresholds' => [
                'target' => $options['target_threshold'],
                'warning' => $options['warning_threshold'],
                'critical' => $options['critical_threshold']
            ],
            'features_included' => [
                'percentages' => $options['include_percentages'],
                'totals' => $options['include_totals'],
                'averages' => $options['include_averages'],
                'quarterly_data' => $options['include_quarterly_data'],
                'yearly_data' => $options['include_yearly_data'],
                'achievement_analysis' => $options['include_achievement_analysis'],
                'trend_analysis' => $options['include_trend_analysis'],
                'comparison' => $options['include_comparison']
            ],
            'processing_summary' => [
                'items_with_ht_data' => count(array_filter($results, function($r) {
                    return isset($r['calculated_data']['ht']);
                })),
                'items_with_dm_data' => count(array_filter($results, function($r) {
                    return isset($r['calculated_data']['dm']);
                })),
                'items_with_monthly_data' => count(array_filter($results, function($r) {
                    return isset($r['calculated_data']['monthly']);
                }))
            ]
        ];
    }

    /**
     * Get Roman numeral for quarter
     *
     * @param int $quarter Quarter number
     * @return string Roman numeral
     */
    private function getRomanNumeral(int $quarter): string
    {
        $numerals = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
        return $numerals[$quarter] ?? (string) $quarter;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);

        // Validate calculation type
        $validTypes = ['comprehensive', 'basic', 'advanced'];
        if (isset($options['calculation_type']) && !in_array($options['calculation_type'], $validTypes)) {
            throw new \InvalidArgumentException('Invalid calculation type. Must be one of: ' . implode(', ', $validTypes));
        }

        // Validate precision values
        if (isset($options['precision']) && (!is_numeric($options['precision']) || $options['precision'] < 0 || $options['precision'] > 10)) {
            throw new \InvalidArgumentException('Precision must be a number between 0 and 10');
        }

        if (isset($options['percentage_precision']) && (!is_numeric($options['percentage_precision']) || $options['percentage_precision'] < 0 || $options['percentage_precision'] > 10)) {
            throw new \InvalidArgumentException('Percentage precision must be a number between 0 and 10');
        }

        // Validate threshold values
        $thresholds = ['target_threshold', 'warning_threshold', 'critical_threshold'];
        foreach ($thresholds as $threshold) {
            if (isset($options[$threshold]) && (!is_numeric($options[$threshold]) || $options[$threshold] < 0 || $options[$threshold] > 100)) {
                throw new \InvalidArgumentException("{$threshold} must be a number between 0 and 100");
            }
        }

        return true;
    }
}