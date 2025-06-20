<?php

namespace App\Services;

use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service untuk mengelola data statistik dengan konsistensi tinggi
 * Memisahkan logika pengambilan data dari controller
 */
class StatisticsDataService
{
    private $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Mendapatkan data puskesmas berdasarkan role user
     */
    public function getPuskesmasQuery($request = null)
    {
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!Auth::user()->isAdmin()) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . Auth::user()->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);
                    // Update the user with the correct puskesmas_id for future requests
                    Auth::user()->update(['puskesmas_id' => $puskesmasWithSameName->id]);
                    Log::info('Updated user ' . Auth::user()->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    // Return empty query
                    $puskesmasQuery->whereRaw('1 = 0'); // Force empty result
                }
            }
        } else {
            // Admin dapat filter berdasarkan nama puskesmas
            if ($request && $request->has('name')) {
                $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
            }
        }

        return $puskesmasQuery;
    }

    /**
     * Mendapatkan data statistik yang konsisten untuk semua endpoint
     */
    public function getConsistentStatisticsData($puskesmasAll, $year, $month = null, $diseaseType = 'all')
    {
        // OPTIMASI: Ambil semua cache statistik bulanan sekaligus
        $puskesmasIds = $puskesmasAll->pluck('id')->toArray();
        $monthlyStats = MonthlyStatisticsCache::where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->when($month, function ($query) use ($month) {
                return $query->where('month', $month);
            })
            ->get();

        $htStats = $monthlyStats->where('disease_type', 'ht')->groupBy('puskesmas_id');
        $dmStats = $monthlyStats->where('disease_type', 'dm')->groupBy('puskesmas_id');
        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Tambahkan data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $data['ht'] = $this->buildHtStatistics($puskesmas->id, $year, $htStats, $month);
            }

            // Tambahkan data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $data['dm'] = $this->buildDmStatistics($puskesmas->id, $year, $dmStats, $month);
            }

            $statistics[] = $data;
        }

        return $this->addRankingToStatistics($statistics, $diseaseType);
    }

    /**
     * Membangun data statistik HT
     */
    private function buildHtStatistics($puskesmasId, $year, $htStats, $month = null)
    {
        $htArr = [
            'target' => 0,
            'total_patients' => 0,
            'achievement_percentage' => 0,
            'standard_patients' => 0,
            'non_standard_patients' => 0,
            'male_patients' => 0,
            'female_patients' => 0,
            'monthly_data' => [],
        ];

        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->first();

        $targetCount = $target ? $target->target_count : 0;
        $htArr['target'] = $targetCount;

        if (isset($htStats[$puskesmasId])) {
            $totalPatients = $htStats[$puskesmasId]->sum('total_count');
            $standardPatients = $htStats[$puskesmasId]->sum('standard_count');
            $nonStandardPatients = $htStats[$puskesmasId]->sum('non_standard_count');
            $malePatients = $htStats[$puskesmasId]->sum('male_count');
            $femalePatients = $htStats[$puskesmasId]->sum('female_count');

            $htArr['total_patients'] = $totalPatients;
            $htArr['standard_patients'] = $standardPatients;
            $htArr['non_standard_patients'] = $nonStandardPatients;
            $htArr['male_patients'] = $malePatients;
            $htArr['female_patients'] = $femalePatients;
            $htArr['achievement_percentage'] = $targetCount > 0 ? round(($standardPatients / $targetCount) * 100, 2) : 0;

            $monthlyData = [];
            foreach ($htStats[$puskesmasId] as $stat) {
                $monthlyData[$stat->month] = [
                    'male' => $stat->male_count,
                    'female' => $stat->female_count,
                    'total' => $stat->total_count,
                    'standard' => $stat->standard_count,
                    'non_standard' => $stat->non_standard_count,
                    'percentage' => $targetCount > 0 ? round(($stat->standard_count / $targetCount) * 100, 2) : 0,
                ];
            }
            $htArr['monthly_data'] = $monthlyData;
        }

        return $htArr;
    }

    /**
     * Membangun data statistik DM
     */
    private function buildDmStatistics($puskesmasId, $year, $dmStats, $month = null)
    {
        $dmArr = [
            'target' => 0,
            'total_patients' => 0,
            'achievement_percentage' => 0,
            'standard_patients' => 0,
            'non_standard_patients' => 0,
            'male_patients' => 0,
            'female_patients' => 0,
            'monthly_data' => [],
        ];

        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->first();

        $targetCount = $target ? $target->target_count : 0;
        $dmArr['target'] = $targetCount;

        if (isset($dmStats[$puskesmasId])) {
            $totalPatients = $dmStats[$puskesmasId]->sum('total_count');
            $standardPatients = $dmStats[$puskesmasId]->sum('standard_count');
            $nonStandardPatients = $dmStats[$puskesmasId]->sum('non_standard_count');
            $malePatients = $dmStats[$puskesmasId]->sum('male_count');
            $femalePatients = $dmStats[$puskesmasId]->sum('female_count');

            $dmArr['total_patients'] = $totalPatients;
            $dmArr['standard_patients'] = $standardPatients;
            $dmArr['non_standard_patients'] = $nonStandardPatients;
            $dmArr['male_patients'] = $malePatients;
            $dmArr['female_patients'] = $femalePatients;
            $dmArr['achievement_percentage'] = $targetCount > 0 ? round(($standardPatients / $targetCount) * 100, 2) : 0;

            $monthlyData = [];
            foreach ($dmStats[$puskesmasId] as $stat) {
                $monthlyData[$stat->month] = [
                    'male' => $stat->male_count,
                    'female' => $stat->female_count,
                    'total' => $stat->total_count,
                    'standard' => $stat->standard_count,
                    'non_standard' => $stat->non_standard_count,
                    'percentage' => $targetCount > 0 ? round(($stat->standard_count / $targetCount) * 100, 2) : 0,
                ];
            }
            $dmArr['monthly_data'] = $monthlyData;
        }

        return $dmArr;
    }

    /**
     * Menambahkan ranking ke data statistik
     */
    private function addRankingToStatistics($statistics, $diseaseType)
    {
        // Urutkan ranking DM/HT jika disease_type=dm/ht
        if ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return ($b['dm']['achievement_percentage'] ?? 0) <=> ($a['dm']['achievement_percentage'] ?? 0);
            });
        } elseif ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return ($b['ht']['achievement_percentage'] ?? 0) <=> ($a['ht']['achievement_percentage'] ?? 0);
            });
        }

        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        return $statistics;
    }

    /**
     * Format data untuk admin (convert to string)
     */
    public function formatDataForAdmin($statistics, $diseaseType)
    {
        foreach ($statistics as $index => $stat) {
            if (isset($stat['ht']) && ($diseaseType === 'all' || $diseaseType === 'ht')) {
                $statistics[$index]['ht'] = $this->formatHtDataToString($stat['ht']);
            }

            if (isset($stat['dm']) && ($diseaseType === 'all' || $diseaseType === 'dm')) {
                $statistics[$index]['dm'] = $this->formatDmDataToString($stat['dm']);
            }
        }

        return $statistics;
    }

    /**
     * Format data HT ke string
     */
    private function formatHtDataToString($htData)
    {
        return [
            'target' => (string)($htData['target'] ?? 0),
            'total_patients' => (string)($htData['total_patients'] ?? 0),
            'standard_patients' => (string)($htData['standard_patients'] ?? 0),
            'non_standard_patients' => (string)($htData['non_standard_patients'] ?? 0),
            'male_patients' => (string)($htData['male_patients'] ?? 0),
            'female_patients' => (string)($htData['female_patients'] ?? 0),
            'achievement_percentage' => $htData['achievement_percentage'] ?? 0,
            'monthly_data' => array_map(function ($m) {
                return [
                    'male' => (string)($m['male'] ?? 0),
                    'female' => (string)($m['female'] ?? 0),
                    'total' => (string)($m['total'] ?? 0),
                    'standard' => (string)($m['standard'] ?? 0),
                    'non_standard' => (string)($m['non_standard'] ?? 0),
                    'percentage' => $m['percentage'] ?? 0,
                ];
            }, $htData['monthly_data'] ?? []),
        ];
    }

    /**
     * Format data DM ke string
     */
    private function formatDmDataToString($dmData)
    {
        return [
            'target' => (string)($dmData['target'] ?? 0),
            'total_patients' => (string)($dmData['total_patients'] ?? 0),
            'standard_patients' => (string)($dmData['standard_patients'] ?? 0),
            'non_standard_patients' => (string)($dmData['non_standard_patients'] ?? 0),
            'male_patients' => (string)($dmData['male_patients'] ?? 0),
            'female_patients' => (string)($dmData['female_patients'] ?? 0),
            'achievement_percentage' => $dmData['achievement_percentage'] ?? 0,
            'monthly_data' => array_map(function ($m) {
                return [
                    'male' => (string)($m['male'] ?? 0),
                    'female' => (string)($m['female'] ?? 0),
                    'total' => (string)($m['total'] ?? 0),
                    'standard' => (string)($m['standard'] ?? 0),
                    'non_standard' => (string)($m['non_standard'] ?? 0),
                    'percentage' => $m['percentage'] ?? 0,
                ];
            }, $dmData['monthly_data'] ?? []),
        ];
    }

    /**
     * Mendapatkan nama bulan dalam bahasa Indonesia
     */
    public function getMonthName($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$month] ?? '';
    }

    /**
     * Validasi parameter request
     */
    public function validateParameters($request)
    {
        $errors = [];

        // Validasi disease_type
        $diseaseType = $request->disease_type ?? 'all';
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            $errors['disease_type'] = 'Parameter disease_type tidak valid. Gunakan all, ht, atau dm.';
        }

        // Validasi month jika diisi
        if ($request->has('month') && $request->month !== null) {
            $month = intval($request->month);
            if ($month < 1 || $month > 12) {
                $errors['month'] = 'Parameter month tidak valid. Gunakan angka 1-12.';
            }
        }

        // Validasi table_type jika ada
        if ($request->has('table_type')) {
            $tableType = $request->table_type;
            if (!in_array($tableType, ['all', 'quarterly', 'monthly', 'puskesmas'])) {
                $errors['table_type'] = 'Parameter table_type tidak valid. Gunakan all, quarterly, monthly, atau puskesmas.';
            }
        }

        // Validasi format jika ada
        if ($request->has('format')) {
            $format = $request->format;
            if (!in_array($format, ['pdf', 'excel'])) {
                $errors['format'] = 'Format tidak valid. Gunakan pdf atau excel.';
            }
        }

        return $errors;
    }

    /**
     * Paginate statistics data
     *
     * @param array $statistics
     * @param int $perPage
     * @param int $currentPage
     * @return array
     */
    public function paginateStatistics($statistics, $perPage = 15, $currentPage = 1)
    {
        $total = count($statistics);
        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($statistics, $offset, $perPage);
        
        $lastPage = ceil($total / $perPage);
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);
        
        return [
            'success' => true,
            'message' => 'Data statistik berhasil diambil',
            'data' => $items,
            'meta' => [
                'current_page' => $currentPage,
                'from' => $from,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $to,
                'total' => $total,
            ],
        ];
    }
}