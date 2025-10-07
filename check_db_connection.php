<?php

// Direct PDO connection to bypass Laravel
$host = '127.0.0.1';
$db   = 'akudihatinya_fresh';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    echo "=== DIRECT PDO CONNECTION TEST ===" . PHP_EOL;
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "✓ Connected to MySQL database: $db" . PHP_EOL . PHP_EOL;
    
    // Check tables
    echo "=== CHECKING TABLES ===" . PHP_EOL;
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables:" . PHP_EOL;
    foreach ($tables as $table) {
        echo "  - $table" . PHP_EOL;
    }
    echo PHP_EOL;
    
    // Check users table specifically
    if (in_array('users', $tables)) {
        echo "=== USERS TABLE INFO ===" . PHP_EOL;
        
        // Get table status
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = 'users'");
        $status = $stmt->fetch();
        echo "Engine: " . ($status['Engine'] ?? 'N/A') . PHP_EOL;
        echo "Rows: " . ($status['Rows'] ?? 'N/A') . PHP_EOL;
        echo PHP_EOL;
        
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $result = $stmt->fetch();
        $totalUsers = $result['total'];
        echo "✓ Total Users: " . $totalUsers . PHP_EOL;
        
        // Count by role
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $roles = $stmt->fetchAll();
        echo PHP_EOL . "Users by role:" . PHP_EOL;
        foreach ($roles as $role) {
            echo "  - {$role['role']}: {$role['count']}" . PHP_EOL;
        }
        
        // Get sample users
        echo PHP_EOL . "=== SAMPLE USERS (First 10) ===" . PHP_EOL;
        $stmt = $pdo->query("SELECT id, username, name, role FROM users LIMIT 10");
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            echo sprintf("ID: %d | Username: %s | Name: %s | Role: %s", 
                $user['id'], 
                $user['username'], 
                $user['name'], 
                $user['role']
            ) . PHP_EOL;
        }
        
        // Test pagination simulation
        echo PHP_EOL . "=== PAGINATION TESTS ===" . PHP_EOL;
        
        // Test 1: Default pagination (15 per page)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $total = $stmt->fetch()['total'];
        $perPage = 15;
        $totalPages = ceil($total / $perPage);
        echo "Test 1 - Default (15 per page): $totalPages pages for $total users" . PHP_EOL;
        
        // Test 2: Request 26 per page
        $perPage = 26;
        $totalPages = ceil($total / $perPage);
        echo "Test 2 - Custom (26 per page): $totalPages pages for $total users" . PHP_EOL;
        
        // Test 3: Get all users (simulate per_page=1000)
        $stmt = $pdo->query("SELECT id FROM users LIMIT 1000");
        $allUsers = $stmt->fetchAll();
        echo "Test 3 - Get all users: Retrieved " . count($allUsers) . " users" . PHP_EOL;
        
        if ($totalUsers >= 26) {
            echo PHP_EOL . "✓ Database has $totalUsers users (>= 26)" . PHP_EOL;
            echo "✓ Can retrieve all 26 users in single page request" . PHP_EOL;
        } else {
            echo PHP_EOL . "⚠ Database only has $totalUsers users (< 26)" . PHP_EOL;
        }
        
    } else {
        echo "✗ users table NOT FOUND!" . PHP_EOL;
    }
    
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . PHP_EOL;
}
