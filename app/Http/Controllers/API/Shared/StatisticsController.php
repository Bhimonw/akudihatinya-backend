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

        // Get month name if month is provided
        $monthName = null;
        if ($month) {
            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            $monthName = $monthNames[$month] ?? null;
        }

        // Get all puskesmas for summary calculation (not paginated)
        $allPuskesmasQuery = $this->puskesmasRepository->getFilteredPuskesmasQuery($request);
        $allPuskesmas = $allPuskesmasQuery->get();

        // Get paginated puskesmas for data display
        $paginatedPuskesmas = $allPuskesmasQuery->paginate($perPage);

        if ($allPuskesmas->isEmpty()) {
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

        // Calculate summary statistics from ALL puskesmas
        $summary = $this->calculateSummaryStatistics($allPuskesmas, $diseaseType, $year);

        // Get formatted data for paginated puskesmas
        $formattedData = [];
        foreach ($paginatedPuskesmas as $puskesmas) {
            $puskesmasData = [
                'id' => $puskesmas->id,
                'name' => $puskesmas->name,
            ];

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'ht', $year);
                $htTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'ht')->value('target_count') ?? 0;
                
                $puskesmasData['ht'] = [
                    'target' => (string)$htTarget,
                    'total_patients' => (string)$htData['yearly_total']['total_count'],
                    'total_standard_patients' => (string)$htData['yearly_total']['standard_count'],
                'achievement_percentage' => $htTarget > 0 ? round(($htData['yearly_total']['standard_count'] / $htTarget) * 100, 2) : 0
                ];
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'dm', $year);
                $dmTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'dm')->value('target_count') ?? 0;
                
                $puskesmasData['dm'] = [
                    'target' => (string)$dmTarget,
                    'total_patients' => (string)$dmData['yearly_total']['total_count'],
                    'total_standard_patients' => (string)$dmData['yearly_total']['standard_count'],
                'achievement_percentage' => $dmTarget > 0 ? round(($dmData['yearly_total']['standard_count'] / $dmTarget) * 100, 2) : 0
                ];
            }

            $formattedData[] = $puskesmasData;
        }

        return response()->json([
            'year' => (string)$year,
            'disease_type' => $diseaseType,
            'month' => $month,
            'month_name' => $monthName,
            'total_puskesmas' => $allPuskesmas->count(),
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
     * Calculate summary statistics with monthly data breakdown
     */
    private function calculateSummaryStatistics($allPuskesmas, $diseaseType, $year): array
    {
        $summary = [];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $summary['ht'] = $this->calculateDiseaseTypeSummary($allPuskesmas, 'ht', $year);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $summary['dm'] = $this->calculateDiseaseTypeSummary($allPuskesmas, 'dm', $year);
        }

        return $summary;
    }

    /**
     * Calculate summary for specific disease type
     */
    private function calculateDiseaseTypeSummary($allPuskesmas, $diseaseType, $year): array
    {
        $totalTarget = 0;
        $totalPatients = 0;
        $totalStandard = 0;
        $totalNonStandard = 0;
        $totalMale = 0;
        $totalFemale = 0;
        $monthlyData = [];

        // Initialize monthly data
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = [
                'male' => '0',
                'female' => '0',
                'total' => '0',
                'standard' => '0',
                'non_standard' => '0',
                'percentage' => 0
            ];
        }

        foreach ($allPuskesmas as $puskesmas) {
            // Get target
            $target = $puskesmas->yearlyTargets()
                ->where('year', $year)
                ->where('disease_type', $diseaseType)
                ->value('target_count') ?? 0;
            $totalTarget += $target;

            // Get statistics data
            $data = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, $diseaseType, $year);
            
            $totalPatients += $data['yearly_total']['total_count'];
            $totalStandard += $data['yearly_total']['standard_count'];
            $totalNonStandard += $data['yearly_total']['non_standard_count'];
            $totalMale += $data['yearly_total']['male_count'];
            $totalFemale += $data['yearly_total']['female_count'];

            // Aggregate monthly data
            foreach ($data['monthly_data'] as $month => $monthData) {
                $monthlyData[$month]['male'] = (string)((int)$monthlyData[$month]['male'] + $monthData['male_count']);
                $monthlyData[$month]['female'] = (string)((int)$monthlyData[$month]['female'] + $monthData['female_count']);
                $monthlyData[$month]['total'] = (string)((int)$monthlyData[$month]['total'] + $monthData['total_count']);
                $monthlyData[$month]['standard'] = (string)((int)$monthlyData[$month]['standard'] + $monthData['standard_count']);
                $monthlyData[$month]['non_standard'] = (string)((int)$monthlyData[$month]['non_standard'] + $monthData['non_standard_count']);
                
                // Calculate percentage for this month
                $monthTotal = (int)$monthlyData[$month]['total'];
                $monthStandard = (int)$monthlyData[$month]['standard'];
                $monthlyData[$month]['percentage'] = $monthTotal > 0 ? round(($monthStandard / $monthTotal) * 100, 2) : 0;
            }
        }

        return [
            'target' => (string)$totalTarget,
            'total_patients' => (string)$totalPatients,
            'total_standard_patients' => (string)$totalStandard,
                'non_standard_patients' => (string)$totalNonStandard,
                'male_patients' => (string)$totalMale,
                'female_patients' => (string)$totalFemale,
                'achievement_percentage' => $totalTarget > 0 ? round(($totalStandard / $totalTarget) * 100, 2) : 0,
            'monthly_data' => $monthlyData
        ];
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
                    'total_standard_patients' => $htData['summary']['standard_count'],
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
                    'total_standard_patients' => $dmData['summary']['standard_count'],
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
    private function calculateAdminSummaryStatistics($data, $diseaseType)
    {
        $summary = [];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htTargets = collect($data)->sum('ht.target');
            $htPatients = collect($data)->sum('ht.total_patients');
            $htStandardPatients = collect($data)->sum('ht.total_standard_patients');
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
            $dmStandardPatients = collect($data)->sum('dm.total_standard_patients');
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
