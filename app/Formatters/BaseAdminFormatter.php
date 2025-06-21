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
            // Menggunakan pendekatan yang sama seperti StatisticsController dashboard admin
            $allPuskesmas = \App\Models\Puskesmas::all();
            $data = [];
            
            // Variabel untuk menghitung summary total (seperti di StatisticsController)
            $totalTarget = 0;
            $totalPatients = 0;
            $totalStandard = 0;
            $totalNonStandard = 0;
            $totalMale = 0;
            $totalFemale = 0;
            $monthlyData = [];
            
            foreach ($allPuskesmas as $puskesmas) {
                // Get statistics data from cache untuk setiap puskesmas
                if ($diseaseType === 'ht') {
                    $diseaseData = $this->statisticsService->getHtStatisticsFromCache($puskesmas->id, $year);
                } else {
                    $diseaseData = $this->statisticsService->getDmStatisticsFromCache($puskesmas->id, $year);
                }
                
                // Get target untuk puskesmas ini
                $target = \App\Models\YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('year', $year)
                    ->where('disease_type', $diseaseType)
                    ->value('target_count') ?? 0;
                
                // Tambahkan target ke diseaseData
                $diseaseData['target'] = $target;
                
                // Akumulasi untuk summary (menggunakan data summary dari setiap puskesmas)
                $summary = $diseaseData['summary'] ?? [];
                $totalTarget += $target;
                $totalPatients += (int)($summary['total'] ?? 0);
                $totalStandard += (int)($summary['standard'] ?? 0);
                $totalNonStandard += (int)($summary['non_standard'] ?? 0);
                $totalMale += (int)($summary['male'] ?? 0);
                $totalFemale += (int)($summary['female'] ?? 0);
                
                // Agregasi data bulanan untuk summary
                foreach ($diseaseData['monthly_data'] ?? [] as $month => $monthData) {
                    if (!isset($monthlyData[$month])) {
                        $monthlyData[$month] = [
                            'male' => 0,
                            'female' => 0,
                            'total' => 0,
                            'standard' => 0,
                            'non_standard' => 0,
                            'percentage' => 0
                        ];
                    }
                    $monthlyData[$month]['male'] += (int)($monthData['male'] ?? 0);
                    $monthlyData[$month]['female'] += (int)($monthData['female'] ?? 0);
                    $monthlyData[$month]['total'] += (int)($monthData['total'] ?? 0);
                    $monthlyData[$month]['standard'] += (int)($monthData['standard'] ?? 0);
                    $monthlyData[$month]['non_standard'] += (int)($monthData['non_standard'] ?? 0);
                }
                
                $data[] = [
                    'puskesmas_id' => $puskesmas->id,
                    'puskesmas_name' => $puskesmas->name,
                    $diseaseType => $diseaseData
                ];
            }
            
            // Hitung persentase untuk data bulanan summary
            foreach ($monthlyData as $month => &$monthData) {
                $monthData['percentage'] = $totalTarget > 0 ? round(($monthData['standard'] / $totalTarget) * 100, 2) : 0;
            }
            
            // Buat data summary seperti di StatisticsController
            $summaryData = [
                'target' => $totalTarget,
                'total' => $totalPatients,
                'standard' => $totalStandard,
                'non_standard' => $totalNonStandard,
                'male' => $totalMale,
                'female' => $totalFemale,
                'percentage' => $totalTarget > 0 ? round(($totalStandard / $totalTarget) * 100, 2) : 0,
                'monthly_data' => $monthlyData
            ];
            
            return [
                'data' => $data,
                'summary' => $summaryData,
                'type' => $diseaseType
            ];
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
