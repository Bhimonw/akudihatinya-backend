<?php

// Direct API simulation without authentication for testing
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Puskesmas;

try {
    echo "=== SIMULATING ADMIN DASHBOARD API ===" . PHP_EOL . PHP_EOL;
    
    $year = 2025;
    $diseaseType = 'dm'; // Test with DM first (as shown in screenshot)
    
    echo "Parameters:" . PHP_EOL;
    echo "  Year: $year" . PHP_EOL;
    echo "  Disease Type: $diseaseType" . PHP_EOL;
    echo PHP_EOL;
    
    // Get all puskesmas
    $allPuskesmas = Puskesmas::all();
    $totalPuskesmas = $allPuskesmas->count();
    
    echo "Total Puskesmas: $totalPuskesmas" . PHP_EOL;
    echo PHP_EOL;
    
    // Initialize summary
    $summary = [];
    
    // DM Statistics
    $dmTarget = DB::table('yearly_targets')
        ->where('disease_type', 'dm')
        ->where('year', $year)
        ->sum('target_count');
    
    $dmTotalPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmStandardPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', 1)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmNonStandardPatients = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', 0)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmMale = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('patient_gender', 'male')
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmFemale = DB::table('dm_examinations')
        ->where('year', $year)
        ->where('patient_gender', 'female')
        ->distinct('patient_id')
        ->count('patient_id');
    
    $dmAchievement = $dmTarget > 0 ? ($dmStandardPatients / $dmTarget) * 100 : 0;
    
    $summary['dm'] = [
        'target' => (string)$dmTarget,
        'total_patients' => (string)$dmTotalPatients,
        'standard_patients' => (string)$dmStandardPatients,
        'non_standard_patients' => (string)$dmNonStandardPatients,
        'male_patients' => (string)$dmMale,
        'female_patients' => (string)$dmFemale,
        'achievement_percentage' => number_format($dmAchievement, 1)
    ];
    
    // HT Statistics
    $htTarget = DB::table('yearly_targets')
        ->where('disease_type', 'ht')
        ->where('year', $year)
        ->sum('target_count');
    
    $htTotalPatients = DB::table('ht_examinations')
        ->where('year', $year)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $htStandardPatients = DB::table('ht_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', 1)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $htNonStandardPatients = DB::table('ht_examinations')
        ->where('year', $year)
        ->where('is_standard_patient', 0)
        ->distinct('patient_id')
        ->count('patient_id');
    
    $htAchievement = $htTarget > 0 ? ($htStandardPatients / $htTarget) * 100 : 0;
    
    $summary['ht'] = [
        'target' => (string)$htTarget,
        'total_patients' => (string)$htTotalPatients,
        'standard_patients' => (string)$htStandardPatients,
        'non_standard_patients' => (string)$htNonStandardPatients,
        'achievement_percentage' => number_format($htAchievement, 1)
    ];
    
    echo "=== API RESPONSE (What Frontend Receives) ===" . PHP_EOL;
    echo PHP_EOL;
    
    $response = [
        'year' => (string)$year,
        'disease_type' => $diseaseType,
        'total_puskesmas' => $totalPuskesmas,
        'summary' => $summary,
        'data' => [] // Empty for this test
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo PHP_EOL;
    
    // What frontend will display
    echo "📊 WHAT FRONTEND DASHBOARD WILL SHOW (DM):" . PHP_EOL;
    echo PHP_EOL;
    echo "  Jumlah Puskesmas:           $totalPuskesmas" . PHP_EOL;
    echo "  Sasaran:                    {$summary['dm']['target']}" . PHP_EOL;
    echo "  Capaian Standar:            {$summary['dm']['standard_patients']}" . PHP_EOL;
    echo "  Capaian Tidak Standar:      {$summary['dm']['non_standard_patients']}" . PHP_EOL;
    echo "  Total Pelayanan:            {$summary['dm']['total_patients']}" . PHP_EOL;
    echo "  % Capaian Layanan:          {$summary['dm']['achievement_percentage']}%" . PHP_EOL;
    echo PHP_EOL;
    
    // Verification
    $totalExams = DB::table('dm_examinations')->where('year', $year)->count() + 
                  DB::table('ht_examinations')->where('year', $year)->count();
    
    if ($totalExams === 0 && $dmTotalPatients === 0 && $htTotalPatients === 0) {
        echo "✅✅✅ DATABASE IS CLEAN!" . PHP_EOL;
        echo PHP_EOL;
        echo "If frontend still shows old data:" . PHP_EOL;
        echo "1. Clear browser cache (Ctrl+Shift+Delete)" . PHP_EOL;
        echo "2. Hard refresh (Ctrl+F5)" . PHP_EOL;
        echo "3. Try incognito mode" . PHP_EOL;
        echo "4. Check browser console for errors" . PHP_EOL;
        echo PHP_EOL;
        echo "Frontend is now running at: http://localhost:5173" . PHP_EOL;
        echo "Backend is running at: http://127.0.0.1:8000" . PHP_EOL;
    } else {
        echo "❌ WARNING: Database still has examination data!" . PHP_EOL;
        echo "Total examinations: $totalExams" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
