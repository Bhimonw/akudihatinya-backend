<?php

namespace App\Formatters;

use App\Services\Statistics\StatisticsService;
use App\Services\Statistics\StatisticsDataService;
use App\Services\Statistics\OptimizedStatisticsService;
use App\Services\Statistics\StatisticsAdminService;
use App\Services\Statistics\RealTimeStatisticsService;
use Carbon\Carbon;

/**
 * Formatter untuk data statistik yang mengintegrasikan semua statistics service
 * Menyediakan format data yang konsisten untuk dashboard dan laporan
 */
class StatisticsFormatter extends BaseAdminFormatter
{
    private $statisticsService;
    private $statisticsDataService;
    private $optimizedStatisticsService;
    private $statisticsAdminService;
    private $realTimeStatisticsService;

    public function __construct(
        StatisticsService $statisticsService,
        StatisticsDataService $statisticsDataService,
        OptimizedStatisticsService $optimizedStatisticsService,
        StatisticsAdminService $statisticsAdminService,
        RealTimeStatisticsService $realTimeStatisticsService
    ) {
        $this->statisticsService = $statisticsService;
        $this->statisticsDataService = $statisticsDataService;
        $this->optimizedStatisticsService = $optimizedStatisticsService;
        $this->statisticsAdminService = $statisticsAdminService;
        $this->realTimeStatisticsService = $realTimeStatisticsService;
    }

    /**
     * Format data statistik untuk dashboard utama
     */
    public function formatDashboardStatistics($puskesmasAll, $year, $month = null, $diseaseType = 'all')
    {
        // Gunakan StatisticsDataService untuk konsistensi data
        $statistics = $this->statisticsDataService->getConsistentStatisticsData(
            $puskesmasAll, 
            $year, 
            $month, 
            $diseaseType
        );

        return array_map(function ($stat) use ($diseaseType) {
            $formatted = [
                'puskesmas_id' => $stat['puskesmas_id'],
                'puskesmas_name' => $stat['puskesmas_name'],
                'ranking' => $stat['ranking'] ?? null,
            ];

            if (isset($stat['ht']) && ($diseaseType === 'all' || $diseaseType === 'ht')) {
                $formatted['ht'] = $this->formatHtStatistics($stat['ht']);
            }

            if (isset($stat['dm']) && ($diseaseType === 'all' || $diseaseType === 'dm')) {
                $formatted['dm'] = $this->formatDmStatistics($stat['dm']);
            }

            return $formatted;
        }, $statistics);
    }

    /**
     * Format data statistik untuk admin dengan optimasi
     */
    public function formatAdminStatistics($request)
    {
        $year = $request->year ?? date('Y');
        $month = $request->month;
        $diseaseType = $request->disease_type ?? 'all';
        $perPage = $request->per_page ?? 15;
        $currentPage = $request->page ?? 1;

        // Gunakan StatisticsAdminService untuk admin
        $result = $this->statisticsAdminService->getAdminStatistics($request);
        
        if (!$result['success']) {
            return $result;
        }

        // Format data dengan konsistensi
        $formattedData = $this->statisticsDataService->formatDataForAdmin(
            $result['data'], 
            $diseaseType
        );

        return [
            'success' => true,
            'message' => 'Data statistik admin berhasil diformat',
            'data' => $formattedData,
            'meta' => $result['meta'] ?? [],
            'summary' => $this->formatSummaryStatistics($result['summary'] ?? [], $diseaseType),
        ];
    }

    /**
     * Format data statistik dengan caching optimal
     */
    public function formatOptimizedStatistics($puskesmasIds, $year, $month = null, $diseaseType = 'all')
    {
        // Gunakan OptimizedStatisticsService untuk performa tinggi
        $summary = $this->optimizedStatisticsService->calculateSummaryStatistics(
            $puskesmasIds, 
            $year, 
            $month, 
            $diseaseType
        );

        return $this->formatSummaryStatistics($summary, $diseaseType);
    }

    /**
     * Format data HT dengan konsistensi
     */
    private function formatHtStatistics($htData)
    {
        return [
            'target' => $this->formatNumber($htData['target'] ?? 0),
            'total_patients' => $this->formatNumber($htData['total_patients'] ?? 0),
            'standard_patients' => $this->formatNumber($htData['standard_patients'] ?? 0),
            'non_standard_patients' => $this->formatNumber($htData['non_standard_patients'] ?? 0),
            'male_patients' => $this->formatNumber($htData['male_patients'] ?? 0),
            'female_patients' => $this->formatNumber($htData['female_patients'] ?? 0),
            'achievement_percentage' => $this->formatPercentage($htData['achievement_percentage'] ?? 0),
            'monthly_data' => $this->formatMonthlyData($htData['monthly_data'] ?? []),
        ];
    }

    /**
     * Format data DM dengan konsistensi
     */
    private function formatDmStatistics($dmData)
    {
        return [
            'target' => $this->formatNumber($dmData['target'] ?? 0),
            'total_patients' => $this->formatNumber($dmData['total_patients'] ?? 0),
            'standard_patients' => $this->formatNumber($dmData['standard_patients'] ?? 0),
            'non_standard_patients' => $this->formatNumber($dmData['non_standard_patients'] ?? 0),
            'male_patients' => $this->formatNumber($dmData['male_patients'] ?? 0),
            'female_patients' => $this->formatNumber($dmData['female_patients'] ?? 0),
            'achievement_percentage' => $this->formatPercentage($dmData['achievement_percentage'] ?? 0),
            'monthly_data' => $this->formatMonthlyData($dmData['monthly_data'] ?? []),
        ];
    }

    /**
     * Format data bulanan
     */
    private function formatMonthlyData($monthlyData)
    {
        $formatted = [];
        
        foreach ($monthlyData as $month => $data) {
            $formatted[$month] = [
                'male' => $this->formatNumber($data['male'] ?? 0),
                'female' => $this->formatNumber($data['female'] ?? 0),
                'total' => $this->formatNumber($data['total'] ?? 0),
                'standard' => $this->formatNumber($data['standard'] ?? 0),
                'non_standard' => $this->formatNumber($data['non_standard'] ?? 0),
                'percentage' => $this->formatPercentage($data['percentage'] ?? 0),
                'month_name' => $this->statisticsDataService->getMonthName($month),
            ];
        }

        return $formatted;
    }

    /**
     * Format summary statistics
     */
    private function formatSummaryStatistics($summary, $diseaseType)
    {
        $formatted = [];

        if (isset($summary['ht']) && ($diseaseType === 'all' || $diseaseType === 'ht')) {
            $formatted['ht'] = $this->formatHtStatistics($summary['ht']);
        }

        if (isset($summary['dm']) && ($diseaseType === 'all' || $diseaseType === 'dm')) {
            $formatted['dm'] = $this->formatDmStatistics($summary['dm']);
        }

        return $formatted;
    }

    /**
     * Format data for PDF export - Puskesmas Statistics
     */
    public function formatPuskesmasStatisticsForPdf(int $puskesmasId, int $year, string $diseaseType = 'ht'): array
    {
        try {
            // Get puskesmas data using optimized service
            $puskesmasData = $this->optimizedStatisticsService->getLatestStatisticsOptimized(
                $diseaseType, [$puskesmasId], $year
            );
            
            $currentPuskesmas = $puskesmasData[0] ?? null;
            if (!$currentPuskesmas) {
                throw new \Exception("Puskesmas dengan ID {$puskesmasId} tidak ditemukan");
            }

            // Get monthly data using statistics data service
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $stats = $this->statisticsDataService->getConsistentStatisticsData(
                    [$puskesmasId], $year, $month, $diseaseType
                );
                
                $puskesmasStats = $stats[0] ?? [];
                $htData = $puskesmasStats['ht_statistics'] ?? [];
                $dmData = $puskesmasStats['dm_statistics'] ?? [];
                
                $data = $diseaseType === 'ht' ? $htData : $dmData;
                $total = $data['total_patients'] ?? 0;
                $standardPercentage = $total > 0 ? 
                    round(($data['standard_patients'] / $total) * 100, 2) : 0;
                
                $monthlyData[$month] = [
                    'male' => $data['male_patients'] ?? 0,
                    'female' => $data['female_patients'] ?? 0,
                    'standard' => $data['standard_patients'] ?? 0,
                    'non_standard' => $data['non_standard_patients'] ?? 0,
                    'total' => $total,
                    'percentage' => $standardPercentage
                ];
            }

            // Calculate yearly totals
            $yearlyTotal = $this->calculateYearlyTotals($monthlyData);
            $yearlyTarget = $currentPuskesmas['yearly_target'] ?? 0;
            $achievementPercentage = $yearlyTarget > 0 ? 
                round(($yearlyTotal['standard'] / $yearlyTarget) * 100, 2) : 0;

            return [
                'puskesmas_name' => $currentPuskesmas['nama_puskesmas'] ?? '',
                'year' => $year,
                'disease_type' => $diseaseType,
                'disease_label' => $this->getDiseaseLabel($diseaseType),
                'monthly_data' => $monthlyData,
                'yearly_target' => $yearlyTarget,
                'yearly_total' => $yearlyTotal,
                'achievement_percentage' => $achievementPercentage,
                'generated_at' => now()->format('d/m/Y H:i:s')
            ];
            
        } catch (\Exception $e) {
            throw new \Exception("Error formatting puskesmas statistics for PDF: " . $e->getMessage());
        }
    }

    /**
     * Format data for PDF export - All Quarters Recap
     */
    public function formatAllQuartersRecapForPdf(int $year, string $diseaseType = 'ht'): array
    {
        try {
            // Get all puskesmas using optimized service
            $allPuskesmasData = $this->optimizedStatisticsService->getLatestStatisticsOptimized(
                $diseaseType, [], $year
            );
            
            $quarterData = [];
            
            // Data for each quarter
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
                
                foreach ($allPuskesmasData as $puskesmas) {
                    $puskesmasId = $puskesmas['id'];
                    
                    // Get quarterly data
                    $quarterStats = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
                    $monthlyData = [];
                    
                    $quarterMonthNumbers = [
                        1 => [1, 2, 3],
                        2 => [4, 5, 6],
                        3 => [7, 8, 9],
                        4 => [10, 11, 12]
                    ];
                    
                    foreach ($quarterMonthNumbers[$quarter] as $month) {
                        $stats = $this->statisticsDataService->getConsistentStatisticsData(
                            [$puskesmasId], $year, $month, $diseaseType
                        );
                        
                        $puskesmasStats = $stats[0] ?? [];
                        $data = $diseaseType === 'ht' ? 
                            ($puskesmasStats['ht_statistics'] ?? []) : 
                            ($puskesmasStats['dm_statistics'] ?? []);
                        
                        $monthlyData[] = $data;
                        
                        $quarterStats['male'] += $data['male_patients'] ?? 0;
                        $quarterStats['female'] += $data['female_patients'] ?? 0;
                        $quarterStats['standard'] += $data['standard_patients'] ?? 0;
                        $quarterStats['non_standard'] += $data['non_standard_patients'] ?? 0;
                        $quarterStats['total'] += $data['total_patients'] ?? 0;
                    }
                    
                    $yearlyTarget = $puskesmas['yearly_target'] ?? 0;
                    $achievementPercentage = $yearlyTarget > 0 ? 
                        round(($quarterStats['standard'] / $yearlyTarget) * 100, 2) : 0;
                    
                    $puskesmasData[] = [
                        'name' => $puskesmas['nama_puskesmas'] ?? '',
                        'target' => $yearlyTarget,
                        'monthly' => $monthlyData,
                        'male_patients' => $quarterStats['male'],
                        'female_patients' => $quarterStats['female'],
                        'standard_patients' => $quarterStats['standard'],
                        'non_standard_patients' => $quarterStats['non_standard'],
                        'total_patients' => $quarterStats['total'],
                        'achievement_percentage' => $achievementPercentage
                    ];
                    
                    // Update grand total
                    $grandTotal['target'] += $yearlyTarget;
                    $grandTotal['male'] += $quarterStats['male'];
                    $grandTotal['female'] += $quarterStats['female'];
                    $grandTotal['standard'] += $quarterStats['standard'];
                    $grandTotal['non_standard'] += $quarterStats['non_standard'];
                    $grandTotal['total'] += $quarterStats['total'];
                }
                
                $grandTotal['achievement_percentage'] = $grandTotal['target'] > 0 ? 
                    round(($grandTotal['standard'] / $grandTotal['target']) * 100, 2) : 0;
                
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
                'generated_at' => now()->format('d/m/Y H:i:s')
            ];
            
        } catch (\Exception $e) {
            throw new \Exception("Error formatting all quarters recap for PDF: " . $e->getMessage());
        }
    }

    /**
     * Format data for Excel export - All Report
     */
    public function formatAllExcelData(string $diseaseType, int $year): array
    {
        try {
            // Get all puskesmas data using optimized service
            $allPuskesmasData = $this->optimizedStatisticsService->getLatestStatisticsOptimized(
                $diseaseType, [], $year
            );
            
            $formattedData = [];
            
            foreach ($allPuskesmasData as $puskesmas) {
                $puskesmasId = $puskesmas['id'];
                
                // Get monthly data for the year
                $monthlyData = [];
                for ($month = 1; $month <= 12; $month++) {
                    $stats = $this->statisticsDataService->getConsistentStatisticsData(
                        [$puskesmasId], $year, $month, $diseaseType
                    );
                    
                    $puskesmasStats = $stats[0] ?? [];
                    $data = $diseaseType === 'ht' ? 
                        ($puskesmasStats['ht_statistics'] ?? []) : 
                        ($puskesmasStats['dm_statistics'] ?? []);
                    
                    $monthlyData[$month] = [
                        'male' => $data['male_patients'] ?? 0,
                        'female' => $data['female_patients'] ?? 0,
                        'standard' => $data['standard_patients'] ?? 0,
                        'non_standard' => $data['non_standard_patients'] ?? 0,
                        'total' => $data['total_patients'] ?? 0,
                        'percentage' => $data['total_patients'] > 0 ? 
                            round(($data['standard_patients'] / $data['total_patients']) * 100, 2) : 0
                    ];
                }
                
                $formattedData[] = [
                    'nama_puskesmas' => $puskesmas['nama_puskesmas'] ?? '',
                    'sasaran' => $puskesmas['yearly_target'] ?? 0,
                    'monthly_data' => $monthlyData
                ];
            }
            
            return $formattedData;
            
        } catch (\Exception $e) {
            throw new \Exception("Error formatting Excel data: " . $e->getMessage());
        }
    }

    /**
     * Format data for Excel export - Monthly Report
     */
    public function formatMonthlyExcelData(string $diseaseType, int $year): array
    {
        return $this->formatAllExcelData($diseaseType, $year);
    }

    /**
     * Format data for Excel export - Quarterly Report
     */
    public function formatQuarterlyExcelData(string $diseaseType, int $year): array
    {
        try {
            // Get all puskesmas data using optimized service
            $allPuskesmasData = $this->optimizedStatisticsService->getLatestStatisticsOptimized(
                $diseaseType, [], $year
            );
            
            $formattedData = [];
            
            foreach ($allPuskesmasData as $puskesmas) {
                $puskesmasId = $puskesmas['id'];
                
                // Get quarterly data
                $quarterlyData = [];
                for ($quarter = 1; $quarter <= 4; $quarter++) {
                    $quarterMonthNumbers = [
                        1 => [1, 2, 3],
                        2 => [4, 5, 6],
                        3 => [7, 8, 9],
                        4 => [10, 11, 12]
                    ];
                    
                    $quarterStats = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
                    
                    foreach ($quarterMonthNumbers[$quarter] as $month) {
                        $stats = $this->statisticsDataService->getConsistentStatisticsData(
                            [$puskesmasId], $year, $month, $diseaseType
                        );
                        
                        $puskesmasStats = $stats[0] ?? [];
                        $data = $diseaseType === 'ht' ? 
                            ($puskesmasStats['ht_statistics'] ?? []) : 
                            ($puskesmasStats['dm_statistics'] ?? []);
                        
                        $quarterStats['male'] += $data['male_patients'] ?? 0;
                        $quarterStats['female'] += $data['female_patients'] ?? 0;
                        $quarterStats['standard'] += $data['standard_patients'] ?? 0;
                        $quarterStats['non_standard'] += $data['non_standard_patients'] ?? 0;
                        $quarterStats['total'] += $data['total_patients'] ?? 0;
                    }
                    
                    $quarterlyData[$quarter] = [
                        'male' => $quarterStats['male'],
                        'female' => $quarterStats['female'],
                        'standard' => $quarterStats['standard'],
                        'non_standard' => $quarterStats['non_standard'],
                        'total' => $quarterStats['total'],
                        'percentage' => $quarterStats['total'] > 0 ? 
                            round(($quarterStats['standard'] / $quarterStats['total']) * 100, 2) : 0
                    ];
                }
                
                $formattedData[] = [
                    'nama_puskesmas' => $puskesmas['nama_puskesmas'] ?? '',
                    'sasaran' => $puskesmas['yearly_target'] ?? 0,
                    'quarterly_data' => $quarterlyData
                ];
            }
            
            return $formattedData;
            
        } catch (\Exception $e) {
            throw new \Exception("Error formatting quarterly Excel data: " . $e->getMessage());
        }
    }

    /**
     * Format data for Excel export - Puskesmas Template
     */
    public function formatPuskesmasExcelData(int $puskesmasId, string $diseaseType, int $year): array
    {
        try {
            // Get puskesmas data using optimized service
            $puskesmasData = $this->optimizedStatisticsService->getLatestStatisticsOptimized(
                $diseaseType, [$puskesmasId], $year
            );
            
            $currentPuskesmas = $puskesmasData[0] ?? null;
            if (!$currentPuskesmas) {
                throw new \Exception("Puskesmas dengan ID {$puskesmasId} tidak ditemukan");
            }

            // Get monthly data
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $stats = $this->statisticsDataService->getConsistentStatisticsData(
                    [$puskesmasId], $year, $month, $diseaseType
                );
                
                $puskesmasStats = $stats[0] ?? [];
                $data = $diseaseType === 'ht' ? 
                    ($puskesmasStats['ht_statistics'] ?? []) : 
                    ($puskesmasStats['dm_statistics'] ?? []);
                
                $monthlyData[$month] = [
                    'male' => $data['male_patients'] ?? 0,
                    'female' => $data['female_patients'] ?? 0,
                    'standard' => $data['standard_patients'] ?? 0,
                    'non_standard' => $data['non_standard_patients'] ?? 0,
                    'total' => $data['total_patients'] ?? 0,
                    'percentage' => $data['total_patients'] > 0 ? 
                        round(($data['standard_patients'] / $data['total_patients']) * 100, 2) : 0
                ];
            }
            
            return [
                'nama_puskesmas' => $currentPuskesmas['nama_puskesmas'] ?? '',
                'sasaran' => $currentPuskesmas['yearly_target'] ?? 0,
                'monthly_data' => $monthlyData
            ];
            
        } catch (\Exception $e) {
            throw new \Exception("Error formatting puskesmas Excel data: " . $e->getMessage());
        }
    }

    /**
     * Format data untuk chart/grafik
     */
    public function formatForChart($statistics, $diseaseType = 'all')
    {
        $chartData = [
            'labels' => [],
            'datasets' => []
        ];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = [
                'label' => 'Hipertensi',
                'data' => [],
                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                'borderColor' => 'rgba(255, 99, 132, 1)',
            ];
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = [
                'label' => 'Diabetes Mellitus',
                'data' => [],
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderColor' => 'rgba(54, 162, 235, 1)',
            ];
        }

        foreach ($statistics as $stat) {
            $chartData['labels'][] = $stat['puskesmas_name'];
            
            if (isset($htData) && isset($stat['ht'])) {
                $htData['data'][] = $stat['ht']['achievement_percentage'] ?? 0;
            }
            
            if (isset($dmData) && isset($stat['dm'])) {
                $dmData['data'][] = $stat['dm']['achievement_percentage'] ?? 0;
            }
        }

        if (isset($htData)) {
            $chartData['datasets'][] = $htData;
        }
        
        if (isset($dmData)) {
            $chartData['datasets'][] = $dmData;
        }

        return $chartData;
    }

    /**
     * Format data untuk real-time updates
     */
    public function formatRealTimeUpdate($examination, $diseaseType)
    {
        return [
            'puskesmas_id' => $examination->puskesmas_id,
            'disease_type' => $diseaseType,
            'year' => $examination->year,
            'month' => $examination->month,
            'is_standard_patient' => $examination->is_standard_patient,
            'patient_gender' => $examination->patient_gender,
            'is_first_visit_this_month' => $examination->is_first_visit_this_month,
            'updated_at' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Helper method to calculate yearly totals
     */
    private function calculateYearlyTotals(array $monthlyData): array
    {
        $totals = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
        
        foreach ($monthlyData as $data) {
            $totals['male'] += $data['male'];
            $totals['female'] += $data['female'];
            $totals['standard'] += $data['standard'];
            $totals['non_standard'] += $data['non_standard'];
            $totals['total'] += $data['total'];
        }
        
        return $totals;
    }

    /**
     * Helper method to get disease label
     */
    private function getDiseaseLabel(string $diseaseType): string
    {
        return $diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes Mellitus';
    }

    /**
     * Helper method to get roman numeral
     */
    private function getRomanNumeral(int $number): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
        return $map[$number] ?? (string)$number;
    }

    /**
     * Validasi dan format parameter request
     */
    public function validateAndFormatRequest($request)
    {
        $errors = $this->statisticsDataService->validateParameters($request);
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $errors
            ];
        }

        return [
            'success' => true,
            'data' => [
                'year' => $request->year ?? date('Y'),
                'month' => $request->month,
                'disease_type' => $request->disease_type ?? 'all',
                'per_page' => $request->per_page ?? 15,
                'page' => $request->page ?? 1,
                'name' => $request->name,
                'format' => $request->format,
                'table_type' => $request->table_type,
            ]
        ];
    }

    /**
     * Format response dengan metadata
     */
    public function formatResponse($data, $message = 'Data berhasil diformat', $meta = [])
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'formatted_at' => Carbon::now()->toISOString(),
                'formatter_version' => '1.0.0',
            ], $meta)
        ];
    }

    /**
     * Format data for PDF export (Legacy method for backward compatibility)
     */
    public function formatForPdfExport(array $data, array $options = []): array
    {
        $diseaseType = $options['disease_type'] ?? 'ht';
        $year = $options['year'] ?? date('Y');
        $puskesmasId = $options['puskesmas_id'] ?? null;
        
        if ($puskesmasId) {
            return $this->formatPuskesmasStatisticsForPdf($puskesmasId, $year, $diseaseType);
        } else {
            return $this->formatAllQuartersRecapForPdf($year, $diseaseType);
        }
    }

    /**
     * Format data for Excel export (Legacy method for backward compatibility)
     */
    public function formatForExcelExport(array $data, array $options = []): array
    {
        $diseaseType = $options['disease_type'] ?? 'ht';
        $year = $options['year'] ?? date('Y');
        $reportType = $options['report_type'] ?? 'all';
        $puskesmasId = $options['puskesmas_id'] ?? null;
        
        switch ($reportType) {
            case 'monthly':
                return $this->formatMonthlyExcelData($diseaseType, $year);
            case 'quarterly':
                return $this->formatQuarterlyExcelData($diseaseType, $year);
            case 'puskesmas':
                if ($puskesmasId) {
                    return $this->formatPuskesmasExcelData($puskesmasId, $diseaseType, $year);
                }
                break;
            default:
                return $this->formatAllExcelData($diseaseType, $year);
        }
        
        return $this->formatAllExcelData($diseaseType, $year);
    }
}