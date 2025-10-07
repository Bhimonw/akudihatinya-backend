<?php

// Script to drop all tables manually
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'akudihatinya';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DROPPING ALL TABLES ===" . PHP_EOL . PHP_EOL;
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✓ Foreign key checks disabled" . PHP_EOL;
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($tables) . " tables" . PHP_EOL . PHP_EOL;
    
    // Drop each table
    foreach ($tables as $table) {
        echo "Dropping table: $table..." . PHP_EOL;
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "  ✓ Dropped" . PHP_EOL;
        } catch (PDOException $e) {
            echo "  ✗ Error: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo PHP_EOL . "✓ Foreign key checks re-enabled" . PHP_EOL;
    
    // Verify
    $stmt = $pdo->query("SHOW TABLES");
    $remaining = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo PHP_EOL . "=== VERIFICATION ===" . PHP_EOL;
    echo "Remaining tables: " . count($remaining) . PHP_EOL;
    
    if (count($remaining) === 0) {
        echo PHP_EOL . "✓✓✓ ALL TABLES DROPPED SUCCESSFULLY ✓✓✓" . PHP_EOL;
        echo "You can now run: php artisan migrate --seed" . PHP_EOL;
    } else {
        echo PHP_EOL . "⚠ Some tables remain:" . PHP_EOL;
        foreach ($remaining as $table) {
            echo "  - $table" . PHP_EOL;
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
