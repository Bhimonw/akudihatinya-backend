<?php

namespace App\Formatters\Strategies\DataFormatting;

use App\Formatters\Strategies\BaseFormatterStrategy;

/**
 * Strategy untuk formatting data dashboard
 * Menangani format data yang dioptimalkan untuk tampilan dashboard
 */
class DashboardDataStrategy extends BaseFormatterStrategy
{
    protected string $name = 'DashboardDataStrategy';

    protected array $defaultOptions = [
        'include_ranking' => true,
        'include_monthly_data' => false,
        'format_numbers' => true,
        'include_metadata' => true
    ];

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Formatting dashboard data', [
            'data_count' => count($data),
            'options' => $options
        ]);

        $formattedData = [];
        
        foreach ($data as $index => $item) {
            $formatted = $this->formatSingleItem($item, $options, $index + 1);
            $formattedData[] = $formatted;
        }

        if ($options['include_metadata']) {
            return [
                'data' => $formattedData,
                'meta' => $this->generateMetadata($data, $options)
            ];
        }

        return $formattedData;
    }

    /**
     * Format single data item untuk dashboard
     *
     * @param array $item Data item
     * @param array $options Formatting options
     * @param int $ranking Ranking position
     * @return array Formatted item
     */
    private function formatSingleItem(array $item, array $options, int $ranking): array
    {
        $formatted = [
            'puskesmas_id' => $item['puskesmas_id'] ?? null,
            'puskesmas_name' => $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? 'Unknown',
        ];

        // Add ranking if requested
        if ($options['include_ranking']) {
            $formatted['ranking'] = $ranking;
        }

        // Format HT data if exists
        if (isset($item['ht']) || isset($item['ht_statistics'])) {
            $htData = $item['ht'] ?? $item['ht_statistics'] ?? [];
            $formatted['ht'] = $this->formatDiseaseData($htData, $options);
        }

        // Format DM data if exists
        if (isset($item['dm']) || isset($item['dm_statistics'])) {
            $dmData = $item['dm'] ?? $item['dm_statistics'] ?? [];
            $formatted['dm'] = $this->formatDiseaseData($dmData, $options);
        }

        return $formatted;
    }

    /**
     * Format disease-specific data
     *
     * @param array $diseaseData Disease data
     * @param array $options Formatting options
     * @return array Formatted disease data
     */
    private function formatDiseaseData(array $diseaseData, array $options): array
    {
        $formatted = [];

        // Basic statistics
        $fields = ['target', 'total_patients', 'standard_patients', 'non_standard_patients', 'male_patients', 'female_patients'];
        
        foreach ($fields as $field) {
            if (isset($diseaseData[$field])) {
                $formatted[$field] = $options['format_numbers'] 
                    ? $this->formatNumber($diseaseData[$field])
                    : $diseaseData[$field];
            }
        }

        // Achievement percentage
        if (isset($diseaseData['achievement_percentage'])) {
            $formatted['achievement_percentage'] = $this->formatPercentage($diseaseData['achievement_percentage']);
        } elseif (isset($diseaseData['target']) && isset($diseaseData['standard_patients'])) {
            $percentage = $this->calculateAchievementPercentage(
                $diseaseData['standard_patients'],
                $diseaseData['target']
            );
            $formatted['achievement_percentage'] = $this->formatPercentage($percentage);
        }

        // Standard percentage
        if (isset($diseaseData['total_patients']) && isset($diseaseData['standard_patients'])) {
            $standardPercentage = $this->calculateStandardPercentage(
                $diseaseData['standard_patients'],
                $diseaseData['total_patients']
            );
            $formatted['standard_percentage'] = $this->formatPercentage($standardPercentage);
        }

        // Monthly data if requested
        if ($options['include_monthly_data'] && isset($diseaseData['monthly_data'])) {
            $formatted['monthly_data'] = $this->formatMonthlyData($diseaseData['monthly_data'], $options);
        }

        return $formatted;
    }

    /**
     * Format monthly data
     *
     * @param array $monthlyData Monthly data
     * @param array $options Formatting options
     * @return array Formatted monthly data
     */
    private function formatMonthlyData(array $monthlyData, array $options): array
    {
        $formatted = [];
        
        foreach ($monthlyData as $month => $data) {
            $monthFormatted = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
            ];

            $fields = ['male', 'female', 'total', 'standard', 'non_standard'];
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $monthFormatted[$field] = $options['format_numbers']
                        ? $this->formatNumber($data[$field])
                        : $data[$field];
                }
            }

            if (isset($data['percentage'])) {
                $monthFormatted['percentage'] = $this->formatPercentage($data['percentage']);
            }

            $formatted[$month] = $monthFormatted;
        }

        return $formatted;
    }

    /**
     * Generate metadata untuk response
     *
     * @param array $data Original data
     * @param array $options Formatting options
     * @return array Metadata
     */
    private function generateMetadata(array $data, array $options): array
    {
        return [
            'total_items' => count($data),
            'formatted_at' => now()->toISOString(),
            'strategy' => $this->getName(),
            'options_used' => $options,
            'has_ht_data' => $this->hasFieldInData($data, ['ht', 'ht_statistics']),
            'has_dm_data' => $this->hasFieldInData($data, ['dm', 'dm_statistics']),
        ];
    }

    /**
     * Check if data contains specific fields
     *
     * @param array $data Data to check
     * @param array $fields Fields to look for
     * @return bool True if any field exists
     */
    private function hasFieldInData(array $data, array $fields): bool
    {
        foreach ($data as $item) {
            foreach ($fields as $field) {
                if (isset($item[$field])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);

        // Validate that data contains required fields
        foreach ($data as $item) {
            if (!isset($item['puskesmas_id']) && !isset($item['id'])) {
                throw new \InvalidArgumentException('Each data item must have puskesmas_id or id field');
            }
            
            if (!isset($item['puskesmas_name']) && !isset($item['nama_puskesmas'])) {
                throw new \InvalidArgumentException('Each data item must have puskesmas_name or nama_puskesmas field');
            }
        }

        return true;
    }
}