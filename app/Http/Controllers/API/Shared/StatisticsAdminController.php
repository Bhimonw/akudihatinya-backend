<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\StatisticsService;
use App\Services\StatisticsAdminService;
use Carbon\Carbon;

class StatisticsAdminController extends Controller
{
    protected $statisticsService;
    protected $statisticsAdminService;

    public function __construct(StatisticsService $statisticsService, StatisticsAdminService $statisticsAdminService)
    {
        $this->statisticsService = $statisticsService;
        $this->statisticsAdminService = $statisticsAdminService;
    }

    /**
     * Display aggregated statistics for admin (all puskesmas)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminStatistics(Request $request)
    {
        $result = $this->statisticsAdminService->getAdminStatistics($request);
        if (isset($result['error']) && $result['error']) {
            return response()->json(['message' => $result['message']], $result['status'] ?? 400);
        }
        return response()->json($result);
    }

    protected function getMonthName($month)
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        return $months[$month] ?? '';
    }
}
