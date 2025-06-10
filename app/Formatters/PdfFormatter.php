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

                // Accumulate for grand totals
                $formattedData['grand_total']['target'] += $formattedPuskesmas['target'];
                $formattedData['grand_total']['monthly'][$month - 1]['male'] += $formattedMonth['male'];
                $formattedData['grand_total']['monthly'][$month - 1]['female'] += $formattedMonth['female'];
                $formattedData['grand_total']['monthly'][$month - 1]['standard'] += $formattedMonth['standard'];
                $formattedData['grand_total']['monthly'][$month - 1]['non_standard'] += $formattedMonth['non_standard'];
                $formattedData['grand_total']['monthly'][$month - 1]['total'] += $formattedMonth['total'];
            }

            // Format quarterly data (using last month of each quarter)
            for ($quarter = 0; $quarter < 4; $quarter++) {
                $endMonthIndex = ($quarter + 1) * 3 - 1; // 2, 5, 8, 11
                $formattedPuskesmas['quarterly'][$quarter] = $formattedPuskesmas['monthly'][$endMonthIndex];
                
                // Accumulate quarterly grand totals
                $formattedData['grand_total']['quarterly'][$quarter]['male'] += $formattedPuskesmas['quarterly'][$quarter]['male'];
                $formattedData['grand_total']['quarterly'][$quarter]['female'] += $formattedPuskesmas['quarterly'][$quarter]['female'];
                $formattedData['grand_total']['quarterly'][$quarter]['standard'] += $formattedPuskesmas['quarterly'][$quarter]['standard'];
                $formattedData['grand_total']['quarterly'][$quarter]['non_standard'] += $formattedPuskesmas['quarterly'][$quarter]['non_standard'];
                $formattedData['grand_total']['quarterly'][$quarter]['total'] += $formattedPuskesmas['quarterly'][$quarter]['total'];
            }

            // Calculate total patients and achievement percentage (using December data)
            $decemberData = $formattedPuskesmas['monthly'][11]; // December is index 11
            $formattedPuskesmas['total_patients'] = $decemberData['total'];
            $formattedPuskesmas['achievement_percentage'] = $formattedPuskesmas['target'] > 0
                ? round(($decemberData['standard'] / $formattedPuskesmas['target']) * 100, 2)
                : 0;

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
                'total_target' => 0,
                'total_patients' => 0,
                'total_standard' => 0,
                'total_achievement' => 0,
                'puskesmas_count' => 0
            ];

            foreach ($statisticsData['data'] as $puskesmasData) {
                if (isset($puskesmasData[$type])) {
                    $diseaseData = $puskesmasData[$type];
                    $summary[$type]['total_target'] += $diseaseData['target'] ?? 0;
                    $summary[$type]['total_patients'] += $diseaseData['total_patients'] ?? 0;
                    $summary[$type]['total_standard'] += $diseaseData['standard_patients'] ?? 0;
                    $summary[$type]['puskesmas_count']++;
                }
            }

            $summary[$type]['total_achievement'] = $summary[$type]['total_target'] > 0
                ? round(($summary[$type]['total_standard'] / $summary[$type]['total_target']) * 100, 2)
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

        // Calculate total achievement using December data
        $decemberData = $grandTotal['monthly'][11];
        $grandTotal['total_patients'] = $decemberData['total'];
        $grandTotal['achievement_percentage'] = $decemberData['percentage'];
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