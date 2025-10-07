<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Services\Statistics\StatisticsService;
use App\Services\Statistics\StatisticsDataService;
use App\Services\Export\StatisticsExportService;
use App\Services\System\MonitoringReportService;
use App\Services\Statistics\RealTimeStatisticsService;
use App\Repositories\PuskesmasRepositoryInterface;
use App\Traits\Validation\StatisticsValidationTrait;
use App\Traits\Calculation\PercentageCalculationTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StatisticsController extends Controller
{
    use StatisticsValidationTrait, PercentageCalculationTrait;

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
        $diseaseType = $request->type ?? 'all';
        $perPage = min($request->per_page ?? 15, 100); // Max 100 per page

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
        $diseaseType = $request->disease_type ?? $request->type ?? 'all';
        
        // Check if user is admin or puskesmas
        $user = Auth::user();
        $isPuskesmasUser = $user && $user->puskesmas_id;
        
        if ($isPuskesmasUser) {
            // For puskesmas users, return single puskesmas data
            return $this->getPuskesmasSpecificData($user->puskesmas_id, $year, $diseaseType);
        } else {
            // For admin users, return all puskesmas data with ranking
            return $this->getAdminDashboardData($year, $diseaseType);
        }
    }

    /**
     * Get puskesmas specific data for puskesmas users
     */
    private function getPuskesmasSpecificData($puskesmasId, $year, $diseaseType): JsonResponse
    {
        $puskesmas = $this->puskesmasRepository->find($puskesmasId);
        if (!$puskesmas) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
                'data' => []
            ], 404);
        }

        $data = [];
        $puskesmasData = [
            'puskesmas_id' => $puskesmas->id,
            'puskesmas_name' => $puskesmas->name,
        ];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'ht', $year);
            $htTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'ht')->value('target_count') ?? 0;
            
            $puskesmasData['ht'] = [
                'target' => $htTarget,
                'total_patients' => (string)$htData['summary']['total'],
                'achievement_percentage' => $this->calculateAchievementPercentage($htData['summary']['standard'], $htTarget),
                'standard_patients' => (string)$htData['summary']['standard'],
                'non_standard_patients' => (string)$htData['summary']['non_standard'],
                'male_patients' => (string)$htData['summary']['male'],
                'female_patients' => (string)$htData['summary']['female'],
                'monthly_data' => $this->formatMonthlyDataForPuskesmas($htData['monthly_data'], $htTarget)
            ];
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'dm', $year);
            $dmTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'dm')->value('target_count') ?? 0;
            
            $puskesmasData['dm'] = [
                'target' => $dmTarget,
                'total_patients' => (string)$dmData['summary']['total'],
                'achievement_percentage' => $this->calculateAchievementPercentage($dmData['summary']['standard'], $dmTarget),
                'standard_patients' => (string)$dmData['summary']['standard'],
                'non_standard_patients' => (string)$dmData['summary']['non_standard'],
                'male_patients' => (string)$dmData['summary']['male'],
                'female_patients' => (string)$dmData['summary']['female'],
                'monthly_data' => $this->formatMonthlyDataForPuskesmas($dmData['monthly_data'], $dmTarget)
            ];
        }

        $puskesmasData['ranking'] = 1; // Single puskesmas always rank 1
        $data[] = $puskesmasData;

        return response()->json([
            'year' => (string)$year,
            'disease_type' => $diseaseType,
            'month' => null,
            'month_name' => null,
            'data' => $data
        ]);
    }

    /**
     * Get admin dashboard data with all puskesmas and ranking
     */
    private function getAdminDashboardData($year, $diseaseType): JsonResponse
    {
        $allPuskesmas = $this->puskesmasRepository->getAllPuskesmas();
        $data = [];
        $summary = [];
        
        // Initialize summary totals
        $htTotalTarget = 0;
        $htTotalPatients = 0;
        $htTotalStandard = 0;
        $htTotalNonStandard = 0;
        $htTotalMale = 0;
        $htTotalFemale = 0;
        $dmTotalTarget = 0;
        $dmTotalPatients = 0;
        $dmTotalStandard = 0;
        $dmTotalNonStandard = 0;
        $dmTotalMale = 0;
        $dmTotalFemale = 0;
        $htMonthlyData = [];
        $dmMonthlyData = [];

        foreach ($allPuskesmas as $puskesmas) {
            $puskesmasData = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'ht', $year);
                $htTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'ht')->value('target_count') ?? 0;
                
                $puskesmasData['ht'] = [
                    'target' => $htTarget,
                    'total_patients' => (string)$htData['summary']['total'],
                    'achievement_percentage' => $this->calculateAchievementPercentage($htData['summary']['standard'], $htTarget),
                    'standard_patients' => (string)$htData['summary']['standard'],
                    'non_standard_patients' => (string)$htData['summary']['non_standard'],
                    'male_patients' => (string)$htData['summary']['male'],
                    'female_patients' => (string)$htData['summary']['female'],
                    'monthly_data' => $this->formatMonthlyDataForPuskesmas($htData['monthly_data'], $htTarget)
                ];
                
                // Add to summary totals
                $htTotalTarget += $htTarget;
                $htTotalPatients += (int)$htData['summary']['total'];
                $htTotalStandard += (int)$htData['summary']['standard'];
                $htTotalNonStandard += (int)$htData['summary']['non_standard'];
                $htTotalMale += (int)$htData['summary']['male'];
                $htTotalFemale += (int)$htData['summary']['female'];
                
                // Aggregate monthly data for summary
                foreach ($htData['monthly_data'] as $month => $monthData) {
                    if (!isset($htMonthlyData[$month])) {
                        $htMonthlyData[$month] = [
                            'male' => 0,
                            'female' => 0,
                            'total' => 0,
                            'standard' => 0,
                            'non_standard' => 0,
                            'percentage' => 0
                        ];
                    }
                    $htMonthlyData[$month]['male'] += $monthData['male'] ?? 0;
                    $htMonthlyData[$month]['female'] += $monthData['female'] ?? 0;
                    $htMonthlyData[$month]['total'] += $monthData['total'] ?? 0;
                    $htMonthlyData[$month]['standard'] += $monthData['standard'] ?? 0;
                    $htMonthlyData[$month]['non_standard'] += $monthData['non_standard'] ?? 0;
                }
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $this->realTimeStatisticsService->getFastDashboardStats($puskesmas->id, 'dm', $year);
                $dmTarget = $puskesmas->yearlyTargets()->where('year', $year)->where('disease_type', 'dm')->value('target_count') ?? 0;
                
                $puskesmasData['dm'] = [
                    'target' => $dmTarget,
                    'total_patients' => (string)$dmData['summary']['total'],
                    'achievement_percentage' => $this->calculateAchievementPercentage($dmData['summary']['standard'], $dmTarget),
                    'standard_patients' => (string)$dmData['summary']['standard'],
                    'non_standard_patients' => (string)$dmData['summary']['non_standard'],
                    'male_patients' => (string)$dmData['summary']['male'],
                    'female_patients' => (string)$dmData['summary']['female'],
                    'monthly_data' => $this->formatMonthlyDataForPuskesmas($dmData['monthly_data'], $dmTarget)
                ];
                
                // Add to summary totals
                $dmTotalTarget += $dmTarget;
                $dmTotalPatients += (int)$dmData['summary']['total'];
                $dmTotalStandard += (int)$dmData['summary']['standard'];
                $dmTotalNonStandard += (int)$dmData['summary']['non_standard'];
                $dmTotalMale += (int)$dmData['summary']['male'];
                $dmTotalFemale += (int)$dmData['summary']['female'];
                
                // Aggregate monthly data for summary
                foreach ($dmData['monthly_data'] as $month => $monthData) {
                    if (!isset($dmMonthlyData[$month])) {
                        $dmMonthlyData[$month] = [
                            'male' => 0,
                            'female' => 0,
                            'total' => 0,
                            'standard' => 0,
                            'non_standard' => 0,
                            'percentage' => 0
                        ];
                    }
                    $dmMonthlyData[$month]['male'] += $monthData['male'] ?? 0;
                    $dmMonthlyData[$month]['female'] += $monthData['female'] ?? 0;
                    $dmMonthlyData[$month]['total'] += $monthData['total'] ?? 0;
                    $dmMonthlyData[$month]['standard'] += $monthData['standard'] ?? 0;
                    $dmMonthlyData[$month]['non_standard'] += $monthData['non_standard'] ?? 0;
                }
            }

            $data[] = $puskesmasData;
        }

        // Calculate ranking based on achievement percentage
        $data = $this->calculateRanking($data, $diseaseType);
        
        // Calculate monthly percentages for summary
        foreach ($htMonthlyData as $month => &$monthData) {
            $monthData['percentage'] = $this->calculateAchievementPercentage($monthData['standard'], $htTotalTarget);
            // Convert all values to strings for consistency
            $monthData['male'] = (string)$monthData['male'];
            $monthData['female'] = (string)$monthData['female'];
            $monthData['total'] = (string)$monthData['total'];
            $monthData['standard'] = (string)$monthData['standard'];
            $monthData['non_standard'] = (string)$monthData['non_standard'];
        }
        foreach ($dmMonthlyData as $month => &$monthData) {
            $monthData['percentage'] = $this->calculateAchievementPercentage($monthData['standard'], $dmTotalTarget);
            // Convert all values to strings for consistency
            $monthData['male'] = (string)$monthData['male'];
            $monthData['female'] = (string)$monthData['female'];
            $monthData['total'] = (string)$monthData['total'];
            $monthData['standard'] = (string)$monthData['standard'];
            $monthData['non_standard'] = (string)$monthData['non_standard'];
        }
        
        // Build summary
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $summary['ht'] = [
                'target' => (string)$htTotalTarget,
                'total_patients' => (string)$htTotalPatients,
                'standard_patients' => (string)$htTotalStandard,
                'non_standard_patients' => (string)$htTotalNonStandard,
                'male_patients' => (string)$htTotalMale,
                'female_patients' => (string)$htTotalFemale,
                'achievement_percentage' => $this->calculateAchievementPercentage($htTotalStandard, $htTotalTarget),
                'monthly_data' => $htMonthlyData
            ];
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $summary['dm'] = [
                'target' => (string)$dmTotalTarget,
                'total_patients' => (string)$dmTotalPatients,
                'standard_patients' => (string)$dmTotalStandard,
                'non_standard_patients' => (string)$dmTotalNonStandard,
                'male_patients' => (string)$dmTotalMale,
                'female_patients' => (string)$dmTotalFemale,
                'achievement_percentage' => $this->calculateAchievementPercentage($dmTotalStandard, $dmTotalTarget),
                'monthly_data' => $dmMonthlyData
            ];
        }
        
        $totalPuskesmas = count($allPuskesmas);

        return response()->json([
            'year' => (string)$year,
            'disease_type' => $diseaseType,
            'month' => null,
            'month_name' => null,
            'total_puskesmas' => $totalPuskesmas,
            'summary' => $summary,
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => $totalPuskesmas,
                'to' => $totalPuskesmas,
                'total' => $totalPuskesmas
            ],
            'all_puskesmas' => $allPuskesmas->map(function($puskesmas) {
                return [
                    'id' => $puskesmas->id,
                    'name' => $puskesmas->name
                ];
            })->toArray()
        ]);
    }

    /**
     * Format monthly data to match the required structure
     */
    private function formatMonthlyData($monthlyData, $target): array
    {
        $formatted = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = (string)$month;
            $monthData = $monthlyData[$monthStr] ?? [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0
            ];
            
            $formatted[$monthStr] = [
                'male' => (string)$monthData['male'],
                'female' => (string)$monthData['female'],
                'total' => (string)$monthData['total'],
                'standard' => (string)$monthData['standard'],
                'non_standard' => (string)$monthData['non_standard'],
                'percentage' => $this->calculateAchievementPercentage($monthData['standard'], $target)
            ];
        }
        return $formatted;
    }

    /**
     * Format monthly data for puskesmas with proper monthly targets
     */
    private function formatMonthlyDataForPuskesmas($monthlyData, $yearlyTarget): array
    {
        $formatted = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = (string)$month;
            $monthData = $monthlyData[$monthStr] ?? [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0
            ];
            
            $formatted[$monthStr] = [
                'male' => (string)$monthData['male'],
                'female' => (string)$monthData['female'],
                'total' => (string)$monthData['total'],
                'standard' => (string)$monthData['standard'],
                'non_standard' => (string)$monthData['non_standard'],
                'percentage' => $this->calculateAchievementPercentage($monthData['standard'], $yearlyTarget)
            ];
        }
        return $formatted;
    }

    /**
     * Calculate ranking based on achievement percentage
     */
    private function calculateRanking($data, $diseaseType): array
    {
        // Sort by achievement percentage (descending)
        usort($data, function ($a, $b) use ($diseaseType) {
            $aPercentage = 0;
            $bPercentage = 0;
            
            if ($diseaseType === 'ht') {
                $aPercentage = $a['ht']['achievement_percentage'] ?? 0;
                $bPercentage = $b['ht']['achievement_percentage'] ?? 0;
            } elseif ($diseaseType === 'dm') {
                $aPercentage = $a['dm']['achievement_percentage'] ?? 0;
                $bPercentage = $b['dm']['achievement_percentage'] ?? 0;
            } else { // 'all' - use average of both
                $aHt = $a['ht']['achievement_percentage'] ?? 0;
                $aDm = $a['dm']['achievement_percentage'] ?? 0;
                $aPercentage = ($aHt + $aDm) / 2;
                
                $bHt = $b['ht']['achievement_percentage'] ?? 0;
                $bDm = $b['dm']['achievement_percentage'] ?? 0;
                $bPercentage = ($bHt + $bDm) / 2;
            }
            
            return $bPercentage <=> $aPercentage;
        });
        
        // Add ranking
        foreach ($data as $index => &$item) {
            $item['ranking'] = $index + 1;
        }
        
        return $data;
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
            
            // Use latest month data instead of accumulating yearly_total
            $latestData = $data['yearly_total']; // yearly_total now contains latest month data
            $totalPatients = $latestData['total'];
            $totalStandard = $latestData['standard'];
            $totalNonStandard = $latestData['non_standard'];
            $totalMale = $latestData['male'];
            $totalFemale = $latestData['female'];

            // Aggregate monthly data
            foreach ($data['monthly_data'] as $month => $monthData) {
                $monthlyData[$month]['male'] = (string)((int)$monthlyData[$month]['male'] + $monthData['male']);
                $monthlyData[$month]['female'] = (string)((int)$monthlyData[$month]['female'] + $monthData['female']);
                $monthlyData[$month]['total'] = (string)((int)$monthlyData[$month]['total'] + $monthData['total']);
                $monthlyData[$month]['standard'] = (string)((int)$monthlyData[$month]['standard'] + $monthData['standard']);
                $monthlyData[$month]['non_standard'] = (string)((int)$monthlyData[$month]['non_standard'] + $monthData['non_standard']);
                
                // Calculate percentage for this month using yearly target
                $monthStandard = (int)$monthlyData[$month]['standard'];
                $monthlyData[$month]['percentage'] = $this->calculateAchievementPercentage($monthStandard, $totalTarget);
            }
        }

        return [
            'target' => (string)$totalTarget,
            'total_patients' => (string)$totalPatients,
            'standard_patients' => (string)$totalStandard,
                'non_standard_patients' => (string)$totalNonStandard,
                'male_patients' => (string)$totalMale,
                'female_patients' => (string)$totalFemale,
                'achievement_percentage' => $this->calculateAchievementPercentage($totalStandard, $totalTarget),
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
            $request->type ?? 'all'
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
            $request->type ?? 'all'
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
        $diseaseType = $request->disease_type ?? $request->type ?? 'all';
        
        // Use the same method as dashboard for admin
        return $this->getAdminDashboardData($year, $diseaseType);
    }

    /**
     * Calculate summary statistics for admin view
     */
    private function calculateAdminSummaryStatistics($data, $diseaseType)
    {
        $summary = [];

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            // Use latest data instead of accumulating
            $latestHtData = collect($data)->last()['ht'] ?? [];
            $htTargets = $latestHtData['target'] ?? 0;
            $htPatients = $latestHtData['total_patients'] ?? 0;
            $htStandardPatients = $latestHtData['standard_patients'] ?? 0;
            $htAchievement = $htTargets > 0 ? round(($htStandardPatients / $htTargets) * 100, 2) : 0;

            $summary['ht'] = [
                'target' => $htTargets,
                'total_patients' => $htPatients,
                'standard_patients' => $htStandardPatients,
                'achievement_percentage' => $htAchievement
            ];
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // Use latest data instead of accumulating
            $latestDmData = collect($data)->last()['dm'] ?? [];
            $dmTargets = $latestDmData['target'] ?? 0;
            $dmPatients = $latestDmData['total_patients'] ?? 0;
            $dmStandardPatients = $latestDmData['standard_patients'] ?? 0;
            $dmAchievement = $dmTargets > 0 ? round(($dmStandardPatients / $dmTargets) * 100, 2) : 0;

            $summary['dm'] = [
                'target' => $dmTargets,
                'total_patients' => $dmPatients,
                'standard_patients' => $dmStandardPatients,
                'achievement_percentage' => $dmAchievement
            ];
        }

        return $summary;
    }



}
