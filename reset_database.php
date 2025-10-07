<?php

// Script to completely reset database
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'akudihatinya';

try {
    // Connect without selecting database
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== RESETTING DATABASE ===" . PHP_EOL . PHP_EOL;
    
    // Drop database completely
    echo "Dropping database '$dbName'..." . PHP_EOL;
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "✓ Database dropped" . PHP_EOL . PHP_EOL;
    
    // Recreate database
    echo "Creating fresh database '$dbName'..." . PHP_EOL;
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created" . PHP_EOL . PHP_EOL;
    
    echo "=== DATABASE RESET COMPLETE ===" . PHP_EOL;
    echo "You can now run: php artisan migrate:fresh --seed" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
