<?php

// Verify MySQL connection and data location
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

try {
    echo "=== VERIFYING MYSQL CONNECTION ===" . PHP_EOL . PHP_EOL;
    
    // 1. Check database configuration
    echo "📋 DATABASE CONFIGURATION:" . PHP_EOL;
    $connection = Config::get('database.default');
    $host = Config::get('database.connections.' . $connection . '.host');
    $port = Config::get('database.connections.' . $connection . '.port');
    $database = Config::get('database.connections.' . $connection . '.database');
    $username = Config::get('database.connections.' . $connection . '.username');
    $driver = Config::get('database.connections.' . $connection . '.driver');
    
    echo "  Connection Type: $connection" . PHP_EOL;
    echo "  Driver: $driver" . PHP_EOL;
    echo "  Host: $host" . PHP_EOL;
    echo "  Port: $port" . PHP_EOL;
    echo "  Database: $database" . PHP_EOL;
    echo "  Username: $username" . PHP_EOL;
    echo PHP_EOL;
    
    // 2. Test actual connection
    echo "🔌 TESTING CONNECTION:" . PHP_EOL;
    $pdo = DB::connection()->getPdo();
    $dbName = DB::connection()->getDatabaseName();
    echo "  ✓ Connected to: $dbName" . PHP_EOL;
    
    // Get MySQL version
    $version = DB::select('SELECT VERSION() as version')[0]->version;
    echo "  ✓ MySQL Version: $version" . PHP_EOL;
    echo PHP_EOL;
    
    // 3. Verify tables exist in MySQL
    echo "📊 TABLES IN MYSQL DATABASE '$database':" . PHP_EOL;
    $tables = DB::select('SHOW TABLES');
    $tableCount = count($tables);
    echo "  Total Tables: $tableCount" . PHP_EOL;
    echo PHP_EOL;
    
    foreach ($tables as $table) {
        $tableName = $table->{'Tables_in_' . $database};
        echo "  - $tableName" . PHP_EOL;
    }
    echo PHP_EOL;
    
    // 4. Verify data in key tables
    echo "✅ DATA VERIFICATION IN MYSQL:" . PHP_EOL;
    
    // Users
    $userCount = DB::table('users')->count();
    echo "  Users: $userCount records" . PHP_EOL;
    
    // Puskesmas
    $puskesmasCount = DB::table('puskesmas')->count();
    echo "  Puskesmas: $puskesmasCount records" . PHP_EOL;
    
    // Yearly Targets
    $targetCount = DB::table('yearly_targets')->count();
    echo "  Yearly Targets: $targetCount records" . PHP_EOL;
    
    // Patients
    $patientCount = DB::table('patients')->count();
    echo "  Patients: $patientCount records" . PHP_EOL;
    
    // Examinations
    $htExamCount = DB::table('ht_examinations')->count();
    $dmExamCount = DB::table('dm_examinations')->count();
    echo "  HT Examinations: $htExamCount records" . PHP_EOL;
    echo "  DM Examinations: $dmExamCount records" . PHP_EOL;
    echo PHP_EOL;
    
    // 5. Show sample data with MySQL query
    echo "📝 SAMPLE DATA FROM MYSQL:" . PHP_EOL;
    echo PHP_EOL;
    
    echo "Users (first 3):" . PHP_EOL;
    $users = DB::select('SELECT id, username, name, role FROM users LIMIT 3');
    foreach ($users as $user) {
        echo "  ID: $user->id | Username: $user->username | Name: $user->name | Role: $user->role" . PHP_EOL;
    }
    echo PHP_EOL;
    
    echo "Yearly Targets (first 5):" . PHP_EOL;
    $targets = DB::select('
        SELECT yt.id, p.name as puskesmas_name, yt.disease_type, yt.year, yt.target_count
        FROM yearly_targets yt
        JOIN puskesmas p ON yt.puskesmas_id = p.id
        LIMIT 5
    ');
    foreach ($targets as $target) {
        echo "  ID: $target->id | Puskesmas: $target->puskesmas_name | Type: $target->disease_type | Year: $target->year | Target: $target->target_count" . PHP_EOL;
    }
    echo PHP_EOL;
    
    // 6. Check table engines (should be InnoDB for MySQL)
    echo "🔧 TABLE ENGINES:" . PHP_EOL;
    $tableStatus = DB::select('SHOW TABLE STATUS');
    $innoDB = 0;
    $other = 0;
    
    foreach ($tableStatus as $status) {
        if ($status->Engine === 'InnoDB') {
            $innoDB++;
        } else {
            $other++;
            echo "  ⚠ {$status->Name}: {$status->Engine}" . PHP_EOL;
        }
    }
    
    echo "  ✓ InnoDB tables: $innoDB" . PHP_EOL;
    if ($other > 0) {
        echo "  ⚠ Other engines: $other" . PHP_EOL;
    }
    echo PHP_EOL;
    
    // 7. Final verification
    echo "=== FINAL VERIFICATION ===" . PHP_EOL;
    
    if ($driver === 'mysql' && $userCount > 0 && $puskesmasCount > 0) {
        echo "✅ CONFIRMED: Data is stored in MySQL database!" . PHP_EOL;
        echo "✅ Database: $database" . PHP_EOL;
        echo "✅ Server: $host:$port" . PHP_EOL;
        echo "✅ Tables: $tableCount" . PHP_EOL;
        echo "✅ Total Records: " . ($userCount + $puskesmasCount + $targetCount + $patientCount + $htExamCount + $dmExamCount) . PHP_EOL;
    } else {
        echo "⚠ WARNING: Please check database configuration!" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
