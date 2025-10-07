<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== CHECKING DATABASE ===" . PHP_EOL;
    echo "Database: " . DB::connection()->getDatabaseName() . PHP_EOL;
    echo PHP_EOL;
    
    // Check if users table exists and query it
    echo "=== USER STATISTICS ===" . PHP_EOL;
    
    $totalUsers = DB::table('users')->count();
    echo "Total Users: " . $totalUsers . PHP_EOL;
    
    $adminCount = DB::table('users')->where('role', 'admin')->count();
    echo "Admin Users: " . $adminCount . PHP_EOL;
    
    $puskesmasCount = DB::table('users')->where('role', 'puskesmas')->count();
    echo "Puskesmas Users: " . $puskesmasCount . PHP_EOL;
    
    echo PHP_EOL;
    echo "=== SAMPLE USERS (First 10) ===" . PHP_EOL;
    $users = DB::table('users')->limit(10)->get();
    
    foreach ($users as $user) {
        echo sprintf("ID: %d | Username: %s | Name: %s | Role: %s", 
            $user->id, 
            $user->username, 
            $user->name, 
            $user->role
        ) . PHP_EOL;
    }
    
    // Check pagination settings in UserController
    echo PHP_EOL;
    echo "=== PAGINATION ANALYSIS ===" . PHP_EOL;
    echo "Default per_page in UserController: 15" . PHP_EOL;
    echo "User can request custom per_page via query parameter" . PHP_EOL;
    echo "No maximum limit enforced (POTENTIAL ISSUE)" . PHP_EOL;
    
    // Test if 26 users can be retrieved
    if ($totalUsers >= 26) {
        echo PHP_EOL;
        echo "=== TEST: Retrieve ALL users (no pagination) ===" . PHP_EOL;
        $allUsers = DB::table('users')->get();
        echo "Successfully retrieved " . $allUsers->count() . " users without pagination" . PHP_EOL;
        
        echo PHP_EOL;
        echo "=== TEST: Retrieve 26 users with pagination ===" . PHP_EOL;
        $page1 = DB::table('users')->paginate(26);
        echo "Page 1 retrieved " . $page1->count() . " users" . PHP_EOL;
        echo "Total pages: " . $page1->lastPage() . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}
