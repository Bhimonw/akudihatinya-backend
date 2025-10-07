<?php

// Test with correct column name
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== FINAL VERIFICATION - CORRECT COLUMN NAMES ===" . PHP_EOL . PHP_EOL;
    
    $year = 2025;
    
    echo "📊 HT (HIPERTENSI) DATA:" . PHP_EOL;
    echo str_repeat('-', 50) . PHP_EOL;
    
    $htTarget = DB::table('yearly_targets')
        ->where('disease_type', 'ht')
        ->where('year', $year)
        ->sum('target_count');
    
    $htTotalPatients = DB::table('ht_examinations')
        ->where('year', $year)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $htTotalExams = DB::table('ht_examinations')
        ->where('year', $year)
        ->count();
    
    $htStandardPatients = DB::table('ht_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', true)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $htNonStandardPatients = DB::table('ht_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', false)
        ->distinct('patient_id')
        ->count('patient_id');
    
    echo "Target: $htTarget" . PHP_EOL;
    echo "Total Unique Patients: $htTotalPatients" . PHP_EOL;
    echo "Total Examinations: $htTotalExams" . PHP_EOL;
    echo "Standard Patients: $htStandardPatients" . PHP_EOL;
    echo "Non-Standard Patients: $htNonStandardPatients" . PHP_EOL;
    
    $htAchievement = $htTarget > 0 ? ($htStandardPatients / $htTarget) * 100 : 0;
    echo "Achievement: " . number_format($htAchievement, 1) . "%" . PHP_EOL;
    
    echo PHP_EOL;
    echo "💉 DM (DIABETES MELLITUS) DATA:" . PHP_EOL;
    echo str_repeat('-', 50) . PHP_EOL;
    
    $dmTarget = DB::table('yearly_targets')
        ->where('disease_type', 'dm')
        ->where('year', $year)
        ->sum('target_count');
    
    $dmTotalPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmTotalExams = DB::table('dm_examinations')
        ->where('year', $year)
        ->count();
    
    $dmStandardPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', true)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmNonStandardPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', false)
        ->distinct('patient_id')
        ->count('patient_id');
    
    echo "Target: $dmTarget" . PHP_EOL;
    echo "Total Unique Patients: $dmTotalPatients" . PHP_EOL;
    echo "Total Examinations: $dmTotalExams" . PHP_EOL;
    echo "Standard Patients: $dmStandardPatients" . PHP_EOL;
    echo "Non-Standard Patients: $dmNonStandardPatients" . PHP_EOL;
    
    $dmAchievement = $dmTarget > 0 ? ($dmStandardPatients / $dmTarget) * 100 : 0;
    echo "Achievement: " . number_format($dmAchievement, 1) . "%" . PHP_EOL;
    
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo PHP_EOL;
    
    // Check cache
    echo "🗄️ STATISTICS CACHE:" . PHP_EOL;
    $cacheCount = DB::table('statistics_cache')->count();
    echo "Total cache records: $cacheCount" . PHP_EOL;
    
    if ($cacheCount > 0) {
        echo PHP_EOL . "Cache records by year and type:" . PHP_EOL;
        $cacheBreakdown = DB::table('statistics_cache')
            ->select('year', 'disease_type', DB::raw('COUNT(*) as count'))
            ->groupBy('year', 'disease_type')
            ->get();
        
        foreach ($cacheBreakdown as $cache) {
            echo "  - Year {$cache->year}, Type {$cache->disease_type}: {$cache->count} records" . PHP_EOL;
        }
        
        echo PHP_EOL . "⚠️ WARNING: Cache exists! This might affect frontend display." . PHP_EOL;
        echo "   Consider clearing cache with: php artisan cache:clear" . PHP_EOL;
    } else {
        echo "✓ No cache records found" . PHP_EOL;
    }
    
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo PHP_EOL;
    
    // Final verdict
    $totalRecords = $htTotalExams + $dmTotalExams;
    
    echo "🎯 FINAL VERDICT:" . PHP_EOL;
    echo PHP_EOL;
    
    if ($totalRecords === 0 && $cacheCount === 0) {
        echo "✅✅✅ DATABASE IS COMPLETELY CLEAN!" . PHP_EOL;
        echo PHP_EOL;
        echo "If frontend still shows '121' Total Pelayanan:" . PHP_EOL;
        echo "1. Clear browser cache (Ctrl+Shift+Delete)" . PHP_EOL;
        echo "2. Hard refresh frontend (Ctrl+F5)" . PHP_EOL;
        echo "3. Check if frontend is connected to correct API endpoint" . PHP_EOL;
        echo "4. Verify frontend .env file has correct API_URL" . PHP_EOL;
    } elseif ($totalRecords === 0 && $cacheCount > 0) {
        echo "⚠️ Database is clean but CACHE EXISTS" . PHP_EOL;
        echo PHP_EOL;
        echo "Run: php artisan cache:clear" . PHP_EOL;
    } else {
        echo "❌ DATABASE STILL HAS DATA!" . PHP_EOL;
        echo "Total examination records: $totalRecords" . PHP_EOL;
        echo PHP_EOL;
        echo "Run: php artisan migrate:fresh --seed" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
