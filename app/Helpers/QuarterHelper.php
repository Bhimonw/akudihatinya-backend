<?php

namespace App\Helpers;

class QuarterHelper
{
    /**
     * Ambil summary data untuk satu triwulan dari array monthly_data.
     * Jika bulan terakhir triwulan kosong, ambil data bulan terakhir yang terisi pada triwulan tersebut.
     *
     * @param array $monthlyData Array data bulanan (key: 1-12)
     * @param int $quarter Nomor triwulan (1-4)
     * @return array Data summary triwulan (male, female, standard, non_standard, total, percentage)
     */
    public static function getQuarterSummary(array $monthlyData, int $quarter): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        // Cari data bulan terakhir yang terisi pada triwulan
        $lastFilledMonth = null;
        for ($m = $endMonth; $m >= $startMonth; $m--) {
            if (isset($monthlyData[$m]) && ($monthlyData[$m]['total'] ?? 0) > 0) {
                $lastFilledMonth = $m;
                break;
            }
        }

        if ($lastFilledMonth) {
            return $monthlyData[$lastFilledMonth];
        }

        // Jika tidak ada data sama sekali di triwulan, kembalikan data kosong
        return [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0,
            'percentage' => 0
        ];
    }

    /**
     * Hitung total summary seluruh puskesmas untuk tiap triwulan.
     * @param array $allMonthlyData Array of monthly_data dari banyak puskesmas
     * @param int $target Total target (untuk hitung persentase)
     * @return array Array [quarter => summary]
     */
    public static function getQuarterTotals(array $allMonthlyData, int $target): array
    {
        $totals = [];
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $summary = [
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'total' => 0,
                'percentage' => 0
            ];
            foreach ($allMonthlyData as $monthlyData) {
                $q = self::getQuarterSummary($monthlyData, $quarter);
                $summary['male'] += $q['male'] ?? 0;
                $summary['female'] += $q['female'] ?? 0;
                $summary['standard'] += $q['standard'] ?? 0;
                $summary['non_standard'] += $q['non_standard'] ?? 0;
                $summary['total'] += $q['total'] ?? 0;
            }
            $summary['percentage'] = $target > 0 ? round(($summary['standard'] / $target) * 100, 2) : 0;
            $totals[$quarter] = $summary;
        }
        return $totals;
    }
}
