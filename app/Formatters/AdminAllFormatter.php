<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export all.xlsx - Rekap Tahunan Komprehensif
 * Menyajikan rekap data bulanan, triwulan, dan total tahunan untuk setiap puskesmas
 */
class AdminAllFormatter extends ExcelExportFormatter
{
    public function __construct(StatisticsService $statisticsService)
    {
        parent::__construct($statisticsService);
    }

    /**
     * Format data untuk export all.xlsx
     * 
     * @param string $diseaseType Jenis penyakit (ht/dm)
     * @param int $year Tahun laporan
     * @param array $additionalData Data tambahan jika diperlukan
     * @return Spreadsheet
     */
    public function format(string $diseaseType = 'ht', int $year = null, array $additionalData = []): Spreadsheet
    {
        try {
            $year = $year ?? date('Y');
            
            // Buat spreadsheet baru
            $spreadsheet = new Spreadsheet();
            
            // Ambil data puskesmas dari StatisticsService
            $puskesmasData = $this->getPuskesmasDataForAll($diseaseType, $year);
            
            // Format menggunakan parent method
            $this->formatAllExcel($spreadsheet, $diseaseType, $year, $puskesmasData);
            
            Log::info('AdminAllFormatter: Successfully formatted all.xlsx', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'puskesmas_count' => count($puskesmasData)
            ]);
            
            return $spreadsheet;
            
        } catch (\Exception $e) {
            Log::error('AdminAllFormatter: Error formatting all.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
        }
    }

    /**
     * Ambil data puskesmas untuk laporan all.xlsx
     * Termasuk data bulanan, triwulan, dan total tahunan
     */
    protected function getPuskesmasDataForAll(string $diseaseType, int $year): array
    {
        try {
            // Ambil semua puskesmas
            $puskesmasList = $this->statisticsService->getAllPuskesmas();
            $formattedData = [];
            
            foreach ($puskesmasList as $puskesmas) {
                $puskesmasId = $puskesmas['id'];
                $monthlyData = [];
                
                // Ambil data untuk setiap bulan
                for ($month = 1; $month <= 12; $month++) {
                    $monthlyStats = $this->statisticsService->getMonthlyStatistics(
                        $puskesmasId, 
                        $year, 
                        $month, 
                        $diseaseType
                    );
                    
                    $monthlyData[$month] = [
                        'male' => $monthlyStats['male_count'] ?? 0,
                        'female' => $monthlyStats['female_count'] ?? 0,
                        'standard' => $monthlyStats['standard_service_count'] ?? 0,
                        'non_standard' => $monthlyStats['non_standard_service_count'] ?? 0,
                        'total' => ($monthlyStats['male_count'] ?? 0) + ($monthlyStats['female_count'] ?? 0)
                    ];
                }
                
                // Ambil sasaran tahunan
                $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
                
                $formattedData[] = [
                    'id' => $puskesmasId,
                    'nama_puskesmas' => $puskesmas['name'],
                    'sasaran' => $yearlyTarget['target'] ?? 0,
                    'monthly_data' => $monthlyData
                ];
            }
            
            return $formattedData;
            
        } catch (\Exception $e) {
            Log::error('AdminAllFormatter: Error getting puskesmas data', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            // Return empty data jika error
            return [];
        }
    }

    /**
     * Validasi parameter input
     */
    protected function validateInput(string $diseaseType, int $year): bool
    {
        $validDiseaseTypes = ['ht', 'dm', 'both'];
        
        if (!in_array($diseaseType, $validDiseaseTypes)) {
            throw new \InvalidArgumentException("Invalid disease type: {$diseaseType}");
        }
        
        $currentYear = date('Y');
        if ($year < 2020 || $year > $currentYear + 1) {
            throw new \InvalidArgumentException("Invalid year: {$year}");
        }
        
        return true;
    }

    /**
     * Get filename untuk export
     */
    public function getFilename(string $diseaseType, int $year): string
    {
        $diseaseLabel = $diseaseType === 'ht' ? 'Hipertensi' : 
                       ($diseaseType === 'dm' ? 'Diabetes' : 'HT-DM');
        
        return "Laporan_Tahunan_{$diseaseLabel}_{$year}.xlsx";
    }
}