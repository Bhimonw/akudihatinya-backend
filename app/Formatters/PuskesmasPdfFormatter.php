<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use App\Repositories\PuskesmasRepositoryInterface;
use App\Models\YearlyTarget;
use App\Exceptions\PuskesmasNotFoundException;
use Illuminate\Support\Facades\Log;

class PuskesmasPdfFormatter
{
    protected $statisticsService;
    protected $puskesmasRepository;

    public function __construct(
        StatisticsService $statisticsService,
        PuskesmasRepositoryInterface $puskesmasRepository
    ) {
        $this->statisticsService = $statisticsService;
        $this->puskesmasRepository = $puskesmasRepository;
    }

    /**
     * Format data for puskesmas PDF template
     *
     * @param int $puskesmasId
     * @param string $diseaseType
     * @param int $year
     * @return array
     * @throws PuskesmasNotFoundException
     */
    public function formatPuskesmasData($puskesmasId, $diseaseType, $year)
    {
        $correlationId = uniqid('pdf_', true);

        try {
            Log::info('Starting PDF data formatting', [
                'correlation_id' => $correlationId,
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);

            // Get puskesmas information using repository with caching
            $puskesmas = $this->puskesmasRepository->findWithCache($puskesmasId);
            if (!$puskesmas) {
                throw new PuskesmasNotFoundException($puskesmasId, [
                    'correlation_id' => $correlationId,
                    'disease_type' => $diseaseType,
                    'year' => $year,
                    'method' => __METHOD__
                ]);
            }

            Log::info('Puskesmas found', [
                'correlation_id' => $correlationId,
                'puskesmas_name' => $puskesmas->name
            ]);

            // Get yearly target
            $yearlyTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('disease_type', $diseaseType)
                ->first();
            $target = $yearlyTarget ? $yearlyTarget->target_count : 0;

            // Get statistics data from cache
            if ($diseaseType === 'ht') {
                $diseaseData = $this->statisticsService->getHtStatisticsFromCache($puskesmasId, $year);
            } else {
                $diseaseData = $this->statisticsService->getDmStatisticsFromCache($puskesmasId, $year);
            }

            $monthlyData = $diseaseData['monthly_data'] ?? [];

            // Calculate yearly totals
            $yearlyTotal = $this->calculateYearlyTotals($monthlyData, $target);

            // Format disease type label
            $diseaseTypeLabel = $this->getDiseaseTypeLabel($diseaseType);

            $formattedData = [
                'puskesmas_name' => $puskesmas->name,
                'disease_type' => $diseaseType,
                'disease_type_label' => $diseaseTypeLabel,
                'year' => $year,
                'target' => $target,
                'monthly_data' => $this->formatMonthlyData($monthlyData),
                'yearly_total' => $yearlyTotal,
                'generated_at' => now()->format('d/m/Y H:i:s'),
                'correlation_id' => $correlationId
            ];

            Log::info('PDF data formatting completed successfully', [
                'correlation_id' => $correlationId,
                'data_points' => count($monthlyData),
                'yearly_total' => $yearlyTotal
            ]);

            return $formattedData;
        } catch (PuskesmasNotFoundException $e) {
            // Re-throw PuskesmasNotFoundException without additional logging
            // as it's already logged in the repository
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error formatting puskesmas PDF data', [
                'correlation_id' => $correlationId,
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception(
                "Gagal memformat data PDF: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Format monthly data for PDF template
     *
     * @param array $monthlyData
     * @return array
     */
    protected function formatMonthlyData($monthlyData)
    {
        $formattedData = [];

        for ($month = 1; $month <= 12; $month++) {
            $data = $monthlyData[$month] ?? [];

            // Ensure all required fields are present
            $formattedData[$month] = [
                'male' => $data['male'] ?? 0,
                'female' => $data['female'] ?? 0,
                'total' => ($data['male'] ?? 0) + ($data['female'] ?? 0),
                'standard' => $data['standard'] ?? 0,
                'non_standard' => $data['non_standard'] ?? 0,
                'total_services' => ($data['standard'] ?? 0) + ($data['non_standard'] ?? 0),
                'percentage' => $data['percentage'] ?? 0
            ];
        }

        return $formattedData;
    }

    /**
     * Calculate yearly totals from monthly data
     *
     * @param array $monthlyData
     * @param int $target
     * @return array
     */
    protected function calculateYearlyTotals($monthlyData, $target = 0)
    {
        $totals = [
            'male' => 0,
            'female' => 0,
            'total' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total_services' => 0,
            'percentage' => 0
        ];

        foreach ($monthlyData as $month => $data) {
            $totals['male'] += $data['male'] ?? 0;
            $totals['female'] += $data['female'] ?? 0;
            $totals['standard'] += $data['standard'] ?? 0;
            $totals['non_standard'] += $data['non_standard'] ?? 0;
        }

        $totals['total'] = $totals['male'] + $totals['female'];
        $totals['total_services'] = $totals['standard'] + $totals['non_standard'];

        // Calculate percentage based on target
        if ($target > 0) {
            $totals['percentage'] = ($totals['standard'] / $target) * 100;
        }

        return $totals;
    }

    /**
     * Get disease type label
     *
     * @param string $diseaseType
     * @return string
     */
    protected function getDiseaseTypeLabel($diseaseType)
    {
        $labels = [
            'dm' => 'Diabetes Melitus',
            'ht' => 'Hipertensi'
        ];

        return $labels[$diseaseType] ?? ucfirst($diseaseType);
    }

    /**
     * Format data for multiple puskesmas comparison
     *
     * @param array $puskesmasIds
     * @param string $diseaseType
     * @param int $year
     * @return array
     */
    public function formatMultiplePuskesmasData($puskesmasIds, $diseaseType, $year)
    {
        $formattedData = [];

        foreach ($puskesmasIds as $puskesmasId) {
            try {
                $data = $this->formatPuskesmasData($puskesmasId, $diseaseType, $year);
                $formattedData[] = $data;
            } catch (\Exception $e) {
                Log::warning('Failed to format data for puskesmas', [
                    'puskesmas_id' => $puskesmasId,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return [
            'puskesmas_data' => $formattedData,
            'disease_type' => $diseaseType,
            'disease_type_label' => $this->getDiseaseTypeLabel($diseaseType),
            'year' => $year,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'total_puskesmas' => count($formattedData)
        ];
    }

    /**
     * Format quarterly data for puskesmas
     *
     * @param int $puskesmasId
     * @param string $diseaseType
     * @param int $year
     * @param int $quarter
     * @return array
     */
    public function formatQuarterlyData($puskesmasId, $diseaseType, $year, $quarter)
    {
        $quarterMonths = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12]
        ];

        if (!isset($quarterMonths[$quarter])) {
            throw new \Exception('Invalid quarter number');
        }

        $fullData = $this->formatPuskesmasData($puskesmasId, $diseaseType, $year);
        $months = $quarterMonths[$quarter];

        $quarterlyData = [];
        $quarterlyTotal = [
            'male' => 0,
            'female' => 0,
            'total' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'percentage' => 0
        ];

        foreach ($months as $month) {
            $monthData = $fullData['monthly_data'][$month] ?? [];
            $quarterlyData[$month] = $monthData;

            $quarterlyTotal['male'] += $monthData['male'] ?? 0;
            $quarterlyTotal['female'] += $monthData['female'] ?? 0;
            $quarterlyTotal['standard'] += $monthData['standard'] ?? 0;
            $quarterlyTotal['non_standard'] += $monthData['non_standard'] ?? 0;
        }

        $quarterlyTotal['total'] = $quarterlyTotal['male'] + $quarterlyTotal['female'];
        $totalServices = $quarterlyTotal['standard'] + $quarterlyTotal['non_standard'];

        // Get target for percentage calculation
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $fullData['puskesmas_id'])
            ->where('year', $year)
            ->where('disease_type', $diseaseType)
            ->first();
        $target = $yearlyTarget ? $yearlyTarget->target_count : 0;
        
        if ($target > 0) {
            $quarterlyTotal['percentage'] = ($quarterlyTotal['standard'] / $target) * 100;
        }

        return [
            'puskesmas_name' => $fullData['puskesmas_name'],
            'disease_type' => $diseaseType,
            'disease_type_label' => $fullData['disease_type_label'],
            'year' => $year,
            'quarter' => $quarter,
            'quarter_name' => 'Triwulan ' . $this->numberToRoman($quarter),
            'target' => $fullData['target'],
            'monthly_data' => $quarterlyData,
            'quarterly_total' => $quarterlyTotal,
            'generated_at' => now()->format('d/m/Y H:i:s')
        ];
    }

    /**
     * Convert number to Roman numeral
     *
     * @param int $number
     * @return string
     */
    protected function numberToRoman($number)
    {
        $romans = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
        return $romans[$number] ?? (string)$number;
    }
}
