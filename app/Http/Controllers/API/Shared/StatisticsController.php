<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Services\Statistics\StatisticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class StatisticsController extends Controller
{
    protected $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Display a listing of statistics.
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null;
        $diseaseType = $request->type ?? 'all';
        $perPage = $request->per_page ?? 15;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
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

        // Dapatkan data statistik dari service
        $result = $this->statisticsService->getStatistics($request, $year, $month, $diseaseType, $perPage);
        
        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'to' => 0,
                    'total' => 0,
                ],
            ], 400);
        }

        return response()->json([
            'data' => $result['paginator']->items(),
            'meta' => [
                'current_page' => $result['paginator']->currentPage(),
                'from' => $result['paginator']->firstItem(),
                'last_page' => $result['paginator']->lastPage(),
                'per_page' => $result['paginator']->perPage(),
                'to' => $result['paginator']->lastItem(),
                'total' => $result['paginator']->total(),
            ],
        ]);
    }

    /**
     * Dashboard statistics API untuk frontend
     */
    public function dashboardStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all'; // Default 'all', bisa juga 'ht' atau 'dm'

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        $data = $this->statisticsService->getDashboardStatistics($request, $year, $type);

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }

    /**
     * Export statistics to PDF
     */
    public function exportPdf(Request $request)
    {
        return $this->statisticsService->exportPdf($request);
    }

    /**
     * Export statistics to Excel
     */
    public function exportExcel(Request $request)
    {
        return $this->statisticsService->exportExcel($request);
    }
}