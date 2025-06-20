<?php

namespace App\Formatters;

use App\Services\StatisticsService;

abstract class BaseAdminFormatter
{
    protected $statisticsService;
    protected $sheet;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    protected function getStatisticsData($diseaseType, $year, $puskesmasId = null)
    {
        if ($puskesmasId) {
            return [
                'data' => [
                    [
                        'puskesmas_id' => $puskesmasId,
                        'puskesmas_name' => \App\Models\Puskesmas::find($puskesmasId)->name,
                        'ht' => $this->statisticsService->getHtStatisticsFromCache($puskesmasId, $year),
                        'dm' => $this->statisticsService->getDmStatisticsFromCache($puskesmasId, $year)
                    ]
                ],
                'type' => $diseaseType
            ];
        } else {
            $request = new \Illuminate\Http\Request([
                'year' => $year,
                'type' => $diseaseType
            ]);
            return app(\App\Services\StatisticsAdminService::class)->getAdminStatistics($request);
        }
    }

    /**
     * Hitung total capaian tahunan (S Total Desember), total pelayanan (Total pasien Desember),
     * dan persentase capaian pelayanan sesuai standar (S Total Desember / target tahunan)
     * @param array $allMonthlyData Array of monthly_data dari banyak puskesmas
     * @param int $target Total target tahunan
     * @return array [standard, total, percentage]
     */
    protected function getYearlySummary(array $allMonthlyData, int $target): array
    {
        $summary = [
            'standard' => 0,
            'total' => 0,
            'percentage' => 0
        ];
        foreach ($allMonthlyData as $monthlyData) {
            // Get last available month data (flexible, not hardcoded to December)
            $lastMonthData = null;
            for ($month = 12; $month >= 1; $month--) {
                if (isset($monthlyData[$month]) && ($monthlyData[$month]['total'] ?? 0) > 0) {
                    $lastMonthData = $monthlyData[$month];
                    break;
                }
            }
            
            if ($lastMonthData) {
                $summary['standard'] += $lastMonthData['standard'] ?? 0;
                $summary['total'] += $lastMonthData['total'] ?? 0;
            }
        }
        $summary['percentage'] = $target > 0 ? round(($summary['standard'] / $target) * 100, 2) : 0;
        return $summary;
    }
}
