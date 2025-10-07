<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
echo "DB: ".DB::connection()->getDatabaseName().PHP_EOL;
$total=DB::table('users')->count(); echo "Total Users: $total".PHP_EOL;
$admin=DB::table('users')->where('role','admin')->count(); echo "Admin: $admin".PHP_EOL;
$pusk=DB::table('users')->where('role','puskesmas')->count(); echo "Puskesmas: $pusk".PHP_EOL;