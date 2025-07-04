<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export quarterly.xlsx - Laporan Triwulan
 * Ringkasan capaian per triwulan untuk masing-masing puskesmas
 */
class AdminQuarterlyFormatter extends ExcelExportFormatter
{
    public function __construct(StatisticsService $statisticsService)
    {
        parent::__construct($statisticsService);
    }

    /**
     * Format data untuk export quarterly.xlsx
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
            $puskesmasData = $this->getPuskesmasDataForQuarterly($diseaseType, $year);
            
            // Format menggunakan parent method
            $this->formatQuarterlyExcel($spreadsheet, $diseaseType, $year, $puskesmasData);
            
            Log::info('AdminQuarterlyFormatter: Successfully formatted quarterly.xlsx', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'puskesmas_count' => count($puskesmasData)
            ]);
            
            return $spreadsheet;
            
        } catch (\Exception $e) {
            Log::error('AdminQuarterlyFormatter: Error formatting quarterly.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
        }
    }

    /**
     * Ambil data puskesmas untuk laporan quarterly.xlsx
     * Rekap bulanan yang dijumlahkan per triwulan
     */
    protected function getPuskesmasDataForQuarterly(string $diseaseType, int $year): array
    {
        try {
            // Ambil semua puskesmas
            $puskesmasList = $this->statisticsService->getAllPuskesmas();
            $formattedData = [];
            
            foreach ($puskesmasList as $puskesmas) {
                $puskesmasId = $puskesmas['id'];
                $monthlyData = [];
                $quarterlyData = [];
                
                // Ambil data bulanan terlebih dahulu
                for ($month = 1; $month <= 12; $month++) {
                    $monthlyStats = $this->statisticsService->getDetailedMonthlyStatistics(
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
                
                // Hitung data per triwulan
                for ($quarter = 1; $quarter <= 4; $quarter++) {
                    $quarterlyData[$quarter] = $this->calculateQuarterDataFromMonthly($monthlyData, $quarter);
                }
                
                // Ambil sasaran tahunan
                $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
                
                // Hitung total tahunan dan persentase capaian
                $yearlyTotal = $this->calculateYearlyTotalFromQuarterly($quarterlyData);
                $achievementPercentage = $yearlyTarget['target'] > 0 ? 
                    round(($yearlyTotal['total'] / $yearlyTarget['target']) * 100, 2) : 0;
                
                $formattedData[] = [
                    'id' => $puskesmasId,
                    'nama_puskesmas' => $puskesmas['name'],
                    'sasaran' => $yearlyTarget['target'] ?? 0,
                    'monthly_data' => $monthlyData, // Tetap simpan untuk perhitungan
                    'quarterly_data' => $quarterlyData,
                    'yearly_total' => $yearlyTotal,
                    'achievement_percentage' => $achievementPercentage
                ];
            }
            
            return $formattedData;
            
        } catch (\Exception $e) {
            Log::error('AdminQuarterlyFormatter: Error getting puskesmas data', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            // Return empty data jika error
            return [];
        }
    }

    /**
     * Hitung data triwulan dari data bulanan
     */
    protected function calculateQuarterDataFromMonthly(array $monthlyData, int $quarter): array
    {
        $quarterMonths = [
            1 => [1, 2, 3],   // Q1: Jan, Feb, Mar
            2 => [4, 5, 6],   // Q2: Apr, May, Jun
            3 => [7, 8, 9],   // Q3: Jul, Aug, Sep
            4 => [10, 11, 12] // Q4: Oct, Nov, Dec
        ];
        
        $months = $quarterMonths[$quarter] ?? [];
        $quarterData = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];
        
        foreach ($months as $month) {
            if (isset($monthlyData[$month])) {
                $quarterData['male'] += $monthlyData[$month]['male'] ?? 0;
                $quarterData['female'] += $monthlyData[$month]['female'] ?? 0;
                $quarterData['standard'] += $monthlyData[$month]['standard'] ?? 0;
                $quarterData['non_standard'] += $monthlyData[$month]['non_standard'] ?? 0;
                $quarterData['total'] += $monthlyData[$month]['total'] ?? 0;
            }
        }
        
        // Hitung persentase standar untuk triwulan
        $quarterData['standard_percentage'] = $quarterData['total'] > 0 ? 
            round(($quarterData['standard'] / $quarterData['total']) * 100, 2) : 0;
        
        return $quarterData;
    }

    /**
     * Hitung total tahunan dari data triwulan
     */
    protected function calculateYearlyTotalFromQuarterly(array $quarterlyData): array
    {
        $totals = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];
        
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            if (isset($quarterlyData[$quarter])) {
                $totals['male'] += $quarterlyData[$quarter]['male'] ?? 0;
                $totals['female'] += $quarterlyData[$quarter]['female'] ?? 0;
                $totals['standard'] += $quarterlyData[$quarter]['standard'] ?? 0;
                $totals['non_standard'] += $quarterlyData[$quarter]['non_standard'] ?? 0;
                $totals['total'] += $quarterlyData[$quarter]['total'] ?? 0;
            }
        }
        
        // Hitung persentase standar tahunan
        $totals['standard_percentage'] = $totals['total'] > 0 ? 
            round(($totals['standard'] / $totals['total']) * 100, 2) : 0;
        
        return $totals;
    }

    /**
     * Override method untuk menambahkan kolom khusus quarterly
     */
    protected function setupQuarterlyHeaders()
    {
        parent::setupQuarterlyHeaders();
        
        // Tambahkan header khusus untuk laporan triwulan
        $this->sheet->setCellValue('A2', 'LAPORAN TRIWULAN PELAYANAN KESEHATAN');
        $this->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $this->sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Merge cells untuk sub-header
        $this->sheet->mergeCells('A2:' . $this->sheet->getHighestColumn() . '2');
        
        // Tambahkan keterangan periode triwulan
        $this->sheet->setCellValue('A5', 'Keterangan: TW I (Jan-Mar), TW II (Apr-Jun), TW III (Jul-Sep), TW IV (Okt-Des)');
        $this->sheet->getStyle('A5')->getFont()->setItalic(true)->setSize(9);
    }

    /**
     * Override untuk menambahkan informasi tambahan pada quarterly report
     */
    protected function fillQuarterlyData(int $row, array $data, int $year, string $diseaseType)
    {
        // Gunakan data quarterly yang sudah dihitung
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $data['quarterly_data'][$quarter] ?? [];
            $this->fillQuarterDataToColumns($row, $quarter, $quarterData);
        }
        
        // Total tahunan
        $totalData = $data['yearly_total'] ?? [];
        $this->fillTotalDataToColumns($row, $totalData);
        
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
        
        return "Laporan_Triwulan_{$diseaseLabel}_{$year}.xlsx";
    }

    /**
     * Get summary statistics untuk quarterly report
     */
    public function getSummaryStatistics(array $puskesmasData): array
    {
        $summary = [
            'total_puskesmas' => count($puskesmasData),
            'total_sasaran' => 0,
            'total_capaian' => 0,
            'rata_rata_capaian' => 0,
            'quarterly_performance' => []
        ];
        
        // Inisialisasi data per triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $summary['quarterly_performance'][$quarter] = [
                'total' => 0,
                'standard' => 0,
                'percentage' => 0
            ];
        }
        
        foreach ($puskesmasData as $data) {
            $summary['total_sasaran'] += $data['sasaran'] ?? 0;
            $summary['total_capaian'] += $data['yearly_total']['total'] ?? 0;
            
            // Akumulasi data per triwulan
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                if (isset($data['quarterly_data'][$quarter])) {
                    $summary['quarterly_performance'][$quarter]['total'] += 
                        $data['quarterly_data'][$quarter]['total'] ?? 0;
                    $summary['quarterly_performance'][$quarter]['standard'] += 
                        $data['quarterly_data'][$quarter]['standard'] ?? 0;
                }
            }
        }
        
        // Hitung rata-rata capaian
        $summary['rata_rata_capaian'] = $summary['total_sasaran'] > 0 ? 
            round(($summary['total_capaian'] / $summary['total_sasaran']) * 100, 2) : 0;
        
        // Hitung persentase per triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $total = $summary['quarterly_performance'][$quarter]['total'];
            $standard = $summary['quarterly_performance'][$quarter]['standard'];
            $summary['quarterly_performance'][$quarter]['percentage'] = $total > 0 ? 
                round(($standard / $total) * 100, 2) : 0;
        }
        
        return $summary;
    }

    /**
     * Get quarter name in Indonesian
     */
    public function getQuarterName(int $quarter): string
    {
        $quarterNames = [
            1 => 'Triwulan I (Januari - Maret)',
            2 => 'Triwulan II (April - Juni)',
            3 => 'Triwulan III (Juli - September)',
            4 => 'Triwulan IV (Oktober - Desember)'
        ];
        
        return $quarterNames[$quarter] ?? 'Triwulan Tidak Valid';
    }
}