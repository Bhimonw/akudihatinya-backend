<?php

// Create yearly targets for all puskesmas
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== CREATING YEARLY TARGETS ===" . PHP_EOL . PHP_EOL;
    
    $currentYear = date('Y');
    
    // Get all puskesmas
    $puskesmasList = DB::table('puskesmas')->get();
    echo "Found {$puskesmasList->count()} puskesmas" . PHP_EOL . PHP_EOL;
    
    $diseaseTypes = ['ht', 'dm'];
    $created = 0;
    
    foreach ($puskesmasList as $puskesmas) {
        foreach ($diseaseTypes as $diseaseType) {
            // Check if already exists
            $exists = DB::table('yearly_targets')
                ->where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', $diseaseType)
                ->where('year', $currentYear)
                ->exists();
            
            if (!$exists) {
                DB::table('yearly_targets')->insert([
                    'puskesmas_id' => $puskesmas->id,
                    'disease_type' => $diseaseType,
                    'year' => $currentYear,
                    'target_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
                echo "✓ Created target: {$puskesmas->name} - $diseaseType" . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "=== SUMMARY ===" . PHP_EOL;
    echo "✓ Yearly targets created: $created" . PHP_EOL;
    echo "✓ Total expected: " . ($puskesmasList->count() * 2) . " (25 puskesmas × 2 disease types)" . PHP_EOL;
    
    // Verify
    $total = DB::table('yearly_targets')->where('year', $currentYear)->count();
    echo "✓ Total targets in database: $total" . PHP_EOL;
    
    echo PHP_EOL . "✅ YEARLY TARGETS SETUP COMPLETE!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
