<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$tables=DB::select('SHOW TABLES');
foreach($tables as $row){ $table=array_values((array)$row)[0]; echo "== $table ==".PHP_EOL; $cols=DB::select("SHOW COLUMNS FROM $table"); foreach($cols as $c){ echo ' - '.$c->Field.' '.$c->Type.PHP_EOL;} echo PHP_EOL; }