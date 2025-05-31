<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Services\StatisticsCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatisticsController extends Controller
{
    protected $statisticsService;
    protected $cacheVersion = 'v1';
    protected $cacheDuration = 1800; // 30 menit

    public function __construct(StatisticsCalculationService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get dashboard statistics
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all';
        $user = Auth::user();

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Siapkan query untuk mengambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!$user->is_admin) {
            $puskesmasQuery->where('id', $user->puskesmas_id);
        }

        $puskesmasAll = $puskesmasQuery->get();

        // Siapkan data untuk dikirim ke frontend
        $data = [];

        foreach ($puskesmasAll as $puskesmas) {
            $puskesmasData = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Generate cache key dengan versioning
            $cacheKey = "dashboard_stats:{$this->cacheVersion}:{$puskesmas->id}:{$year}";

            // Get cached data or calculate new
            $cachedData = Cache::remember($cacheKey, $this->cacheDuration, function () use ($puskesmas, $year) {
                return $this->calculatePuskesmasStatistics($puskesmas, $year);
            });

            // Add HT data if needed
            if ($type === 'all' || $type === 'ht') {
                $puskesmasData['ht'] = $cachedData['ht'];
            }

            // Add DM data if needed
            if ($type === 'all' || $type === 'dm') {
                $puskesmasData['dm'] = $cachedData['dm'];
            }

            $data[] = $puskesmasData;
        }

        // Sort data by achievement percentage
        usort($data, function ($a, $b) use ($type) {
            $aValue = $type === 'dm' ?
                ($a['dm']['achievement_percentage'] ?? 0) : ($a['ht']['achievement_percentage'] ?? 0);

            $bValue = $type === 'dm' ?
                ($b['dm']['achievement_percentage'] ?? 0) : ($b['ht']['achievement_percentage'] ?? 0);

            return $bValue <=> $aValue;
        });

        // Add ranking
        foreach ($data as $index => $item) {
            $data[$index]['ranking'] = $index + 1;
        }

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }

    /**
     * Calculate statistics for a puskesmas
     */
    protected function calculatePuskesmasStatistics($puskesmas, $year)
    {
        // Get targets with cache
        $htTarget = Cache::remember(
            "yearly_target:{$this->cacheVersion}:{$puskesmas->id}:ht:{$year}",
            $this->cacheDuration,
            function () use ($puskesmas, $year) {
                return YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();
            }
        );

        $dmTarget = Cache::remember(
            "yearly_target:{$this->cacheVersion}:{$puskesmas->id}:dm:{$year}",
            $this->cacheDuration,
            function () use ($puskesmas, $year) {
                return YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();
            }
        );

        // Get statistics using service
        $htStats = $this->statisticsService->calculateHtStatistics($puskesmas->id, $year);
        $dmStats = $this->statisticsService->calculateDmStatistics($puskesmas->id, $year);

        return [
            'ht' => [
                'target' => $htTarget ? $htTarget->target_count : 0,
                'total_patients' => $htStats['total_patients'],
                'achievement_percentage' => $htTarget && $htTarget->target_count > 0
                    ? round(($htStats['standard_patients'] / $htTarget->target_count) * 100, 2)
                    : 0,
                'standard_patients' => $htStats['standard_patients'],
                'monthly_data' => $htStats['monthly_data'],
            ],
            'dm' => [
                'target' => $dmTarget ? $dmTarget->target_count : 0,
                'total_patients' => $dmStats['total_patients'],
                'achievement_percentage' => $dmTarget && $dmTarget->target_count > 0
                    ? round(($dmStats['standard_patients'] / $dmTarget->target_count) * 100, 2)
                    : 0,
                'standard_patients' => $dmStats['standard_patients'],
                'monthly_data' => $dmStats['monthly_data'],
            ]
        ];
    }

    /**
     * Get HT statistics for dashboard
     */
    protected function getHtStatistics($puskesmasId, $year)
    {
        // Get all patients with HT examinations in this year
        $patients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('htExaminations', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->with(['htExaminations' => function ($query) use ($year) {
                $query->where('year', $year)->orderBy('month');
            }])
            ->get();

        $totalPatients = $patients->count();
        $standardPatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'total' => 0,
                'standard' => 0
            ];
        }

        foreach ($patients as $patient) {
            $firstExamMonth = $patient->htExaminations->min('month');

            if ($firstExamMonth === null) continue;

            // Check if patient has examinations every month since first exam
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $patient->htExaminations
                    ->where('month', $m)
                    ->count() > 0;

                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }

            if ($isStandard) {
                $standardPatients++;
            }

            // Count monthly visits
            foreach ($patient->htExaminations as $exam) {
                $month = $exam->month;
                $monthlyData[$month]['total']++;
                if ($isStandard) {
                    $monthlyData[$month]['standard']++;
                }
            }
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get DM statistics for dashboard
     */
    protected function getDmStatistics($puskesmasId, $year)
    {
        // Get all patients with DM examinations in this year
        $patients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('dmExaminations', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->with(['dmExaminations' => function ($query) use ($year) {
                $query->where('year', $year)->orderBy('month');
            }])
            ->get();

        $totalPatients = $patients->count();
        $standardPatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'total' => 0,
                'standard' => 0
            ];
        }

        foreach ($patients as $patient) {
            $firstExamMonth = $patient->dmExaminations->min('month');

            if ($firstExamMonth === null) continue;

            // Check if patient has examinations every month since first exam
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $patient->dmExaminations
                    ->where('month', $m)
                    ->count() > 0;

                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }

            if ($isStandard) {
                $standardPatients++;
            }

            // Count monthly visits
            foreach ($patient->dmExaminations as $exam) {
                $month = $exam->month;
                $monthlyData[$month]['total']++;
                if ($isStandard) {
                    $monthlyData[$month]['standard']++;
                }
            }
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get HT statistics from cache
     */
    protected function getHtStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = DB::table('monthly_statistics_cache')
            ->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', 'ht');

        if ($month) {
            $query->where('month', $month);
        }

        $stats = $query->get();

        $totalPatients = 0;
        $totalStandard = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($stats as $stat) {
            $totalPatients += $stat->total_count;
            $totalStandard += $stat->standard_count;
            $malePatients += $stat->male_count;
            $femalePatients += $stat->female_count;

            $monthlyData[$stat->month] = [
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => 0 // Will be calculated later with target
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'total_standard' => $totalStandard,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get DM statistics from cache
     */
    protected function getDmStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = DB::table('monthly_statistics_cache')
            ->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', 'dm');

        if ($month) {
            $query->where('month', $month);
        }

        $stats = $query->get();

        $totalPatients = 0;
        $totalStandard = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($stats as $stat) {
            $totalPatients += $stat->total_count;
            $totalStandard += $stat->standard_count;
            $malePatients += $stat->male_count;
            $femalePatients += $stat->female_count;

            $monthlyData[$stat->month] = [
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => 0 // Will be calculated later with target
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'total_standard' => $totalStandard,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Clear cache for a specific puskesmas and year
     */
    public function clearCache($puskesmasId, $year)
    {
        $keys = [
            "dashboard_stats:{$this->cacheVersion}:{$puskesmasId}:{$year}",
            "yearly_target:{$this->cacheVersion}:{$puskesmasId}:ht:{$year}",
            "yearly_target:{$this->cacheVersion}:{$puskesmasId}:dm:{$year}"
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        return true;
    }
}
