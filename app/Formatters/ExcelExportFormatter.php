<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export Excel berdasarkan template all.xlsx, monthly.xlsx, quarterly.xlsx, dan puskesmas.xlsx
 * Mengikuti struktur yang telah ditentukan dengan header bertingkat dan data bulanan/triwulan
 */
class ExcelExportFormatter
{
    protected $statisticsService;
    protected $sheet;
    protected $currentRow = 7; // Mulai dari baris 7 untuk data
    
    // Mapping bulan ke kolom untuk data bulanan
    protected $monthColumns = [
        1 => ['D', 'E', 'F', 'G', 'H'],     // Jan: L, P, Total, TS, %S
        2 => ['I', 'J', 'K', 'L', 'M'],     // Feb: L, P, Total, TS, %S
        3 => ['N', 'O', 'P', 'Q', 'R'],     // Mar: L, P, Total, TS, %S
        4 => ['S', 'T', 'U', 'V', 'W'],     // Apr: L, P, Total, TS, %S
        5 => ['X', 'Y', 'Z', 'AA', 'AB'],   // May: L, P, Total, TS, %S
        6 => ['AC', 'AD', 'AE', 'AF', 'AG'], // Jun: L, P, Total, TS, %S
        7 => ['AH', 'AI', 'AJ', 'AK', 'AL'], // Jul: L, P, Total, TS, %S
        8 => ['AM', 'AN', 'AO', 'AP', 'AQ'], // Aug: L, P, Total, TS, %S
        9 => ['AR', 'AS', 'AT', 'AU', 'AV'], // Sep: L, P, Total, TS, %S
        10 => ['AW', 'AX', 'AY', 'AZ', 'BA'], // Oct: L, P, Total, TS, %S
        11 => ['BB', 'BC', 'BD', 'BE', 'BF'], // Nov: L, P, Total, TS, %S
        12 => ['BG', 'BH', 'BI', 'BJ', 'BK']  // Dec: L, P, Total, TS, %S
    ];
    
    // Mapping triwulan ke kolom
    protected $quarterColumns = [
        1 => ['BL', 'BM', 'BN', 'BO', 'BP'], // TW I: L, P, Total, TS, %S
        2 => ['BQ', 'BR', 'BS', 'BT', 'BU'], // TW II: L, P, Total, TS, %S
        3 => ['BV', 'BW', 'BX', 'BY', 'BZ'], // TW III: L, P, Total, TS, %S
        4 => ['CA', 'CB', 'CC', 'CD', 'CE']  // TW IV: L, P, Total, TS, %S
    ];
    
    // Kolom untuk total tahunan dan persentase
    protected $totalColumns = ['CF', 'CG', 'CH', 'CI', 'CJ']; // Total: L, P, Total, TS, %S

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Format spreadsheet untuk export all.xlsx (rekap tahunan komprehensif)
     */
    public function formatAllExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $puskesmasData = [])
    {
        try {
            $this->sheet = $spreadsheet->getActiveSheet();
            $this->sheet->setTitle('Laporan');
            
            // Clear any existing content
            $this->sheet->removeRow(1, $this->sheet->getHighestRow());
            $this->sheet->removeColumn('A', $this->sheet->getHighestColumn());
            
            // Reset current row
            $this->currentRow = 7;
            
            // Setup header struktur
            $this->setupHeaders($diseaseType, $year, 'all');
            
            // Isi data puskesmas
            $this->fillPuskesmasData($puskesmasData, $year, $diseaseType, 'all');
            
            // Apply styling
            $this->applyExcelStyling();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('Sistem Akudihatinya')
                ->setLastModifiedBy('Sistem Akudihatinya')
                ->setTitle('Laporan Statistik Kesehatan')
                ->setSubject('Laporan ' . ($diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes Melitus'))
                ->setDescription('Laporan statistik kesehatan tahun ' . $year)
                ->setKeywords('laporan,statistik,kesehatan')
                ->setCategory('Laporan');
            
            Log::info('Excel formatting completed successfully', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'data_rows' => count($puskesmasData)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error formatting Excel spreadsheet', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            throw $e;
        }
        
        return $spreadsheet;
    }

    /**
     * Format spreadsheet untuk export monthly.xlsx (laporan bulanan)
     */
    public function formatMonthlyExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $puskesmasData = [])
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Laporan');
        
        // Setup header struktur untuk monthly
        $this->setupHeaders($diseaseType, $year, 'monthly');
        
        // Isi data puskesmas untuk monthly
        $this->fillPuskesmasData($puskesmasData, $year, $diseaseType, 'monthly');
        
        // Apply styling
        $this->applyExcelStyling();
        
        return $spreadsheet;
    }

    /**
     * Format spreadsheet untuk export quarterly.xlsx (laporan triwulan)
     */
    public function formatQuarterlyExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $puskesmasData = [])
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Laporan');
        
        // Setup header struktur untuk quarterly
        $this->setupHeaders($diseaseType, $year, 'quarterly');
        
        // Isi data puskesmas untuk quarterly
        $this->fillPuskesmasData($puskesmasData, $year, $diseaseType, 'quarterly');
        
        // Apply styling
        $this->applyExcelStyling();
        
        return $spreadsheet;
    }

    /**
     * Format spreadsheet untuk export puskesmas.xlsx (template per puskesmas)
     */
    public function formatPuskesmasExcel(Spreadsheet $spreadsheet, string $diseaseType, int $year, $puskesmasData = null)
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle('Laporan');
        
        // Setup header struktur untuk puskesmas
        $this->setupHeaders($diseaseType, $year, 'puskesmas');
        
        // Isi metadata puskesmas jika ada
        if ($puskesmasData) {
            $this->fillSinglePuskesmasData($puskesmasData, $year, $diseaseType);
        }
        
        // Apply styling
        $this->applyExcelStyling();
        
        return $spreadsheet;
    }

    /**
     * Setup header struktur Excel sesuai spesifikasi
     */
    protected function setupHeaders(string $diseaseType, int $year, string $reportType)
    {
        // Baris 0: Judul
        $diseaseTypeLabel = $diseaseType === 'ht' ? 'Hipertensi' : ($diseaseType === 'dm' ? 'Diabetes Melitus' : 'Hipertensi dan Diabetes Melitus');
        $this->sheet->setCellValue('A1', "Pelayanan Kesehatan Pada Penderita {$diseaseTypeLabel}");
        
        // Baris 2: Header tingkat 1
        $this->sheet->setCellValue('A3', 'NO');
        $this->sheet->setCellValue('B3', 'NAMA PUSKESMAS');
        $this->sheet->setCellValue('C3', 'SASARAN');
        
        // Setup header berdasarkan jenis laporan
        switch ($reportType) {
            case 'all':
                $this->setupAllHeaders();
                break;
            case 'monthly':
                $this->setupMonthlyHeaders();
                break;
            case 'quarterly':
                $this->setupQuarterlyHeaders();
                break;
            case 'puskesmas':
                $this->setupPuskesmasHeaders();
                break;
        }
    }

    /**
     * Setup header untuk laporan all.xlsx (bulanan + triwulan + total)
     */
    protected function setupAllHeaders()
    {
        // Baris 3: Header tingkat 2 - Periode
        $months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 
                  'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        
        $col = 'D';
        foreach ($months as $month) {
            $this->sheet->setCellValue($col . '4', $month);
            $col = $this->incrementColumn($col, 5); // Skip 5 columns for each month
        }
        
        // Triwulan headers
        $quarters = ['TRIWULAN I', 'TRIWULAN II', 'TRIWULAN III', 'TRIWULAN IV'];
        foreach ($quarters as $i => $quarter) {
            $quarterCol = $this->quarterColumns[$i + 1][0];
            $this->sheet->setCellValue($quarterCol . '4', $quarter);
        }
        
        // Total tahunan
        $this->sheet->setCellValue($this->totalColumns[0] . '4', 'TOTAL TAHUNAN');
        
        // Baris 5: Header tingkat 4 - Kategori data
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header untuk laporan monthly.xlsx
     */
    protected function setupMonthlyHeaders()
    {
        $months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 
                  'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
        
        $col = 'D';
        foreach ($months as $month) {
            $this->sheet->setCellValue($col . '4', $month);
            $col = $this->incrementColumn($col, 5);
        }
        
        // Total tahunan
        $this->sheet->setCellValue($this->totalColumns[0] . '4', 'TOTAL TAHUNAN');
        
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header untuk laporan quarterly.xlsx
     */
    protected function setupQuarterlyHeaders()
    {
        $quarters = ['TRIWULAN I', 'TRIWULAN II', 'TRIWULAN III', 'TRIWULAN IV'];
        
        $col = 'D';
        foreach ($quarters as $quarter) {
            $this->sheet->setCellValue($col . '4', $quarter);
            $col = $this->incrementColumn($col, 5);
        }
        
        // Total tahunan
        $this->sheet->setCellValue($this->totalColumns[0] . '4', 'TOTAL TAHUNAN');
        
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header untuk template puskesmas.xlsx
     */
    protected function setupPuskesmasHeaders()
    {
        // Template dasar untuk puskesmas individual
        $this->sheet->setCellValue('D4', 'DATA BULANAN');
        $this->setupDataCategoryHeaders();
    }

    /**
     * Setup header kategori data (S, TS, %S, L, P, TOTAL)
     */
    protected function setupDataCategoryHeaders()
    {
        $categories = ['L', 'P', 'TOTAL', 'TS', '%S'];
        
        // Untuk setiap bulan
        foreach ($this->monthColumns as $month => $columns) {
            foreach ($categories as $i => $category) {
                if (isset($columns[$i])) {
                    $this->sheet->setCellValue($columns[$i] . '6', $category);
                }
            }
        }
        
        // Untuk setiap triwulan
        foreach ($this->quarterColumns as $quarter => $columns) {
            foreach ($categories as $i => $category) {
                if (isset($columns[$i])) {
                    $this->sheet->setCellValue($columns[$i] . '6', $category);
                }
            }
        }
        
        // Untuk total tahunan
        foreach ($categories as $i => $category) {
            if (isset($this->totalColumns[$i])) {
                $this->sheet->setCellValue($this->totalColumns[$i] . '6', $category);
            }
        }
    }

    /**
     * Isi data puskesmas ke dalam spreadsheet
     */
    protected function fillPuskesmasData(array $puskesmasData, int $year, string $diseaseType, string $reportType)
    {
        $startRow = 9; // Mulai dari baris 9 (setelah header)
        $currentRow = $startRow;
        
        // Hapus baris TOTAL yang sudah ada di template (baris 34) dan baris kosong sebelumnya
        $this->sheet->removeRow(34, 7); // Hapus baris 34-40 yang tidak diperlukan
        
        foreach ($puskesmasData as $index => $data) {
            // Kolom A: NO
            $this->sheet->setCellValue('A' . $currentRow, $index + 1);
            
            // Kolom B: NAMA PUSKESMAS
            $this->sheet->setCellValue('B' . $currentRow, $data['nama_puskesmas'] ?? '');
            
            // Kolom C: SASARAN
            $this->sheet->setCellValue('C' . $currentRow, $data['sasaran'] ?? 0);
            
            // Isi data berdasarkan jenis laporan
            switch ($reportType) {
                case 'all':
                    $this->fillAllData($currentRow, $data, $year, $diseaseType);
                    break;
                case 'monthly':
                    $this->fillMonthlyData($currentRow, $data, $year, $diseaseType);
                    break;
                case 'quarterly':
                    $this->fillQuarterlyData($currentRow, $data, $year, $diseaseType);
                    break;
            }
            
            $currentRow++;
        }
        
        // Tambah baris total di posisi yang tepat setelah semua data puskesmas
        $this->addTotalRow($currentRow, $puskesmasData, $reportType);
        
        // Update current row untuk styling
        $this->currentRow = $currentRow + 1;
    }

    /**
     * Isi data untuk laporan all.xlsx (bulanan + triwulan + total)
     */
    protected function fillAllData(int $row, array $data, int $year, string $diseaseType)
    {
        // Data bulanan
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $data['monthly_data'][$month] ?? [];
            $this->fillMonthDataToColumns($row, $month, $monthData);
        }
        
        // Data triwulan (dihitung dari data bulanan)
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $this->calculateQuarterData($data['monthly_data'] ?? [], $quarter);
            $this->fillQuarterDataToColumns($row, $quarter, $quarterData);
        }
        
        // Total tahunan
        $totalData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
        $this->fillTotalDataToColumns($row, $totalData);
    }

    /**
     * Isi data untuk laporan monthly.xlsx
     */
    protected function fillMonthlyData(int $row, array $data, int $year, string $diseaseType)
    {
        // Data bulanan
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $data['monthly_data'][$month] ?? [];
            $this->fillMonthDataToColumns($row, $month, $monthData);
        }
        
        // Total tahunan
        $totalData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
        $this->fillTotalDataToColumns($row, $totalData);
    }

    /**
     * Isi data untuk laporan quarterly.xlsx
     */
    protected function fillQuarterlyData(int $row, array $data, int $year, string $diseaseType)
    {
        // Data triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $this->calculateQuarterData($data['monthly_data'] ?? [], $quarter);
            $this->fillQuarterDataToColumns($row, $quarter, $quarterData);
        }
        
        // Total tahunan
        $totalData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
        $this->fillTotalDataToColumns($row, $totalData);
    }

    /**
     * Isi data untuk single puskesmas template
     */
    protected function fillSinglePuskesmasData($puskesmasData, int $year, string $diseaseType)
    {
        // Baris 3: Nama puskesmas
        $this->sheet->setCellValue('B3', $puskesmasData['nama_puskesmas'] ?? '');
        
        // Baris 2: Sasaran (jika ada)
        if (isset($puskesmasData['sasaran'])) {
            $this->sheet->setCellValue('C2', $puskesmasData['sasaran']);
        }
    }

    /**
     * Isi data bulanan ke kolom yang sesuai
     */
    protected function fillMonthDataToColumns(int $row, int $month, array $monthData)
    {
        if (!isset($this->monthColumns[$month])) {
            return;
        }
        
        $columns = $this->monthColumns[$month];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $row, $monthData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $row, $monthData['female'] ?? 0);
        
        // TOTAL
        $total = ($monthData['male'] ?? 0) + ($monthData['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($columns[3] . $row, $monthData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $monthData['standard'] ?? 0;
        // Hitung persentase standar (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        $this->sheet->setCellValue($columns[4] . $row, $percentage . '%');
    }

    /**
     * Isi data triwulan ke kolom yang sesuai
     */
    protected function fillQuarterDataToColumns(int $row, int $quarter, array $quarterData)
    {
        if (!isset($this->quarterColumns[$quarter])) {
            return;
        }
        
        $columns = $this->quarterColumns[$quarter];
        
        // L (Laki-laki)
        $this->sheet->setCellValue($columns[0] . $row, $quarterData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($columns[1] . $row, $quarterData['female'] ?? 0);
        
        // TOTAL
        $total = ($quarterData['male'] ?? 0) + ($quarterData['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($columns[3] . $row, $quarterData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $quarterData['standard'] ?? 0;
        // Hitung persentase standar (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        $this->sheet->setCellValue($columns[4] . $row, $percentage . '%');
    }

    /**
     * Isi data total tahunan ke kolom yang sesuai
     */
    protected function fillTotalDataToColumns(int $row, array $totalData)
    {
        // L (Laki-laki)
        $this->sheet->setCellValue($this->totalColumns[0] . $row, $totalData['male'] ?? 0);
        
        // P (Perempuan)
        $this->sheet->setCellValue($this->totalColumns[1] . $row, $totalData['female'] ?? 0);
        
        // TOTAL
        $total = ($totalData['male'] ?? 0) + ($totalData['female'] ?? 0);
        $this->sheet->setCellValue($this->totalColumns[2] . $row, $total);
        
        // TS (Tidak Standar)
        $this->sheet->setCellValue($this->totalColumns[3] . $row, $totalData['non_standard'] ?? 0);
        
        // %S (Persentase Standar)
        $standard = $totalData['standard'] ?? 0;
        // Hitung persentase standar total (standar/total tidak bisa >100%)
        $percentage = $this->calculateStandardPercentage($standard, $total);
        $this->sheet->setCellValue($this->totalColumns[4] . $row, $percentage . '%');
    }

    /**
     * Hitung data triwulan dari data bulanan
     */
    protected function calculateQuarterData(array $monthlyData, int $quarter): array
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
            'non_standard' => 0
        ];
        
        foreach ($months as $month) {
            if (isset($monthlyData[$month])) {
                $quarterData['male'] += $monthlyData[$month]['male'] ?? 0;
                $quarterData['female'] += $monthlyData[$month]['female'] ?? 0;
                $quarterData['standard'] += $monthlyData[$month]['standard'] ?? 0;
                $quarterData['non_standard'] += $monthlyData[$month]['non_standard'] ?? 0;
            }
        }
        
        return $quarterData;
    }

    /**
     * Hitung total tahunan dari data bulanan
     */
    protected function calculateYearlyTotal(array $monthlyData): array
    {
        $totalData = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0
        ];
        
        for ($month = 1; $month <= 12; $month++) {
            if (isset($monthlyData[$month])) {
                $totalData['male'] += $monthlyData[$month]['male'] ?? 0;
                $totalData['female'] += $monthlyData[$month]['female'] ?? 0;
                $totalData['standard'] += $monthlyData[$month]['standard'] ?? 0;
                $totalData['non_standard'] += $monthlyData[$month]['non_standard'] ?? 0;
            }
        }
        
        return $totalData;
    }

    /**
     * Tambah baris total di akhir data
     */
    protected function addTotalRow(int $row, array $puskesmasData, string $reportType)
    {
        // Insert baris baru untuk TOTAL
        $this->sheet->insertNewRowBefore($row, 1);
        
        $this->sheet->setCellValue('A' . $row, 'TOTAL');
        $this->sheet->setCellValue('B' . $row, 'KESELURUHAN');
        
        // Hitung total sasaran
        $totalSasaran = array_sum(array_column($puskesmasData, 'sasaran'));
        $this->sheet->setCellValue('C' . $row, $totalSasaran);
        
        // Hitung dan isi total data berdasarkan jenis laporan
        $this->fillTotalRowData($row, $puskesmasData, $reportType);
        
        // Apply bold styling untuk baris total
        $this->sheet->getStyle('A' . $row . ':' . $this->sheet->getHighestColumn() . $row)
            ->getFont()
            ->setBold(true);
            
        // Apply background color untuk baris total
        $this->sheet->getStyle('A' . $row . ':' . $this->sheet->getHighestColumn() . $row)
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E6E6FA');
    }

    /**
     * Apply styling ke Excel
     */
    protected function applyExcelStyling()
    {
        // Style untuk judul (baris 1)
        $this->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Style untuk header (baris 4-8)
        $headerRange = 'A4:' . $this->sheet->getHighestColumn() . '8';
        $this->sheet->getStyle($headerRange)->getFont()->setBold(true);
        $this->sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        // Border untuk semua data (mulai dari header sampai akhir data)
        $dataRange = 'A4:' . $this->sheet->getHighestColumn() . $this->sheet->getHighestRow();
        $this->sheet->getStyle($dataRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Background color untuk header
        $this->sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E6E6FA');
        
        // Auto-size columns
        $highestColumn = $this->sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Merge cells untuk judul
        $this->sheet->mergeCells('A1:' . $this->sheet->getHighestColumn() . '1');
        $this->sheet->mergeCells('A2:' . $this->sheet->getHighestColumn() . '2');
    }

    /**
     * Isi data total untuk baris TOTAL berdasarkan jenis laporan
     */
    protected function fillTotalRowData(int $row, array $puskesmasData, string $reportType)
    {
        switch ($reportType) {
            case 'all':
                $this->fillTotalAllData($row, $puskesmasData);
                break;
            case 'monthly':
                $this->fillTotalMonthlyData($row, $puskesmasData);
                break;
            case 'quarterly':
                $this->fillTotalQuarterlyData($row, $puskesmasData);
                break;
        }
    }
    
    /**
     * Isi total data untuk laporan all.xlsx
     */
    protected function fillTotalAllData(int $row, array $puskesmasData)
    {
        // Hitung total untuk setiap bulan
        for ($month = 1; $month <= 12; $month++) {
            $monthTotal = $this->calculateMonthTotal($puskesmasData, $month);
            $this->fillMonthDataToColumns($row, $month, $monthTotal);
        }
        
        // Hitung total untuk setiap triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterTotal = $this->calculateQuarterTotal($puskesmasData, $quarter);
            $this->fillQuarterDataToColumns($row, $quarter, $quarterTotal);
        }
        
        // Hitung total tahunan
        $yearlyTotal = $this->calculateYearlyTotalFromAll($puskesmasData);
        $this->fillTotalDataToColumns($row, $yearlyTotal);
    }
    
    /**
     * Isi total data untuk laporan monthly.xlsx
     */
    protected function fillTotalMonthlyData(int $row, array $puskesmasData)
    {
        // Hitung total untuk setiap bulan
        for ($month = 1; $month <= 12; $month++) {
            $monthTotal = $this->calculateMonthTotal($puskesmasData, $month);
            $this->fillMonthDataToColumns($row, $month, $monthTotal);
        }
        
        // Hitung total tahunan
        $yearlyTotal = $this->calculateYearlyTotalFromAll($puskesmasData);
        $this->fillTotalDataToColumns($row, $yearlyTotal);
    }
    
    /**
     * Isi total data untuk laporan quarterly.xlsx
     */
    protected function fillTotalQuarterlyData(int $row, array $puskesmasData)
    {
        // Hitung total untuk setiap triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterTotal = $this->calculateQuarterTotal($puskesmasData, $quarter);
            $this->fillQuarterDataToColumns($row, $quarter, $quarterTotal);
        }
        
        // Hitung total tahunan
        $yearlyTotal = $this->calculateYearlyTotalFromAll($puskesmasData);
        $this->fillTotalDataToColumns($row, $yearlyTotal);
    }
    
    /**
     * Hitung total bulanan dari semua puskesmas
     */
    protected function calculateMonthTotal(array $puskesmasData, int $month): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $monthData = $data['monthly_data'][$month] ?? [];
            $total['male'] += $monthData['male'] ?? 0;
            $total['female'] += $monthData['female'] ?? 0;
            $total['standard'] += $monthData['standard'] ?? 0;
            $total['non_standard'] += $monthData['non_standard'] ?? 0;
        }
        
        return $total;
    }
    
    /**
     * Hitung total triwulan dari semua puskesmas
     */
    protected function calculateQuarterTotal(array $puskesmasData, int $quarter): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $quarterData = $this->calculateQuarterData($data['monthly_data'] ?? [], $quarter);
            $total['male'] += $quarterData['male'] ?? 0;
            $total['female'] += $quarterData['female'] ?? 0;
            $total['standard'] += $quarterData['standard'] ?? 0;
            $total['non_standard'] += $quarterData['non_standard'] ?? 0;
        }
        
        return $total;
    }
    
    /**
     * Hitung total tahunan dari semua puskesmas
     */
    protected function calculateYearlyTotalFromAll(array $puskesmasData): array
    {
        $total = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0];
        
        foreach ($puskesmasData as $data) {
            $yearlyData = $this->calculateYearlyTotal($data['monthly_data'] ?? []);
            $total['male'] += $yearlyData['male'] ?? 0;
            $total['female'] += $yearlyData['female'] ?? 0;
            $total['standard'] += $yearlyData['standard'] ?? 0;
            $total['non_standard'] += $yearlyData['non_standard'] ?? 0;
        }
        
        return $total;
    }

    /**
     * Helper function untuk increment kolom Excel
     */
    protected function incrementColumn(string $column, int $increment = 1): string
    {
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column);
        $newColumnIndex = $columnIndex + $increment;
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($newColumnIndex);
    }
}