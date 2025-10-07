<?php

// Test the actual API endpoint that frontend is calling
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== TESTING ADMIN STATISTICS API ===" . PHP_EOL . PHP_EOL;
    
    $year = 2025;
    $diseaseType = 'dm'; // Diabetes Mellitus (shown in screenshot)
    
    echo "Parameters:" . PHP_EOL;
    echo "Year: $year" . PHP_EOL;
    echo "Disease Type: $diseaseType" . PHP_EOL;
    echo PHP_EOL;
    
    // Get all puskesmas
    $allPuskesmas = DB::table('puskesmas')->get();
    $totalPuskesmas = $allPuskesmas->count();
    
    echo "Total Puskesmas: $totalPuskesmas" . PHP_EOL;
    echo PHP_EOL;
    
    // Calculate summary statistics for DM
    echo "=== DM STATISTICS ===" . PHP_EOL;
    
    // Get yearly targets
    $dmTarget = DB::table('yearly_targets')
        ->where('disease_type', 'dm')
        ->where('year', $year)
        ->sum('target_count');
    
    echo "Target: $dmTarget" . PHP_EOL;
    
    // Get total unique patients from DM examinations
    $dmTotalPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->distinct('patient_id')
        ->count('patient_id');
    
    echo "Total Patients (unique): $dmTotalPatients" . PHP_EOL;
    
    // Get total examinations (not unique patients)
    $dmTotalExams = DB::table('dm_examinations')
        ->where('year', $year)
        ->count();
    
    echo "Total Examinations: $dmTotalExams" . PHP_EOL;
    
    // Get standard patients
    $dmStandardPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('is_standard_therapy', true)
        ->distinct('patient_id')
        ->count('patient_id');
    
    echo "Standard Patients: $dmStandardPatients" . PHP_EOL;
    
    // Get non-standard patients
    $dmNonStandardPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('is_standard_therapy', false)
        ->distinct('patient_id')
        ->count('patient_id');
    
    echo "Non-Standard Patients: $dmNonStandardPatients" . PHP_EOL;
    
    // Get male/female breakdown
    $dmMale = DB::table('dm_examinations')
        ->join('patients', 'dm_examinations.patient_id', '=', 'patients.id')
        ->where('dm_examinations.year', $year)
        ->where('patients.gender', 'male')
        ->distinct('dm_examinations.patient_id')
        ->count('dm_examinations.patient_id');
    
    $dmFemale = DB::table('dm_examinations')
        ->join('patients', 'dm_examinations.patient_id', '=', 'patients.id')
        ->where('dm_examinations.year', $year)
        ->where('patients.gender', 'female')
        ->distinct('dm_examinations.patient_id')
        ->count('dm_examinations.patient_id');
    
    echo "Male Patients: $dmMale" . PHP_EOL;
    echo "Female Patients: $dmFemale" . PHP_EOL;
    
    // Calculate achievement percentage
    $achievementPercentage = $dmTarget > 0 ? ($dmStandardPatients / $dmTarget) * 100 : 0;
    echo "Achievement Percentage: " . number_format($achievementPercentage, 2) . "%" . PHP_EOL;
    
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo PHP_EOL;
    
    // Summary for frontend
    echo "📊 WHAT FRONTEND WILL SHOW:" . PHP_EOL;
    echo "Total Puskesmas: $totalPuskesmas" . PHP_EOL;
    echo "Sasaran (Target): $dmTarget" . PHP_EOL;
    echo "Capaian Standar: $dmStandardPatients" . PHP_EOL;
    echo "Capaian Tidak Standar: $dmNonStandardPatients" . PHP_EOL;
    echo "Total Pelayanan (total_patients): $dmTotalPatients" . PHP_EOL;
    echo "% Capaian: " . number_format($achievementPercentage, 1) . "%" . PHP_EOL;
    echo PHP_EOL;
    
    if ($dmTotalPatients > 0 || $dmTotalExams > 0) {
        echo "❌ PROBLEM: Database still has examination data!" . PHP_EOL;
        echo "   This means frontend is reading REAL data from MySQL." . PHP_EOL;
        echo "   The '121' shown in frontend is likely from this database." . PHP_EOL;
    } else {
        echo "✅ Database is clean - all values should be 0" . PHP_EOL;
    }
    
    echo PHP_EOL;
    echo "🔍 Checking cache tables..." . PHP_EOL;
    
    // Check statistics cache table
    $cacheCount = DB::table('statistics_cache')->count();
    echo "Statistics Cache Records: $cacheCount" . PHP_EOL;
    
    if ($cacheCount > 0) {
        $cacheSample = DB::table('statistics_cache')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
        
        echo PHP_EOL . "Recent cache entries:" . PHP_EOL;
        foreach ($cacheSample as $cache) {
            echo "  - Year: {$cache->year}, Type: {$cache->disease_type}, Puskesmas: {$cache->puskesmas_id}, Updated: {$cache->updated_at}" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
