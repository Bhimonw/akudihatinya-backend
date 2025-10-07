<?php
// Manual Diagnostic Script (relocated from project root)
// Usage: php development/manual-tests/test_admin_api.php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== TESTING ADMIN STATISTICS API (DIRECT DB QUERIES) ===" . PHP_EOL . PHP_EOL;

    $year = 2025;
    $diseaseType = 'dm'; // or 'ht'

    echo "Parameters:" . PHP_EOL;
    echo "Year: $year" . PHP_EOL;
    echo "Disease Type: $diseaseType" . PHP_EOL . PHP_EOL;

    $allPuskesmas = DB::table('puskesmas')->get();
    $totalPuskesmas = $allPuskesmas->count();
    echo "Total Puskesmas: $totalPuskesmas" . PHP_EOL . PHP_EOL;

    echo "=== {$diseaseType} STATISTICS ===" . PHP_EOL;

    $target = DB::table('yearly_targets')
        ->where('disease_type', $diseaseType)
        ->where('year', $year)
        ->sum('target_count');
    echo "Target: $target" . PHP_EOL;

    $table = $diseaseType . '_examinations';

    $totalPatients = DB::table($table)
        ->where('year', $year)
        ->distinct('patient_id')
        ->count('patient_id');
    echo "Total Patients (unique): $totalPatients" . PHP_EOL;

    $totalExams = DB::table($table)
        ->where('year', $year)
        ->count();
    echo "Total Examinations: $totalExams" . PHP_EOL;

    $standardPatients = DB::table($table)
        ->where('year', $year)
        ->where('is_standard_therapy', true)
        ->distinct('patient_id')
        ->count('patient_id');
    echo "Standard Patients: $standardPatients" . PHP_EOL;

    $nonStandardPatients = DB::table($table)
        ->where('year', $year)
        ->where('is_standard_therapy', false)
        ->distinct('patient_id')
        ->count('patient_id');
    echo "Non-Standard Patients: $nonStandardPatients" . PHP_EOL;

    $male = DB::table($table)
        ->join('patients', $table.'.patient_id', '=', 'patients.id')
        ->where($table.'.year', $year)
        ->where('patients.gender', 'male')
        ->distinct($table.'.patient_id')
        ->count($table.'.patient_id');
    $female = DB::table($table)
        ->join('patients', $table.'.patient_id', '=', 'patients.id')
        ->where($table.'.year', $year)
        ->where('patients.gender', 'female')
        ->distinct($table.'.patient_id')
        ->count($table.'.patient_id');
    echo "Male Patients: $male" . PHP_EOL;
    echo "Female Patients: $female" . PHP_EOL;

    $achievementPercentage = $target > 0 ? ($standardPatients / $target) * 100 : 0;
    echo "Achievement Percentage: " . number_format($achievementPercentage, 2) . "%" . PHP_EOL . PHP_EOL;

    echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;
    echo "📊 SUMMARY (for dashboard parity):" . PHP_EOL;
    echo "Total Puskesmas: $totalPuskesmas" . PHP_EOL;
    echo "Sasaran (Target): $target" . PHP_EOL;
    echo "Capaian Standar: $standardPatients" . PHP_EOL;
    echo "Capaian Tidak Standar: $nonStandardPatients" . PHP_EOL;
    echo "Total Pelayanan (Unique Patients): $totalPatients" . PHP_EOL;
    echo "% Capaian: " . number_format($achievementPercentage, 1) . "%" . PHP_EOL . PHP_EOL;

    if ($totalPatients > 0 || $totalExams > 0) {
        echo "⚠️ NOTE: Database contains examination data for $diseaseType ($year)." . PHP_EOL;
    } else {
        echo "✅ Clean state: no examinations for $diseaseType ($year)." . PHP_EOL;
    }

    echo PHP_EOL . "🔍 Cache table inspection" . PHP_EOL;
    if (DB::getSchemaBuilder()->hasTable('statistics_cache')) {
        $cacheCount = DB::table('statistics_cache')->count();
        echo "Statistics Cache Records: $cacheCount" . PHP_EOL;
        if ($cacheCount > 0) {
            $cacheSample = DB::table('statistics_cache')
                ->orderBy('updated_at', 'desc')
                ->limit(3)
                ->get();
            foreach ($cacheSample as $c) {
                echo "  - Year: {$c->year}, Type: {$c->disease_type}, Puskesmas: {$c->puskesmas_id}, Updated: {$c->updated_at}" . PHP_EOL;
            }
        }
    } else {
        echo "(statistics_cache table not present)" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
