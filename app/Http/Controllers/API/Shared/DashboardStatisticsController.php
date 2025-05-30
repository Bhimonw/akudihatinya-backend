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

class DashboardStatisticsController extends Controller
{
    /**
     * Dashboard statistics API untuk frontend
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

        // Jika user bukan admin, filter data ke puskesmas user
        if (!$user->is_admin) {
            $userPuskesmas = $user->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Log this issue to debug
                Log::warning('Puskesmas user without puskesmas_id: ' . $user->id);

                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . $user->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);

                    // Update the user with the correct puskesmas_id for future requests
                    $user->update(['puskesmas_id' => $puskesmasWithSameName->id]);

                    Log::info('Updated user ' . $user->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    return response()->json([
                        'message' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
                        'data' => [],
                    ], 400);
                }
            }
        }

        $puskesmas = $puskesmasQuery->first();

        if (!$puskesmas) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
                'data' => [],
            ]);
        }

        $data = [
            'puskesmas_id' => $puskesmas->id,
            'puskesmas_name' => $puskesmas->name,
        ];

        // Ambil data HT jika diperlukan
        if ($type === 'all' || $type === 'ht') {
            $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->first();

            $htData = app(\App\Services\StatisticsCalculationService::class)
                ->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

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

        // Ambil data DM jika diperlukan
        if ($type === 'all' || $type === 'dm') {
            $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->first();

            $dmData = app(\App\Services\StatisticsCalculationService::class)
                ->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

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
            'data' => $data,
        ]);
    }
} 