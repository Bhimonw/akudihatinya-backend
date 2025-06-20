<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use App\Services\StatisticsDataService;
use App\Services\StatisticsExportService;
use App\Services\MonitoringReportService;
use App\Services\RealTimeStatisticsService;
use App\Repositories\PuskesmasRepositoryInterface;
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
    protected $realTimeStatisticsService;

    public function __construct(
        StatisticsService $statisticsService,
        StatisticsDataService $statisticsDataService,
        StatisticsExportService $statisticsExportService,
        MonitoringReportService $monitoringReportService,
        PuskesmasRepositoryInterface $puskesmasRepository,
        RealTimeStatisticsService $realTimeStatisticsService
    ) {
        $this->statisticsService = $statisticsService;
        $this->statisticsDataService = $statisticsDataService;
        $this->statisticsExportService = $statisticsExportService;
        $this->monitoringReportService = $monitoringReportService;
        $this->puskesmasRepository = $puskesmasRepository;
        $this->realTimeStatisticsService = $realTimeStatisticsService;
    }

    /**
     * Get statistics data with pagination
     */
    public function index(Request $request): JsonResponse
    {    // Validate request parameters
        $validationResponse = $this->validateIndexRequest($request);
        if ($validationResponse !== null) {
            return $validationResponse;
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
     * Get dashboard statistics using real-time service
     */
    public function dashboardStatistics(Request $request): JsonResponse
    {
        // Validate request parameters
        $validation = $this->validateDashboardStatisticsRequest($request);
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

        // Get filtered puskesmas based on user role and bearer token
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

        // Get fast dashboard statistics using real-time service
        $formattedData = [];
        $totalHtTarget = 0;
        $totalDmTarget = 0;
        $totalHtPatients = 0;
        $totalDmPatients = 0;
        $totalHtStandard = 0;
        $totalDmStandard = 0;

        foreach ($paginatedPuskesmas as $puskesmas) {
            $puskesmasData = [
                'id' => $puskesmas->id,
                'name' => $puskesmas->name,
            ];

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'ht', $year);
                $htTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'ht')->value('target_count') ?? 0;
                
                $puskesmasData['ht'] = [
                    'target' => $htTarget,
                    'total_patients' => $htData['summary']['total_count'],
                    'standard_patients' => $htData['summary']['standard_count'],
                    'achievement_percentage' => $htTarget > 0 ? round(($htData['summary']['standard_count'] / $htTarget) * 100, 2) : 0
                ];

                $totalHtTarget += $htTarget;
                $totalHtPatients += $htData['summary']['total_count'];
                $totalHtStandard += $htData['summary']['standard_count'];
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'dm', $year);
                $dmTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'dm')->value('target_count') ?? 0;
                
                $puskesmasData['dm'] = [
                    'target' => $dmTarget,
                    'total_patients' => $dmData['summary']['total_count'],
                    'standard_patients' => $dmData['summary']['standard_count'],
                    'achievement_percentage' => $dmTarget > 0 ? round(($dmData['summary']['standard_count'] / $dmTarget) * 100, 2) : 0
                ];

                $totalDmTarget += $dmTarget;
                $totalDmPatients += $dmData['summary']['total_count'];
                $totalDmStandard += $dmData['summary']['standard_count'];
            }

            $formattedData[] = $puskesmasData;
        }

        // Calculate summary statistics
        $summary = [];
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $summary['ht'] = [
                'total_target' => $totalHtTarget,
                'total_patients' => $totalHtPatients,
                'total_standard_patients' => $totalHtStandard,
                'average_achievement_percentage' => $totalHtTarget > 0 ? round(($totalHtStandard / $totalHtTarget) * 100, 2) : 0
            ];
        }
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $summary['dm'] = [
                'total_target' => $totalDmTarget,
                'total_patients' => $totalDmPatients,
                'total_standard_patients' => $totalDmStandard,
                'average_achievement_percentage' => $totalDmTarget > 0 ? round(($totalDmStandard / $totalDmTarget) * 100, 2) : 0
            ];
        }

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
     * Get admin statistics with enhanced features using real-time service
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

        // Get fast admin statistics using real-time service
        $formattedData = [];
        $totalHtTarget = 0;
        $totalDmTarget = 0;
        $totalHtPatients = 0;
        $totalDmPatients = 0;
        $totalHtStandard = 0;
        $totalDmStandard = 0;

        foreach ($paginatedPuskesmas as $puskesmas) {
            $puskesmasData = [
                'id' => $puskesmas->id,
                'name' => $puskesmas->name,
            ];

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'ht', $year);
                $htTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'ht')->value('target_count') ?? 0;
                
                $puskesmasData['ht'] = [
                    'target' => $htTarget,
                    'total_patients' => $htData['summary']['total_count'],
                    'standard_patients' => $htData['summary']['standard_count'],
                    'achievement_percentage' => $htTarget > 0 ? round(($htData['summary']['standard_count'] / $htTarget) * 100, 2) : 0,
                    'monthly_data' => $htData['monthly_data']
                ];

                $totalHtTarget += $htTarget;
                $totalHtPatients += $htData['summary']['total_count'];
                $totalHtStandard += $htData['summary']['standard_count'];
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'dm', $year);
                $dmTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'dm')->value('target_count') ?? 0;
                
                $puskesmasData['dm'] = [
                    'target' => $dmTarget,
                    'total_patients' => $dmData['summary']['total_count'],
                    'standard_patients' => $dmData['summary']['standard_count'],
                    'achievement_percentage' => $dmTarget > 0 ? round(($dmData['summary']['standard_count'] / $dmTarget) * 100, 2) : 0,
                    'monthly_data' => $dmData['monthly_data']
                ];

                $totalDmTarget += $dmTarget;
                $totalDmPatients += $dmData['summary']['total_count'];
                $totalDmStandard += $dmData['summary']['standard_count'];
            }

            $formattedData[] = $puskesmasData;
        }

        // Calculate summary statistics for all puskesmas (not just paginated)
        $allPuskesmas = $this->puskesmasRepository->getAllPuskesmas();
        $allHtTarget = 0;
        $allDmTarget = 0;
        $allHtPatients = 0;
        $allDmPatients = 0;
        $allHtStandard = 0;
        $allDmStandard = 0;

        foreach ($allPuskesmas as $puskesmas) {
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'ht', $year);
                $htTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'ht')->value('target_count') ?? 0;
                
                $allHtTarget += $htTarget;
                $allHtPatients += $htData['summary']['total_count'];
                $allHtStandard += $htData['summary']['standard_count'];
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'dm', $year);
                $dmTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'dm')->value('target_count') ?? 0;
                
                $allDmTarget += $dmTarget;
                $allDmPatients += $dmData['summary']['total_count'];
                $allDmStandard += $dmData['summary']['standard_count'];
            }
        }

        $summary = [];
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $summary['ht'] = [
                'total_target' => $allHtTarget,
                'total_patients' => $allHtPatients,
                'total_standard_patients' => $allHtStandard,
                'average_achievement_percentage' => $allHtTarget > 0 ? round(($allHtStandard / $allHtTarget) * 100, 2) : 0
            ];
        }
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $summary['dm'] = [
                'total_target' => $allDmTarget,
                'total_patients' => $allDmPatients,
                'total_standard_patients' => $allDmStandard,
                'average_achievement_percentage' => $allDmTarget > 0 ? round(($allDmStandard / $allDmTarget) * 100, 2) : 0
            ];
        }

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
