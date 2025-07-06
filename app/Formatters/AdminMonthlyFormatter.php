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
     * Format data untuk export monthly.xlsx menggunakan template yang sudah dimuat
     * 
     * @param Spreadsheet $spreadsheet Template yang sudah dimuat dari IOFactory::load
     * @param string $diseaseType Jenis penyakit (ht/dm)
     * @param int $year Tahun laporan
     * @param array $additionalData Data tambahan jika diperlukan
     * @return Spreadsheet
     */
    public function format(Spreadsheet $spreadsheet, string $diseaseType = 'ht', int $year = null, array $additionalData = []): Spreadsheet
    {
        try {
            $year = $year ?? date('Y');
            
            // Validasi input
            $this->validateInput($diseaseType, $year);
            
            // Set active sheet dari template
            $this->sheet = $spreadsheet->getActiveSheet();
            
            // Ambil data puskesmas dari StatisticsService
            $puskesmasData = $this->getPuskesmasDataForMonthly($diseaseType, $year);
            
            // Isi data ke template yang sudah ada
            $this->fillMonthlyTemplateWithData($puskesmasData, $year, $diseaseType);
            
            Log::info('AdminMonthlyFormatter: Successfully formatted monthly.xlsx using template', [
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
     * Isi template monthly.xlsx dengan data puskesmas
     * Metode ini hanya mengisi data ke template yang sudah ada tanpa mengubah struktur
     */
    protected function fillMonthlyTemplateWithData($puskesmasData, int $year, string $diseaseType)
    {
        if (!$this->sheet) {
            throw new \Exception('Sheet template tidak tersedia');
        }
        
        // Isi informasi header
        $this->findAndFillCell('TAHUN', $year);
        $this->findAndFillCell('JENIS PENYAKIT', $diseaseType === 'ht' ? 'HIPERTENSI' : 'DIABETES MELITUS');
        
        // Cari baris awal data puskesmas
        $dataStartRow = $this->findMonthlyDataStartRow();
        
        if ($dataStartRow && !empty($puskesmasData)) {
            $currentRow = $dataStartRow;
            
            foreach ($puskesmasData as $index => $puskesmas) {
                $this->fillPuskesmasRowInMonthlyTemplate($currentRow, $index + 1, $puskesmas);
                $currentRow++;
            }
            
            // Tambahkan total keseluruhan
            $this->addMonthlyTemplateSummary($currentRow, $puskesmasData);
        }
    }
    
    /**
     * Cari cell yang mengandung teks tertentu dan isi dengan nilai baru
     */
    protected function findAndFillCell($searchText, $value)
    {
        $highestRow = $this->sheet->getHighestRow();
        $highestColumn = $this->sheet->getHighestColumn();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $this->sheet->getCell($col . $row)->getValue();
                if (is_string($cellValue) && stripos($cellValue, $searchText) !== false) {
                    // Jika cell berisi teks pencarian, isi cell sebelahnya atau yang sama
                    if (stripos($cellValue, ':') !== false) {
                        // Format "Label: [value]" - isi di cell yang sama
                        $this->sheet->setCellValue($col . $row, str_ireplace($searchText, $searchText . ': ' . $value, $cellValue));
                    } else {
                        // Isi di cell sebelahnya (kolom berikutnya)
                        $nextCol = chr(ord($col) + 1);
                        $this->sheet->setCellValue($nextCol . $row, $value);
                    }
                    return;
                }
            }
        }
    }
    
    /**
     * Cari baris awal untuk data puskesmas di template monthly.xlsx
     */
    protected function findMonthlyDataStartRow()
    {
        $highestRow = $this->sheet->getHighestRow();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $this->sheet->getCell('A' . $row)->getValue();
            if (is_string($cellValue) && (stripos($cellValue, 'NO') !== false || stripos($cellValue, 'NAMA PUSKESMAS') !== false)) {
                // Cek jika ini adalah header row
                $nextCellValue = $this->sheet->getCell('B' . $row)->getValue();
                if (is_string($nextCellValue) && stripos($nextCellValue, 'PUSKESMAS') !== false) {
                    return $row + 1; // Baris setelah header
                }
            }
        }
        
        return 5; // Default jika tidak ditemukan
    }
    
    /**
     * Isi satu baris data puskesmas di template monthly.xlsx
     */
    protected function fillPuskesmasRowInMonthlyTemplate($row, $no, $puskesmasData)
    {
        // Isi data berdasarkan struktur template
        $this->sheet->setCellValue('A' . $row, $no); // No
        $this->sheet->setCellValue('B' . $row, $puskesmasData['nama_puskesmas']); // Nama Puskesmas
        $this->sheet->setCellValue('C' . $row, number_format($puskesmasData['sasaran'])); // Sasaran
        
        // Isi data bulanan (kolom D sampai O untuk 12 bulan)
        $startCol = 'D';
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $puskesmasData['monthly_data'][$month] ?? ['total' => 0];
            $col = chr(ord($startCol) + $month - 1);
            $this->sheet->setCellValue($col . $row, $monthData['total']);
        }
        
        // Total tahunan dan persentase capaian
        $this->sheet->setCellValue('P' . $row, number_format($puskesmasData['yearly_total']['total'])); // Total
        $this->sheet->setCellValue('Q' . $row, $puskesmasData['achievement_percentage'] . '%'); // % Capaian
    }
    
    /**
     * Tambahkan summary total di akhir template monthly
     */
    protected function addMonthlyTemplateSummary($startRow, $puskesmasData)
    {
        $grandTotal = ['sasaran' => 0, 'capaian' => 0, 'monthly' => array_fill(1, 12, 0)];
        
        foreach ($puskesmasData as $puskesmas) {
            $grandTotal['sasaran'] += $puskesmas['sasaran'];
            $grandTotal['capaian'] += $puskesmas['yearly_total']['total'];
            
            // Total bulanan
            for ($month = 1; $month <= 12; $month++) {
                $grandTotal['monthly'][$month] += $puskesmas['monthly_data'][$month]['total'] ?? 0;
            }
        }
        
        // Isi baris total
        $this->sheet->setCellValue('A' . $startRow, '');
        $this->sheet->setCellValue('B' . $startRow, 'TOTAL KESELURUHAN');
        $this->sheet->setCellValue('C' . $startRow, number_format($grandTotal['sasaran']));
        
        // Total bulanan
        $startCol = 'D';
        for ($month = 1; $month <= 12; $month++) {
            $col = chr(ord($startCol) + $month - 1);
            $this->sheet->setCellValue($col . $startRow, number_format($grandTotal['monthly'][$month]));
        }
        
        $this->sheet->setCellValue('P' . $startRow, number_format($grandTotal['capaian']));
        
        $overallPercentage = $grandTotal['sasaran'] > 0 ? 
            round(($grandTotal['capaian'] / $grandTotal['sasaran']) * 100, 2) : 0;
        $this->sheet->setCellValue('Q' . $startRow, $overallPercentage . '%');
        
        // Style bold untuk baris total
        $this->sheet->getStyle('A' . $startRow . ':Q' . $startRow)->getFont()->setBold(true);
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
                $puskesmasId = $puskesmas->id;
                $monthlyData = [];
                
                // Ambil data untuk setiap bulan dengan detail klasifikasi
                for ($month = 1; $month <= 12; $month++) {
                    $monthlyStats = $this->statisticsService->getMonthlyStatistics(
                        $puskesmasId, 
                        $year, 
                        $diseaseType,
                        $month
                    );
                    
                    // Hitung persentase standar
                    $totalPatients = $monthlyStats['total_patients'] ?? 0;
                    $standardCount = $monthlyStats['standard_patients'] ?? 0;
                    $nonStandardCount = $monthlyStats['non_standard_patients'] ?? 0;
                    
                    $standardPercentage = $totalPatients > 0 ? 
                        round(($standardCount / $totalPatients) * 100, 2) : 0;
                    
                    $monthlyData[$month] = [
                        'male' => $monthlyStats['male_patients'] ?? 0,
                        'female' => $monthlyStats['female_patients'] ?? 0,
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
                    'nama_puskesmas' => $puskesmas->name,
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