<?php

namespace App\Formatters\Strategies\DataFormatting;

use App\Formatters\Strategies\BaseFormatterStrategy;

/**
 * Strategy untuk formatting data puskesmas individual
 * Menangani format data yang dioptimalkan untuk view puskesmas spesifik
 */
class PuskesmasDataStrategy extends BaseFormatterStrategy
{
    protected string $name = 'PuskesmasDataStrategy';

    protected array $defaultOptions = [
        'include_monthly_breakdown' => true,
        'include_quarterly_summary' => true,
        'include_yearly_total' => true,
        'include_achievement_analysis' => true,
        'include_comparison' => false,
        'format_numbers' => true,
        'disease_type' => 'all',
        'year' => null,
        'puskesmas_id' => null
    ];

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Formatting puskesmas data', [
            'puskesmas_id' => $options['puskesmas_id'],
            'disease_type' => $options['disease_type'],
            'year' => $options['year']
        ]);

        // Format main puskesmas information
        $formattedData = $this->formatPuskesmasInfo($data, $options);

        // Add disease-specific data
        $formattedData['diseases'] = $this->formatDiseaseData($data, $options);

        // Add monthly breakdown if requested
        if ($options['include_monthly_breakdown']) {
            $formattedData['monthly_data'] = $this->formatMonthlyBreakdown($data, $options);
        }

        // Add quarterly summary if requested
        if ($options['include_quarterly_summary']) {
            $formattedData['quarterly_summary'] = $this->formatQuarterlySummary($data, $options);
        }

        // Add yearly total if requested
        if ($options['include_yearly_total']) {
            $formattedData['yearly_total'] = $this->formatYearlyTotal($data, $options);
        }

        // Add achievement analysis if requested
        if ($options['include_achievement_analysis']) {
            $formattedData['achievement_analysis'] = $this->formatAchievementAnalysis($data, $options);
        }

        // Add comparison data if requested
        if ($options['include_comparison'] && isset($data['comparison_data'])) {
            $formattedData['comparison'] = $this->formatComparisonData($data['comparison_data'], $options);
        }

        return [
            'data' => $formattedData,
            'success' => true,
            'message' => 'Data puskesmas berhasil diformat',
            'meta' => $this->generateMetadata($data, $options)
        ];
    }

    /**
     * Format basic puskesmas information
     *
     * @param array $data Puskesmas data
     * @param array $options Formatting options
     * @return array Formatted puskesmas info
     */
    private function formatPuskesmasInfo(array $data, array $options): array
    {
        return [
            'id' => $data['id'] ?? $data['puskesmas_id'] ?? null,
            'name' => $data['name'] ?? $data['puskesmas_name'] ?? $data['nama_puskesmas'] ?? 'Unknown',
            'code' => $data['code'] ?? $data['kode_puskesmas'] ?? null,
            'address' => $data['address'] ?? $data['alamat'] ?? null,
            'phone' => $data['phone'] ?? $data['telepon'] ?? null,
            'head_of_puskesmas' => $data['head_of_puskesmas'] ?? $data['kepala_puskesmas'] ?? null,
            'year' => $options['year'] ?? date('Y'),
            'last_updated' => $data['last_updated'] ?? $data['updated_at'] ?? null
        ];
    }

    /**
     * Format disease-specific data
     *
     * @param array $data Puskesmas data
     * @param array $options Formatting options
     * @return array Formatted disease data
     */
    private function formatDiseaseData(array $data, array $options): array
    {
        $diseases = [];
        $diseaseType = $options['disease_type'];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            if (isset($data['ht']) || isset($data['ht_data'])) {
                $htData = $data['ht'] ?? $data['ht_data'] ?? [];
                $diseases['ht'] = $this->formatSingleDiseaseData($htData, 'ht', $options);
            }
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if (isset($data['dm']) || isset($data['dm_data'])) {
                $dmData = $data['dm'] ?? $data['dm_data'] ?? [];
                $diseases['dm'] = $this->formatSingleDiseaseData($dmData, 'dm', $options);
            }
        }

        return $diseases;
    }

    /**
     * Format single disease data
     *
     * @param array $diseaseData Disease data
     * @param string $diseaseType Disease type (ht/dm)
     * @param array $options Formatting options
     * @return array Formatted disease data
     */
    private function formatSingleDiseaseData(array $diseaseData, string $diseaseType, array $options): array
    {
        $formatted = [
            'type' => $diseaseType,
            'label' => $this->getDiseaseTypeLabel($diseaseType),
            'target' => $this->formatNumberValue($diseaseData['target'] ?? 0, $options),
            'patients' => [
                'total' => $this->formatNumberValue($diseaseData['total_patients'] ?? 0, $options),
                'male' => $this->formatNumberValue($diseaseData['male_patients'] ?? 0, $options),
                'female' => $this->formatNumberValue($diseaseData['female_patients'] ?? 0, $options),
                'standard' => $this->formatNumberValue($diseaseData['standard_patients'] ?? 0, $options),
                'non_standard' => $this->formatNumberValue($diseaseData['non_standard_patients'] ?? 0, $options)
            ]
        ];

        // Calculate and format percentages
        $totalPatients = $diseaseData['total_patients'] ?? 0;
        $standardPatients = $diseaseData['standard_patients'] ?? 0;
        $target = $diseaseData['target'] ?? 0;

        if ($totalPatients > 0) {
            $formatted['percentages'] = [
                'standard_percentage' => $this->formatPercentage(
                    $this->calculatePercentage($standardPatients, $totalPatients)
                ),
                'male_percentage' => $this->formatPercentage(
                    $this->calculatePercentage($diseaseData['male_patients'] ?? 0, $totalPatients)
                ),
                'female_percentage' => $this->formatPercentage(
                    $this->calculatePercentage($diseaseData['female_patients'] ?? 0, $totalPatients)
                )
            ];
        }

        if ($target > 0) {
            $achievementPercentage = $this->calculatePercentage($totalPatients, $target);
            $formatted['achievement'] = [
                'percentage' => $this->formatPercentage($achievementPercentage),
                'status' => $this->getAchievementStatus($achievementPercentage),
                'gap' => $this->formatNumberValue(max(0, $target - $totalPatients), $options)
            ];
        }

        return $formatted;
    }

    /**
     * Format monthly breakdown data
     *
     * @param array $data Puskesmas data
     * @param array $options Formatting options
     * @return array Formatted monthly data
     */
    private function formatMonthlyBreakdown(array $data, array $options): array
    {
        $monthlyData = [];
        $diseaseType = $options['disease_type'];

        for ($month = 1; $month <= 12; $month++) {
            $monthData = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'diseases' => []
            ];

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                if (isset($data['monthly_ht'][$month]) || isset($data['ht_monthly'][$month])) {
                    $htMonthData = $data['monthly_ht'][$month] ?? $data['ht_monthly'][$month] ?? [];
                    $monthData['diseases']['ht'] = $this->formatMonthlyDiseaseData($htMonthData, $options);
                }
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                if (isset($data['monthly_dm'][$month]) || isset($data['dm_monthly'][$month])) {
                    $dmMonthData = $data['monthly_dm'][$month] ?? $data['dm_monthly'][$month] ?? [];
                    $monthData['diseases']['dm'] = $this->formatMonthlyDiseaseData($dmMonthData, $options);
                }
            }

            $monthlyData[] = $monthData;
        }

        return $monthlyData;
    }

    /**
     * Format monthly disease data
     *
     * @param array $monthData Monthly disease data
     * @param array $options Formatting options
     * @return array Formatted monthly disease data
     */
    private function formatMonthlyDiseaseData(array $monthData, array $options): array
    {
        return [
            'total_patients' => $this->formatNumberValue($monthData['total_patients'] ?? 0, $options),
            'male_patients' => $this->formatNumberValue($monthData['male_patients'] ?? 0, $options),
            'female_patients' => $this->formatNumberValue($monthData['female_patients'] ?? 0, $options),
            'standard_patients' => $this->formatNumberValue($monthData['standard_patients'] ?? 0, $options),
            'non_standard_patients' => $this->formatNumberValue($monthData['non_standard_patients'] ?? 0, $options),
            'cumulative_total' => $this->formatNumberValue($monthData['cumulative_total'] ?? 0, $options)
        ];
    }

    /**
     * Format quarterly summary
     *
     * @param array $data Puskesmas data
     * @param array $options Formatting options
     * @return array Formatted quarterly summary
     */
    private function formatQuarterlySummary(array $data, array $options): array
    {
        $quarterlySummary = [];
        $diseaseType = $options['disease_type'];

        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = [
                'quarter' => $quarter,
                'quarter_name' => "Triwulan {$quarter}",
                'months' => $this->getQuarterMonths($quarter),
                'diseases' => []
            ];

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                if (isset($data['quarterly_ht'][$quarter])) {
                    $quarterData['diseases']['ht'] = $this->formatQuarterlyDiseaseData(
                        $data['quarterly_ht'][$quarter], $options
                    );
                }
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                if (isset($data['quarterly_dm'][$quarter])) {
                    $quarterData['diseases']['dm'] = $this->formatQuarterlyDiseaseData(
                        $data['quarterly_dm'][$quarter], $options
                    );
                }
            }

            $quarterlySummary[] = $quarterData;
        }

        return $quarterlySummary;
    }

    /**
     * Format quarterly disease data
     *
     * @param array $quarterData Quarterly disease data
     * @param array $options Formatting options
     * @return array Formatted quarterly disease data
     */
    private function formatQuarterlyDiseaseData(array $quarterData, array $options): array
    {
        $totalPatients = $quarterData['total_patients'] ?? 0;
        $standardPatients = $quarterData['standard_patients'] ?? 0;

        return [
            'total_patients' => $this->formatNumberValue($totalPatients, $options),
            'standard_patients' => $this->formatNumberValue($standardPatients, $options),
            'standard_percentage' => $this->formatPercentage(
                $this->calculatePercentage($standardPatients, $totalPatients)
            ),
            'average_monthly' => $this->formatNumberValue($totalPatients / 3, $options)
        ];
    }

    /**
     * Format yearly total
     *
     * @param array $data Puskesmas data
     * @param array $options Formatting options
     * @return array Formatted yearly total
     */
    private function formatYearlyTotal(array $data, array $options): array
    {
        $yearlyTotal = [];
        $diseaseType = $options['disease_type'];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            if (isset($data['yearly_ht']) || isset($data['ht_yearly'])) {
                $htYearlyData = $data['yearly_ht'] ?? $data['ht_yearly'] ?? [];
                $yearlyTotal['ht'] = $this->formatYearlyDiseaseData($htYearlyData, 'ht', $options);
            }
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if (isset($data['yearly_dm']) || isset($data['dm_yearly'])) {
                $dmYearlyData = $data['yearly_dm'] ?? $data['dm_yearly'] ?? [];
                $yearlyTotal['dm'] = $this->formatYearlyDiseaseData($dmYearlyData, 'dm', $options);
            }
        }

        return $yearlyTotal;
    }

    /**
     * Format yearly disease data
     *
     * @param array $yearlyData Yearly disease data
     * @param string $diseaseType Disease type
     * @param array $options Formatting options
     * @return array Formatted yearly disease data
     */
    private function formatYearlyDiseaseData(array $yearlyData, string $diseaseType, array $options): array
    {
        $totalPatients = $yearlyData['total_patients'] ?? 0;
        $standardPatients = $yearlyData['standard_patients'] ?? 0;
        $target = $yearlyData['target'] ?? 0;

        $formatted = [
            'disease_type' => $diseaseType,
            'disease_label' => $this->getDiseaseTypeLabel($diseaseType),
            'total_patients' => $this->formatNumberValue($totalPatients, $options),
            'standard_patients' => $this->formatNumberValue($standardPatients, $options),
            'target' => $this->formatNumberValue($target, $options)
        ];

        if ($target > 0) {
            $achievementPercentage = $this->calculatePercentage($totalPatients, $target);
            $formatted['achievement'] = [
                'percentage' => $this->formatPercentage($achievementPercentage),
                'status' => $this->getAchievementStatus($achievementPercentage),
                'gap' => $this->formatNumberValue(max(0, $target - $totalPatients), $options)
            ];
        }

        if ($totalPatients > 0) {
            $formatted['standard_percentage'] = $this->formatPercentage(
                $this->calculatePercentage($standardPatients, $totalPatients)
            );
        }

        return $formatted;
    }

    /**
     * Format achievement analysis
     *
     * @param array $data Puskesmas data
     * @param array $options Formatting options
     * @return array Formatted achievement analysis
     */
    private function formatAchievementAnalysis(array $data, array $options): array
    {
        $analysis = [
            'overall_performance' => 'good',
            'recommendations' => [],
            'strengths' => [],
            'areas_for_improvement' => []
        ];

        $diseaseType = $options['disease_type'];
        $totalAchievements = [];

        // Analyze HT performance
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            if (isset($data['ht']) || isset($data['ht_data'])) {
                $htData = $data['ht'] ?? $data['ht_data'] ?? [];
                $htAnalysis = $this->analyzeDiseasePerformance($htData, 'ht');
                $analysis['diseases']['ht'] = $htAnalysis;
                if (isset($htAnalysis['achievement_percentage'])) {
                    $totalAchievements[] = $htAnalysis['achievement_percentage'];
                }
            }
        }

        // Analyze DM performance
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if (isset($data['dm']) || isset($data['dm_data'])) {
                $dmData = $data['dm'] ?? $data['dm_data'] ?? [];
                $dmAnalysis = $this->analyzeDiseasePerformance($dmData, 'dm');
                $analysis['diseases']['dm'] = $dmAnalysis;
                if (isset($dmAnalysis['achievement_percentage'])) {
                    $totalAchievements[] = $dmAnalysis['achievement_percentage'];
                }
            }
        }

        // Calculate overall performance
        if (!empty($totalAchievements)) {
            $averageAchievement = array_sum($totalAchievements) / count($totalAchievements);
            $analysis['overall_achievement'] = $this->formatPercentage($averageAchievement);
            $analysis['overall_performance'] = $this->getOverallPerformanceLevel($averageAchievement);
        }

        return $analysis;
    }

    /**
     * Analyze disease performance
     *
     * @param array $diseaseData Disease data
     * @param string $diseaseType Disease type
     * @return array Disease analysis
     */
    private function analyzeDiseasePerformance(array $diseaseData, string $diseaseType): array
    {
        $totalPatients = $diseaseData['total_patients'] ?? 0;
        $target = $diseaseData['target'] ?? 0;
        $standardPatients = $diseaseData['standard_patients'] ?? 0;

        $analysis = [
            'disease_type' => $diseaseType,
            'disease_label' => $this->getDiseaseTypeLabel($diseaseType)
        ];

        if ($target > 0) {
            $achievementPercentage = $this->calculatePercentage($totalPatients, $target);
            $analysis['achievement_percentage'] = $achievementPercentage;
            $analysis['achievement_status'] = $this->getAchievementStatus($achievementPercentage);
            
            if ($achievementPercentage >= 100) {
                $analysis['performance_note'] = 'Target tercapai dengan sangat baik';
            } elseif ($achievementPercentage >= 80) {
                $analysis['performance_note'] = 'Target hampir tercapai';
            } else {
                $analysis['performance_note'] = 'Perlu peningkatan untuk mencapai target';
            }
        }

        if ($totalPatients > 0) {
            $standardPercentage = $this->calculatePercentage($standardPatients, $totalPatients);
            $analysis['standard_percentage'] = $standardPercentage;
            
            if ($standardPercentage >= 80) {
                $analysis['quality_note'] = 'Kualitas pelayanan sangat baik';
            } elseif ($standardPercentage >= 60) {
                $analysis['quality_note'] = 'Kualitas pelayanan cukup baik';
            } else {
                $analysis['quality_note'] = 'Perlu peningkatan kualitas pelayanan';
            }
        }

        return $analysis;
    }

    /**
     * Format comparison data
     *
     * @param array $comparisonData Comparison data
     * @param array $options Formatting options
     * @return array Formatted comparison data
     */
    private function formatComparisonData(array $comparisonData, array $options): array
    {
        return [
            'previous_year' => $this->formatComparisonYear($comparisonData['previous_year'] ?? [], $options),
            'district_average' => $this->formatComparisonDistrict($comparisonData['district_average'] ?? [], $options),
            'provincial_average' => $this->formatComparisonProvincial($comparisonData['provincial_average'] ?? [], $options)
        ];
    }

    /**
     * Get quarter months
     *
     * @param int $quarter Quarter number
     * @return array Quarter months
     */
    private function getQuarterMonths(int $quarter): array
    {
        $quarters = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12]
        ];

        return $quarters[$quarter] ?? [];
    }

    /**
     * Get achievement status
     *
     * @param float $percentage Achievement percentage
     * @return array Status information
     */
    private function getAchievementStatus(float $percentage): array
    {
        if ($percentage >= 100) {
            return ['label' => 'Sangat Baik', 'class' => 'success', 'color' => 'green'];
        } elseif ($percentage >= 80) {
            return ['label' => 'Baik', 'class' => 'info', 'color' => 'blue'];
        } elseif ($percentage >= 60) {
            return ['label' => 'Cukup', 'class' => 'warning', 'color' => 'orange'];
        } else {
            return ['label' => 'Kurang', 'class' => 'danger', 'color' => 'red'];
        }
    }

    /**
     * Get overall performance level
     *
     * @param float $averageAchievement Average achievement percentage
     * @return string Performance level
     */
    private function getOverallPerformanceLevel(float $averageAchievement): string
    {
        if ($averageAchievement >= 90) {
            return 'excellent';
        } elseif ($averageAchievement >= 80) {
            return 'good';
        } elseif ($averageAchievement >= 60) {
            return 'fair';
        } else {
            return 'needs_improvement';
        }
    }

    /**
     * Format number value based on options
     *
     * @param mixed $value Value to format
     * @param array $options Formatting options
     * @return mixed Formatted value
     */
    private function formatNumberValue($value, array $options)
    {
        return $options['format_numbers'] ? $this->formatNumber($value) : $value;
    }

    /**
     * Generate metadata
     *
     * @param array $data Original data
     * @param array $options Options
     * @return array Metadata
     */
    private function generateMetadata(array $data, array $options): array
    {
        return [
            'puskesmas_id' => $options['puskesmas_id'],
            'disease_type' => $options['disease_type'],
            'disease_label' => $this->getDiseaseTypeLabel($options['disease_type']),
            'year' => $options['year'] ?? date('Y'),
            'generated_at' => now()->toISOString(),
            'data_completeness' => $this->calculateDataCompleteness($data),
            'last_updated' => $data['last_updated'] ?? $data['updated_at'] ?? null
        ];
    }

    /**
     * Calculate data completeness percentage
     *
     * @param array $data Data to analyze
     * @return float Completeness percentage
     */
    private function calculateDataCompleteness(array $data): float
    {
        $requiredFields = ['id', 'name', 'ht', 'dm'];
        $presentFields = 0;

        foreach ($requiredFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $presentFields++;
            }
        }

        return ($presentFields / count($requiredFields)) * 100;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);

        // Validate puskesmas_id if provided
        if (isset($options['puskesmas_id']) && !is_numeric($options['puskesmas_id'])) {
            throw new \InvalidArgumentException('puskesmas_id must be numeric');
        }

        // Validate disease type
        if (isset($options['disease_type'])) {
            $this->validateDiseaseType($options['disease_type']);
        }

        // Validate year
        if (isset($options['year'])) {
            $this->validateYear($options['year']);
        }

        return true;
    }
}