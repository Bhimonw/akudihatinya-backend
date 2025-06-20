<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyStatisticsCache extends Model
{
    use HasFactory;

    protected $table = 'monthly_statistics_cache';

    protected $fillable = [
        'puskesmas_id',
        'disease_type',
        'year',
        'month',
        'male_count',
        'female_count',
        'total_count',
        'standard_count',
        'non_standard_count',
        'standard_percentage',
    ];

    protected $casts = [
        'standard_percentage' => 'decimal:2',
    ];

    /**
     * Relationship with Puskesmas
     */
    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class);
    }

    /**
     * Update or create statistics for a specific month
     */
    public static function updateOrCreateStatistics(
        int $puskesmasId,
        string $diseaseType,
        int $year,
        int $month,
        array $data
    ): self {
        return self::updateOrCreate(
            [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
            ],
            $data
        );
    }

    /**
     * Increment patient counts
     */
    public function incrementPatient(string $gender, bool $isStandard): void
    {
        // Only count gender for standard patients
        if ($isStandard) {
            if ($gender === 'male') {
                $this->increment('male_count');
            } else {
                $this->increment('female_count');
            }
            $this->increment('standard_count');
        } else {
            $this->increment('non_standard_count');
        }

        $this->increment('total_count');

        // Recalculate percentage
        $this->recalculatePercentage();
    }

    /**
     * Recalculate and update the standard percentage based on yearly target
     */
    private function recalculatePercentage(): void
    {
        // Get yearly target for correct percentage calculation
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $this->puskesmas_id)
            ->where('disease_type', $this->disease_type)
            ->where('year', $this->year)
            ->value('target_count') ?? 0;
            
        if ($yearlyTarget > 0) {
            $this->standard_percentage = round(($this->standard_count / $yearlyTarget) * 100, 2);
        } else {
            $this->standard_percentage = 0;
        }

        $this->save();
    }

    /**
     * Get yearly summary
     */
    public static function getYearlySummary(int $puskesmasId, string $diseaseType, int $year): array
    {
        $data = self::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->get();

        $totalMale = $data->sum('male_count');
        $totalFemale = $data->sum('female_count');
        $totalCount = $data->sum('total_count');
        $totalStandard = $data->sum('standard_count');
        $totalNonStandard = $data->sum('non_standard_count');
        
        // Get yearly target for percentage calculation
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $diseaseType)
            ->where('year', $year)
            ->value('target_count') ?? 0;

        return [
            'male_count' => $totalMale,
            'female_count' => $totalFemale,
            'total_count' => $totalCount,
            'standard_count' => $totalStandard,
            'non_standard_count' => $totalNonStandard,
            'standard_percentage' => $yearlyTarget > 0 ? round(($totalStandard / $yearlyTarget) * 100, 2) : 0,
        ];
    }
}
