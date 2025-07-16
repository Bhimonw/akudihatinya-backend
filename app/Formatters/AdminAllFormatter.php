<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Services\Statistics\StatisticsService;
use App\Services\Statistics\RealTimeStatisticsService;
use App\Traits\Calculation\PercentageCalculationTrait;
use Illuminate\Support\Facades\Log;

/**
 * Formatter untuk export all.xlsx - Rekap Tahunan Komprehensif
 * Menyajikan rekap data bulanan, triwulan, dan total tahunan untuk setiap puskesmas
 */
class AdminAllFormatter extends ExcelExportFormatter
{
    use PercentageCalculationTrait;
    
    protected $realTimeStatisticsService;
    
    public function __construct(StatisticsService $statisticsService, RealTimeStatisticsService $realTimeStatisticsService)
    {
        parent::__construct($statisticsService);
        $this->realTimeStatisticsService = $realTimeStatisticsService;
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
        
        // Isi informasi header - ganti placeholder
        $this->replacePlaceholder('<tipe_penyakit>', $diseaseType === 'ht' ? 'HIPERTENSI' : 'DIABETES MELITUS');
        $this->replacePlaceholder('<tahun>', $year);
        
        // Buat template responsif sesuai jumlah puskesmas
        $this->makeTemplateResponsive(count($puskesmasData));
        
        // Cari baris awal data puskesmas
        $dataStartRow = $this->findAllDataStartRow();
        
        if ($dataStartRow && !empty($puskesmasData)) {
            $currentRow = $dataStartRow;
            
            // Hitung berapa baris yang tersedia di template
            $templateRows = $this->countAvailableTemplateRows($dataStartRow);
            $puskesmasCount = count($puskesmasData);
            
            // Template analysis completed
            
            // Jika jumlah puskesmas lebih banyak dari baris template, tambah baris baru
            if ($puskesmasCount > $templateRows) {
                $insertPosition = $dataStartRow + $templateRows;
                $rowsToAdd = $puskesmasCount - $templateRows;
                // Adding additional rows for extra puskesmas
                $this->insertAdditionalRows($insertPosition, $rowsToAdd);
            }
            
            // Isi data puskesmas dan hitung posisi dinamis untuk summary
            $lastDataRow = $currentRow;
            foreach ($puskesmasData as $index => $puskesmas) {
                $this->fillPuskesmasRowInAllTemplate($currentRow, $index + 1, $puskesmas);
                $lastDataRow = $currentRow; // Track baris data terakhir
                $currentRow++;
            }
            
            // Posisi summary dinamis: selalu setelah baris data terakhir + 1 baris kosong
            $summaryRow = $lastDataRow + 2;
            
            // Bersihkan baris template yang tidak terpakai untuk membuat tabel lebih dinamis
            $this->cleanUnusedTemplateRows($currentRow, $summaryRow - 1);
            
            // Tambahkan total keseluruhan di posisi dinamis
            $this->addAllTemplateSummary($summaryRow, $puskesmasData);
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
            $cellA = $this->sheet->getCell('A' . $row)->getValue();
            
            // Cari marker <mulai > yang menandakan awal data
            if (is_string($cellA) && stripos($cellA, '<mulai') !== false) {
                // Found data start marker
                return $row; // Baris dengan marker <mulai >
            }
        }
        
        // Data start marker not found, using default
        return 9; // Default berdasarkan struktur template yang diketahui
    }
    
    /**
     * Hitung berapa baris yang tersedia di template untuk data puskesmas
     */
    protected function countAvailableTemplateRows($dataStartRow)
    {
        $highestRow = $this->sheet->getHighestRow();
        $availableRows = 0;
        
        // Hitung baris kosong yang tersedia mulai dari dataStartRow
        // Berdasarkan template, biasanya ada sekitar 6-7 baris kosong untuk data
        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $cellA = $this->sheet->getCell('A' . $row)->getValue();
            $cellB = $this->sheet->getCell('B' . $row)->getValue();
            $cellC = $this->sheet->getCell('C' . $row)->getValue();
            
            // Jika menemukan baris yang berisi data atau marker tertentu, hentikan
            if (!empty($cellA) && !in_array($cellA, ['<mulai >', '<mulai>', 'mulai'])) {
                break;
            }
            if (!empty($cellB) && stripos($cellB, 'TOTAL') !== false) {
                break;
            }
            
            // Jika semua cell kosong dan sudah melewati batas wajar, hentikan
            if (empty($cellA) && empty($cellB) && empty($cellC) && $availableRows > 10) {
                break;
            }
            
            $availableRows++;
        }
        
        // Batasi maksimal 10 baris untuk menghindari penghitungan yang berlebihan
        $availableRows = min($availableRows, 10);
        
        // Counted available template rows
        
        return max(1, $availableRows); // Minimal 1 baris
    }
    
    /**
     * Tambah baris baru untuk data puskesmas tambahan
     */
    protected function insertAdditionalRows($insertPosition, $rowsToAdd)
    {
        for ($i = 0; $i < $rowsToAdd; $i++) {
            $this->sheet->insertNewRowBefore($insertPosition, 1);
        }
    }
    
    /**
     * Bersihkan baris template yang tidak terpakai untuk membuat tabel lebih dinamis
     * Menghapus baris kosong antara data terakhir dan summary
     */
    protected function cleanUnusedTemplateRows($startRow, $endRow)
    {
        if ($startRow >= $endRow) {
            return; // Tidak ada baris yang perlu dibersihkan
        }
        
        // Hapus baris dari bawah ke atas untuk menghindari pergeseran indeks
        for ($row = $endRow; $row >= $startRow; $row--) {
            // Periksa apakah baris kosong atau hanya berisi template placeholder
            $cellA = $this->sheet->getCell('A' . $row)->getValue();
            $cellB = $this->sheet->getCell('B' . $row)->getValue();
            $cellC = $this->sheet->getCell('C' . $row)->getValue();
            
            // Hapus baris jika kosong atau berisi placeholder template
            if (empty($cellA) && empty($cellB) && empty($cellC)) {
                $this->sheet->removeRow($row, 1);
            }
        }
    }
    
    /**
     * Isi satu baris data puskesmas di template all.xlsx
     */
    protected function fillPuskesmasRowInAllTemplate($row, $no, $puskesmasData)
    {
        // Hitung total tahunan dan triwulan
        $yearlyTotal = $this->calculateYearlyTotal($puskesmasData['monthly_data']);
        $quarterlyData = $this->calculateQuarterlyData($puskesmasData['monthly_data']);
        
        // Isi data dasar
        $this->sheet->setCellValue('A' . $row, $no); // No
        $this->sheet->setCellValue('B' . $row, $puskesmasData['nama_puskesmas']); // Nama Puskesmas
        $this->sheet->setCellValue('C' . $row, $puskesmasData['sasaran']); // Sasaran
        
        // Kolom mulai dari D untuk data bulanan dan triwulan
        $currentCol = 'D';
        
        // Isi data Triwulan I (Januari-Maret) - kolom D sampai T
        $currentCol = $this->fillQuarterData($row, $currentCol, $puskesmasData['monthly_data'], [1, 2, 3], $quarterlyData[1], $puskesmasData['sasaran']);
        
        // Isi data Triwulan II (April-Juni) - kolom U sampai AK
        $currentCol = $this->fillQuarterData($row, $currentCol, $puskesmasData['monthly_data'], [4, 5, 6], $quarterlyData[2], $puskesmasData['sasaran']);
        
        // Isi data Triwulan III (Juli-September) - kolom AL sampai BB
        $currentCol = $this->fillQuarterData($row, $currentCol, $puskesmasData['monthly_data'], [7, 8, 9], $quarterlyData[3], $puskesmasData['sasaran']);
        
        // Isi data Triwulan IV (Oktober-Desember) - kolom BC sampai BS
        $currentCol = $this->fillQuarterData($row, $currentCol, $puskesmasData['monthly_data'], [10, 11, 12], $quarterlyData[4], $puskesmasData['sasaran']);
        
        // Isi total tahunan - kolom BT sampai BY
        $this->fillYearlyTotalData($row, $currentCol, $yearlyTotal, $puskesmasData['sasaran']);
    }
    
    /**
     * Hitung total tahunan dari data bulanan dengan format konsisten seperti dashboard
     * Menggunakan data bulan terakhir yang memiliki data, bukan akumulasi
     */
    protected function calculateYearlyTotal(array $monthlyData): array
    {
        // Cari bulan terakhir yang memiliki data (seperti dashboard)
        $lastMonthWithData = null;
        for ($month = 12; $month >= 1; $month--) {
            $data = $monthlyData[$month] ?? ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
            if ((int)($data['total'] ?? 0) > 0) {
                $lastMonthWithData = $data;
                break;
            }
        }
        
        // Jika tidak ada data, return zero
        if (!$lastMonthWithData) {
            return ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
        }
        
        // Format data seperti dashboard - gunakan data bulan terakhir
        return [
            'male' => (int)($lastMonthWithData['male'] ?? 0),
            'female' => (int)($lastMonthWithData['female'] ?? 0),
            'standard' => (int)($lastMonthWithData['standard'] ?? 0),
            'non_standard' => (int)($lastMonthWithData['non_standard'] ?? 0),
            'total' => (int)($lastMonthWithData['total'] ?? 0)
        ];
    }
    
    /**
     * Hitung data triwulan dari data bulanan dengan format konsisten seperti dashboard
     * Menggunakan data bulan terakhir dalam setiap triwulan yang memiliki data
     */
    protected function calculateQuarterlyData(array $monthlyData): array
    {
        $quarters = [
            1 => [1, 2, 3],    // Q1: Jan-Mar
            2 => [4, 5, 6],    // Q2: Apr-Jun
            3 => [7, 8, 9],    // Q3: Jul-Sep
            4 => [10, 11, 12]  // Q4: Oct-Dec
        ];
        
        $quarterlyData = [];
        
        foreach ($quarters as $quarter => $months) {
            // Cari bulan terakhir dalam triwulan yang memiliki data (seperti dashboard)
            $lastMonthWithDataInQuarter = null;
            
            // Cek dari bulan terakhir ke bulan pertama dalam triwulan
            for ($i = count($months) - 1; $i >= 0; $i--) {
                $month = $months[$i];
                $data = $monthlyData[$month] ?? ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
                
                if ((int)($data['total'] ?? 0) > 0) {
                    $lastMonthWithDataInQuarter = $data;
                    break;
                }
            }
            
            // Jika tidak ada data dalam triwulan, gunakan zero
            if (!$lastMonthWithDataInQuarter) {
                $quarterlyData[$quarter] = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
            } else {
                // Format data seperti dashboard - gunakan data bulan terakhir dalam triwulan
                $quarterlyData[$quarter] = [
                    'male' => (int)($lastMonthWithDataInQuarter['male'] ?? 0),
                    'female' => (int)($lastMonthWithDataInQuarter['female'] ?? 0),
                    'standard' => (int)($lastMonthWithDataInQuarter['standard'] ?? 0),
                    'non_standard' => (int)($lastMonthWithDataInQuarter['non_standard'] ?? 0),
                    'total' => (int)($lastMonthWithDataInQuarter['total'] ?? 0)
                ];
            }
        }
        
        return $quarterlyData;
    }
    
    /**
     * Isi data untuk satu triwulan (3 bulan + total triwulan)
     * Menggunakan format konsisten seperti dashboard
     */
    protected function fillQuarterData($row, $startCol, $monthlyData, $months, $quarterTotal, $yearlyTarget = 0)
    {
        $currentCol = $startCol;
        
        // Isi data untuk 3 bulan dalam triwulan
        foreach ($months as $month) {
            $monthData = $monthlyData[$month] ?? ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
            
            // Format data seperti dashboard - konversi ke string untuk konsistensi
            $formattedData = [
                'male_patients' => (string)($monthData['male'] ?? 0),
                'female_patients' => (string)($monthData['female'] ?? 0),
                'total_patients' => (string)($monthData['total'] ?? 0),
                'standard_patients' => (string)($monthData['standard'] ?? 0),
                'non_standard_patients' => (string)($monthData['non_standard'] ?? 0),
                'target' => (string)$yearlyTarget,
                'achievement_percentage' => $this->calculateAchievementPercentage($monthData['standard'] ?? 0, $yearlyTarget)
            ];
            
            // L, P, Total, TS, %S untuk setiap bulan
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedData['male_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedData['female_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedData['total_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedData['non_standard_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            // Set nilai persentase dalam format desimal (75% = 0.75)
            $this->sheet->setCellValue($currentCol . $row, $formattedData['achievement_percentage']);
            // Terapkan format persentase untuk tampilan yang bersih
            $this->sheet->getStyle($currentCol . $row)->getNumberFormat()->setFormatCode('0.00"%"');
            $currentCol = $this->getNextColumn($currentCol);
        }
        
        // Format data triwulan seperti dashboard
        $formattedQuarterData = [
            'standard_patients' => (string)($quarterTotal['standard'] ?? 0),
            'non_standard_patients' => (string)($quarterTotal['non_standard'] ?? 0),
            'target' => (string)$yearlyTarget,
            'achievement_percentage' => $this->calculateAchievementPercentage($quarterTotal['standard'] ?? 0, $yearlyTarget)
        ];
        
        // Isi total triwulan (S, TS, %S)
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedQuarterData['standard_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedQuarterData['non_standard_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        // Set nilai persentase dalam format desimal (75% = 0.75)
        $this->sheet->setCellValue($currentCol . $row, $formattedQuarterData['achievement_percentage']);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle($currentCol . $row)->getNumberFormat()->setFormatCode('0.00"%"');
        $currentCol = $this->getNextColumn($currentCol);
        
        return $currentCol;
    }
    
    /**
     * Ganti placeholder di seluruh worksheet
     */
    protected function replacePlaceholder($placeholder, $value)
    {
        $highestRow = $this->sheet->getHighestRow();
        $highestColumn = $this->sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cellValue = $this->sheet->getCell($col . $row)->getValue();
                if (is_string($cellValue) && strpos($cellValue, $placeholder) !== false) {
                    $newValue = str_replace($placeholder, $value, $cellValue);
                    $this->sheet->setCellValue($col . $row, $newValue);
                    Log::info("Replaced placeholder {$placeholder} with {$value} at {$col}{$row}");
                }
            }
        }
    }
    
    /**
     * Buat template responsif sesuai jumlah puskesmas
     */
    protected function makeTemplateResponsive($puskesmasCount)
    {
        // Jika jumlah puskesmas lebih dari template default (10), 
        // duplikasi kolom untuk menampung semua data
        $defaultColumns = 26; // A-Z
        $columnsNeeded = 3 + ($puskesmasCount * 6); // 3 kolom awal + 6 kolom per puskesmas
        
        if ($columnsNeeded > $defaultColumns) {
            // Extend kolom jika diperlukan
            $this->extendColumns($columnsNeeded);
        }
        
        // Sesuaikan header kolom berdasarkan jumlah puskesmas
        $this->adjustColumnHeaders($puskesmasCount);
    }
    
    /**
     * Extend kolom template jika diperlukan
     */
    protected function extendColumns($columnsNeeded)
    {
        $currentHighestColumn = $this->sheet->getHighestColumn();
        $currentColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($currentHighestColumn);
        
        // Tambah kolom jika diperlukan
        while ($currentColumnIndex < $columnsNeeded) {
            $currentColumnIndex++;
            $newColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColumnIndex);
            
            // Copy style dari kolom sebelumnya
            $prevColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColumnIndex - 1);
            $this->sheet->duplicateStyle(
                $this->sheet->getStyle($prevColumn . '1:' . $prevColumn . $this->sheet->getHighestRow()),
                $newColumn . '1:' . $newColumn . $this->sheet->getHighestRow()
            );
        }
    }
    
    /**
     * Sesuaikan header kolom berdasarkan jumlah puskesmas
     */
    protected function adjustColumnHeaders($puskesmasCount)
    {
        // Implementasi penyesuaian header kolom
        // Bisa disesuaikan dengan kebutuhan spesifik template
        Log::info("Template disesuaikan untuk {$puskesmasCount} puskesmas");
    }

    /**
     * Isi data total tahunan dengan format konsisten seperti dashboard
     */
    protected function fillYearlyTotalData($row, $startCol, $yearlyTotal, $sasaran)
    {
        $currentCol = $startCol;
        
        // Format data tahunan seperti dashboard - konversi ke string untuk konsistensi
        $formattedYearlyData = [
            'target' => (string)$sasaran,
            'total_patients' => (string)($yearlyTotal['total'] ?? 0),
            'standard_patients' => (string)($yearlyTotal['standard'] ?? 0),
            'non_standard_patients' => (string)($yearlyTotal['non_standard'] ?? 0),
            'male_patients' => (string)($yearlyTotal['male'] ?? 0),
            'female_patients' => (string)($yearlyTotal['female'] ?? 0),
            'achievement_percentage' => $this->calculateAchievementPercentage(
                $yearlyTotal['standard'] ?? 0, 
                $sasaran
            )
        ];
        
        // L, P, Total, TS untuk tahun
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedYearlyData['male_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedYearlyData['female_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedYearlyData['total_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedYearlyData['non_standard_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        // Total pelayanan
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedYearlyData['total_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        // % Capaian pelayanan sesuai standar (dapat melebihi 100% untuk over-achievement)
        // Set nilai persentase dalam format desimal untuk konsistensi
        $this->sheet->setCellValue($currentCol . $row, $formattedYearlyData['achievement_percentage']);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle($currentCol . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    }
    
    /**
     * Dapatkan kolom berikutnya (A->B, Z->AA, dll)
     */
    protected function getNextColumn($column)
    {
        if (strlen($column) == 1) {
            if ($column == 'Z') {
                return 'AA';
            }
            return chr(ord($column) + 1);
        } else {
            // Handle AA, AB, etc.
            $lastChar = substr($column, -1);
            $prefix = substr($column, 0, -1);
            
            if ($lastChar == 'Z') {
                return $this->getNextColumn($prefix) . 'A';
            }
            return $prefix . chr(ord($lastChar) + 1);
        }
    }
    
    /**
     * Tambahkan summary total di akhir template
     */
    protected function addAllTemplateSummary($startRow, $puskesmasData)
    {
        // Hitung grand total untuk semua data
        $grandTotal = [
            'sasaran' => 0,
            'yearly' => ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0],
            'quarterly' => [
                1 => ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0],
                2 => ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0],
                3 => ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0],
                4 => ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0]
            ],
            'monthly' => []
        ];
        
        // Inisialisasi data bulanan
        for ($month = 1; $month <= 12; $month++) {
            $grandTotal['monthly'][$month] = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
        }
        
        // Akumulasi data dari semua puskesmas
        foreach ($puskesmasData as $puskesmas) {
            $grandTotal['sasaran'] += $puskesmas['sasaran'];
            
            // Akumulasi data bulanan
            foreach ($puskesmas['monthly_data'] as $month => $data) {
                $grandTotal['monthly'][$month]['male'] += $data['male'] ?? 0;
                $grandTotal['monthly'][$month]['female'] += $data['female'] ?? 0;
                $grandTotal['monthly'][$month]['standard'] += $data['standard'] ?? 0;
                $grandTotal['monthly'][$month]['non_standard'] += $data['non_standard'] ?? 0;
                $grandTotal['monthly'][$month]['total'] += $data['total'] ?? 0;
            }
        }
        
        // Hitung data tahunan dan triwulan menggunakan logika dashboard (bulan terakhir dengan data)
        $grandTotal['yearly'] = $this->calculateYearlyTotal($grandTotal['monthly']);
        $grandTotal['quarterly'] = $this->calculateQuarterlyData($grandTotal['monthly']);
        
        // Isi data dasar
        $this->sheet->setCellValue('A' . $startRow, '');
        $this->sheet->setCellValue('B' . $startRow, 'TOTAL KESELURUHAN');
        $this->sheet->setCellValue('C' . $startRow, $grandTotal['sasaran']);
        
        // Isi data summary menggunakan struktur yang sama dengan data puskesmas
        $currentCol = 'D';
        
        // Isi data Triwulan I
        $currentCol = $this->fillQuarterSummaryData($startRow, $currentCol, $grandTotal['monthly'], [1, 2, 3], $grandTotal['quarterly'][1], $grandTotal['sasaran']);
        
        // Isi data Triwulan II
        $currentCol = $this->fillQuarterSummaryData($startRow, $currentCol, $grandTotal['monthly'], [4, 5, 6], $grandTotal['quarterly'][2], $grandTotal['sasaran']);
        
        // Isi data Triwulan III
        $currentCol = $this->fillQuarterSummaryData($startRow, $currentCol, $grandTotal['monthly'], [7, 8, 9], $grandTotal['quarterly'][3], $grandTotal['sasaran']);
        
        // Isi data Triwulan IV
        $currentCol = $this->fillQuarterSummaryData($startRow, $currentCol, $grandTotal['monthly'], [10, 11, 12], $grandTotal['quarterly'][4], $grandTotal['sasaran']);
        
        // Isi total tahunan
        $this->fillYearlyTotalData($startRow, $currentCol, $grandTotal['yearly'], $grandTotal['sasaran']);
        
        // Style bold untuk baris total dan tambahkan border untuk pemisahan yang jelas
        $this->sheet->getStyle('A' . $startRow . ':' . $this->sheet->getHighestColumn() . $startRow)->getFont()->setBold(true);
        
        // Tambahkan border atas untuk memisahkan summary dari data
        $this->sheet->getStyle('A' . $startRow . ':' . $this->sheet->getHighestColumn() . $startRow)
            ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        
        // Tambahkan background color untuk highlight summary
        $this->sheet->getStyle('A' . $startRow . ':' . $this->sheet->getHighestColumn() . $startRow)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE6E6E6'); // Light gray background
    }
    
    /**
     * Isi data summary untuk satu triwulan dengan format konsisten seperti dashboard
     */
    protected function fillQuarterSummaryData($row, $startCol, $monthlyData, $months, $quarterTotal, $yearlyTarget = 0)
    {
        $currentCol = $startCol;
        
        // Isi data untuk 3 bulan dalam triwulan
        foreach ($months as $month) {
            $monthData = $monthlyData[$month];
            
            // Format data seperti dashboard - konversi ke string untuk konsistensi
            $formattedSummaryData = [
                'male_patients' => (string)($monthData['male'] ?? 0),
                'female_patients' => (string)($monthData['female'] ?? 0),
                'total_patients' => (string)($monthData['total'] ?? 0),
                'standard_patients' => (string)($monthData['standard'] ?? 0),
                'non_standard_patients' => (string)($monthData['non_standard'] ?? 0),
                'target' => (string)$yearlyTarget,
                'achievement_percentage' => $this->calculateAchievementPercentage(
                    $monthData['standard'] ?? 0, 
                    $yearlyTarget
                )
            ];
            
            // L, P, Total, TS, %S untuk setiap bulan
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedSummaryData['male_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedSummaryData['female_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedSummaryData['total_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            $this->sheet->setCellValue($currentCol . $row, (int)$formattedSummaryData['non_standard_patients']);
            $currentCol = $this->getNextColumn($currentCol);
            
            // Set nilai persentase dalam format desimal (75% = 0.75)
            $this->sheet->setCellValue($currentCol . $row, $formattedSummaryData['achievement_percentage']);
            // Terapkan format persentase untuk tampilan yang bersih
            $this->sheet->getStyle($currentCol . $row)->getNumberFormat()->setFormatCode('0.00"%"');
            $currentCol = $this->getNextColumn($currentCol);
        }
        
        // Format data triwulan seperti dashboard
        $formattedQuarterSummary = [
            'standard_patients' => (string)($quarterTotal['standard'] ?? 0),
            'non_standard_patients' => (string)($quarterTotal['non_standard'] ?? 0),
            'target' => (string)$yearlyTarget,
            'achievement_percentage' => $this->calculateAchievementPercentage(
                $quarterTotal['standard'] ?? 0, 
                $yearlyTarget
            )
        ];
        
        // Isi total triwulan (S, TS, %S)
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedQuarterSummary['standard_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        $this->sheet->setCellValue($currentCol . $row, (int)$formattedQuarterSummary['non_standard_patients']);
        $currentCol = $this->getNextColumn($currentCol);
        
        // Set nilai persentase dalam format desimal (75% = 0.75)
        $this->sheet->setCellValue($currentCol . $row, $formattedQuarterSummary['achievement_percentage']);
        // Terapkan format persentase untuk tampilan yang bersih
        $this->sheet->getStyle($currentCol . $row)->getNumberFormat()->setFormatCode('0.00"%"');
        $currentCol = $this->getNextColumn($currentCol);
        
        return $currentCol;
    }

    /**
     * Ambil data puskesmas untuk laporan all.xlsx
     * Menggunakan RealTimeStatisticsService yang sama dengan dashboard untuk konsistensi
     */
    protected function getPuskesmasDataForAll(string $diseaseType, int $year): array
    {
        try {
            // Ambil semua puskesmas
            $puskesmasList = $this->statisticsService->getAllPuskesmas();
            $formattedData = [];
            
            foreach ($puskesmasList as $puskesmas) {
                $puskesmasId = $puskesmas->id;
                
                // Gunakan RealTimeStatisticsService yang sama dengan dashboard
                $dashboardStats = $this->realTimeStatisticsService->getFastDashboardStats(
                    $puskesmasId, 
                    $diseaseType, 
                    $year
                );
                
                // Konversi format data dari dashboard ke format yang dibutuhkan Excel
                $monthlyData = [];
                foreach ($dashboardStats['monthly_data'] as $month => $data) {
                    $monthlyData[$month] = [
                        'male' => (int)$data['male'],
                        'female' => (int)$data['female'],
                        'standard' => (int)$data['standard'],
                        'non_standard' => (int)$data['non_standard'],
                        'total' => (int)$data['total']
                    ];
                }
                
                // Ambil sasaran tahunan dengan format konsisten seperti dashboard
                $yearlyTarget = $this->statisticsService->getYearlyTarget($puskesmasId, $year, $diseaseType);
                
                // Format data puskesmas seperti dashboard
                $formattedPuskesmasData = [
                    'id' => $puskesmasId,
                    'nama_puskesmas' => $puskesmas->name,
                    'target' => (string)($yearlyTarget['target'] ?? 0),
                    'sasaran' => (int)($yearlyTarget['target'] ?? 0),
                    'monthly_data' => $monthlyData
                ];
                
                $formattedData[] = $formattedPuskesmasData;
                
                Log::info('AdminAllFormatter: Processed puskesmas data using RealTimeStatisticsService', [
                    'puskesmas_id' => $puskesmasId,
                    'puskesmas_name' => $puskesmas->name,
                    'disease_type' => $diseaseType,
                    'year' => $year,
                    'yearly_target' => $yearlyTarget['target'] ?? 0
                ]);
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