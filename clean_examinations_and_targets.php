<?php

// Script to clean examinations and set yearly targets to 0
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== CLEANING DATABASE ===" . PHP_EOL . PHP_EOL;
    
    // 1. Delete all HT examinations
    echo "Deleting HT examinations..." . PHP_EOL;
    $htDeleted = DB::table('ht_examinations')->delete();
    echo "✓ Deleted $htDeleted HT examination records" . PHP_EOL;
    
    // 2. Delete all DM examinations
    echo "Deleting DM examinations..." . PHP_EOL;
    $dmDeleted = DB::table('dm_examinations')->delete();
    echo "✓ Deleted $dmDeleted DM examination records" . PHP_EOL;
    
    // 3. Clear monthly statistics cache
    echo "Clearing statistics cache..." . PHP_EOL;
    $cacheDeleted = DB::table('monthly_statistics_cache')->delete();
    echo "✓ Deleted $cacheDeleted cache records" . PHP_EOL;
    
    echo PHP_EOL;
    
    // 4. Set yearly targets to 0 if they don't exist
    echo "Setting up yearly targets..." . PHP_EOL;
    
    $currentYear = date('Y');
    $puskesmasList = DB::table('puskesmas')->get();
    $diseaseTypes = ['ht', 'dm'];
    
    $created = 0;
    $existing = 0;
    
    foreach ($puskesmasList as $puskesmas) {
        foreach ($diseaseTypes as $diseaseType) {
            // Check if target exists
            $existingTarget = DB::table('yearly_targets')
                ->where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', $diseaseType)
                ->where('year', $currentYear)
                ->first();
            
            if (!$existingTarget) {
                // Create new target with 0
                DB::table('yearly_targets')->insert([
                    'puskesmas_id' => $puskesmas->id,
                    'disease_type' => $diseaseType,
                    'year' => $currentYear,
                    'target_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            } else {
                $existing++;
            }
        }
    }
    
    echo "✓ Created $created new yearly targets (set to 0)" . PHP_EOL;
    echo "✓ Found $existing existing yearly targets" . PHP_EOL;
    
    echo PHP_EOL . "=== SUMMARY ===" . PHP_EOL;
    echo "✓ HT Examinations deleted: $htDeleted" . PHP_EOL;
    echo "✓ DM Examinations deleted: $dmDeleted" . PHP_EOL;
    echo "✓ Cache records cleared: $cacheDeleted" . PHP_EOL;
    echo "✓ Yearly targets created: $created" . PHP_EOL;
    echo "✓ Total puskesmas: " . $puskesmasList->count() . PHP_EOL;
    
    echo PHP_EOL . "✅ DATABASE CLEANED SUCCESSFULLY!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
