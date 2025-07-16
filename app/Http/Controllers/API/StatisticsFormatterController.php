<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Formatters\StatisticsFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatisticsFormatterController extends Controller
{
    protected StatisticsFormatter $statisticsFormatter;

    public function __construct(StatisticsFormatter $statisticsFormatter)
    {
        $this->statisticsFormatter = $statisticsFormatter;
    }

    /**
     * Get formatted dashboard statistics
     */
    public function getDashboardStatistics(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');
            $diseaseType = $request->get('disease_type', 'all');

            $statistics = $this->statisticsFormatter->formatDashboardStatistics(
                $year,
                $month,
                $diseaseType
            );

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted admin statistics with optimization
     */
    public function getAdminStatistics(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');
            $diseaseType = $request->get('disease_type', 'all');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);

            $statistics = $this->statisticsFormatter->formatAdminStatistics(
                $year,
                $month,
                $diseaseType,
                $page,
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get admin statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get optimized statistics with caching
     */
    public function getOptimizedStatistics(Request $request): JsonResponse
    {
        try {
            $puskesmasIds = $request->get('puskesmas_ids', []);
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');
            $diseaseType = $request->get('disease_type', 'all');

            $statistics = $this->statisticsFormatter->formatOptimizedStatistics(
                $puskesmasIds,
                $year,
                $month,
                $diseaseType
            );

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get optimized statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted HT statistics
     */
    public function getHtStatistics(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');

            $statistics = $this->statisticsFormatter->formatHtStatistics($year, $month);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get HT statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted DM statistics
     */
    public function getDmStatistics(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');

            $statistics = $this->statisticsFormatter->formatDmStatistics($year, $month);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get DM statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted monthly data
     */
    public function getMonthlyData(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $diseaseType = $request->get('disease_type', 'all');

            $monthlyData = $this->statisticsFormatter->formatMonthlyData($year, $diseaseType);

            return response()->json([
                'success' => true,
                'data' => $monthlyData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get monthly data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted summary statistics
     */
    public function getSummaryStatistics(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');
            $diseaseType = $request->get('disease_type', 'all');

            $summary = $this->statisticsFormatter->formatSummaryStatistics(
                $year,
                $month,
                $diseaseType
            );

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted chart data
     */
    public function getChartData(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $diseaseType = $request->get('disease_type', 'all');
            $chartType = $request->get('chart_type', 'line');

            $chartData = $this->statisticsFormatter->formatChartData(
                $year,
                $diseaseType,
                $chartType
            );

            return response()->json([
                'success' => true,
                'data' => $chartData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update real-time statistics
     */
    public function updateRealTimeStatistics(Request $request): JsonResponse
    {
        try {
            $puskesmasId = $request->get('puskesmas_id');
            $year = $request->get('year', date('Y'));
            $diseaseType = $request->get('disease_type', 'all');

            $result = $this->statisticsFormatter->updateRealTimeStatistics(
                $puskesmasId,
                $year,
                $diseaseType
            );

            return response()->json([
                'success' => true,
                'message' => 'Real-time statistics updated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update real-time statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate request parameters
     */
    public function validateParameters(Request $request): JsonResponse
    {
        try {
            $validated = $this->statisticsFormatter->validateAndFormatRequest($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Parameters are valid',
                'data' => $validated
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter validation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get formatted data for PDF export - Puskesmas Statistics
     */
    public function getPdfPuskesmasData(Request $request): JsonResponse
    {
        try {
            $puskesmasId = $request->input('puskesmas_id');
            $year = $request->input('year', date('Y'));
            $diseaseType = $request->input('disease_type', 'ht');
            
            if (!$puskesmasId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Puskesmas ID is required'
                ], 400);
            }
            
            $data = $this->statisticsFormatter->formatPuskesmasStatisticsForPdf(
                (int)$puskesmasId, 
                (int)$year, 
                $diseaseType
            );
            
            return response()->json([
                'success' => true,
                'message' => 'PDF puskesmas data retrieved successfully',
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get PDF puskesmas data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted data for PDF export - All Quarters Recap
     */
    public function getPdfQuartersRecapData(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', date('Y'));
            $diseaseType = $request->input('disease_type', 'ht');
            
            $data = $this->statisticsFormatter->formatAllQuartersRecapForPdf(
                (int)$year, 
                $diseaseType
            );
            
            return response()->json([
                'success' => true,
                'message' => 'PDF quarters recap data retrieved successfully',
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get PDF quarters recap data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted data for Excel export - All Report
     */
    public function getExcelAllData(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', date('Y'));
            $diseaseType = $request->input('disease_type', 'ht');
            
            $data = $this->statisticsFormatter->formatAllExcelData(
                $diseaseType, 
                (int)$year
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Excel all data retrieved successfully',
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Excel all data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted data for Excel export - Monthly Report
     */
    public function getExcelMonthlyData(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', date('Y'));
            $diseaseType = $request->input('disease_type', 'ht');
            
            $data = $this->statisticsFormatter->formatMonthlyExcelData(
                $diseaseType, 
                (int)$year
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Excel monthly data retrieved successfully',
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Excel monthly data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted data for Excel export - Quarterly Report
     */
    public function getExcelQuarterlyData(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', date('Y'));
            $diseaseType = $request->input('disease_type', 'ht');
            
            $data = $this->statisticsFormatter->formatQuarterlyExcelData(
                $diseaseType, 
                (int)$year
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Excel quarterly data retrieved successfully',
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Excel quarterly data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatted data for Excel export - Puskesmas Template
     */
    public function getExcelPuskesmasData(Request $request): JsonResponse
    {
        try {
            $puskesmasId = $request->input('puskesmas_id');
            $year = $request->input('year', date('Y'));
            $diseaseType = $request->input('disease_type', 'ht');
            
            if (!$puskesmasId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Puskesmas ID is required'
                ], 400);
            }
            
            $data = $this->statisticsFormatter->formatPuskesmasExcelData(
                (int)$puskesmasId, 
                $diseaseType, 
                (int)$year
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Excel puskesmas data retrieved successfully',
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Excel puskesmas data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}