<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export puskesmas.xlsx - Template Per Puskesmas
 * Template individu untuk masing-masing puskesmas
 */
class PuskesmasFormatter extends ExcelExportFormatter
{
    public function __construct(StatisticsService $statisticsService)
    {
        parent::__construct($statisticsService);
    }

    /**
     * Format data untuk export puskesmas.xlsx
     * 
     * @param string $diseaseType Jenis penyakit (ht/dm)
     * @param int $year Tahun laporan
     * @param array $additionalData Data tambahan termasuk puskesmas_id
     * @return Spreadsheet
     */
    public function format(string $diseaseType = 'ht', int $year = null, array $additionalData = []): Spreadsheet
    {
        try {
            $year = $year ?? date('Y');
            $puskesmasId = $additionalData['puskesmas_id'] ?? null;
            
            // Validasi input
            $this->validateInput($diseaseType, $year, $puskesmasId);
            
            // Buat spreadsheet baru
            $spreadsheet = new Spreadsheet();
            
            // Ambil data puskesmas spesifik
            $puskesmasData = $this->getPuskesmasSpecificData($puskesmasId, $diseaseType, $year);
            
            // Format menggunakan parent method
            $this->formatPuskesmasExcel($spreadsheet, $diseaseType, $year, $puskesmasData);
            
            Log::info('PuskesmasFormatter: Successfully formatted puskesmas.xlsx', [
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
     * Format template kosong untuk puskesmas (tanpa data spesifik)
     */
    public function formatTemplate(string $diseaseType = 'ht', int $year = null): Spreadsheet
    {
        try {
            $year = $year ?? date('Y');
            
            // Buat spreadsheet baru
            $spreadsheet = new Spreadsheet();
            
            // Format template kosong
            $this->formatPuskesmasExcel($spreadsheet, $diseaseType, $year, null);
            
            Log::info('PuskesmasFormatter: Successfully formatted template puskesmas.xlsx', [
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return $spreadsheet;
            
        } catch (\Exception $e) {
            Log::error('PuskesmasFormatter: Error formatting template puskesmas.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
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
            
            // Hitung total tahunan dan persentase capaian
            $yearlyTotal = $this->calculateYearlyTotal($monthlyData);
            $achievementPercentage = $yearlyTarget['target'] > 0 ? 
                round(($yearlyTotal['total'] / $yearlyTarget['target']) * 100, 2) : 0;
            
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
     * Override setup headers untuk template puskesmas
     */
    protected function setupPuskesmasHeaders()
    {
        // Baris 1: Judul utama
        $this->sheet->setCellValue('A1', 'LAPORAN PELAYANAN KESEHATAN PUSKESMAS');
        $this->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Baris 2: Informasi puskesmas (akan diisi kemudian)
        $this->sheet->setCellValue('A2', 'Nama Puskesmas:');
        $this->sheet->setCellValue('A3', 'Sasaran Tahunan:');
        $this->sheet->setCellValue('A4', 'Tahun Laporan:');
        
        // Header tabel data mulai dari baris 6
        $this->sheet->setCellValue('A6', 'NO');
        $this->sheet->setCellValue('B6', 'BULAN');
        $this->sheet->setCellValue('C6', 'LAKI-LAKI');
        $this->sheet->setCellValue('D6', 'PEREMPUAN');
        $this->sheet->setCellValue('E6', 'TOTAL');
        $this->sheet->setCellValue('F6', 'STANDAR');
        $this->sheet->setCellValue('G6', 'TIDAK STANDAR');
        $this->sheet->setCellValue('H6', '% STANDAR');
        
        // Style untuk header
        $this->sheet->getStyle('A6:H6')->getFont()->setBold(true);
        $this->sheet->getStyle('A6:H6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Merge cells untuk judul
        $this->sheet->mergeCells('A1:H1');
    }

    /**
     * Override untuk mengisi data puskesmas spesifik
     */
    protected function fillSinglePuskesmasData($puskesmasData, int $year, string $diseaseType)
    {
        if (!$puskesmasData) {
            // Isi template kosong
            $this->sheet->setCellValue('B2', '[NAMA PUSKESMAS]');
            $this->sheet->setCellValue('B3', '[SASARAN TAHUNAN]');
            $this->sheet->setCellValue('B4', $year);
            return;
        }
        
        // Isi informasi puskesmas
        $this->sheet->setCellValue('B2', $puskesmasData['nama_puskesmas']);
        $this->sheet->setCellValue('B3', number_format($puskesmasData['sasaran']));
        $this->sheet->setCellValue('B4', $year);
        
        // Isi data bulanan
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        $row = 7; // Mulai dari baris 7
        
        foreach ($months as $monthNum => $monthName) {
            $monthData = $puskesmasData['monthly_data'][$monthNum] ?? [];
            
            $this->sheet->setCellValue('A' . $row, $monthNum);
            $this->sheet->setCellValue('B' . $row, $monthName);
            $this->sheet->setCellValue('C' . $row, $monthData['male'] ?? 0);
            $this->sheet->setCellValue('D' . $row, $monthData['female'] ?? 0);
            $this->sheet->setCellValue('E' . $row, $monthData['total'] ?? 0);
            $this->sheet->setCellValue('F' . $row, $monthData['standard'] ?? 0);
            $this->sheet->setCellValue('G' . $row, $monthData['non_standard'] ?? 0);
            
            // Hitung persentase standar
            $total = $monthData['total'] ?? 0;
            $standard = $monthData['standard'] ?? 0;
            $percentage = $total > 0 ? round(($standard / $total) * 100, 2) : 0;
            $this->sheet->setCellValue('H' . $row, $percentage . '%');
            
            $row++;
        }
        
        // Tambahkan baris total
        $this->addPuskesmasTotalRow($row, $puskesmasData);
        
        // Tambahkan informasi tambahan
        $this->addPuskesmasAdditionalInfo($row + 2, $puskesmasData, $year, $diseaseType);
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
        $percentage = $total > 0 ? round(($standard / $total) * 100, 2) : 0;
        $this->sheet->setCellValue('H' . $row, $percentage . '%');
        
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
        $this->sheet->setCellValue('C' . $row, number_format($puskesmasData['sasaran']));
        $row++;
        
        $this->sheet->setCellValue('A' . $row, 'Total Capaian:');
        $this->sheet->setCellValue('C' . $row, number_format($puskesmasData['yearly_total']['total'] ?? 0));
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