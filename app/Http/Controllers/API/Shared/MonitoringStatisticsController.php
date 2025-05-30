<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\Puskesmas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
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
                return response()->json([
                    'message' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
                ], 400);
            }
        }

        $puskesmas = $puskesmasQuery->first();

        if (!$puskesmas) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
            ], 404);
        }

        // Get patient attendance data
        $patientData = $this->calculationService->getPatientAttendanceData(
            $puskesmas->id,
            $year,
            $month,
            $diseaseType
        );

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
