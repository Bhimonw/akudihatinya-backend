<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use App\Services\StatisticsDataService;
use App\Services\StatisticsExportService;
use App\Services\MonitoringReportService;
use App\Repositories\Interfaces\PuskesmasRepositoryInterface;
use App\Traits\StatisticsValidationTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    use StatisticsValidationTrait;

    protected $statisticsService;
    protected $statisticsDataService;
    protected $statisticsExportService;
    protected $monitoringReportService;
    protected $puskesmasRepository;

    public function __construct(
        StatisticsService $statisticsService,
        StatisticsDataService $statisticsDataService,
        StatisticsExportService $statisticsExportService,
        MonitoringReportService $monitoringReportService,
        PuskesmasRepositoryInterface $puskesmasRepository
    ) {
        $this->statisticsService = $statisticsService;
        $this->statisticsDataService = $statisticsDataService;
        $this->statisticsExportService = $statisticsExportService;
        $this->monitoringReportService = $monitoringReportService;
        $this->puskesmasRepository = $puskesmasRepository;
    }

    /**
     * Get statistics data with pagination
     */
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $validation = $this->validateStatisticsRequest($request);
        if ($validation->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        // Get request parameters
        $year = $request->year ?? date('Y');
        $month = $request->month;
        $diseaseType = $request->disease_type ?? 'all';
        $perPage = $request->per_page ?? 15;

        // Get puskesmas data with filters
        $puskesmasAll = $this->puskesmasRepository->getFilteredPuskesmas($request);

        if ($puskesmasAll->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
                'data' => []
            ]);
        }

        // Get statistics data using StatisticsDataService
        $statistics = $this->statisticsDataService->getConsistentStatisticsData(
            $puskesmasAll,
            $year,
            $month,
            $diseaseType
        );

        // Paginate the results
        $paginatedData = $this->statisticsDataService->paginateStatistics($statistics, $perPage, $request->page ?? 1);

        return response()->json($paginatedData);
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardStatistics(Request $request): JsonResponse
    {
        // This method still uses old logic and needs to be refactored
        // TODO: Refactor to use StatisticsDataService
        return response()->json([
            'message' => 'Dashboard statistics endpoint needs refactoring'
        ], 501);
    }

    /**
     * Export statistics data
     */
    public function exportStatistics(Request $request)
    {
        return $this->statisticsExportService->exportStatistics($request);
    }

    /**
     * Generate monitoring report
     */
    public function monitoringReport(Request $request)
    {
        return $this->monitoringReportService->generateReport($request);
    }

    /**
     * Get available years
     */
    public function getAvailableYears(): JsonResponse
    {
        $years = $this->statisticsDataService->getAvailableYears();
        return response()->json($years);
    }

    /**
     * Get puskesmas list
     */
    public function getPuskesmasList(): JsonResponse
    {
        $puskesmas = $this->puskesmasRepository->getAllPuskesmas();
        return response()->json($puskesmas);
    }

    /**
     * Get export options
     */
    public function getExportOptions(): JsonResponse
    {
        return response()->json([
            'formats' => ['pdf', 'excel'],
            'table_types' => ['monthly', 'quarterly'],
            'disease_types' => ['all', 'ht', 'dm']
        ]);
    }

    /**
     * Export puskesmas PDF
     */
    public function exportPuskesmasPdf(Request $request)
    {
        $validation = $this->validatePuskesmasExportRequest($request);
        if ($validation->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        return $this->statisticsExportService->exportPuskesmasPdf(
            $request->puskesmas_id,
            $request->year,
            $request->month,
            $request->disease_type ?? 'all'
        );
    }

    /**
     * Export puskesmas quarterly PDF
     */
    public function exportPuskesmasQuarterlyPdf(Request $request)
    {
        $validation = $this->validatePuskesmasQuarterlyExportRequest($request);
        if ($validation->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        return $this->statisticsExportService->exportPuskesmasQuarterlyPdf(
            $request->puskesmas_id,
            $request->year,
            $request->disease_type ?? 'all'
        );
    }

    /**
     * Get admin statistics with enhanced features
     */
    public function adminStatistics(Request $request): JsonResponse
    {
        // Validate request parameters
        $validation = $this->validateAdminStatisticsRequest($request);
        if ($validation->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        // Get request parameters
        $year = $request->year ?? date('Y');
        $month = $request->month;
        $diseaseType = $request->disease_type ?? 'all';
        $perPage = $request->per_page ?? 15;

        // Get paginated puskesmas with filters
        $puskesmasQuery = $this->puskesmasRepository->getFilteredPuskesmasQuery($request);
        $paginatedPuskesmas = $puskesmasQuery->paginate($perPage);

        if ($paginatedPuskesmas->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'to' => 0,
                    'total' => 0,
                ],
            ]);
        }

        // Get statistics data for paginated puskesmas
        $statistics = $this->statisticsDataService->getConsistentStatisticsData(
            $paginatedPuskesmas,
            $year,
            $month,
            $diseaseType
        );

        // Format data for admin view
        $formattedData = $this->statisticsDataService->formatDataForAdmin($statistics, $diseaseType);

        // Calculate summary statistics for all puskesmas
        $allPuskesmasIds = $this->puskesmasRepository->getAllPuskesmasIds();
        $summary = $this->calculateSummaryStatistics($formattedData, $diseaseType);

        return response()->json([
            'year' => $year,
            'disease_type' => $diseaseType,
            'month' => $month,
            'total_puskesmas' => $this->puskesmasRepository->getTotalCount(),
            'summary' => $summary,
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginatedPuskesmas->currentPage(),
                'from' => $paginatedPuskesmas->firstItem(),
                'last_page' => $paginatedPuskesmas->lastPage(),
                'per_page' => $paginatedPuskesmas->perPage(),
                'to' => $paginatedPuskesmas->lastItem(),
                'total' => $paginatedPuskesmas->total(),
            ],
            'all_puskesmas' => $this->puskesmasRepository->getAllPuskesmas(['id', 'name'])
        ]);
    }

    /**
     * Calculate summary statistics for admin view
     */
    private function calculateSummaryStatistics($data, $diseaseType)
    {
        $summary = [];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htTargets = collect($data)->sum('ht.target');
            $htPatients = collect($data)->sum('ht.total_patients');
            $htStandardPatients = collect($data)->sum('ht.standard_patients');
            $htAchievement = $htTargets > 0 ? round(($htStandardPatients / $htTargets) * 100, 2) : 0;

            $summary['ht'] = [
                'total_target' => $htTargets,
                'total_patients' => $htPatients,
                'total_standard_patients' => $htStandardPatients,
                'average_achievement_percentage' => $htAchievement
            ];
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmTargets = collect($data)->sum('dm.target');
            $dmPatients = collect($data)->sum('dm.total_patients');
            $dmStandardPatients = collect($data)->sum('dm.standard_patients');
            $dmAchievement = $dmTargets > 0 ? round(($dmStandardPatients / $dmTargets) * 100, 2) : 0;

            $summary['dm'] = [
                'total_target' => $dmTargets,
                'total_patients' => $dmPatients,
                'total_standard_patients' => $dmStandardPatients,
                'average_achievement_percentage' => $dmAchievement
            ];
        }

        return $summary;
    }
}
