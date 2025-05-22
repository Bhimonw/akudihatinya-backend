<?php

namespace App\Http\Controllers\API\Statistic;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    /**
     * Display monitoring data for admin
     */
    public function adminMonitoring(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $diseaseType = $request->type ?? 'all';

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Ambil semua puskesmas
        $puskesmas = Puskesmas::all();
        
        $result = [];
        
        foreach ($puskesmas as $p) {
            // Ambil target tahunan
            $target = YearlyTarget::where('puskesmas_id', $p->id)
                ->where('year', $year)
                ->first();
            
            // Jika tidak ada target, skip
            if (!$target) {
                continue;
            }
            
            // Ambil data pasien
            $patientQuery = Patient::where('puskesmas_id', $p->id);
            
            // Filter berdasarkan jenis penyakit
            if ($diseaseType === 'ht') {
                $patientQuery->where('has_ht', true);
            } elseif ($diseaseType === 'dm') {
                $patientQuery->where('has_dm', true);
            }
            
            $totalPatients = $patientQuery->count();
            
            // Ambil data pemeriksaan
            $examinationCount = 0;
            $controlledCount = 0;
            
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htExams = HtExamination::where('puskesmas_id', $p->id)
                    ->where('year', $year)
                    ->get();
                
                $examinationCount += $htExams->count();
                $controlledCount += $htExams->filter(function ($exam) {
                    return $exam->isControlled();
                })->count();
            }
            
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmExams = DmExamination::where('puskesmas_id', $p->id)
                    ->where('year', $year)
                    ->get();
                
                $examinationCount += $dmExams->count();
                $controlledCount += $dmExams->filter(function ($exam) {
                    return $exam->isControlled();
                })->count();
            }
            
            // Hitung persentase
            $targetValue = 0;
            if ($diseaseType === 'ht') {
                $targetValue = $target->ht_target;
            } elseif ($diseaseType === 'dm') {
                $targetValue = $target->dm_target;
            } else {
                $targetValue = $target->ht_target + $target->dm_target;
            }
            
            $percentage = $targetValue > 0 ? ($totalPatients / $targetValue) * 100 : 0;
            $controlledPercentage = $examinationCount > 0 ? ($controlledCount / $examinationCount) * 100 : 0;
            
            $result[] = [
                'puskesmas_id' => $p->id,
                'puskesmas_name' => $p->name,
                'target' => $targetValue,
                'total_patients' => $totalPatients,
                'percentage' => round($percentage, 2),
                'examination_count' => $examinationCount,
                'controlled_count' => $controlledCount,
                'controlled_percentage' => round($controlledPercentage, 2),
            ];
        }
        
        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Display monitoring data for puskesmas
     */
    public function puskesmasMonitoring(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $diseaseType = $request->type ?? 'all';
        $puskesmasId = Auth::user()->puskesmas_id;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Jika user tidak terkait dengan puskesmas
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmas = Puskesmas::find($puskesmasId);
        
        // Ambil target tahunan
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->first();
        
        // Jika tidak ada target
        if (!$target) {
            return response()->json([
                'message' => 'Belum ada target yang ditetapkan untuk tahun ini.',
            ], 404);
        }
        
        // Ambil data pasien
        $patientQuery = Patient::where('puskesmas_id', $puskesmasId);
        
        // Filter berdasarkan jenis penyakit
        if ($diseaseType === 'ht') {
            $patientQuery->where('has_ht', true);
        } elseif ($diseaseType === 'dm') {
            $patientQuery->where('has_dm', true);
        }
        
        $totalPatients = $patientQuery->count();
        
        // Ambil data pemeriksaan
        $monthlyData = [];
        
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'month' => $m,
                'month_name' => Carbon::create()->month($m)->locale('id')->monthName,
                'examination_count' => 0,
                'controlled_count' => 0,
                'controlled_percentage' => 0,
            ];
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htExams = HtExamination::where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->get();
            
            foreach ($htExams as $exam) {
                $monthlyData[$exam->month]['examination_count']++;
                if ($exam->isControlled()) {
                    $monthlyData[$exam->month]['controlled_count']++;
                }
            }
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmExams = DmExamination::where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->get();
            
            foreach ($dmExams as $exam) {
                $monthlyData[$exam->month]['examination_count']++;
                if ($exam->isControlled()) {
                    $monthlyData[$exam->month]['controlled_count']++;
                }
            }
        }
        
        // Hitung persentase
        $totalExams = 0;
        $totalControlled = 0;
        
        foreach ($monthlyData as &$data) {
            $data['controlled_percentage'] = $data['examination_count'] > 0 
                ? round(($data['controlled_count'] / $data['examination_count']) * 100, 2) 
                : 0;
            
            $totalExams += $data['examination_count'];
            $totalControlled += $data['controlled_count'];
        }
        
        // Hitung persentase keseluruhan
        $targetValue = 0;
        if ($diseaseType === 'ht') {
            $targetValue = $target->ht_target;
        } elseif ($diseaseType === 'dm') {
            $targetValue = $target->dm_target;
        } else {
            $targetValue = $target->ht_target + $target->dm_target;
        }
        
        $percentage = $targetValue > 0 ? ($totalPatients / $targetValue) * 100 : 0;
        $controlledPercentage = $totalExams > 0 ? ($totalControlled / $totalExams) * 100 : 0;
        
        return response()->json([
            'data' => [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
                'target' => $targetValue,
                'total_patients' => $totalPatients,
                'percentage' => round($percentage, 2),
                'examination_count' => $totalExams,
                'controlled_count' => $totalControlled,
                'controlled_percentage' => round($controlledPercentage, 2),
                'monthly_data' => array_values($monthlyData),
            ],
        ]);
    }
}