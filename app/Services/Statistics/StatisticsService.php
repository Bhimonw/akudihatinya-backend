<?php

namespace App\Services\Statistics;

use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class StatisticsService
{
    protected $htService;
    protected $dmService;
    protected $puskesmasService;

    public function __construct(
        HtStatisticsService $htService,
        DmStatisticsService $dmService,
        PuskesmasService $puskesmasService
    ) {
        $this->htService = $htService;
        $this->dmService = $dmService;
        $this->puskesmasService = $puskesmasService;
    }

    /**
     * Get statistics data
     */
    public function getStatistics(Request $request, $year, $month, $diseaseType, $perPage)
    {
        // Dapatkan data puskesmas
        $puskesmasResult = $this->puskesmasService->getPuskesmasData($request);
        
        if (isset($puskesmasResult['error'])) {
            return ['error' => $puskesmasResult['error']];
        }
        
        $puskesmasAll = $puskesmasResult['puskesmas'];
        
        if ($puskesmasAll->isEmpty()) {
            return ['error' => 'Tidak ada data puskesmas yang ditemukan.'];
        }

        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Ambil data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->htService->getStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $htTargetCount = $htTarget ? $htTarget->target_count : 0;

                $data['ht'] = [
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

                $dmData = $this->dmService->getStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

                $data['dm'] = [
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

            $statistics[] = $data;
        }

        // Sort by achievement percentage berdasarkan jenis penyakit
        $statistics = $this->sortStatistics($statistics, $diseaseType);

        // Add ranking
        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        // Paginate the results
        $page = $request->page ?? 1;
        $offset = ($page - 1) * $perPage;

        $paginatedItems = array_slice($statistics, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            count($statistics),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return ['paginator' => $paginator];
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStatistics(Request $request, $year, $type)
    {
        $puskesmasResult = $this->puskesmasService->getPuskesmasData($request);
        
        if (isset($puskesmasResult['error'])) {
            return [];
        }
        
        $puskesmasAll = $puskesmasResult['puskesmas'];
        
        // Siapkan data untuk dikirim ke frontend
        $data = [];

        foreach ($puskesmasAll as $puskesmas) {
            $puskesmasData = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Tambahkan data HT jika diperlukan
            if ($type === 'all' || $type === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->htService->getStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

                $targetCount = $htTarget ? $htTarget->target_count : 0;

                $puskesmasData['ht'] = [
                    'target' => $targetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($htData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Tambahkan data DM jika diperlukan
            if ($type === 'all' || $type === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->dmService->getStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

                $targetCount = $dmTarget ? $dmTarget->target_count : 0;

                $puskesmasData['dm'] = [
                    'target' => $targetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($dmData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $data[] = $puskesmasData;
        }

        // Urutkan data berdasarkan achievement_percentage
        $data = $this->sortDashboardData($data, $type);

        // Tambahkan ranking
        foreach ($data as $index => $item) {
            $data[$index]['ranking'] = $index + 1;
        }

        return $data;
    }

    /**
     * Sort statistics data
     */
    private function sortStatistics($statistics, $diseaseType)
    {
        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            // Sort by combined achievement percentage (HT + DM) for ranking
            usort($statistics, function ($a, $b) {
                $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                return $bTotal <=> $aTotal;
            });
        }

        return $statistics;
    }

    /**
     * Sort dashboard data
     */
    private function sortDashboardData($data, $type)
    {
        usort($data, function ($a, $b) use ($type) {
            $aValue = $type === 'dm' ?
                ($a['dm']['achievement_percentage'] ?? 0) : ($a['ht']['achievement_percentage'] ?? 0);

            $bValue = $type === 'dm' ?
                ($b['dm']['achievement_percentage'] ?? 0) : ($b['ht']['achievement_percentage'] ?? 0);

            return $bValue <=> $aValue;
        });

        return $data;
    }

    /**
     * Export statistics to PDF
     */
    public function exportPdf(Request $request)
    {
        // Implementasi export PDF
    }

    /**
     * Export statistics to Excel
     */
    public function exportExcel(Request $request)
    {
        // Implementasi export Excel
    }
}