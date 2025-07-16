<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\Statistics\StatisticsService;
use App\Traits\Calculation\PercentageCalculationTrait;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export puskesmas.xlsx - Template Per Puskesmas
 * Template individu untuk masing-masing puskesmas
 */
class PuskesmasFormatter extends ExcelExportFormatter
{
    use PercentageCalculationTrait;
    public function __construct(StatisticsService $statisticsService)
    {
        parent::__construct($statisticsService);
    }

    /**
     * Format data untuk export puskesmas.xlsx menggunakan template yang sudah dimuat
     * 
     * @param Spreadsheet $spreadsheet Template yang sudah dimuat dari IOFactory::load
     * @param string $diseaseType Jenis penyakit (ht/dm)
     * @param int $year Tahun laporan
     * @param int $puskesmasId ID puskesmas
     * @return Spreadsheet
     */
    public function format(Spreadsheet $spreadsheet, string $diseaseType = 'ht', int $year = null, int $puskesmasId = null): Spreadsheet
    {
        try {
            $year = $year ?? date('Y');
            
            // Validasi input
            $this->validateInput($diseaseType, $year, $puskesmasId);
            
            // Set active sheet dari template
            $this->sheet = $spreadsheet->getActiveSheet();
            
            // Ambil data puskesmas spesifik
            $puskesmasData = $this->getPuskesmasSpecificData($puskesmasId, $diseaseType, $year);
            
            // Isi data ke template yang sudah ada
            $this->fillTemplateWithData($puskesmasData, $year, $diseaseType);
            
            Log::info('PuskesmasFormatter: Successfully formatted puskesmas.xlsx using template', [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return $spreadsheet;
            
        } catch (\Exception $e) {
            Log::error('PuskesmasFormatter: Error formatting puskesmas.xlsx', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId ?? null,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
        }
    }

    /**
     * Isi template dengan data puskesmas
     * Metode ini hanya mengisi data ke template yang sudah ada tanpa mengubah struktur
     */
    protected function fillTemplateWithData($puskesmasData, int $year, string $diseaseType)
    {
        if (!$this->sheet) {
            throw new \Exception('Sheet template tidak tersedia');
        }
        
        // Isi informasi header berdasarkan posisi yang sudah ada di template
        if ($puskesmasData) {
            // Cari dan isi nama puskesmas (biasanya di cell tertentu)
            $this->findAndFillCell('NAMA PUSKESMAS', $puskesmasData['nama_puskesmas']);
            $this->findAndFillCell('SASARAN', $this->formatNumber($puskesmasData['sasaran']));
            $this->findAndFillCell('TAHUN', $year);
            
            // Isi data bulanan ke template
            $this->fillMonthlyDataToTemplate($puskesmasData['monthly_data']);
            
            // Isi total dan persentase capaian
            $this->fillSummaryDataToTemplate($puskesmasData);
        } else {
            // Template kosong - isi dengan placeholder
            $this->findAndFillCell('NAMA PUSKESMAS', '[NAMA PUSKESMAS]');
            $this->findAndFillCell('SASARAN', '[SASARAN TAHUNAN]');
            $this->findAndFillCell('TAHUN', $year);
        }
    }

    /**
     * Ambil data spesifik untuk satu puskesmas
     */
    protected function getPuskesmasSpecificData($puskesmasId, string $diseaseType, int $year): ?array
    {
        try {
            if (!$puskesmasId) {
                return null;
            }
            
            // Ambil informasi puskesmas
            $puskesmasInfo = $this->statisticsService->getPuskesmasById($puskesmasId);
            
            if (!$puskesmasInfo) {
                throw new \Exception("Puskesmas with ID {$puskesmasId} not found");
            }
            
            // Ambil sasaran tahunan
            $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
            
            // Ambil data bulanan
            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthlyStats = $this->statisticsService->getMonthlyStatistics(
                    $puskesmasId, 
                    $year, 
                    $diseaseType,
                    $month
                );
                
                $monthlyData[$month] = [
                    'male' => $monthlyStats['male_patients'] ?? 0,
                    'female' => $monthlyStats['female_patients'] ?? 0,
                    'standard' => $monthlyStats['standard_patients'] ?? 0,
                    'non_standard' => $monthlyStats['non_standard_patients'] ?? 0,
                    'total' => $monthlyStats['total_patients'] ?? 0
                ];
            }
            
            // Hitung total tahunan dan persentase capaian (izinkan >100% untuk over-achievement)
            $yearlyTotal = $this->calculateYearlyTotal($monthlyData);
            $achievementPercentage = $this->calculateAchievementPercentage(
                $yearlyTotal['total'], 
                $yearlyTarget['target']
            );
            
            return [
                'id' => $puskesmasId,
                'nama_puskesmas' => $puskesmasInfo['name'],
                'alamat' => $puskesmasInfo['address'] ?? '',
                'kode_puskesmas' => $puskesmasInfo['code'] ?? '',
                'sasaran' => $yearlyTarget['target'] ?? 0,
                'monthly_data' => $monthlyData,
                'yearly_total' => $yearlyTotal,
                'achievement_percentage' => $achievementPercentage,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            Log::error('PuskesmasFormatter: Error getting specific puskesmas data', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return null;
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
     * Isi data bulanan ke template
     */
    protected function fillMonthlyDataToTemplate($monthlyData)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        // Cari baris awal data (biasanya setelah header)
        $dataStartRow = $this->findDataStartRow();
        
        if ($dataStartRow) {
            for ($month = 1; $month <= 12; $month++) {
                $row = $dataStartRow + $month - 1;
                $data = $monthlyData[$month] ?? ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
                
                // Isi data berdasarkan struktur template
                $this->sheet->setCellValue('A' . $row, $month); // No
                $this->sheet->setCellValue('B' . $row, $months[$month]); // Bulan
                $this->sheet->setCellValue('C' . $row, $data['male']); // Laki-laki
                $this->sheet->setCellValue('D' . $row, $data['female']); // Perempuan
                $this->sheet->setCellValue('E' . $row, $data['total']); // Total
                $this->sheet->setCellValue('F' . $row, $data['standard']); // Standar
                $this->sheet->setCellValue('G' . $row, $data['non_standard']); // Tidak Standar
                
                // Hitung persentase standar (standar/total tidak bisa >100%)
                $percentage = $this->calculateStandardPercentage($data['standard'], $data['total']);
                // Set nilai persentase dalam format desimal (75% = 0.75)
                $this->sheet->setCellValue('H' . $row, $percentage / 100);
                // Terapkan format persentase untuk tampilan yang bersih
                $this->sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('0.00"%"');
            }
        }
    }
    
    /**
     * Cari baris awal untuk data bulanan
     */
    protected function findDataStartRow()
    {
        $highestRow = $this->sheet->getHighestRow();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $this->sheet->getCell('B' . $row)->getValue();
            if (is_string($cellValue) && (stripos($cellValue, 'BULAN') !== false || stripos($cellValue, 'MONTH') !== false)) {
                return $row + 1; // Baris setelah header
            }
        }
        
        return 7; // Default jika tidak ditemukan
    }
    
    /**
      * Isi data summary/total ke template
      */
     protected function fillSummaryDataToTemplate($puskesmasData)
     {
         // Cari area summary dan isi total tahunan
         $this->findAndFillCell('TOTAL TAHUNAN', $this->formatNumber($puskesmasData['yearly_total']['total']));
         $this->findAndFillCell('CAPAIAN', $puskesmasData['achievement_percentage'] . '%');
         $this->findAndFillCell('STANDAR TAHUNAN', $this->formatNumber($puskesmasData['yearly_total']['standard']));
     }

    /**
     * Tambahkan baris total untuk puskesmas
     */
    protected function addPuskesmasTotalRow(int $row, array $puskesmasData)
    {
        $yearlyTotal = $puskesmasData['yearly_total'] ?? [];
        
        $this->sheet->setCellValue('A' . $row, '');
        $this->sheet->setCellValue('B' . $row, 'TOTAL TAHUNAN');
        $this->sheet->setCellValue('C' . $row, $yearlyTotal['male'] ?? 0);
        $this->sheet->setCellValue('D' . $row, $yearlyTotal['female'] ?? 0);
        $this->sheet->setCellValue('E' . $row, $yearlyTotal['total'] ?? 0);
        $this->sheet->setCellValue('F' . $row, $yearlyTotal['standard'] ?? 0);
        $this->sheet->setCellValue('G' . $row, $yearlyTotal['non_standard'] ?? 0);
        
        // Persentase standar tahunan
        $total = $yearlyTotal['total'] ?? 0;
        $standard = $yearlyTotal['standard'] ?? 0;
        // Hitung persentase standar total (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        // Set nilai persentase dalam format desimal (75% = 0.75)
        $this->sheet->setCellValue('H' . $row, $percentage / 100);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('0.00"%"');
        
        // Style bold untuk baris total
        $this->sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
        
        // Background color untuk baris total
        $this->sheet->getStyle('A' . $row . ':H' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFFF99');
    }

    /**
     * Tambahkan informasi tambahan
     */
    protected function addPuskesmasAdditionalInfo(int $startRow, array $puskesmasData, int $year, string $diseaseType)
    {
        $row = $startRow;
        
        // Informasi capaian
        $this->sheet->setCellValue('A' . $row, 'INFORMASI CAPAIAN:');
        $this->sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        $this->sheet->setCellValue('A' . $row, 'Sasaran Tahunan:');
        $this->sheet->setCellValue('C' . $row, $this->formatNumber($puskesmasData['sasaran']));
        $row++;
        
        $this->sheet->setCellValue('A' . $row, 'Total Capaian:');
        $this->sheet->setCellValue('C' . $row, $this->formatNumber($puskesmasData['yearly_total']['total'] ?? 0));
        $row++;
        
        $this->sheet->setCellValue('A' . $row, 'Persentase Capaian:');
        $this->sheet->setCellValue('C' . $row, $puskesmasData['achievement_percentage'] . '%');
        $row++;
        
        // Status capaian
        $achievementStatus = $this->getAchievementStatus($puskesmasData['achievement_percentage']);
        $this->sheet->setCellValue('A' . $row, 'Status Capaian:');
        $this->sheet->setCellValue('C' . $row, $achievementStatus['text']);
        $this->sheet->getStyle('C' . $row)->getFont()->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color($achievementStatus['color'])
        );
        $row += 2;
        
        // Informasi update
        $this->sheet->setCellValue('A' . $row, 'Terakhir diperbarui:');
        $this->sheet->setCellValue('C' . $row, $puskesmasData['last_updated'] ?? date('Y-m-d H:i:s'));
        $this->sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setItalic(true)->setSize(9);
    }

    /**
     * Dapatkan status capaian berdasarkan persentase
     */
    protected function getAchievementStatus(float $percentage): array
    {
        if ($percentage >= 100) {
            return ['text' => 'SANGAT BAIK (≥100%)', 'color' => '008000']; // Green
        } elseif ($percentage >= 80) {
            return ['text' => 'BAIK (80-99%)', 'color' => '0066CC']; // Blue
        } elseif ($percentage >= 60) {
            return ['text' => 'CUKUP (60-79%)', 'color' => 'FF8C00']; // Orange
        } else {
            return ['text' => 'KURANG (<60%)', 'color' => 'FF0000']; // Red
        }
    }

    /**
     * Override apply styling untuk puskesmas template
     */
    protected function applyExcelStyling()
    {
        parent::applyExcelStyling();
        
        // Additional styling untuk template puskesmas
        // Border untuk tabel data
        $dataRange = 'A6:H' . ($this->sheet->getHighestRow());
        $this->sheet->getStyle($dataRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Auto-size semua kolom
        foreach (range('A', 'H') as $col) {
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Validasi parameter input untuk puskesmas
     */
    protected function validateInput(string $diseaseType, int $year, $puskesmasId = null): bool
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
     * Get filename untuk export puskesmas
     */
    public function getFilename(string $diseaseType, int $year, $puskesmasData = null): string
    {
        $diseaseLabel = $diseaseType === 'ht' ? 'Hipertensi' : 
                       ($diseaseType === 'dm' ? 'Diabetes' : 'HT-DM');
        
        if ($puskesmasData && isset($puskesmasData['nama_puskesmas'])) {
            $puskesmasName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $puskesmasData['nama_puskesmas']);
            return "Laporan_{$puskesmasName}_{$diseaseLabel}_{$year}.xlsx";
        }
        
        return "Template_Puskesmas_{$diseaseLabel}_{$year}.xlsx";
    }
}