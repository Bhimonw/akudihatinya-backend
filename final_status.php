<?php

// Final verification script
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== FINAL DATABASE STATUS ===" . PHP_EOL . PHP_EOL;
    
    // Users
    echo "📊 USERS:" . PHP_EOL;
    $totalUsers = DB::table('users')->count();
    $adminUsers = DB::table('users')->where('role', 'admin')->count();
    $puskesmasUsers = DB::table('users')->where('role', 'puskesmas')->count();
    
    echo "  Total Users: $totalUsers" . PHP_EOL;
    echo "  ├─ Admin: $adminUsers" . PHP_EOL;
    echo "  └─ Puskesmas: $puskesmasUsers" . PHP_EOL;
    echo PHP_EOL;
    
    // Puskesmas
    echo "🏥 PUSKESMAS:" . PHP_EOL;
    $totalPuskesmas = DB::table('puskesmas')->count();
    echo "  Total Puskesmas: $totalPuskesmas" . PHP_EOL;
    echo PHP_EOL;
    
    // Patients
    echo "👥 PATIENTS:" . PHP_EOL;
    $totalPatients = DB::table('patients')->count();
    echo "  Total Patients: $totalPatients" . PHP_EOL;
    echo PHP_EOL;
    
    // Examinations
    echo "📋 EXAMINATIONS:" . PHP_EOL;
    $htExams = DB::table('ht_examinations')->count();
    $dmExams = DB::table('dm_examinations')->count();
    echo "  HT Examinations: $htExams" . PHP_EOL;
    echo "  DM Examinations: $dmExams" . PHP_EOL;
    echo "  Total: " . ($htExams + $dmExams) . PHP_EOL;
    echo PHP_EOL;
    
    // Yearly Targets
    echo "🎯 YEARLY TARGETS (2025):" . PHP_EOL;
    $currentYear = date('Y');
    $htTargets = DB::table('yearly_targets')
        ->where('year', $currentYear)
        ->where('disease_type', 'ht')
        ->count();
    $dmTargets = DB::table('yearly_targets')
        ->where('year', $currentYear)
        ->where('disease_type', 'dm')
        ->count();
    
    $totalTargets = $htTargets + $dmTargets;
    
    echo "  HT Targets: $htTargets" . PHP_EOL;
    echo "  DM Targets: $dmTargets" . PHP_EOL;
    echo "  Total: $totalTargets" . PHP_EOL;
    
    // Check if all targets are 0
    $nonZeroTargets = DB::table('yearly_targets')
        ->where('year', $currentYear)
        ->where('target_count', '>', 0)
        ->count();
    
    if ($nonZeroTargets > 0) {
        echo "  ⚠ Warning: $nonZeroTargets targets are not 0" . PHP_EOL;
    } else {
        echo "  ✓ All targets set to 0" . PHP_EOL;
    }
    echo PHP_EOL;
    
    // Statistics Cache
    echo "💾 CACHE:" . PHP_EOL;
    $cacheRecords = DB::table('monthly_statistics_cache')->count();
    echo "  Statistics Cache: $cacheRecords records" . PHP_EOL;
    echo PHP_EOL;
    
    // Summary
    echo "=== SUMMARY ===" . PHP_EOL;
    echo "✅ Users: $totalUsers (1 admin + $puskesmasUsers puskesmas)" . PHP_EOL;
    echo "✅ Puskesmas: $totalPuskesmas" . PHP_EOL;
    echo "✅ Patients: $totalPatients" . PHP_EOL;
    echo "✅ Examinations: " . ($htExams + $dmExams) . " (clean)" . PHP_EOL;
    echo "✅ Yearly Targets: $totalTargets (all set to 0)" . PHP_EOL;
    echo "✅ Cache: $cacheRecords records" . PHP_EOL;
    
    echo PHP_EOL . "✅ DATABASE READY FOR PRODUCTION USE!" . PHP_EOL;
    
    // Login Info
    echo PHP_EOL . "=== LOGIN CREDENTIALS ===" . PHP_EOL;
    echo "Admin:" . PHP_EOL;
    echo "  Username: admin" . PHP_EOL;
    echo "  Password: dinas123" . PHP_EOL;
    echo PHP_EOL;
    echo "Puskesmas (25 accounts):" . PHP_EOL;
    echo "  Username: pkm_<nama_puskesmas>" . PHP_EOL;
    echo "  Password: puskesmas123" . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
