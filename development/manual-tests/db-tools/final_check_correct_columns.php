<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
echo "Checking key tables column sets" . PHP_EOL;
$tables=['users','puskesmas','yearly_targets'];
foreach($tables as $t){ $cols=DB::select("SHOW COLUMNS FROM $t"); echo "Table $t:".PHP_EOL; foreach($cols as $c){ echo ' - '.$c->Field.PHP_EOL; } }