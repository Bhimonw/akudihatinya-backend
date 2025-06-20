<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Services\StatisticsService;
use Carbon\Carbon;

class StatisticsAdminService
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function getAdminStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month;
        $diseaseType = $request->type ?? 'all';

        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return [
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
                'error' => true,
                'status' => 400
            ];
        }

        $perPage = $request->per_page ?? 15;
        $page = $request->page ?? 1;

        $puskesmasQuery = \App\Models\Puskesmas::query();
        if ($request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }
        $puskesmas = $puskesmasQuery->paginate($perPage);

        if ($puskesmas->isEmpty()) {
            return [
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'to' => 0,
                    'total' => 0,
                ],
            ];
        }

        $allPuskesmasIds = \App\Models\Puskesmas::pluck('id')->toArray();
        $statistics = [];
        $puskesmasIds = $puskesmas->pluck('id')->toArray();

        $htTargets = \App\Models\YearlyTarget::where('year', $year)
            ->where('disease_type', 'ht')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get()
            ->keyBy('puskesmas_id');

        $dmTargets = \App\Models\YearlyTarget::where('year', $year)
            ->where('disease_type', 'dm')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get()
            ->keyBy('puskesmas_id');

        $monthlyStats = \App\Models\MonthlyStatisticsCache::where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->when($month, function ($query) use ($month) {
                return $query->where('month', $month);
            })
            ->get();

        $htStats = $monthlyStats->where('disease_type', 'ht')->groupBy('puskesmas_id');
        $dmStats = $monthlyStats->where('disease_type', 'dm')->groupBy('puskesmas_id');

        foreach ($puskesmas as $p) {
            $data = [
                'puskesmas_id' => $p->id,
                'puskesmas_name' => $p->name,
            ];
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                if (isset($htStats[$p->id])) {
                    $data['ht'] = $this->statisticsService->processHtCachedStats($htStats[$p->id], $htTargets->get($p->id));
                } else {
                    $htTarget = $htTargets->get($p->id);
                    $htTargetCount = $htTarget ? $htTarget->target_count : 0;
                    $htData = $this->statisticsService->getHtStatisticsFromCache($p->id, $year, $month);
                    $data['ht'] = [
                        'target' => $htTargetCount,
                        'total_patients' => $htData['total_patients'],
                        'achievement_percentage' => $htTargetCount > 0
                            ? round(($htData['total_standard'] / $htTargetCount) * 100, 2)
                            : 0,
                        'standard_patients' => $htData['total_standard'],
                        'non_standard_patients' => $htData['total_patients'] - $htData['total_standard'],
                        'male_patients' => $htData['male_patients'] ?? 0,
                        'female_patients' => $htData['female_patients'] ?? 0,
                        'monthly_data' => $htData['monthly_data'],
                    ];
                }
            }
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                if (isset($dmStats[$p->id])) {
                    $data['dm'] = $this->statisticsService->processDmCachedStats($dmStats[$p->id], $dmTargets->get($p->id));
                } else {
                    $dmTarget = $dmTargets->get($p->id);
                    $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;
                    $dmData = $this->statisticsService->getDmStatisticsFromCache($p->id, $year, $month);
                    $data['dm'] = [
                        'target' => $dmTargetCount,
                        'total_patients' => $dmData['total_patients'],
                        'achievement_percentage' => $dmTargetCount > 0
                            ? round(($dmData['total_standard'] / $dmTargetCount) * 100, 2)
                            : 0,
                        'standard_patients' => $dmData['total_standard'],
                        'non_standard_patients' => $dmData['total_patients'] - $dmData['total_standard'],
                        'male_patients' => $dmData['male_patients'] ?? 0,
                        'female_patients' => $dmData['female_patients'] ?? 0,
                        'monthly_data' => $dmData['monthly_data'],
                    ];
                }
            }
            $statistics[] = $data;
        }

        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            usort($statistics, function ($a, $b) {
                $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                return $bTotal <=> $aTotal;
            });
        }

        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        $summary = $this->statisticsService->calculateSummaryStatistics($allPuskesmasIds, $year, $month, $diseaseType);

        $response = [
            'year' => $year,
            'type' => $diseaseType,
            'month' => $month,
            'month_name' => $month ? $this->getMonthName($month) : null,
            'total_puskesmas' => \App\Models\Puskesmas::count(),
            'summary' => $summary,
            'data' => $statistics,
            'meta' => [
                'current_page' => $puskesmas->currentPage(),
                'from' => $puskesmas->firstItem(),
                'last_page' => $puskesmas->lastPage(),
                'per_page' => $puskesmas->perPage(),
                'to' => $puskesmas->lastItem(),
                'total' => $puskesmas->total(),
            ],
        ];

        $response['chart_data'] = $this->statisticsService->prepareChartData(
            $diseaseType,
            $summary['ht']['monthly_data'] ?? [],
            $summary['dm']['monthly_data'] ?? []
        );

        $response['rankings'] = $this->statisticsService->getTopAndBottomPuskesmas($year, $diseaseType);

        return $response;
    }

    protected function getMonthName($month)
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        return $months[$month] ?? '';
    }
}
