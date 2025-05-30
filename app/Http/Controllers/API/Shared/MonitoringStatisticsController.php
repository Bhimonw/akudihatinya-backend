<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\Puskesmas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MonitoringStatisticsController extends Controller
{
    protected $calculationService;
    protected $exportService;

    public function __construct(
        \App\Services\StatisticsCalculationService $calculationService,
        \App\Services\StatisticsExportService $exportService
    ) {
        $this->calculationService = $calculationService;
        $this->exportService = $exportService;
    }

    /**
     * Export monitoring report
     */
    public function export(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? Carbon::now()->month;
        $diseaseType = $request->type ?? 'all';
        $format = $request->format ?? 'pdf';

        // Validasi tahun
        if (!is_numeric($year) || $year < 2000 || $year > 2100) {
            return response()->json([
                'message' => 'Parameter year tidak valid. Gunakan tahun antara 2000-2100.',
            ], 400);
        }

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Validasi format
        if (!in_array($format, ['pdf', 'excel'])) {
            return response()->json([
                'message' => 'Parameter format tidak valid. Gunakan pdf atau excel.',
            ], 400);
        }

        // Validasi bulan
        $month = intval($month);
        if ($month < 1 || $month > 12) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
                'data' => null
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Jika user bukan admin, filter data ke puskesmas user
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Log this issue to debug
                Log::warning('Puskesmas user without puskesmas_id: ' . Auth::user()->id);

                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . Auth::user()->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);

                    // Update the user with the correct puskesmas_id for future requests
                    Auth::user()->update(['puskesmas_id' => $puskesmasWithSameName->id]);

                    Log::info('Updated user ' . Auth::user()->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
                        'data' => null
                    ], 400);
                }
            }
        }

        $puskesmas = $puskesmasQuery->first();

        if (!$puskesmas) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
            ], 404);
        }

        // Get patient attendance data
        $cacheKey = "monitoring_statistics_{$puskesmas->id}_{$year}_{$month}_{$diseaseType}";
        $patientData = Cache::remember($cacheKey, now()->addHours(24), function () use ($puskesmas, $year, $month, $diseaseType) {
            return $this->calculationService->getPatientAttendanceData(
                $puskesmas->id,
                $year,
                $month,
                $diseaseType
            );
        });

        // Generate filename
        $filename = sprintf(
            'monitoring_%s_%s_%s_%02d.%s',
            $puskesmas->name,
            $diseaseType,
            $year,
            $month,
            $format
        );

        // Export based on format
        if ($format === 'pdf') {
            return $this->exportService->exportMonitoringToPdf(
                $patientData,
                $puskesmas,
                $year,
                $month,
                $diseaseType,
                $filename
            );
        } else {
            return $this->exportService->exportMonitoringToExcel(
                $patientData,
                $puskesmas,
                $year,
                $month,
                $diseaseType,
                $filename
            );
        }
    }
}
