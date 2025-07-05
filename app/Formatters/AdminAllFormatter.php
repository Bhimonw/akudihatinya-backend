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
     * Format data untuk export all.xlsx menggunakan template yang sudah dimuat
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
            
            // Set active sheet dari template
            $this->sheet = $spreadsheet->getActiveSheet();
            
            // Ambil data puskesmas dari StatisticsService
            $puskesmasData = $this->getPuskesmasDataForAll($diseaseType, $year);
            
            // Isi data ke template yang sudah ada
            $this->fillAllTemplateWithData($puskesmasData, $year, $diseaseType);
            
            Log::info('AdminAllFormatter: Successfully formatted all.xlsx using template', [
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
     * Isi template all.xlsx dengan data puskesmas
     * Metode ini hanya mengisi data ke template yang sudah ada tanpa mengubah struktur
     */
    protected function fillAllTemplateWithData($puskesmasData, int $year, string $diseaseType)
    {
        if (!$this->sheet) {
            throw new \Exception('Sheet template tidak tersedia');
        }
        
        // Isi informasi header
        $this->findAndFillCell('TAHUN', $year);
        $this->findAndFillCell('JENIS PENYAKIT', $diseaseType === 'ht' ? 'HIPERTENSI' : 'DIABETES MELITUS');
        
        // Cari baris awal data puskesmas
        $dataStartRow = $this->findAllDataStartRow();
        
        if ($dataStartRow && !empty($puskesmasData)) {
            $currentRow = $dataStartRow;
            
            foreach ($puskesmasData as $index => $puskesmas) {
                $this->fillPuskesmasRowInAllTemplate($currentRow, $index + 1, $puskesmas);
                $currentRow++;
            }
            
            // Tambahkan total keseluruhan
            $this->addAllTemplateSummary($currentRow, $puskesmasData);
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
     * Cari baris awal untuk data puskesmas di template all.xlsx
     */
    protected function findAllDataStartRow()
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
     * Isi satu baris data puskesmas di template all.xlsx
     */
    protected function fillPuskesmasRowInAllTemplate($row, $no, $puskesmasData)
    {
        // Hitung total tahunan
        $yearlyTotal = $this->calculateYearlyTotal($puskesmasData['monthly_data']);
        
        // Isi data berdasarkan struktur template
        $this->sheet->setCellValue('A' . $row, $no); // No
        $this->sheet->setCellValue('B' . $row, $puskesmasData['nama_puskesmas']); // Nama Puskesmas
        $this->sheet->setCellValue('C' . $row, number_format($puskesmasData['sasaran'])); // Sasaran
        $this->sheet->setCellValue('D' . $row, number_format($yearlyTotal['total'])); // Total Capaian
        
        // Hitung persentase capaian
        $achievementPercentage = $puskesmasData['sasaran'] > 0 ? 
            round(($yearlyTotal['total'] / $puskesmasData['sasaran']) * 100, 2) : 0;
        $this->sheet->setCellValue('E' . $row, $achievementPercentage . '%'); // % Capaian
        
        // Isi data bulanan (kolom F sampai Q untuk 12 bulan)
        $startCol = 'F';
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $puskesmasData['monthly_data'][$month] ?? ['total' => 0];
            $col = chr(ord($startCol) + $month - 1);
            $this->sheet->setCellValue($col . $row, $monthData['total']);
        }
    }
    
    /**
     * Hitung total tahunan dari data bulanan
     */
    protected function calculateYearlyTotal(array $monthlyData): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
        
        foreach ($monthlyData as $month => $data) {
            $total['male'] += $data['male'] ?? 0;
            $total['female'] += $data['female'] ?? 0;
            $total['standard'] += $data['standard'] ?? 0;
            $total['non_standard'] += $data['non_standard'] ?? 0;
            $total['total'] += $data['total'] ?? 0;
        }
        
        return $total;
    }
    
    /**
     * Tambahkan summary total di akhir template
     */
    protected function addAllTemplateSummary($startRow, $puskesmasData)
    {
        $grandTotal = ['sasaran' => 0, 'capaian' => 0];
        
        foreach ($puskesmasData as $puskesmas) {
            $grandTotal['sasaran'] += $puskesmas['sasaran'];
            $yearlyTotal = $this->calculateYearlyTotal($puskesmas['monthly_data']);
            $grandTotal['capaian'] += $yearlyTotal['total'];
        }
        
        // Isi baris total
        $this->sheet->setCellValue('A' . $startRow, '');
        $this->sheet->setCellValue('B' . $startRow, 'TOTAL KESELURUHAN');
        $this->sheet->setCellValue('C' . $startRow, number_format($grandTotal['sasaran']));
        $this->sheet->setCellValue('D' . $startRow, number_format($grandTotal['capaian']));
        
        $overallPercentage = $grandTotal['sasaran'] > 0 ? 
            round(($grandTotal['capaian'] / $grandTotal['sasaran']) * 100, 2) : 0;
        $this->sheet->setCellValue('E' . $startRow, $overallPercentage . '%');
        
        // Style bold untuk baris total
        $this->sheet->getStyle('A' . $startRow . ':E' . $startRow)->getFont()->setBold(true);
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
                        $diseaseType,
                        $month
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