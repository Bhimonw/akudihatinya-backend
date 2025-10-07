<?php

// Create new fresh database
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$newDbName = 'akudihatinya_fresh';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CREATING NEW FRESH DATABASE ===" . PHP_EOL . PHP_EOL;
    
    // Drop if exists
    echo "Dropping '$newDbName' if exists..." . PHP_EOL;
    $pdo->exec("DROP DATABASE IF EXISTS `$newDbName`");
    echo "✓ Dropped (if existed)" . PHP_EOL . PHP_EOL;
    
    // Create new database
    echo "Creating database '$newDbName'..." . PHP_EOL;
    $pdo->exec("CREATE DATABASE `$newDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created successfully" . PHP_EOL . PHP_EOL;
    
    echo "=== SUCCESS ===" . PHP_EOL;
    echo "Database '$newDbName' is ready!" . PHP_EOL;
    echo "Run: php artisan migrate --seed" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
