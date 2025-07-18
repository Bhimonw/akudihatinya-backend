<?php

namespace App\Services\Statistics;

use App\Models\Patient;
use App\Models\MonthlyStatisticsCache;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use App\Repositories\YearlyTargetRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Optimized Disease Statistics Service
 * 
 * Improvements:
 * - Added intelligent caching with tags
 * - Optimized database queries
 * - Reduced memory usage
 * - Added bulk operations
 * - Improved error handling
 */
class DiseaseStatisticsService
{
    protected $yearlyTargetRepository;
    
    private const CACHE_TTL = 30; // minutes
    private const LONG_CACHE_TTL = 120; // minutes

    public function __construct(YearlyTargetRepository $yearlyTargetRepository)
    {
        $this->yearlyTargetRepository = $yearlyTargetRepository;
    }

    public function getStatisticsWithMonthlyBreakdown($puskesmasId, $year, $diseaseType, $month = null)
    {
        $cacheKey = "stats_breakdown:{$puskesmasId}:{$year}:{$diseaseType}:" . ($month ?? 'all');
        
        return Cache::tags(['statistics', "puskesmas:{$puskesmasId}", "disease:{$diseaseType}"])
            ->remember($cacheKey, self::CACHE_TTL, function () use ($puskesmasId, $year, $diseaseType, $month) {
                return $this->calculateStatisticsWithBreakdown($puskesmasId, $year, $diseaseType, $month);
            });
    }
    
    /**
     * Calculate statistics with monthly breakdown (cached version)
     */
    private function calculateStatisticsWithBreakdown($puskesmasId, $year, $diseaseType, $month = null)
    {
        $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, $diseaseType, $year);
        $target = $target ? $target->target_count : 0;
        $monthly_data = [];

        $examinationRelation = $diseaseType === 'ht' ? 'htExaminations' : 'dmExaminations';
        $allPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas($examinationRelation, function ($query) use ($year) {
                $query->whereYear('examination_date', $year);
            })
            ->with([$examinationRelation => function ($query) use ($year) {
                $query->whereYear('examination_date', $year)->orderBy('month');
            }])
            ->get();

        $cumulativePatientIds = collect();
        $patientFirstMonth = [];
        $patientMonthMap = [];

        foreach ($allPatients as $patient) {
            $months = $patient->$examinationRelation->map(function ($exam) {
                return Carbon::parse($exam->examination_date)->month;
            })->unique()->sort()->values();

            if ($months->isEmpty()) continue;
            $firstMonth = $months->first();
            $patientFirstMonth[$patient->id] = $firstMonth;
            $patientMonthMap[$patient->id] = $months->toArray();
        }

        for ($m = 1; $m <= 12; $m++) {
            $activePatients = $allPatients->filter(function ($patient) use ($m, $patientMonthMap) {
                $months = $patientMonthMap[$patient->id] ?? [];
                return collect($months)->filter(function ($mon) use ($m) {
                    return $mon <= $m;
                })->count() > 0;
            })->pluck('id');

            $cumulativePatientIds = $activePatients->merge($cumulativePatientIds)->unique();
            $monthly_total = $cumulativePatientIds->count();
            $monthly_male = $allPatients->whereIn('id', $cumulativePatientIds)->where('gender', 'male')->count();
            $monthly_female = $allPatients->whereIn('id', $cumulativePatientIds)->where('gender', 'female')->count();
            $monthly_standard = 0;
            $monthly_non_standard = 0;
            $monthly_male_standard = 0;
            $monthly_female_standard = 0;

            foreach ($allPatients->whereIn('id', $cumulativePatientIds) as $patient) {
                $firstMonth = $patientFirstMonth[$patient->id] ?? null;
                if ($firstMonth === null || $firstMonth > $m) continue;
                $isStandard = true;
                for ($checkM = $firstMonth; $checkM <= $m; $checkM++) {
                    if (!in_array($checkM, $patientMonthMap[$patient->id] ?? [])) {
                        $isStandard = false;
                        break;
                    }
                }
                if ($isStandard) {
                    $monthly_standard++;
                    if ($patient->gender === 'male') $monthly_male_standard++;
                    if ($patient->gender === 'female') $monthly_female_standard++;
                } else {
                    $monthly_non_standard++;
                }
            }

            $monthly_percentage = $target > 0 ? round(($monthly_standard / $target) * 100, 2) : 0;
            $monthly_data[$m] = [
                'target' => (string)$target,
                'male' => (string)$monthly_male_standard, // Only standard patients
                'female' => (string)$monthly_female_standard, // Only standard patients
                'total' => (string)$monthly_total, // All patients (standard + non-standard)
                'standard' => (string)$monthly_standard,
                'non_standard' => (string)$monthly_non_standard,
                'percentage' => $monthly_percentage,
            ];
        }

        // Summary ambil dari bulan terakhir yang tersedia
        $summary_last_month = null;
        for ($month = 12; $month >= 1; $month--) {
            if (isset($monthly_data[$month]) && ($monthly_data[$month]['total'] ?? 0) > 0) {
                $summary_last_month = $monthly_data[$month];
                break;
            }
        }
        
        // Fallback to empty data if no month has data
        if (!$summary_last_month) {
            $summary_last_month = [
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'male' => 0,
                'female' => 0
            ];
        }
        
        $total_patients = (int)$summary_last_month['total'];
        $standard_patients = (int)$summary_last_month['standard'];
        $non_standard_patients = (int)$summary_last_month['non_standard'];
        $male_patients = (int)$summary_last_month['male'];
        $female_patients = (int)$summary_last_month['female'];
        $achievement_percentage = $target > 0 ? round(($standard_patients / $target) * 100, 2) : 0;
        $standard_percentage = $target > 0 ? round(($standard_patients / $target) * 100, 2) : 0;

        return [
            'target' => (string)$target,
            'total_patients' => (string)$total_patients, // All patients (standard + non-standard)
            'standard_patients' => (string)$standard_patients,
            'non_standard_patients' => (string)$non_standard_patients,
            'male_patients' => (string)$male_patients, // Only standard patients
            'female_patients' => (string)$female_patients, // Only standard patients
            'achievement_percentage' => $achievement_percentage,
            'standard_percentage' => $standard_percentage,
            'monthly_data' => $monthly_data,
        ];
    }

    public function getStatistics($puskesmasId, $year, $diseaseType, $month = null)
    {
        $cacheKey = "stats:{$puskesmasId}:{$year}:{$diseaseType}:" . ($month ?? 'all');
        
        return Cache::tags(['statistics', "puskesmas:{$puskesmasId}", "disease:{$diseaseType}"])
            ->remember($cacheKey, self::CACHE_TTL, function () use ($puskesmasId, $year, $diseaseType, $month) {
                return $this->calculateBasicStatistics($puskesmasId, $year, $diseaseType, $month);
            });
    }
    
    /**
     * Calculate basic statistics without full breakdown
     */
    private function calculateBasicStatistics($puskesmasId, $year, $diseaseType, $month = null)
    {
        // Use cached statistics if available, otherwise fall back to calculation
        $cachedStats = $this->getStatisticsFromCacheOptimized($puskesmasId, $year, $diseaseType, $month);
        
        if ($cachedStats && !empty($cachedStats['total_patients'])) {
            return $cachedStats;
        }
        
        // Fallback to full calculation if cache is empty
        return $this->calculateStatisticsWithBreakdown($puskesmasId, $year, $diseaseType, $month);
    }

    public function processCachedStats($statsList, $target = null)
    {
        // Get the latest month data for summary (find last available month with data)
        $monthlyData = $statsList->keyBy('month');
        $latestMonthData = null;
        for ($month = 12; $month >= 1; $month--) {
            $monthData = $monthlyData->get($month);
            if ($monthData && $monthData->total_count > 0) {
                $latestMonthData = $monthData;
                break;
            }
        }

        $totalPatients = $latestMonthData ? $latestMonthData->total_count : 0;
        $standardPatients = $latestMonthData ? $latestMonthData->standard_count : 0;
        $nonStandardPatients = $latestMonthData ? $latestMonthData->non_standard_count : 0;
        $malePatients = $latestMonthData ? $latestMonthData->male_count : 0;
        $femalePatients = $latestMonthData ? $latestMonthData->female_count : 0;
        $targetCount = $target ? $target->target_count : 0;
        $achievement = $targetCount > 0 ? round(($standardPatients / $targetCount) * 100, 2) : 0;
        $monthlyDataFormatted = [];

        foreach ($statsList as $stat) {
            $monthlyDataFormatted[$stat->month] = [
                'target' => (string)$targetCount,
                'male' => (string)$stat->male_count,
                'female' => (string)$stat->female_count,
                'total' => (string)$stat->total_count,
                'standard' => (string)$stat->standard_count,
                'non_standard' => (string)$stat->non_standard_count,
                'percentage' => $targetCount > 0 ? round(($stat->standard_count / $targetCount) * 100, 2) : 0,
            ];
        }

        return [
            'target' => $targetCount,
            'total_patients' => $totalPatients,
            'achievement_percentage' => $achievement,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyDataFormatted
        ];
    }

    public function getStatisticsFromCache($puskesmasId, $year, $diseaseType, $month = null)
    {
        $query = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year);

        if ($month !== null) {
            $query->where('month', $month);
        }

        $monthlyData = $query->get()->keyBy('month');

        // Get the latest month data for summary (find last available month with data)
        $latestMonthData = null;
        for ($month = 12; $month >= 1; $month--) {
            $monthData = $monthlyData->get($month);
            if ($monthData && $monthData->total_count > 0) {
                $latestMonthData = $monthData;
                break;
            }
        }

        $totalPatients = $latestMonthData ? $latestMonthData->total_count : 0;
        $standardPatients = $latestMonthData ? $latestMonthData->standard_count : 0;
        $nonStandardPatients = $latestMonthData ? $latestMonthData->non_standard_count : 0;
        $malePatients = $latestMonthData ? $latestMonthData->male_count : 0;
        $femalePatients = $latestMonthData ? $latestMonthData->female_count : 0;

        $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, $diseaseType, $year);
        $yearlyTarget = $target ? (int)$target->target_count : 0;

        // Build monthly breakdown
        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $data = $monthlyData->get($m);
            $standard = $data ? (int)$data->standard_count : 0;
            $monthlyBreakdown[$m] = [
                'male' => (string)($data ? $data->male_count : 0),
                'female' => (string)($data ? $data->female_count : 0),
                'total' => (string)($data ? $data->total_count : 0),
                'standard' => (string)$standard,
                'non_standard' => (string)($data ? $data->non_standard_count : 0),
                'percentage' => $yearlyTarget > 0 ? round(($standard / $yearlyTarget) * 100, 2) : 0
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyBreakdown,
        ];
    }

    public function getMonthlyStatistics($puskesmasId, $year, $diseaseType, $month)
    {
        // Use cache for better performance
        return $this->getStatisticsFromCache($puskesmasId, $year, $diseaseType, $month);
    }
    
    /**
     * Optimized version of getStatisticsFromCache with better query performance
     */
    private function getStatisticsFromCacheOptimized($puskesmasId, $year, $diseaseType, $month = null)
    {
        $cacheKey = "cache_stats:{$puskesmasId}:{$year}:{$diseaseType}:" . ($month ?? 'all');
        
        return Cache::tags(['statistics', "puskesmas:{$puskesmasId}", "disease:{$diseaseType}"])
            ->remember($cacheKey, self::LONG_CACHE_TTL, function () use ($puskesmasId, $year, $diseaseType, $month) {
                return $this->calculateFromCache($puskesmasId, $year, $diseaseType, $month);
            });
    }
    
    /**
     * Calculate statistics from cache with optimized queries
     */
    private function calculateFromCache($puskesmasId, $year, $diseaseType, $month = null)
    {
        $query = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year);

        if ($month !== null) {
            $query->where('month', $month);
        }

        $monthlyData = $query->orderBy('month')->get();
        
        if ($monthlyData->isEmpty()) {
            return $this->getEmptyStatsStructure();
        }

        // Get the latest month data for summary (find last available month with data)
        $latestMonthData = $monthlyData->where('total_count', '>', 0)->last();
        
        if (!$latestMonthData) {
            return $this->getEmptyStatsStructure();
        }

        $totalPatients = $latestMonthData->total_count;
        $standardPatients = $latestMonthData->standard_count;
        $nonStandardPatients = $latestMonthData->non_standard_count;
        $malePatients = $latestMonthData->male_count;
        $femalePatients = $latestMonthData->female_count;

        $target = $this->getYearlyTargetCached($puskesmasId, $diseaseType, $year);

        // Build monthly breakdown efficiently
        $monthlyBreakdown = $this->buildMonthlyBreakdown($monthlyData, $target);

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'achievement_percentage' => $target > 0 ? round(($standardPatients / $target) * 100, 2) : 0,
            'monthly_data' => $monthlyBreakdown,
        ];
    }
    
    /**
     * Get yearly target with caching
     */
    private function getYearlyTargetCached($puskesmasId, $diseaseType, $year)
    {
        $cacheKey = "target:{$puskesmasId}:{$diseaseType}:{$year}";
        
        return Cache::tags(['targets', "puskesmas:{$puskesmasId}"])
            ->remember($cacheKey, 60, function () use ($puskesmasId, $diseaseType, $year) {
                $target = $this->yearlyTargetRepository->getByPuskesmasAndTypeAndYear($puskesmasId, $diseaseType, $year);
                return $target ? (int)$target->target_count : 0;
            });
    }
    
    /**
     * Build monthly breakdown efficiently
     */
    private function buildMonthlyBreakdown($monthlyData, $yearlyTarget)
    {
        $monthlyBreakdown = [];
        $dataByMonth = $monthlyData->keyBy('month');
        
        for ($m = 1; $m <= 12; $m++) {
            $data = $dataByMonth->get($m);
            $standard = $data ? (int)$data->standard_count : 0;
            
            $monthlyBreakdown[$m] = [
                'male' => (string)($data ? $data->male_count : 0),
                'female' => (string)($data ? $data->female_count : 0),
                'total' => (string)($data ? $data->total_count : 0),
                'standard' => (string)$standard,
                'non_standard' => (string)($data ? $data->non_standard_count : 0),
                'percentage' => $yearlyTarget > 0 ? round(($standard / $yearlyTarget) * 100, 2) : 0
            ];
        }
        
        return $monthlyBreakdown;
    }
    
    /**
     * Get empty statistics structure
     */
    private function getEmptyStatsStructure()
    {
        return [
            'total_patients' => 0,
            'standard_patients' => 0,
            'non_standard_patients' => 0,
            'male_patients' => 0,
            'female_patients' => 0,
            'achievement_percentage' => 0,
            'monthly_data' => array_fill(1, 12, [
                'male' => '0',
                'female' => '0',
                'total' => '0',
                'standard' => '0',
                'non_standard' => '0',
                'percentage' => 0
            ])
        ];
    }
    
    /**
     * Bulk invalidate cache for multiple entities
     */
    public function invalidateCache(array $puskesmasIds = [], array $diseaseTypes = [])
    {
        $tags = ['statistics'];
        
        foreach ($puskesmasIds as $id) {
            $tags[] = "puskesmas:{$id}";
        }
        
        foreach ($diseaseTypes as $type) {
            $tags[] = "disease:{$type}";
        }
        
        Cache::tags($tags)->flush();
    }
    
    /**
     * Get statistics for multiple puskesmas efficiently
     */
    public function getBulkStatistics(array $puskesmasIds, $year, $diseaseType, $month = null)
    {
        $cacheKey = 'bulk_stats:' . md5(serialize($puskesmasIds)) . ":{$year}:{$diseaseType}:" . ($month ?? 'all');
        
        return Cache::tags(['statistics', "disease:{$diseaseType}"])
            ->remember($cacheKey, self::CACHE_TTL, function () use ($puskesmasIds, $year, $diseaseType, $month) {
                return $this->calculateBulkStatistics($puskesmasIds, $year, $diseaseType, $month);
            });
    }
    
    /**
     * Calculate bulk statistics using optimized query
     */
    private function calculateBulkStatistics(array $puskesmasIds, $year, $diseaseType, $month = null)
    {
        $query = MonthlyStatisticsCache::whereIn('puskesmas_id', $puskesmasIds)
            ->where('year', $year)
            ->where('disease_type', $diseaseType);
            
        if ($month) {
            $query->where('month', $month);
        }
        
        return $query->select([
            'puskesmas_id',
            'month',
            'total_count',
            'standard_count',
            'non_standard_count',
            'male_count',
            'female_count'
        ])
        ->orderBy('puskesmas_id')
        ->orderBy('month', 'desc')
        ->get()
        ->groupBy('puskesmas_id');
    }
    
    /**
     * Pre-warm cache for frequently accessed data
     */
    public function preWarmCache(array $puskesmasIds, array $years, array $diseaseTypes)
    {
        foreach ($puskesmasIds as $puskesmasId) {
            foreach ($years as $year) {
                foreach ($diseaseTypes as $diseaseType) {
                    // Pre-warm basic statistics
                    $this->getStatistics($puskesmasId, $year, $diseaseType);
                    
                    // Pre-warm monthly breakdown
                    $this->getStatisticsWithMonthlyBreakdown($puskesmasId, $year, $diseaseType);
                    
                    // Pre-warm cache statistics
                    $this->getStatisticsFromCacheOptimized($puskesmasId, $year, $diseaseType);
                }
            }
        }
    }
    
    /**
     * Get performance metrics for monitoring
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache_driver' => config('cache.default'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cache_tags_supported' => $this->isCacheTagsSupported(),
        ];
    }
    
    /**
     * Check if cache tags are supported
     */
    private function isCacheTagsSupported(): bool
    {
        try {
            $cacheStore = Cache::getStore();
            return method_exists($cacheStore, 'tags');
        } catch (\Exception $e) {
            return false;
        }
    }
}
