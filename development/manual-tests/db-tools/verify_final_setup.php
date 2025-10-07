<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
echo "Users: ".DB::table('users')->count().PHP_EOL;
echo "Puskesmas: ".DB::table('puskesmas')->count().PHP_EOL;
echo "Yearly Targets: ".DB::table('yearly_targets')->count().PHP_EOL;