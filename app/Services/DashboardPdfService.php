<?php

namespace App\Services;

use App\Repositories\PuskesmasRepository;

class DashboardPdfService
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Prepare data for dashboard PDF with optional puskesmas filter
     *
     * @param int $year
     * @param string $diseaseType
     * @param int|null $puskesmasId
     * @return array
     */
    public function prepareData(int $year, string $diseaseType, ?int $puskesmasId): array
    {
        // Gunakan StatisticsAdminService seperti AdminAllFormatter
        $request = new \Illuminate\Http\Request([
            'year' => $year,
            'type' => $diseaseType
        ]);

        $adminStatisticsData = app(\App\Services\StatisticsAdminService::class)->getAdminStatistics($request);

        // Filter data berdasarkan puskesmas jika diperlukan
        if ($puskesmasId) {
            $adminStatisticsData['data'] = array_filter(
                $adminStatisticsData['data'],
                function ($puskesmasData) use ($puskesmasId) {
                    return $puskesmasData['puskesmas_id'] == $puskesmasId;
                }
            );
        }

        // TODO: Further processing of $adminStatisticsData if needed for this method's specific purpose
        // For now, returning it directly or a processed version
        return $adminStatisticsData; // Or whatever this method is supposed to return
    } // End of prepareData method

    // Format data sesuai dengan AdminAllFormatter
    public function prepareDataForPdf(string $diseaseType, int $year): array
    {
        $request = new \Illuminate\Http\Request([
            'year' => $year,
            'type' => $diseaseType
        ]);
        $adminStatisticsData = app(\App\Services\StatisticsAdminService::class)->getAdminStatistics($request);

        $formattedData = [];
        $grandTotals = [
            'target' => 0,
            'monthly' => array_fill(0, 12, [
                'l' => 0,
                'p' => 0,
                'total' => 0,
                'ts' => 0,
                'ps' => 0
            ]),
            'quarterly' => array_fill(0, 4, [
                'l' => 0,
                'p' => 0,
                'total' => 0,
                'ts' => 0,
                'ps' => 0
            ]),
            'total_pasien' => 0,
            'total_ts_tahunan' => 0,
            'persen_capaian_tahunan' => 0
        ];

        foreach ($adminStatisticsData['data'] as $puskesmasData) {
            $diseaseData = $puskesmasData[$adminStatisticsData['type']] ?? [];
            $target = $diseaseData['target'] ?? 0;
            $grandTotals['target'] += $target;

            $formattedPuskesmas = [
                'name' => $puskesmasData['puskesmas_name'],
                'target' => $target,
                'monthly' => [],
                'quarterly' => [],
                'total_pasien' => 0,
                'total_ts_tahunan' => 0,
                'persen_capaian_tahunan' => 0
            ];

            // Format monthly data (1-12 to 0-11 index)
            for ($month = 1; $month <= 12; $month++) {
                $monthData = $diseaseData['monthly_data'][$month] ?? [
                    'male' => 0,
                    'female' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                    'total' => 0,
                    'percentage' => 0
                ];

                $formattedMonth = [
                    'l' => $monthData['male'],
                    'p' => $monthData['female'],
                    'total' => $monthData['standard'],
                    'ts' => $monthData['non_standard'],
                    'ps' => $target > 0 ? round(($monthData['standard'] / $target) * 100, 2) : 0
                ];

                $formattedPuskesmas['monthly'][$month - 1] = $formattedMonth;

                // Akumulasi untuk grand totals
                $grandTotals['monthly'][$month - 1]['l'] += $formattedMonth['l'];
                $grandTotals['monthly'][$month - 1]['p'] += $formattedMonth['p'];
                $grandTotals['monthly'][$month - 1]['total'] += $formattedMonth['total'];
                $grandTotals['monthly'][$month - 1]['ts'] += $formattedMonth['ts'];
            }

            // Format quarterly data
            for ($quarter = 0; $quarter < 4; $quarter++) {
                $endMonthIndex = ($quarter + 1) * 3 - 1; // 2, 5, 8, 11
                $lastMonthOfQuarterData = $formattedPuskesmas['monthly'][$endMonthIndex] ?? [
                    'l' => 0,
                    'p' => 0,
                    'total' => 0,
                    'ts' => 0,
                    'ps' => 0
                ];
                $formattedPuskesmas['quarterly'][$quarter] = $lastMonthOfQuarterData;

                // Akumulasi untuk grand totals quarterly
                $grandTotals['quarterly'][$quarter]['l'] += $lastMonthOfQuarterData['l'];
                $grandTotals['quarterly'][$quarter]['p'] += $lastMonthOfQuarterData['p'];
                $grandTotals['quarterly'][$quarter]['total'] += $lastMonthOfQuarterData['total'];
                $grandTotals['quarterly'][$quarter]['ts'] += $lastMonthOfQuarterData['ts'];
            }

            // PERUBAHAN UTAMA: Gunakan data bulan Desember (index 11) seperti AdminAllFormatter
            $decemberData = $formattedPuskesmas['monthly'][11]; // Desember adalah index 11
            
            // Calculate TOTAL PASIEN berdasarkan data Desember
            $formattedPuskesmas['total_pasien'] = $decemberData['total'] + $decemberData['ts'];
            
            // Calculate % CAPAIAN PELAYANAN SESUAI STANDAR TAHUNAN berdasarkan data Desember
            $formattedPuskesmas['persen_capaian_tahunan'] = $decemberData['ps'];

            $grandTotals['total_pasien'] += $formattedPuskesmas['total_pasien'];

            $formattedData[] = $formattedPuskesmas;
        }

        // Hitung persentase untuk grand totals
        foreach ($grandTotals['monthly'] as &$monthTotal) {
            $monthTotal['ps'] = $grandTotals['target'] > 0
                ? round(($monthTotal['total'] / $grandTotals['target']) * 100, 2)
                : 0;
        }

        foreach ($grandTotals['quarterly'] as &$quarterTotal) {
            $quarterTotal['ps'] = $grandTotals['target'] > 0
                ? round(($quarterTotal['total'] / $grandTotals['target']) * 100, 2)
                : 0;
        }

        // PERUBAHAN UTAMA: Gunakan data Desember untuk grand total seperti AdminAllFormatter
        $grandTotalDecemberData = $grandTotals['monthly'][11]; // Desember
        $grandTotals['persen_capaian_tahunan'] = $grandTotalDecemberData['ps'];

        $labels = [
            'dm' => 'DIABETES MELITUS (DM)',
            'ht' => 'HIPERTENSI (HT)',
        ];

        $monthNames = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        return [
            'puskesmas_data' => $formattedData,
            'grand_total' => $grandTotals,
            'disease_type_label' => $labels[$diseaseType] ?? strtoupper($diseaseType),
            'months' => $monthNames
        ];
    }

    /**
     * Format monthly statistics data
     *
     * @param array|null $stats Raw stats for the month {male, female, standard, non_standard, total, percentage}
     * @param int $yearlyTarget The yearly target for the puskesmas
     * @return array Formatted {l, p, total, ts, ps}
     */
    protected function formatMonthlyData(?array $stats, int $yearlyTarget): array
    {
        if (!$stats) {
            return [
                'l' => 0, // Sesuai standar Laki-laki
                'p' => 0, // Sesuai standar Perempuan
                'total' => 0, // Total Sesuai Standar (S)
                'ts' => 0,    // Total Tidak Sesuai Standar (TS)
                'ps' => 0     // %S (Persentase Sesuai Standar terhadap target)
            ];
        }

        $standardMale = $stats['male'] ?? 0;
        $standardFemale = $stats['female'] ?? 0;
        $standardTotal = $stats['standard'] ?? ($standardMale + $standardFemale); // S
        $nonStandardTotal = $stats['non_standard'] ?? 0; // TS

        // Percentage Standard (PS) is based on the yearly target for the specific puskesmas
        $percentageStandard = $yearlyTarget > 0
            ? round(($standardTotal / $yearlyTarget) * 100, 2)
            : 0;

        return [
            'l' => $standardMale,
            'p' => $standardFemale,
            'total' => $standardTotal,
            'ts' => $nonStandardTotal,
            'ps' => $percentageStandard
        ];
    }

    /**
     * Get quarterly data based on the latest available month with data
     *
     * @param array $monthlyFormattedData Formatted monthly data for a puskesmas
     * @param int $quarter (0-3)
     * @param int $yearlyTarget
     * @return array
     */
    public function getQuarterData(array $monthlyFormattedData, int $quarter, int $yearlyTarget): array
    {
        $startMonthIndex = $quarter * 3;
        $monthIndices = [$startMonthIndex + 2, $startMonthIndex + 1, $startMonthIndex];
        $latestMonthWithData = null;

        // Mencari data terakhir yang memiliki nilai dalam triwulan
        foreach ($monthIndices as $monthIndex) {
            if (isset($monthlyFormattedData[$monthIndex])) {
                if (($monthlyFormattedData[$monthIndex]['total'] ?? 0) > 0 || ($monthlyFormattedData[$monthIndex]['ts'] ?? 0) > 0) {
                    $latestMonthWithData = $monthlyFormattedData[$monthIndex];
                    break;
                }
                if ($latestMonthWithData === null) {
                    $latestMonthWithData = $monthlyFormattedData[$monthIndex];
                }
            }
        }

        return [
            'l' => $latestMonthWithData['l'] ?? 0,
            'p' => $latestMonthWithData['p'] ?? 0,
            'total' => $latestMonthWithData['total'] ?? 0,  // S Total
            'ts' => $latestMonthWithData['ts'] ?? 0,        // TS
            'ps' => $latestMonthWithData['ps'] ?? 0
        ];
    }

    /**
     * Calculate totals for a specific month across all puskesmas
     *
     * @param array $allPuskesmasData Array of prepared puskesmas data
     * @param int $monthIndex (0-11)
     * @param int $totalOverallTarget Sum of targets of all puskesmas
     * @return array
     */
    public function calculateMonthlyTotals(array $allPuskesmasData, int $monthIndex, int $totalOverallTarget): array
    {
        $totals = [
            'l' => 0,
            'p' => 0,
            'total' => 0, // Total Sesuai Standar (S)
            'ts' => 0,    // Total Tidak Sesuai Standar (TS)
            'ps' => 0     // %S (Persentase Sesuai Standar terhadap total target keseluruhan)
        ];

        foreach ($allPuskesmasData as $puskesmasRow) {
            $monthData = $puskesmasRow['monthly'][$monthIndex] ?? null;
            if ($monthData) {
                $totals['l'] += $monthData['l'] ?? 0;
                $totals['p'] += $monthData['p'] ?? 0;
                $totals['total'] += $monthData['total'] ?? 0;
                $totals['ts'] += $monthData['ts'] ?? 0;
            }
        }

        $totals['ps'] = $totalOverallTarget > 0
            ? round(($totals['total'] / $totalOverallTarget) * 100, 2)
            : 0;

        return $totals;
    }

    /**
     * Calculate totals for a specific quarter across all puskesmas
     *
     * @param array $allPuskesmasData
     * @param int $quarterIndex (0-3)
     * @param int $totalOverallTarget
     * @return array
     */
    public function calculateQuarterlyTotals(array $allPuskesmasData, int $quarterIndex, int $totalOverallTarget): array
    {
        $totals = [
            'l' => 0,
            'p' => 0,
            'total' => 0,  // S Total
            'ts' => 0,     // TS
            'ps' => 0
        ];

        foreach ($allPuskesmasData as $puskesmasRow) {
            $quarterData = $puskesmasRow['quarterly'][$quarterIndex] ?? null;
            if ($quarterData) {
                $totals['l'] += $quarterData['l'] ?? 0;
                $totals['p'] += $quarterData['p'] ?? 0;
                $totals['total'] += $quarterData['total'] ?? 0;  // Akumulasi S Total
                $totals['ts'] += $quarterData['ts'] ?? 0;        // Akumulasi TS
            }
        }

        $totals['ps'] = $totalOverallTarget > 0
            ? round(($totals['total'] / $totalOverallTarget) * 100, 2)
            : 0;

        return $totals;
    }

    /**
     * Calculate grand totals for all puskesmas
     *
     * @param array $allPuskesmasData
     * @param array $statisticsRawData Raw data from StatisticsService to get overall target
     * @return array
     */
    protected function calculateGrandTotals(array $allPuskesmasData, array $statisticsRawData): array
    {
        $grandTotalTarget = 0;
        // Calculate total target from raw data to ensure accuracy if some puskesmas have no monthly data
        foreach ($statisticsRawData as $puskesmasId => $puskesmasRaw) {
            if ($puskesmasId !== 'type') { // Skip the 'type' key
                $grandTotalTarget += $puskesmasRaw['target'] ?? 0;
            }
        }

        $grandTotals = [
            'target' => $grandTotalTarget,
            'monthly' => [],
            'quarterly' => [],
            'total_pasien_tahunan' => 0, // Grand total Sesuai Standar tahunan
            'total_ts_tahunan' => 0, // Grand total Tidak Sesuai Standar tahunan
            'persen_capaian_tahunan' => 0,
        ];

        $cumulativeGrandStandardAnnual = 0;
        $cumulativeGrandNonStandardAnnual = 0;

        for ($month = 0; $month < 12; $month++) {
            $monthlyTotal = $this->calculateMonthlyTotals($allPuskesmasData, $month, $grandTotalTarget);
            $grandTotals['monthly'][$month] = $monthlyTotal;
            $cumulativeGrandStandardAnnual += $monthlyTotal['total'];
            $cumulativeGrandNonStandardAnnual += $monthlyTotal['ts'];
        }

        for ($q = 0; $q < 4; $q++) {
            $grandTotals['quarterly'][$q] = $this->calculateQuarterlyTotals($allPuskesmasData, $q, $grandTotalTarget);
        }

        $grandTotals['total_pasien_tahunan'] = $cumulativeGrandStandardAnnual;
        $grandTotals['total_ts_tahunan'] = $cumulativeGrandNonStandardAnnual;
        $grandTotals['persen_capaian_tahunan'] = $grandTotalTarget > 0
            ? round(($cumulativeGrandStandardAnnual / $grandTotalTarget) * 100, 2)
            : 0;

        return $grandTotals;
    }
}
