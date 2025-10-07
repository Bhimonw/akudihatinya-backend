<?php

// Check examinations with detailed connection info
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

try {
    echo "=== CHECKING DATABASE CONNECTION & EXAMINATIONS ===" . PHP_EOL . PHP_EOL;
    
    // Display connection info
    echo "📊 DATABASE CONNECTION INFO:" . PHP_EOL;
    echo "Driver: " . Config::get('database.default') . PHP_EOL;
    echo "Host: " . Config::get('database.connections.mysql.host') . PHP_EOL;
    echo "Port: " . Config::get('database.connections.mysql.port') . PHP_EOL;
    echo "Database: " . Config::get('database.connections.mysql.database') . PHP_EOL;
    echo "Username: " . Config::get('database.connections.mysql.username') . PHP_EOL;
    echo PHP_EOL;
    
    // Test connection
    echo "🔗 Testing Connection..." . PHP_EOL;
    $pdo = DB::connection()->getPdo();
    echo "✓ Connection successful!" . PHP_EOL;
    echo "Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;
    echo PHP_EOL;
    
    // Check current database
    $currentDb = DB::select('SELECT DATABASE() as db');
    echo "Current Database: " . $currentDb[0]->db . PHP_EOL;
    echo PHP_EOL;
    
    // Count examinations with detailed breakdown
    echo "=== EXAMINATION DATA ===" . PHP_EOL . PHP_EOL;
    
    // HT Examinations
    echo "🩺 HT EXAMINATIONS:" . PHP_EOL;
    $htTotal = DB::table('ht_examinations')->count();
    echo "Total: $htTotal" . PHP_EOL;
    
    if ($htTotal > 0) {
        $htByPuskesmas = DB::table('ht_examinations')
            ->join('puskesmas', 'ht_examinations.puskesmas_id', '=', 'puskesmas.id')
            ->select('puskesmas.name', DB::raw('COUNT(*) as count'))
            ->groupBy('puskesmas.id', 'puskesmas.name')
            ->orderBy('count', 'desc')
            ->get();
        
        echo "Breakdown by Puskesmas:" . PHP_EOL;
        foreach ($htByPuskesmas as $record) {
            echo "  - {$record->name}: {$record->count} examinations" . PHP_EOL;
        }
        
        // Show sample data
        $htSample = DB::table('ht_examinations')
            ->select('id', 'puskesmas_id', 'patient_id', 'examination_date', 'created_at')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
        
        echo PHP_EOL . "Sample HT Examinations (last 5):" . PHP_EOL;
        foreach ($htSample as $exam) {
            echo "  ID: {$exam->id}, Puskesmas: {$exam->puskesmas_id}, Patient: {$exam->patient_id}, Date: {$exam->examination_date}" . PHP_EOL;
        }
    } else {
        echo "✓ No HT examinations found (CLEAN)" . PHP_EOL;
    }
    
    echo PHP_EOL;
    
    // DM Examinations
    echo "💉 DM EXAMINATIONS:" . PHP_EOL;
    $dmTotal = DB::table('dm_examinations')->count();
    echo "Total: $dmTotal" . PHP_EOL;
    
    if ($dmTotal > 0) {
        $dmByPuskesmas = DB::table('dm_examinations')
            ->join('puskesmas', 'dm_examinations.puskesmas_id', '=', 'puskesmas.id')
            ->select('puskesmas.name', DB::raw('COUNT(*) as count'))
            ->groupBy('puskesmas.id', 'puskesmas.name')
            ->orderBy('count', 'desc')
            ->get();
        
        echo "Breakdown by Puskesmas:" . PHP_EOL;
        foreach ($dmByPuskesmas as $record) {
            echo "  - {$record->name}: {$record->count} examinations" . PHP_EOL;
        }
        
        // Show sample data
        $dmSample = DB::table('dm_examinations')
            ->select('id', 'puskesmas_id', 'patient_id', 'examination_date', 'created_at')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
        
        echo PHP_EOL . "Sample DM Examinations (last 5):" . PHP_EOL;
        foreach ($dmSample as $exam) {
            echo "  ID: {$exam->id}, Puskesmas: {$exam->puskesmas_id}, Patient: {$exam->patient_id}, Date: {$exam->examination_date}" . PHP_EOL;
        }
    } else {
        echo "✓ No DM examinations found (CLEAN)" . PHP_EOL;
    }
    
    echo PHP_EOL;
    
    // Check patients
    $patientCount = DB::table('patients')->count();
    echo "👥 PATIENTS: $patientCount" . PHP_EOL;
    
    if ($patientCount > 0) {
        $patientByPuskesmas = DB::table('patients')
            ->join('puskesmas', 'patients.puskesmas_id', '=', 'puskesmas.id')
            ->select('puskesmas.name', DB::raw('COUNT(*) as count'))
            ->groupBy('puskesmas.id', 'puskesmas.name')
            ->orderBy('count', 'desc')
            ->get();
        
        echo "Breakdown by Puskesmas:" . PHP_EOL;
        foreach ($patientByPuskesmas as $record) {
            echo "  - {$record->name}: {$record->count} patients" . PHP_EOL;
        }
    }
    
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    
    // Summary
    $totalRecords = $htTotal + $dmTotal + $patientCount;
    echo PHP_EOL . "📊 SUMMARY:" . PHP_EOL;
    echo "Total HT Examinations: $htTotal" . PHP_EOL;
    echo "Total DM Examinations: $dmTotal" . PHP_EOL;
    echo "Total Patients: $patientCount" . PHP_EOL;
    echo "Total Records: $totalRecords" . PHP_EOL;
    echo PHP_EOL;
    
    if ($totalRecords > 0) {
        echo "⚠️ WARNING: Database contains examination data!" . PHP_EOL;
        echo "   Database might be connected to wrong database or data wasn't properly cleared." . PHP_EOL;
        echo PHP_EOL;
        echo "🔧 To fix: Run 'php artisan migrate:fresh --seed' to completely reset database" . PHP_EOL;
    } else {
        echo "✅ Database is clean! No examination or patient data found." . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
