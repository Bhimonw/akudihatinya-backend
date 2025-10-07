<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$year=date('Y');
foreach(['dm','ht'] as $d){ $table=$d.'_examinations'; $count=DB::table($table)->where('year',$year)->count(); echo strtoupper($d).": $count records".PHP_EOL; $sample=DB::table($table)->where('year',$year)->limit(3)->get(); foreach($sample as $s){ echo '  - ID '.$s->id.' patient '.$s->patient_id.PHP_EOL; } }