<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
use Illuminate\\Support\\Facades\\DB;
$year = date('Y');
echo "Resetting target_count to 0 for year $year" . PHP_EOL;
$updated = DB::table('yearly_targets')->where('year',$year)->update(['target_count'=>0,'updated_at'=>now()]);
echo "Updated $updated rows" . PHP_EOL;