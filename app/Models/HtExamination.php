<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HtExamination extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'puskesmas_id',
        'examination_date',
        'systolic',
        'diastolic',
        'year',
        'month',
        'is_archived',
    ];

    protected $casts = [
        'examination_date' => 'date',
        'is_archived' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($examination) {
            Cache::tags(['ht_examinations'])->flush();
        });

        static::updated(function ($examination) {
            Cache::tags(['ht_examinations'])->flush();
        });

        static::deleted(function ($examination) {
            Cache::tags(['ht_examinations'])->flush();
        });
    }

    /**
     * Get cached examination by ID
     */
    public static function getCached($id)
    {
        return Cache::tags(['ht_examinations'])->remember('ht_examination:' . $id, now()->addDay(), function () use ($id) {
            return static::with(['patient', 'puskesmas'])->find($id);
        });
    }

    /**
     * Get cached examinations for a patient
     */
    public static function getCachedForPatient($patientId, $year = null)
    {
        $cacheKey = 'ht_examinations:patient:' . $patientId . ($year ? ':year:' . $year : '');

        return Cache::tags(['ht_examinations'])->remember($cacheKey, now()->addDay(), function () use ($patientId, $year) {
            $query = static::with(['patient', 'puskesmas'])
                ->where('patient_id', $patientId);

            if ($year) {
                $query->where('year', $year);
            }

            return $query->get();
        });
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class)->withDefault();
    }

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class)->withDefault();
    }

    public function isControlled()
    {
        return $this->systolic >= 90 && $this->systolic <= 139 &&
            $this->diastolic >= 60 && $this->diastolic <= 89;
    }
}
