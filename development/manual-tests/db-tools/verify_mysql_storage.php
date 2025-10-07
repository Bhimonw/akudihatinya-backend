<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$sizes = DB::select("SELECT table_name AS name, ROUND((data_length+index_length)/1024/1024,2) size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY size_mb DESC LIMIT 15");
foreach($sizes as $s){ echo str_pad($s->name,32).$s->size_mb.' MB'.PHP_EOL; }