<?php

// Check table structure
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "=== CHECKING DM_EXAMINATIONS TABLE STRUCTURE ===" . PHP_EOL . PHP_EOL;
    
    // Get column names
    $columns = Schema::getColumnListing('dm_examinations');
    
    echo "Columns in dm_examinations table:" . PHP_EOL;
    foreach ($columns as $column) {
        echo "  - $column" . PHP_EOL;
    }
    
    echo PHP_EOL;
    echo "Total columns: " . count($columns) . PHP_EOL;
    echo PHP_EOL;
    
    // Check if is_standard_therapy column exists
    if (in_array('is_standard_therapy', $columns)) {
        echo "✓ Column 'is_standard_therapy' EXISTS" . PHP_EOL;
    } else {
        echo "✗ Column 'is_standard_therapy' NOT FOUND" . PHP_EOL;
        echo "  Available therapy-related columns:" . PHP_EOL;
        foreach ($columns as $column) {
            if (stripos($column, 'standard') !== false || stripos($column, 'therapy') !== false) {
                echo "    - $column" . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL;
    
    // Also check HT examinations
    echo "=== CHECKING HT_EXAMINATIONS TABLE STRUCTURE ===" . PHP_EOL . PHP_EOL;
    
    $htColumns = Schema::getColumnListing('ht_examinations');
    
    echo "Columns in ht_examinations table:" . PHP_EOL;
    foreach ($htColumns as $column) {
        echo "  - $column" . PHP_EOL;
    }
    
    echo PHP_EOL;
    
    // Check if is_standard_therapy column exists
    if (in_array('is_standard_therapy', $htColumns)) {
        echo "✓ Column 'is_standard_therapy' EXISTS in HT table" . PHP_EOL;
    } else {
        echo "✗ Column 'is_standard_therapy' NOT FOUND in HT table" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
