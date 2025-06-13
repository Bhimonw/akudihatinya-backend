<?php

namespace App\Formatters;

use App\Helpers\QuarterHelper;
use App\Services\StatisticsAdminService;
use App\Models\YearlyTarget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PdfTemplateFormatter
{
    protected $statisticsAdminService;

    public function __construct(StatisticsAdminService $statisticsAdminService)
    {
        $this->statisticsAdminService = $statisticsAdminService;
    }
    /**
     * Format data for quarterly recap PDF using resources/pdf template
     */
    public function formatQuarterlyRecapData($puskesmasAll, $year, $diseaseType)
    {
        $quarters = [
            1 => ['months' => ['Januari', 'Februari', 'Maret'], 'quarter' => 'I'],
            2 => ['months' => ['April', 'Mei', 'Juni'], 'quarter' => 'II'],
            3 => ['months' => ['Juli', 'Agustus', 'September'], 'quarter' => 'III'],
            4 => ['months' => ['Oktober', 'November', 'Desember'], 'quarter' => 'IV']
        ];

        $quartersData = [];
        $diseaseTypes = $diseaseType === 'all' ? ['ht', 'dm'] : [$diseaseType];

        foreach ($diseaseTypes as $type) {
            foreach ($quarters as $quarterNum => $quarterInfo) {
                $quarterData = [
                    'quarter' => $quarterInfo['quarter'],
                    'months' => $quarterInfo['months'],
                    'year' => $year,
                    'disease_type' => $type,
                    'disease_label' => $type === 'ht' ? 'HIPERTENSI' : 'DIABETES MELLITUS',
                    'puskesmas_data' => [],
                    'totals' => [
                        'target' => 0,
                        'monthly_totals' => array_fill(0, 3, ['total' => 0, 'standard' => 0, 'percentage' => 0]),
                        'quarter_total' => ['total' => 0, 'standard' => 0, 'percentage' => 0]
                    ]
                ];

                $grandTotals = [
                    'target' => 0,
                    'monthly_totals' => array_fill(0, 3, ['total' => 0, 'standard' => 0, 'male' => 0, 'female' => 0]),
                    'quarter_total' => ['total' => 0, 'standard' => 0, 'male' => 0, 'female' => 0]
                ];

                foreach ($puskesmasAll as $index => $puskesmas) {
                    $puskesmasData = [
                        'no' => $index + 1,
                        'name' => $puskesmas->name,
                        'target' => $this->getPuskesmasTarget($puskesmas->id, $year, $type),
                        'monthly' => [],
                        'quarterly' => [],
                        'total_patients' => 0,
                        'achievement_percentage' => 0
                    ];

                    $quarterTotals = ['total' => 0, 'standard' => 0, 'male' => 0, 'female' => 0];

                    // Get data for each month in the quarter
                    for ($monthIndex = 0; $monthIndex < 3; $monthIndex++) {
                        $month = ($quarterNum - 1) * 3 + $monthIndex + 1;
                        $monthlyStats = $this->getMonthlyStatistics($puskesmas->id, $year, $month, $type);

                        $total = $monthlyStats['total'] ?? 0;
                        $standard = $monthlyStats['standard'] ?? 0;
                        $male = $monthlyStats['male'] ?? 0;
                        $female = $monthlyStats['female'] ?? 0;
                        $nonStandard = $monthlyStats['non_standard'] ?? ($total - $standard);
                        $percentage = $total > 0 ? round(($standard / $total) * 100, 1) : 0;

                        $monthData = [
                            'total' => $total,
                            'standard' => $standard,
                            'male' => $male,
                            'female' => $female,
                            'non_standard' => $nonStandard,
                            'percentage' => $percentage
                        ];

                        $puskesmasData['monthly'][] = $monthData;

                        // Add to quarter totals
                        $quarterTotals['total'] += $total;
                        $quarterTotals['standard'] += $standard;
                        $quarterTotals['male'] += $male;
                        $quarterTotals['female'] += $female;

                        // Add to grand totals
                        $grandTotals['monthly_totals'][$monthIndex]['total'] += $total;
                        $grandTotals['monthly_totals'][$monthIndex]['standard'] += $standard;
                        $grandTotals['monthly_totals'][$monthIndex]['male'] += $male;
                        $grandTotals['monthly_totals'][$monthIndex]['female'] += $female;
                        $grandTotals['quarter_total']['total'] += $total;
                        $grandTotals['quarter_total']['standard'] += $standard;
                        $grandTotals['quarter_total']['male'] += $male;
                        $grandTotals['quarter_total']['female'] += $female;
                    }

                    // Set quarterly data
                    $puskesmasData['quarterly'][] = [
                        'total' => $quarterTotals['total'],
                        'male' => $quarterTotals['male'],
                        'female' => $quarterTotals['female']
                    ];

                    $puskesmasData['total_patients'] = $quarterTotals['total'];
                    $puskesmasData['achievement_percentage'] = $quarterTotals['total'] > 0
                        ? round(($quarterTotals['standard'] / $quarterTotals['total']) * 100, 1)
                        : 0;

                    $quarterData['puskesmas_data'][] = $puskesmasData;
                    $quarterData['totals']['target'] += $puskesmasData['target'];
                }

                // Calculate grand total percentages and format for template
                $grandTotalMonthly = [];
                for ($monthIndex = 0; $monthIndex < 3; $monthIndex++) {
                    $total = $grandTotals['monthly_totals'][$monthIndex]['total'];
                    $standard = $grandTotals['monthly_totals'][$monthIndex]['standard'];
                    $male = $grandTotals['monthly_totals'][$monthIndex]['male'];
                    $female = $grandTotals['monthly_totals'][$monthIndex]['female'];
                    $nonStandard = $total - $standard;
                    $percentage = $total > 0 ? round(($standard / $total) * 100, 1) : 0;

                    $grandTotalMonthly[] = [
                        'total' => $total,
                        'standard' => $standard,
                        'male' => $male,
                        'female' => $female,
                        'non_standard' => $nonStandard,
                        'percentage' => $percentage
                    ];

                    $quarterData['totals']['monthly_totals'][$monthIndex] = [
                        'total' => $total,
                        'standard' => $standard,
                        'percentage' => $percentage
                    ];
                }

                $quarterTotal = $grandTotals['quarter_total']['total'];
                $quarterStandard = $grandTotals['quarter_total']['standard'];
                $quarterMale = $grandTotals['quarter_total']['male'];
                $quarterFemale = $grandTotals['quarter_total']['female'];
                $quarterPercentage = $quarterTotal > 0
                    ? round(($quarterStandard / $quarterTotal) * 100, 1)
                    : 0;

                $quarterData['totals']['quarter_total'] = [
                    'total' => $quarterTotal,
                    'standard' => $quarterStandard,
                    'percentage' => $quarterPercentage
                ];

                $quarterData['grand_total'] = [
                    'target' => $quarterData['totals']['target'],
                    'monthly' => $grandTotalMonthly,
                    'quarterly' => [[
                        'total' => $quarterTotal,
                        'male' => $quarterMale,
                        'female' => $quarterFemale
                    ]],
                    'total_patients' => $quarterTotal,
                    'achievement_percentage' => $quarterPercentage
                ];

                $quartersData[] = $quarterData;
            }
        }

        // Determine disease label for the title
        $diseaseLabel = $diseaseType === 'all' ? 'SEMUA PENYAKIT' : ($diseaseType === 'ht' ? 'HIPERTENSI' : 'DIABETES MELLITUS');

        return [
            'quarters_data' => $quartersData,
            'year' => $year,
            'disease_type' => $diseaseType,
            'disease_label' => $diseaseLabel,
            'generated_at' => now()->format('d F Y H:i:s')
        ];
    }

    /**
     * Format data for statistics PDF using resources/pdf template
     */
    public function formatStatisticsData($puskesmasAll, $year, $month, $diseaseType, $reportType = 'statistics')
    {
        $statisticsData = [];
        $totals = [
            'target' => 0,
            'total_patients' => 0,
            'standard_patients' => 0,
            'percentage' => 0
        ];

        foreach ($puskesmasAll as $index => $puskesmas) {
            $target = $this->getPuskesmasTarget($puskesmas->id, $year, $diseaseType);

            if ($month) {
                // Monthly report
                $monthlyStats = $this->getMonthlyStatistics($puskesmas->id, $year, $month, $diseaseType);
                $totalPatients = $monthlyStats['total'] ?? 0;
                $standardPatients = $monthlyStats['standard'] ?? 0;
            } else {
                // Yearly report
                $yearlyStats = $this->getYearlyStatistics($puskesmas->id, $year, $diseaseType);
                $totalPatients = $yearlyStats['total'] ?? 0;
                $standardPatients = $yearlyStats['standard'] ?? 0;
            }

            $percentage = $totalPatients > 0 ? round(($standardPatients / $totalPatients) * 100, 1) : 0;

            $statisticsData[] = [
                'no' => $index + 1,
                'puskesmas_name' => $puskesmas->name,
                'target' => $target,
                'total_patients' => $totalPatients,
                'standard_patients' => $standardPatients,
                'percentage' => $percentage
            ];

            // Add to totals
            $totals['target'] += $target;
            $totals['total_patients'] += $totalPatients;
            $totals['standard_patients'] += $standardPatients;
        }

        // Calculate overall percentage
        $totals['percentage'] = $totals['total_patients'] > 0
            ? round(($totals['standard_patients'] / $totals['total_patients']) * 100, 1)
            : 0;

        $diseaseLabel = match ($diseaseType) {
            'ht' => 'HIPERTENSI',
            'dm' => 'DIABETES MELLITUS',
            'all' => 'HIPERTENSI & DIABETES MELLITUS',
            default => 'STATISTIK KESEHATAN'
        };

        $periodLabel = $month ? date('F Y', mktime(0, 0, 0, $month, 1, $year)) : "Tahun {$year}";

        return [
            'title' => "LAPORAN STATISTIK {$diseaseLabel}",
            'period' => $periodLabel,
            'year' => $year,
            'month' => $month,
            'disease_type' => $diseaseType,
            'disease_label' => $diseaseLabel,
            'statistics_data' => $statisticsData,
            'totals' => $totals,
            'generated_at' => now()->format('d F Y H:i:s')
        ];
    }

    /**
     * Get puskesmas target for specific year and disease type
     */
    private function getPuskesmasTarget($puskesmasId, $year, $diseaseType)
    {
        try {
            $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('disease_type', $diseaseType)
                ->first();

            return $target ? $target->target : 1000; // Default target if not found
        } catch (\Exception $e) {
            Log::warning('Failed to get puskesmas target', [
                'puskesmas_id' => $puskesmasId,
                'year' => $year,
                'disease_type' => $diseaseType,
                'error' => $e->getMessage()
            ]);
            return 1000; // Default fallback
        }
    }

    /**
     * Get monthly statistics for puskesmas directly from cache
     */
    private function getMonthlyStatistics($puskesmasId, $year, $month, $diseaseType)
    {
        try {
            $stats = DB::table('monthly_statistics_cache')
                ->where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('month', $month)
                ->where('disease_type', $diseaseType)
                ->first();

            if (!$stats) {
                return [
                    'total' => 0,
                    'standard' => 0,
                    'male' => 0,
                    'female' => 0,
                    'non_standard' => 0
                ];
            }

            return [
                'total' => $stats->total_count ?? 0,
                'standard' => $stats->standard_count ?? 0,
                'male' => $stats->male_count ?? 0,
                'female' => $stats->female_count ?? 0,
                'non_standard' => $stats->non_standard_count ?? 0
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get monthly statistics', [
                'puskesmas_id' => $puskesmasId,
                'year' => $year,
                'month' => $month,
                'disease_type' => $diseaseType,
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'standard' => 0,
                'male' => 0,
                'female' => 0,
                'non_standard' => 0
            ];
        }
    }

    /**
     * Get yearly statistics for puskesmas directly from cache
     */
    private function getYearlyStatistics($puskesmasId, $year, $diseaseType)
    {
        try {
            // Get the latest month's data (preferably month 12, or the latest available)
            $latestStats = DB::table('monthly_statistics_cache')
                ->where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('disease_type', $diseaseType)
                ->where('total_count', '>', 0)
                ->orderBy('month', 'desc')
                ->first();

            if (!$latestStats) {
                return [
                    'total' => 0,
                    'standard' => 0,
                    'male' => 0,
                    'female' => 0,
                    'non_standard' => 0
                ];
            }

            return [
                'total' => $latestStats->total_count ?? 0,
                'standard' => $latestStats->standard_count ?? 0,
                'male' => $latestStats->male_count ?? 0,
                'female' => $latestStats->female_count ?? 0,
                'non_standard' => $latestStats->non_standard_count ?? 0
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get yearly statistics', [
                'puskesmas_id' => $puskesmasId,
                'year' => $year,
                'disease_type' => $diseaseType,
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'standard' => 0,
                'male' => 0,
                'female' => 0,
                'non_standard' => 0
            ];
        }
    }
}
