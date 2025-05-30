<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExportStatisticsController extends Controller
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
     * Export statistics to PDF or Excel
     */
    public function export(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null;
        $diseaseType = $request->type ?? 'all';
        $format = $request->format ?? 'pdf';
        $isRecap = $request->recap ?? false;
        $reportType = $request->report_type ?? 'monthly';

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

        // Validasi bulan jika diisi
        if ($month !== null) {
            $month = intval($month);
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
                ], 400);
            }
        }

        // Validasi report_type
        if (!in_array($reportType, ['monthly', 'yearly'])) {
            return response()->json([
                'message' => 'Parameter report_type tidak valid. Gunakan monthly atau yearly.',
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
                        'message' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
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

        $statistics = [];

        // Ambil data dari cache
        $cacheKey = "export_statistics_{$puskesmas->id}_{$year}" . ($month ? "_{$month}" : "") . "_{$diseaseType}";
        $statistics = Cache::remember($cacheKey, now()->addHours(24), function () use ($puskesmas, $year, $month, $diseaseType) {
            $stats = [];

            // Ambil data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->calculationService->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                $htTargetCount = $htTarget ? $htTarget->target_count : 0;

                $stats['ht'] = [
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
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->calculationService->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

                $stats['dm'] = [
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

            return $stats;
        });

        // Generate filename
        $filename = sprintf(
            'statistics_%s_%s_%s_%s.%s',
            $puskesmas->name,
            $diseaseType,
            $year,
            $month ? sprintf('%02d', $month) : 'all',
            $format
        );

        // Export based on format
        if ($format === 'pdf') {
            return $this->exportService->exportToPdf(
                $statistics,
                $year,
                $month,
                $diseaseType,
                $filename,
                $isRecap,
                $reportType
            );
        } else {
            return $this->exportService->exportToExcel(
                $statistics,
                $year,
                $month,
                $diseaseType,
                $filename,
                $isRecap,
                $reportType
            );
        }
    }

    /**
     * Export HT statistics specifically
     */
    public function exportHt(Request $request)
    {
        $request->merge(['type' => 'ht']);
        return $this->export($request);
    }

    /**
     * Export DM statistics specifically
     */
    public function exportDm(Request $request)
    {
        $request->merge(['type' => 'dm']);
        return $this->export($request);
    }
}
