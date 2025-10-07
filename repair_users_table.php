<?php

$host = '127.0.0.1';
$db   = 'akudihatinya';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== REPAIRING DATABASE TABLES ===" . PHP_EOL . PHP_EOL;
    
    // Select database
    $pdo->exec("USE `$db`");
    
    // Try to repair users table
    echo "Attempting to repair users table..." . PHP_EOL;
    try {
        $stmt = $pdo->query("REPAIR TABLE users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        print_r($result);
    } catch (PDOException $e) {
        echo "Repair failed: " . $e->getMessage() . PHP_EOL;
    }
    
    echo PHP_EOL;
    
    // Try to check table
    echo "Checking users table integrity..." . PHP_EOL;
    try {
        $stmt = $pdo->query("CHECK TABLE users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        print_r($result);
    } catch (PDOException $e) {
        echo "Check failed: " . $e->getMessage() . PHP_EOL;
    }
    
    echo PHP_EOL;
    
    // Show create table to see structure
    echo "Getting table structure..." . PHP_EOL;
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Table exists with structure." . PHP_EOL;
        echo PHP_EOL;
        echo $result['Create Table'] . PHP_EOL;
    } catch (PDOException $e) {
        echo "Show create failed: " . $e->getMessage() . PHP_EOL;
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
