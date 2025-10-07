<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
echo "WARNING: This script deletes ALL examinations and cache." . PHP_EOL;
$confirm = readline("Type YES to continue: ");
if($confirm!=='YES'){ echo "Aborted." . PHP_EOL; exit; }
$ht = DB::table('ht_examinations')->delete();
$dm = DB::table('dm_examinations')->delete();
$cache = DB::table('monthly_statistics_cache')->delete();
$year = date('Y');
$puskesmas = DB::table('puskesmas')->get();
$types=['ht','dm']; $created=0;$existing=0;
foreach($puskesmas as $p){ foreach($types as $t){ $ex=DB::table('yearly_targets')->where(['puskesmas_id'=>$p->id,'disease_type'=>$t,'year'=>$year])->first(); if(!$ex){ DB::table('yearly_targets')->insert(['puskesmas_id'=>$p->id,'disease_type'=>$t,'year'=>$year,'target_count'=>0,'created_at'=>now(),'updated_at'=>now()]); $created++; } else { $existing++; } } }
echo "Deleted HT: $ht, DM: $dm, Cache: $cache | Targets created: $created existing: $existing" . PHP_EOL;