<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$year=date('Y');
$dm=DB::table('dm_examinations')->where('year',$year)->count();
$ht=DB::table('ht_examinations')->where('year',$year)->count();
echo "DM: $dm HT: $ht" . PHP_EOL;
if($dm==0 && $ht==0) echo "Clean".PHP_EOL; else echo "NOT clean".PHP_EOL;