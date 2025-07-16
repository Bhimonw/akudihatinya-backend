<?php

namespace App\Formatters\Strategies\DataFormatting;

use App\Formatters\Strategies\BaseFormatterStrategy;

/**
 * Strategy untuk formatting data admin
 * Menangani format data yang dioptimalkan untuk admin interface dengan pagination dan filtering
 */
class AdminDataStrategy extends BaseFormatterStrategy
{
    protected string $name = 'AdminDataStrategy';

    protected array $defaultOptions = [
        'include_pagination' => true,
        'include_summary' => true,
        'include_filters' => true,
        'format_numbers' => true,
        'per_page' => 15,
        'current_page' => 1,
        'disease_type' => 'all'
    ];

    /**
     * {@inheritdoc}
     */
    public function execute(array $data, array $options = []): array
    {
        $options = $this->mergeOptions($options);
        $this->validate($data, $options);

        $this->log('Formatting admin data', [
            'data_count' => count($data),
            'options' => $options
        ]);

        // Format main data
        $formattedData = $this->formatDataItems($data, $options);

        // Build response structure
        $response = [
            'data' => $formattedData,
            'success' => true,
            'message' => 'Data admin berhasil diformat'
        ];

        // Add pagination if requested
        if ($options['include_pagination']) {
            $response['meta'] = $this->generatePaginationMeta($data, $options);
        }

        // Add summary if requested
        if ($options['include_summary']) {
            $response['summary'] = $this->generateSummary($data, $options);
        }

        // Add filter information if requested
        if ($options['include_filters']) {
            $response['filters'] = $this->generateFilterInfo($options);
        }

        return $response;
    }

    /**
     * Format array of data items
     *
     * @param array $data Data items
     * @param array $options Formatting options
     * @return array Formatted data items
     */
    private function formatDataItems(array $data, array $options): array
    {
        $formatted = [];
        
        foreach ($data as $item) {
            $formatted[] = $this->formatSingleDataItem($item, $options);
        }

        return $formatted;
    }

    /**
     * Format single data item untuk admin
     *
     * @param array $item Data item
     * @param array $options Formatting options
     * @return array Formatted item
     */
    private function formatSingleDataItem(array $item, array $options): array
    {
        $formatted = [
            'id' => $item['id'] ?? $item['puskesmas_id'] ?? null,
            'puskesmas_name' => $item['puskesmas_name'] ?? $item['nama_puskesmas'] ?? 'Unknown',
            'code' => $item['code'] ?? $item['kode_puskesmas'] ?? null,
            'address' => $item['address'] ?? $item['alamat'] ?? null,
        ];

        // Add disease-specific data based on disease_type option
        $diseaseType = $options['disease_type'];
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            if (isset($item['ht']) || isset($item['ht_statistics'])) {
                $htData = $item['ht'] ?? $item['ht_statistics'] ?? [];
                $formatted['ht'] = $this->formatDiseaseDataForAdmin($htData, $options);
            }
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if (isset($item['dm']) || isset($item['dm_statistics'])) {
                $dmData = $item['dm'] ?? $item['dm_statistics'] ?? [];
                $formatted['dm'] = $this->formatDiseaseDataForAdmin($dmData, $options);
            }
        }

        // Add administrative information
        $formatted['last_updated'] = $item['last_updated'] ?? $item['updated_at'] ?? null;
        $formatted['status'] = $this->determineStatus($item, $options);

        return $formatted;
    }

    /**
     * Format disease data untuk admin view
     *
     * @param array $diseaseData Disease data
     * @param array $options Formatting options
     * @return array Formatted disease data
     */
    private function formatDiseaseDataForAdmin(array $diseaseData, array $options): array
    {
        $formatted = [];

        // Target and achievement
        if (isset($diseaseData['target'])) {
            $formatted['target'] = $options['format_numbers']
                ? $this->formatNumber($diseaseData['target'])
                : $diseaseData['target'];
        }

        // Patient counts
        $patientFields = ['total_patients', 'standard_patients', 'non_standard_patients', 'male_patients', 'female_patients'];
        foreach ($patientFields as $field) {
            if (isset($diseaseData[$field])) {
                $formatted[$field] = $options['format_numbers']
                    ? $this->formatNumber($diseaseData[$field])
                    : $diseaseData[$field];
            }
        }

        // Percentages
        if (isset($diseaseData['achievement_percentage'])) {
            $formatted['achievement_percentage'] = $this->formatPercentage($diseaseData['achievement_percentage']);
            $formatted['achievement_status'] = $this->getAchievementStatus($diseaseData['achievement_percentage']);
        }

        if (isset($diseaseData['total_patients']) && isset($diseaseData['standard_patients'])) {
            $standardPercentage = $this->calculateStandardPercentage(
                $diseaseData['standard_patients'],
                $diseaseData['total_patients']
            );
            $formatted['standard_percentage'] = $this->formatPercentage($standardPercentage);
        }

        // Quarterly breakdown if available
        if (isset($diseaseData['quarterly_data'])) {
            $formatted['quarterly_summary'] = $this->formatQuarterlySummary($diseaseData['quarterly_data'], $options);
        }

        return $formatted;
    }

    /**
     * Format quarterly summary
     *
     * @param array $quarterlyData Quarterly data
     * @param array $options Formatting options
     * @return array Formatted quarterly summary
     */
    private function formatQuarterlySummary(array $quarterlyData, array $options): array
    {
        $summary = [];
        
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            if (isset($quarterlyData[$quarter])) {
                $data = $quarterlyData[$quarter];
                $summary["q{$quarter}"] = [
                    'total' => $options['format_numbers'] ? $this->formatNumber($data['total'] ?? 0) : ($data['total'] ?? 0),
                    'standard' => $options['format_numbers'] ? $this->formatNumber($data['standard'] ?? 0) : ($data['standard'] ?? 0),
                    'percentage' => $this->formatPercentage($data['percentage'] ?? 0)
                ];
            }
        }

        return $summary;
    }

    /**
     * Determine status berdasarkan data
     *
     * @param array $item Data item
     * @param array $options Options
     * @return string Status
     */
    private function determineStatus(array $item, array $options): string
    {
        // Check if has recent data
        $lastUpdated = $item['last_updated'] ?? $item['updated_at'] ?? null;
        if ($lastUpdated) {
            $lastUpdateTime = strtotime($lastUpdated);
            $oneMonthAgo = strtotime('-1 month');
            
            if ($lastUpdateTime < $oneMonthAgo) {
                return 'outdated';
            }
        }

        // Check achievement status
        $diseaseType = $options['disease_type'];
        $hasGoodAchievement = false;
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = $item['ht'] ?? $item['ht_statistics'] ?? [];
            if (isset($htData['achievement_percentage']) && $htData['achievement_percentage'] >= 80) {
                $hasGoodAchievement = true;
            }
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = $item['dm'] ?? $item['dm_statistics'] ?? [];
            if (isset($dmData['achievement_percentage']) && $dmData['achievement_percentage'] >= 80) {
                $hasGoodAchievement = true;
            }
        }

        return $hasGoodAchievement ? 'good' : 'needs_attention';
    }

    /**
     * Get achievement status label
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
     * Generate pagination metadata
     *
     * @param array $data Original data
     * @param array $options Options
     * @return array Pagination meta
     */
    private function generatePaginationMeta(array $data, array $options): array
    {
        $total = count($data);
        $perPage = $options['per_page'];
        $currentPage = $options['current_page'];
        $lastPage = ceil($total / $perPage);

        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => ($currentPage - 1) * $perPage + 1,
            'to' => min($currentPage * $perPage, $total),
            'has_more_pages' => $currentPage < $lastPage
        ];
    }

    /**
     * Generate summary statistics
     *
     * @param array $data Original data
     * @param array $options Options
     * @return array Summary
     */
    private function generateSummary(array $data, array $options): array
    {
        $summary = [
            'total_puskesmas' => count($data),
            'active_puskesmas' => 0,
            'good_performance' => 0,
            'needs_attention' => 0
        ];

        foreach ($data as $item) {
            $status = $this->determineStatus($item, $options);
            
            if ($status !== 'outdated') {
                $summary['active_puskesmas']++;
            }
            
            if ($status === 'good') {
                $summary['good_performance']++;
            } elseif ($status === 'needs_attention') {
                $summary['needs_attention']++;
            }
        }

        return $summary;
    }

    /**
     * Generate filter information
     *
     * @param array $options Options
     * @return array Filter info
     */
    private function generateFilterInfo(array $options): array
    {
        return [
            'disease_type' => $options['disease_type'],
            'disease_label' => $this->getDiseaseTypeLabel($options['disease_type']),
            'year' => $options['year'] ?? date('Y'),
            'month' => $options['month'] ?? null,
            'applied_at' => now()->toISOString()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        parent::validate($data, $options);

        // Validate pagination options
        if (isset($options['per_page']) && ($options['per_page'] < 1 || $options['per_page'] > 100)) {
            throw new \InvalidArgumentException('per_page must be between 1 and 100');
        }

        if (isset($options['current_page']) && $options['current_page'] < 1) {
            throw new \InvalidArgumentException('current_page must be greater than 0');
        }

        // Validate disease type
        if (isset($options['disease_type'])) {
            $this->validateDiseaseType($options['disease_type']);
        }

        return true;
    }
}