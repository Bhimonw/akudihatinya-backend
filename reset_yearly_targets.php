<?php

// Script to reset all yearly targets to 0
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== RESETTING YEARLY TARGETS ===" . PHP_EOL . PHP_EOL;
    
    $currentYear = date('Y');
    
    // Update all yearly targets to 0 for current year
    echo "Resetting all yearly targets to 0 for year $currentYear..." . PHP_EOL;
    
    $updated = DB::table('yearly_targets')
        ->where('year', $currentYear)
        ->update([
            'target_count' => 0,
            'updated_at' => now()
        ]);
    
    echo "✓ Updated $updated yearly target records to 0" . PHP_EOL;
    
    // Show breakdown by disease type
    echo PHP_EOL . "Breakdown:" . PHP_EOL;
    
    $htTargets = DB::table('yearly_targets')
        ->where('year', $currentYear)
        ->where('disease_type', 'ht')
        ->count();
    
    $dmTargets = DB::table('yearly_targets')
        ->where('year', $currentYear)
        ->where('disease_type', 'dm')
        ->count();
    
    echo "  - HT targets: $htTargets (all set to 0)" . PHP_EOL;
    echo "  - DM targets: $dmTargets (all set to 0)" . PHP_EOL;
    
    // Show sample targets
    echo PHP_EOL . "Sample Targets:" . PHP_EOL;
    $samples = DB::table('yearly_targets')
        ->join('puskesmas', 'yearly_targets.puskesmas_id', '=', 'puskesmas.id')
        ->where('yearly_targets.year', $currentYear)
        ->select('puskesmas.name', 'yearly_targets.disease_type', 'yearly_targets.target_count')
        ->limit(5)
        ->get();
    
    foreach ($samples as $sample) {
        echo "  - {$sample->name} ({$sample->disease_type}): {$sample->target_count}" . PHP_EOL;
    }
    
    echo PHP_EOL . "✅ ALL YEARLY TARGETS RESET TO 0!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
