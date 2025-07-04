<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export monthly.xlsx - Laporan Bulanan
 * Menampilkan detail data bulanan untuk masing-masing puskesmas
 */
class AdminMonthlyFormatter extends ExcelExportFormatter
{
    public function __construct(StatisticsService $statisticsService)
    {
        parent::__construct($statisticsService);
    }

    /**
     * Format data untuk export monthly.xlsx
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
            
            // Validasi input
            $this->validateInput($diseaseType, $year);
            
            // Buat spreadsheet baru
            $spreadsheet = new Spreadsheet();
            
            // Ambil data puskesmas dari StatisticsService
            $puskesmasData = $this->getPuskesmasDataForMonthly($diseaseType, $year);
            
            // Format menggunakan parent method
            $this->formatMonthlyExcel($spreadsheet, $diseaseType, $year, $puskesmasData);
            
            Log::info('AdminMonthlyFormatter: Successfully formatted monthly.xlsx', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'puskesmas_count' => count($puskesmasData)
            ]);
            
            return $spreadsheet;
            
        } catch (\Exception $e) {
            Log::error('AdminMonthlyFormatter: Error formatting monthly.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
        }
    }

    /**
     * Ambil data puskesmas untuk laporan monthly.xlsx
     * Fokus pada data bulanan dengan klasifikasi S/TS dan persentase
     */
    protected function getPuskesmasDataForMonthly(string $diseaseType, int $year): array
    {
        try {
            // Ambil semua puskesmas
            $puskesmasList = $this->statisticsService->getAllPuskesmas();
            $formattedData = [];
            
            foreach ($puskesmasList as $puskesmas) {
                $puskesmasId = $puskesmas['id'];
                $monthlyData = [];
                
                // Ambil data untuk setiap bulan dengan detail klasifikasi
                for ($month = 1; $month <= 12; $month++) {
                    $monthlyStats = $this->statisticsService->getDetailedMonthlyStatistics(
                        $puskesmasId, 
                        $year, 
                        $month, 
                        $diseaseType
                    );
                    
                    // Hitung persentase standar
                    $totalPatients = ($monthlyStats['male_count'] ?? 0) + ($monthlyStats['female_count'] ?? 0);
                    $standardCount = $monthlyStats['standard_service_count'] ?? 0;
                    $nonStandardCount = $monthlyStats['non_standard_service_count'] ?? 0;
                    
                    $standardPercentage = $totalPatients > 0 ? 
                        round(($standardCount / $totalPatients) * 100, 2) : 0;
                    
                    $monthlyData[$month] = [
                        'male' => $monthlyStats['male_count'] ?? 0,
                        'female' => $monthlyStats['female_count'] ?? 0,
                        'standard' => $standardCount,
                        'non_standard' => $nonStandardCount,
                        'total' => $totalPatients,
                        'standard_percentage' => $standardPercentage
                    ];
                }
                
                // Ambil sasaran tahunan
                $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
                
                // Hitung total tahunan dan persentase capaian
                $yearlyTotal = $this->calculateYearlyTotals($monthlyData);
                $achievementPercentage = $yearlyTarget['target'] > 0 ? 
                    round(($yearlyTotal['total'] / $yearlyTarget['target']) * 100, 2) : 0;
                
                $formattedData[] = [
                    'id' => $puskesmasId,
                    'nama_puskesmas' => $puskesmas['name'],
                    'sasaran' => $yearlyTarget['target'] ?? 0,
                    'monthly_data' => $monthlyData,
                    'yearly_total' => $yearlyTotal,
                    'achievement_percentage' => $achievementPercentage
                ];
            }
            
            return $formattedData;
            
        } catch (\Exception $e) {
            Log::error('AdminMonthlyFormatter: Error getting puskesmas data', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            // Return empty data jika error
            return [];
        }
    }

    /**
     * Hitung total tahunan dari data bulanan
     */
    protected function calculateYearlyTotals(array $monthlyData): array
    {
        $totals = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];
        
        foreach ($monthlyData as $month => $data) {
            $totals['male'] += $data['male'] ?? 0;
            $totals['female'] += $data['female'] ?? 0;
            $totals['standard'] += $data['standard'] ?? 0;
            $totals['non_standard'] += $data['non_standard'] ?? 0;
            $totals['total'] += $data['total'] ?? 0;
        }
        
        // Hitung persentase standar tahunan
        $totals['standard_percentage'] = $totals['total'] > 0 ? 
            round(($totals['standard'] / $totals['total']) * 100, 2) : 0;
        
        return $totals;
    }

    /**
     * Override method untuk menambahkan kolom khusus monthly
     */
    protected function setupMonthlyHeaders()
    {
        parent::setupMonthlyHeaders();
        
        // Tambahkan header khusus untuk laporan bulanan
        $this->sheet->setCellValue('A2', 'LAPORAN BULANAN PELAYANAN KESEHATAN');
        $this->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $this->sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Merge cells untuk sub-header
        $this->sheet->mergeCells('A2:' . $this->sheet->getHighestColumn() . '2');
    }

    /**
     * Override untuk menambahkan informasi tambahan pada monthly report
     */
    protected function fillMonthlyData(int $row, array $data, int $year, string $diseaseType)
    {
        parent::fillMonthlyData($row, $data, $year, $diseaseType);
        
        // Tambahkan kolom persentase capaian di akhir
        $lastColumn = $this->incrementColumn($this->totalColumns[4], 1);
        $this->sheet->setCellValue($lastColumn . '6', '% CAPAIAN');
        $this->sheet->setCellValue($lastColumn . $row, $data['achievement_percentage'] . '%');
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
        
        return "Laporan_Bulanan_{$diseaseLabel}_{$year}.xlsx";
    }

    /**
     * Get summary statistics untuk monthly report
     */
    public function getSummaryStatistics(array $puskesmasData): array
    {
        $summary = [
            'total_puskesmas' => count($puskesmasData),
            'total_sasaran' => 0,
            'total_capaian' => 0,
            'rata_rata_capaian' => 0,
            'puskesmas_tercapai' => 0
        ];
        
        foreach ($puskesmasData as $data) {
            $summary['total_sasaran'] += $data['sasaran'] ?? 0;
            $summary['total_capaian'] += $data['yearly_total']['total'] ?? 0;
            
            // Hitung puskesmas yang mencapai target (>= 80%)
            if (($data['achievement_percentage'] ?? 0) >= 80) {
                $summary['puskesmas_tercapai']++;
            }
        }
        
        // Hitung rata-rata capaian
        $summary['rata_rata_capaian'] = $summary['total_sasaran'] > 0 ? 
            round(($summary['total_capaian'] / $summary['total_sasaran']) * 100, 2) : 0;
        
        return $summary;
    }
}