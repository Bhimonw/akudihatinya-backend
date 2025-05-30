<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DashboardStatisticsController extends Controller
{
    protected $calculationService;

    public function __construct(
        \App\Services\StatisticsCalculationService $calculationService
    ) {
        $this->calculationService = $calculationService;
    }

    /**
     * Dashboard statistics API untuk frontend
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all';
        $user = Auth::user();

        // Validasi tahun
        if (!is_numeric($year) || $year < 2000 || $year > 2100) {
            return response()->json([
                'message' => 'Parameter year tidak valid. Gunakan tahun antara 2000-2100.',
            ], 400);
        }

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Siapkan query untuk mengambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!$user->isAdmin()) {
            $puskesmasQuery->where('id', $user->puskesmas_id);
        }

        $puskesmas = $puskesmasQuery->first();

        if (!$puskesmas) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
            ], 404);
        }

        $data = [];

        // Get HT data if requested
        if ($type === 'all' || $type === 'ht') {
            $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->first();

            $htData = $this->calculationService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

            $htTargetCount = $htTarget ? $htTarget->target_count : 0;

            $data['ht'] = [
                'target' => $htTargetCount,
                'total_patients' => $htData['total_patients'],
                'achievement_percentage' => $htTargetCount > 0
                    ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
                    : 0,
                'standard_patients' => $htData['standard_patients'],
                'non_standard_patients' => $htData['non_standard_patients'],
                'male_patients' => $htData['male_patients'],
                'female_patients' => $htData['female_patients'],
                'monthly_data' => $htData['monthly_data'],
            ];
        }

        // Get DM data if requested
        if ($type === 'all' || $type === 'dm') {
            $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->first();

            $dmData = $this->calculationService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

            $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

            $data['dm'] = [
                'target' => $dmTargetCount,
                'total_patients' => $dmData['total_patients'],
                'achievement_percentage' => $dmTargetCount > 0
                    ? round(($dmData['standard_patients'] / $dmTargetCount) * 100, 2)
                    : 0,
                'standard_patients' => $dmData['standard_patients'],
                'non_standard_patients' => $dmData['non_standard_patients'],
                'male_patients' => $dmData['male_patients'],
                'female_patients' => $dmData['female_patients'],
                'monthly_data' => $dmData['monthly_data'],
            ];
        }

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }
} 