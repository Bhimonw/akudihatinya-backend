<?php

namespace App\Services;

use App\Models\MonthlyStatisticsCache;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Models\Puskesmas;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsCacheService
{
    /**
     * Get cached statistics for a specific month and puskesmas
     *
     * @param int $puskesmasId
     * @param string $month
     * @param string $diseaseType
     * @return array|null
     */
    public function getMonthlyStatistics(int $puskesmasId, string $month, string $diseaseType)
    {
        $cacheKey = "statistics:{$puskesmasId}:{$month}:{$diseaseType}";
        $date = Carbon::createFromFormat('Y-m', $month);

        $result = Cache::remember($cacheKey, now()->addDay(), function () use ($puskesmasId, $date, $diseaseType) {
            return MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', $diseaseType)
                ->where('year', $date->year)
                ->where('month', $date->month)
                ->first() ?? new MonthlyStatisticsCache([
                    'puskesmas_id' => $puskesmasId,
                    'disease_type' => $diseaseType,
                    'year' => $date->year,
                    'month' => $date->month,
                    'male_count' => 0,
                    'female_count' => 0,
                    'total_count' => 0,
                    'standard_count' => 0,
                    'non_standard_count' => 0,
                    'standard_percentage' => 0,
                ]);
        });

        // If result is array, convert to model instance
        if (is_array($result)) {
            $result = new MonthlyStatisticsCache($result);
        }
        return $result;
    }

    /**
     * Store statistics in cache
     *
     * @param int $puskesmasId
     * @param string $month
     * @param string $diseaseType
     * @param array $statistics
     * @return void
     */
    public function storeMonthlyStatistics(int $puskesmasId, string $month, string $diseaseType, array $statistics)
    {
        $cacheKey = "statistics:{$puskesmasId}:{$month}:{$diseaseType}";
        $date = Carbon::createFromFormat('Y-m', $month);

        DB::transaction(function () use ($puskesmasId, $date, $diseaseType, $statistics) {
            // Delete any existing record first
            MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', $diseaseType)
                ->where('year', $date->year)
                ->where('month', $date->month)
                ->delete();

            // Create new record
            MonthlyStatisticsCache::create($statistics);
        });

        Cache::put($cacheKey, $statistics, now()->addDay());
    }

    /**
     * Clear statistics cache for a specific month and puskesmas
     *
     * @param int $puskesmasId
     * @param string $month
     * @param string $diseaseType
     * @return void
     */
    public function clearMonthlyStatistics(int $puskesmasId, string $month, string $diseaseType)
    {
        $cacheKey = "statistics:{$puskesmasId}:{$month}:{$diseaseType}";
        $date = Carbon::createFromFormat('Y-m', $month);

        Cache::forget($cacheKey);

        MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->delete();
    }

    /**
     * Update cache when a new examination is created
     *
     * @param HtExamination|DmExamination $examination
     * @param string $type 'ht' for hypertension or 'dm' for diabetes
     * @return void
     */
    public function updateCacheOnExaminationCreate($examination, string $type)
    {
        $date = Carbon::parse($examination->examination_date);
        $month = $date->format('Y-m');
        $puskesmasId = $examination->puskesmas_id;

        // Get current statistics or initialize new ones
        $statistics = $this->getMonthlyStatistics($puskesmasId, $month, $type);

        if (!$statistics) {
            $statistics = new MonthlyStatisticsCache([
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $type,
                'year' => $date->year,
                'month' => $date->month,
                'male_count' => 0,
                'female_count' => 0,
                'total_count' => 0,
                'standard_count' => 0,
                'non_standard_count' => 0,
                'standard_percentage' => 0,
            ]);
        }

        // Update the appropriate counters
        $statistics->total_count++;
        if ($examination->patient->gender === 'male') {
            $statistics->male_count++;
        } else {
            $statistics->female_count++;
        }

        // Update standard/non-standard counts based on examination result
        if ($examination->is_standard) {
            $statistics->standard_count++;
        } else {
            $statistics->non_standard_count++;
        }

        // Calculate standard percentage
        $statistics->standard_percentage = $statistics->total_count > 0
            ? round(($statistics->standard_count / $statistics->total_count) * 100, 2)
            : 0;

        // Store updated statistics
        $statistics->save();

        // Update cache
        $cacheKey = "statistics:{$puskesmasId}:{$month}:{$type}";
        Cache::put($cacheKey, $statistics, now()->addDay());
    }

    /**
     * Rebuild cache for all puskesmas and months
     *
     * @return void
     */
    public function rebuildAllCache(): void
    {
        // Clear all existing cache and database records
        MonthlyStatisticsCache::truncate();
        Cache::flush();

        // Get all puskesmas
        $puskesmas = Puskesmas::all();
        $currentYear = Carbon::now()->year;

        foreach ($puskesmas as $puskesmas) {
            // Process HT examinations
            $htExaminations = HtExamination::where('puskesmas_id', $puskesmas->id)
                ->whereYear('examination_date', $currentYear)
                ->get()
                ->groupBy(function ($examination) {
                    return Carbon::parse($examination->examination_date)->format('Y-m');
                });

            foreach ($htExaminations as $month => $examinations) {
                $statistics = new MonthlyStatisticsCache([
                    'puskesmas_id' => $puskesmas->id,
                    'disease_type' => 'ht',
                    'year' => Carbon::parse($month)->year,
                    'month' => Carbon::parse($month)->month,
                    'male_count' => 0,
                    'female_count' => 0,
                    'total_count' => 0,
                    'standard_count' => 0,
                    'non_standard_count' => 0,
                    'standard_percentage' => 0,
                ]);

                foreach ($examinations as $examination) {
                    $statistics->total_count++;
                    if ($examination->patient->gender === 'male') {
                        $statistics->male_count++;
                    } else {
                        $statistics->female_count++;
                    }
                    if ($examination->is_standard) {
                        $statistics->standard_count++;
                    } else {
                        $statistics->non_standard_count++;
                    }
                }

                $statistics->standard_percentage = $statistics->total_count > 0
                    ? round(($statistics->standard_count / $statistics->total_count) * 100, 2)
                    : 0;

                $statistics->save();

                // Update cache
                $cacheKey = "statistics:{$puskesmas->id}:{$month}:ht";
                Cache::put($cacheKey, $statistics, now()->addDay());
            }

            // Process DM examinations
            $dmExaminations = DmExamination::where('puskesmas_id', $puskesmas->id)
                ->whereYear('examination_date', $currentYear)
                ->get()
                ->groupBy(function ($examination) {
                    return Carbon::parse($examination->examination_date)->format('Y-m');
                });

            foreach ($dmExaminations as $month => $examinations) {
                $statistics = new MonthlyStatisticsCache([
                    'puskesmas_id' => $puskesmas->id,
                    'disease_type' => 'dm',
                    'year' => Carbon::parse($month)->year,
                    'month' => Carbon::parse($month)->month,
                    'male_count' => 0,
                    'female_count' => 0,
                    'total_count' => 0,
                    'standard_count' => 0,
                    'non_standard_count' => 0,
                    'standard_percentage' => 0,
                ]);

                foreach ($examinations as $examination) {
                    $statistics->total_count++;
                    if ($examination->patient->gender === 'male') {
                        $statistics->male_count++;
                    } else {
                        $statistics->female_count++;
                    }
                    if ($examination->is_standard) {
                        $statistics->standard_count++;
                    } else {
                        $statistics->non_standard_count++;
                    }
                }

                $statistics->standard_percentage = $statistics->total_count > 0
                    ? round(($statistics->standard_count / $statistics->total_count) * 100, 2)
                    : 0;

                $statistics->save();

                // Update cache
                $cacheKey = "statistics:{$puskesmas->id}:{$month}:dm";
                Cache::put($cacheKey, $statistics, now()->addDay());
            }
        }
    }
}
