<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
use Illuminate\\Support\\Facades\\DB;
$currentYear = date('Y');
echo "Creating yearly targets for year $currentYear" . PHP_EOL;
$puskesmas = DB::table('puskesmas')->get();
$diseaseTypes = ['ht','dm'];
$created = 0;
foreach ($puskesmas as $p) {
  foreach ($diseaseTypes as $d) {
    $exists = DB::table('yearly_targets')->where(['puskesmas_id'=>$p->id,'disease_type'=>$d,'year'=>$currentYear])->exists();
    if(!$exists){
      DB::table('yearly_targets')->insert([
        'puskesmas_id'=>$p->id,
        'disease_type'=>$d,
        'year'=>$currentYear,
        'target_count'=>0,
        'created_at'=>now(),
        'updated_at'=>now(),
      ]);
      $created++;
      echo " + {$p->name} $d" . PHP_EOL;
    }
  }
}
echo "Done. Created $created records." . PHP_EOL;