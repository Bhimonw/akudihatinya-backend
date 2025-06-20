<?php

namespace App\Formatters;

class PdfFormatter
{
    /**
     * Format statistics data for PDF generation
     */
    public function formatForPdf($statisticsData, $diseaseType, $year, $reportType = 'all')
    {
        $formattedData = [
            'title' => $this->generateTitle($diseaseType, $year, $reportType),
            'disease_type' => $diseaseType,
            'disease_label' => $this->getDiseaseLabel($diseaseType),
            'year' => $year,
            'report_type' => $reportType,
            'generated_at' => date('d/m/Y H:i:s'),
            'puskesmas_data' => [],
            'grand_total' => $this->initializeGrandTotal(),
            'months' => $this->getMonthNames(),
            'quarters' => $this->getQuarterNames()
        ];

        if (empty($statisticsData['data'])) {
            return $formattedData;
        }

        // Process each puskesmas data
        foreach ($statisticsData['data'] as $puskesmasData) {
            $diseaseData = $puskesmasData[$diseaseType] ?? [];
            
            $formattedPuskesmas = [
                'name' => $puskesmasData['puskesmas_name'],
                'target' => $diseaseData['target'] ?? 0,
                'monthly' => [],
                'quarterly' => [],
                'total_patients' => 0,
                'achievement_percentage' => 0
            ];

            // Format monthly data
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
                    'male' => $monthData['male'],
                    'female' => $monthData['female'],
                    'standard' => $monthData['standard'],
                    'non_standard' => $monthData['non_standard'],
                    'total' => $monthData['total'],
                    'percentage' => $monthData['percentage']
                ];

                $formattedPuskesmas['monthly'][$month - 1] = $formattedMonth;

                // Accumulate for grand totals (per puskesmas, not monthly)
                $formattedData['grand_total']['target'] += $formattedPuskesmas['target'];
                // Use latest month data instead of accumulating monthly data
                $formattedData['grand_total']['monthly'][$month - 1]['male'] = $formattedMonth['male'];
                $formattedData['grand_total']['monthly'][$month - 1]['female'] = $formattedMonth['female'];
                $formattedData['grand_total']['monthly'][$month - 1]['standard'] = $formattedMonth['standard'];
                $formattedData['grand_total']['monthly'][$month - 1]['non_standard'] = $formattedMonth['non_standard'];
                $formattedData['grand_total']['monthly'][$month - 1]['total'] = $formattedMonth['total'];
            }

            // Format quarterly data (using last month of each quarter)
            for ($quarter = 0; $quarter < 4; $quarter++) {
                $endMonthIndex = ($quarter + 1) * 3 - 1; // 2, 5, 8, 11
                $formattedPuskesmas['quarterly'][$quarter] = $formattedPuskesmas['monthly'][$endMonthIndex];
                
                // Use latest data instead of accumulating quarterly grand totals
                $formattedData['grand_total']['quarterly'][$quarter]['male'] = $formattedPuskesmas['quarterly'][$quarter]['male'];
                $formattedData['grand_total']['quarterly'][$quarter]['female'] = $formattedPuskesmas['quarterly'][$quarter]['female'];
                $formattedData['grand_total']['quarterly'][$quarter]['standard'] = $formattedPuskesmas['quarterly'][$quarter]['standard'];
                $formattedData['grand_total']['quarterly'][$quarter]['non_standard'] = $formattedPuskesmas['quarterly'][$quarter]['non_standard'];
                $formattedData['grand_total']['quarterly'][$quarter]['total'] = $formattedPuskesmas['quarterly'][$quarter]['total'];
            }

            // Calculate total patients and achievement percentage (using last available month data)
            $lastMonthData = null;
            for ($month = 11; $month >= 0; $month--) { // December is index 11, January is 0
                if ($formattedPuskesmas['monthly'][$month]['total'] > 0) {
                    $lastMonthData = $formattedPuskesmas['monthly'][$month];
                    break;
                }
            }
            
            if ($lastMonthData) {
                $formattedPuskesmas['total_patients'] = $lastMonthData['total'];
                $formattedPuskesmas['achievement_percentage'] = $formattedPuskesmas['target'] > 0
                    ? round(($lastMonthData['standard'] / $formattedPuskesmas['target']) * 100, 2)
                    : 0;
            } else {
                $formattedPuskesmas['total_patients'] = 0;
                $formattedPuskesmas['achievement_percentage'] = 0;
            }

            $formattedData['puskesmas_data'][] = $formattedPuskesmas;
        }

        // Calculate grand total percentages
        $this->calculateGrandTotalPercentages($formattedData['grand_total']);

        return $formattedData;
    }

    /**
     * Format summary data for PDF
     */
    public function formatSummaryForPdf($statisticsData, $diseaseType, $year)
    {
        $summary = [
            'title' => 'Ringkasan Statistik Kesehatan',
            'year' => $year,
            'generated_at' => date('d/m/Y H:i:s'),
            'disease_types' => []
        ];

        if ($diseaseType === 'all') {
            $summary['disease_types'] = ['ht', 'dm'];
        } else {
            $summary['disease_types'] = [$diseaseType];
        }

        foreach ($summary['disease_types'] as $type) {
            $summary[$type] = [
                'label' => $this->getDiseaseLabel($type),
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'achievement_percentage' => 0,
                'puskesmas_count' => 0
            ];

            // Use latest month data instead of accumulating
            $latestData = null;
            foreach ($statisticsData['data'] as $puskesmasData) {
                if (isset($puskesmasData[$type])) {
                    $latestData = $puskesmasData[$type];
                    $summary[$type]['puskesmas_count']++;
                }
            }

            if ($latestData) {
                $summary[$type]['target'] = $latestData['target'] ?? 0;
                $summary[$type]['total_patients'] = $latestData['total_patients'] ?? 0;
                $summary[$type]['standard_patients'] = $latestData['standard_patients'] ?? 0;
            }

            $summary[$type]['achievement_percentage'] = $summary[$type]['target'] > 0
                ? round(($summary[$type]['standard_patients'] / $summary[$type]['target']) * 100, 2)
                : 0;
        }

        return $summary;
    }

    private function initializeGrandTotal()
    {
        return [
            'target' => 0,
            'monthly' => array_fill(0, 12, [
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'total' => 0,
                'percentage' => 0
            ]),
            'quarterly' => array_fill(0, 4, [
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'total' => 0,
                'percentage' => 0
            ]),
            'total_patients' => 0,
            'achievement_percentage' => 0
        ];
    }

    private function calculateGrandTotalPercentages(&$grandTotal)
    {
        // Calculate monthly percentages
        foreach ($grandTotal['monthly'] as &$monthData) {
            $monthData['percentage'] = $grandTotal['target'] > 0
                ? round(($monthData['standard'] / $grandTotal['target']) * 100, 2)
                : 0;
        }

        // Calculate quarterly percentages
        foreach ($grandTotal['quarterly'] as &$quarterData) {
            $quarterData['percentage'] = $grandTotal['target'] > 0
                ? round(($quarterData['standard'] / $grandTotal['target']) * 100, 2)
                : 0;
        }

        // Calculate total achievement using last available month data
        $lastMonthData = null;
        for ($month = 11; $month >= 0; $month--) { // December is index 11, January is 0
            if ($grandTotal['monthly'][$month]['total'] > 0) {
                $lastMonthData = $grandTotal['monthly'][$month];
                break;
            }
        }
        
        if ($lastMonthData) {
            $grandTotal['total_patients'] = $lastMonthData['total'];
            $grandTotal['achievement_percentage'] = $lastMonthData['percentage'];
        } else {
            $grandTotal['total_patients'] = 0;
            $grandTotal['achievement_percentage'] = 0;
        }
    }

    private function generateTitle($diseaseType, $year, $reportType)
    {
        $diseaseLabel = $this->getDiseaseLabel($diseaseType);
        return "Laporan Statistik {$diseaseLabel} Tahun {$year}";
    }

    private function getDiseaseLabel($diseaseType)
    {
        $labels = [
            'dm' => 'Diabetes Melitus (DM)',
            'ht' => 'Hipertensi (HT)',
            'all' => 'Semua Penyakit'
        ];
        
        return $labels[$diseaseType] ?? $diseaseType;
    }

    private function getMonthNames()
    {
        return [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
    }

    private function getQuarterNames()
    {
        return ['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'];
    }
}