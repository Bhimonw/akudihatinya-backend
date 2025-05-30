<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardStatisticsController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all'; // Default 'all', bisa juga 'ht' atau 'dm'
        $user = Auth::user();

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Siapkan query untuk mengambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!$user->is_admin) {
            $puskesmasQuery->where('id', $user->puskesmas_id);
        }

        $puskesmasAll = $puskesmasQuery->get();

        // Siapkan data untuk dikirim ke frontend
        $data = [];

        foreach ($puskesmasAll as $puskesmas) {
            $puskesmasData = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Tambahkan data HT jika diperlukan
            if ($type === 'all' || $type === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->getHtStatistics($puskesmas->id, $year);

                $targetCount = $htTarget ? $htTarget->target_count : 0;

                $puskesmasData['ht'] = [
                    'target' => $targetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($htData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Tambahkan data DM jika diperlukan
            if ($type === 'all' || $type === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getDmStatistics($puskesmas->id, $year);

                $targetCount = $dmTarget ? $dmTarget->target_count : 0;

                $puskesmasData['dm'] = [
                    'target' => $targetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($dmData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $data[] = $puskesmasData;
        }

        // Urutkan data berdasarkan achievement_percentage
        usort($data, function ($a, $b) use ($type) {
            $aValue = $type === 'dm' ?
                ($a['dm']['achievement_percentage'] ?? 0) : ($a['ht']['achievement_percentage'] ?? 0);

            $bValue = $type === 'dm' ?
                ($b['dm']['achievement_percentage'] ?? 0) : ($b['ht']['achievement_percentage'] ?? 0);

            return $bValue <=> $aValue;
        });

        // Tambahkan ranking
        foreach ($data as $index => $item) {
            $data[$index]['ranking'] = $index + 1;
        }

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }

    /**
     * Get HT statistics for dashboard
     */
    protected function getHtStatistics($puskesmasId, $year)
    {
        // Get all patients with HT examinations in this year
        $patients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('htExaminations', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->with(['htExaminations' => function ($query) use ($year) {
                $query->where('year', $year)->orderBy('month');
            }])
            ->get();

        $totalPatients = $patients->count();
        $standardPatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'total' => 0,
                'standard' => 0
            ];
        }

        foreach ($patients as $patient) {
            $firstExamMonth = $patient->htExaminations->min('month');

            if ($firstExamMonth === null) continue;

            // Check if patient has examinations every month since first exam
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $patient->htExaminations
                    ->where('month', $m)
                    ->count() > 0;

                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }

            if ($isStandard) {
                $standardPatients++;
            }

            // Count monthly visits
            foreach ($patient->htExaminations as $exam) {
                $month = $exam->month;
                $monthlyData[$month]['total']++;
                if ($isStandard) {
                    $monthlyData[$month]['standard']++;
                }
            }
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get DM statistics for dashboard
     */
    protected function getDmStatistics($puskesmasId, $year)
    {
        // Get all patients with DM examinations in this year
        $patients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('dmExaminations', function ($query) use ($year) {
                $query->where('year', $year);
            })
            ->with(['dmExaminations' => function ($query) use ($year) {
                $query->where('year', $year)->orderBy('month');
            }])
            ->get();

        $totalPatients = $patients->count();
        $standardPatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'total' => 0,
                'standard' => 0
            ];
        }

        foreach ($patients as $patient) {
            $firstExamMonth = $patient->dmExaminations->min('month');

            if ($firstExamMonth === null) continue;

            // Check if patient has examinations every month since first exam
            $isStandard = true;
            for ($m = $firstExamMonth; $m <= 12; $m++) {
                $hasExam = $patient->dmExaminations
                    ->where('month', $m)
                    ->count() > 0;

                if (!$hasExam) {
                    $isStandard = false;
                    break;
                }
            }

            if ($isStandard) {
                $standardPatients++;
            }

            // Count monthly visits
            foreach ($patient->dmExaminations as $exam) {
                $month = $exam->month;
                $monthlyData[$month]['total']++;
                if ($isStandard) {
                    $monthlyData[$month]['standard']++;
                }
            }
        }

        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $standardPatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get HT statistics from cache
     */
    protected function getHtStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = DB::table('monthly_statistics_cache')
            ->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', 'ht');

        if ($month) {
            $query->where('month', $month);
        }

        $stats = $query->get();

        $totalPatients = 0;
        $totalStandard = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($stats as $stat) {
            $totalPatients += $stat->total_count;
            $totalStandard += $stat->standard_count;
            $malePatients += $stat->male_count;
            $femalePatients += $stat->female_count;

            $monthlyData[$stat->month] = [
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => 0 // Will be calculated later with target
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'total_standard' => $totalStandard,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get DM statistics from cache
     */
    protected function getDmStatisticsFromCache($puskesmasId, $year, $month = null)
    {
        $query = DB::table('monthly_statistics_cache')
            ->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', 'dm');

        if ($month) {
            $query->where('month', $month);
        }

        $stats = $query->get();

        $totalPatients = 0;
        $totalStandard = 0;
        $malePatients = 0;
        $femalePatients = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($stats as $stat) {
            $totalPatients += $stat->total_count;
            $totalStandard += $stat->standard_count;
            $malePatients += $stat->male_count;
            $femalePatients += $stat->female_count;

            $monthlyData[$stat->month] = [
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => 0 // Will be calculated later with target
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'total_standard' => $totalStandard,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'monthly_data' => $monthlyData
        ];
    }
}
