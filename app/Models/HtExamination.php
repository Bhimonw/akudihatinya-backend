<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HtExamination extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'puskesmas_id',
        'examination_date',
        'systolic',
        'diastolic',
        'is_controlled',
        'is_first_visit_this_month',
        'is_standard_patient',
        'patient_gender',
        'year',
        'month',
        'is_archived',
    ];

    protected $casts = [
        'examination_date' => 'date',
        'is_controlled' => 'boolean',
        'is_first_visit_this_month' => 'boolean',
        'is_standard_patient' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function isControlled()
    {
        return $this->systolic >= 90 && $this->systolic <= 139 && 
               $this->diastolic >= 60 && $this->diastolic <= 89;
    }

    /**
     * Calculate and set pre-calculated statistics
     */
    public function calculateStatistics(): void
    {
        // Calculate if controlled
        $this->is_controlled = $this->isControlled();
        
        // Get patient data
        $patient = $this->patient;
        if ($patient) {
            $this->patient_gender = $patient->gender;
        }
        
        // Check if this is first visit this month
        $this->is_first_visit_this_month = $this->checkIfFirstVisitThisMonth();
        
        // Calculate if patient is standard (only if first visit)
        if ($this->is_first_visit_this_month) {
            $this->is_standard_patient = $this->calculateIfStandardPatient();
        }
    }
    
    /**
     * Check if this is the first visit this month for this patient
     */
    private function checkIfFirstVisitThisMonth(): bool
    {
        return !self::where('patient_id', $this->patient_id)
            ->where('puskesmas_id', $this->puskesmas_id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->where('id', '!=', $this->id ?? 0)
            ->exists();
    }
    
    /**
     * Calculate if patient is standard for this specific month
     * Logic: Patient is standard if they visit this month AND have no gaps from their first visit of the year
     */
    private function calculateIfStandardPatient(): bool
    {
        // Get first visit in the year
        $firstVisit = self::where('patient_id', $this->patient_id)
            ->where('year', $this->year)
            ->orderBy('examination_date')
            ->first();
            
        if (!$firstVisit) {
            return false;
        }
        
        $firstMonth = $firstVisit->month;
        
        // If this is the first month of the year for this patient, they are standard
        if ($this->month == $firstMonth) {
            return true;
        }
        
        // Check if patient has visits for every month from first visit until current month
        // If there's any gap, patient becomes non-standard for this month
        for ($month = $firstMonth; $month <= $this->month; $month++) {
            $hasVisit = self::where('patient_id', $this->patient_id)
                ->where('year', $this->year)
                ->where('month', $month)
                ->exists();
                
            if (!$hasVisit) {
                return false;
            }
        }
        
        return true;
    }
}