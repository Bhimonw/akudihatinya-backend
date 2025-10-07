<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB; use App\Models\Puskesmas;
$year=2025; $type='dm';
$totalPusk=Puskesmas::count();
$summary=[]; foreach(['dm','ht'] as $d){ $target=DB::table('yearly_targets')->where(['disease_type'=>$d,'year'=>$year])->sum('target_count'); $total=DB::table($d.'_examinations')->where('year',$year)->distinct('patient_id')->count('patient_id'); $standard=DB::table($d.'_examinations')->where(['year'=>$year,'is_standard_patient'=>1])->distinct('patient_id')->count('patient_id'); $non=DB::table($d.'_examinations')->where(['year'=>$year,'is_standard_patient'=>0])->distinct('patient_id')->count('patient_id'); $ach=$target>0?($standard/$target*100):0; $summary[$d]=['target'=>(string)$target,'total_patients'=>(string)$total,'standard_patients'=>(string)$standard,'non_standard_patients'=>(string)$non,'achievement_percentage'=>number_format($ach,1)]; }
echo json_encode(['year'=>(string)$year,'disease_type'=>$type,'total_puskesmas'=>$totalPusk,'summary'=>$summary],JSON_PRETTY_PRINT).PHP_EOL;