<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
echo "(Legacy) Inspecting users table columns" . PHP_EOL;
$columns = DB::select("SHOW COLUMNS FROM users");
foreach($columns as $c){ echo $c->Field.' - '.$c->Type.PHP_EOL; }