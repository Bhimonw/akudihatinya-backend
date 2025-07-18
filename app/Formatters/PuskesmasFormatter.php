<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Formatter untuk template puskesmas.xlsx
 * Menangani laporan khusus puskesmas
 */
class PuskesmasFormatter extends BaseAdminFormatter
{
    /**
     * Format spreadsheet menggunakan template puskesmas.xlsx
     */
    public function format(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $statistics, array $options = []): Spreadsheet
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        
        $puskesmasName = $options['puskesmas_name'] ?? 'Puskesmas';
        $month = $options['month'] ?? null;
        $quarter = $options['quarter'] ?? null;
        
        // Ambil data target untuk placeholder <sasaran>
        $target = 0;
        if (isset($statistics['ht_summary']['target'])) {
            $target += $statistics['ht_summary']['target'];
        }
        if (isset($statistics['dm_summary']['target'])) {
            $target += $statistics['dm_summary']['target'];
        }
        
        // Replace placeholders di template
        $this->replacePlaceholders($spreadsheet, [
            '{{PUSKESMAS_NAME}}' => $puskesmasName,
            '{{DISEASE_TYPE}}' => $this->getDiseaseLabel($diseaseType),
            '{{YEAR}}' => $year,
            '{{TARGET}}' => $target,
            '{{GENERATED_DATE}}' => date('d/m/Y'),
            '{{GENERATED_TIME}}' => date('H:i:s')
        ]);
        
        // Format data statistik
        $this->formatData($statistics, $diseaseType, $options);
        
        // Apply styles
        $this->applyStyles($spreadsheet, 'A1:Z100', [
            'font' => ['name' => 'Arial', 'size' => 10],
        ]);
        
        return $spreadsheet;
    }
    
    /**
     * Format data statistik ke dalam spreadsheet
     */
    private function formatData(array $statistics, string $diseaseType, array $options): void
    {
        $startRow = 8; // Mulai dari baris 8 (sesuaikan dengan template)
        $currentRow = $startRow;
        
        // Format data pasien individual
        if (isset($statistics['patients']) && is_array($statistics['patients'])) {
            foreach ($statistics['patients'] as $index => $patient) {
                $this->formatPatientData($patient, $currentRow, $index + 1, $diseaseType);
                $currentRow++;
            }
        }
        
        // Format summary data
        $this->formatSummaryData($statistics, $diseaseType, $options);
        
        // Format data bulanan jika ada
        if (isset($options['month'])) {
            $this->formatMonthlyDetailData($statistics, $diseaseType, $options['month']);
        }
        
        // Format data triwulanan jika ada
        if (isset($options['quarter'])) {
            $this->formatQuarterlyDetailData($statistics, $diseaseType, $options['quarter']);
        }
        
        // Apply number formatting
        $this->applyNumberFormats();
    }
    
    /**
     * Format data individual pasien
     */
    private function formatPatientData(array $patient, int $row, int $no, string $diseaseType): void
    {
        // Kolom A: No
        $this->sheet->setCellValue('A' . $row, $no);
        
        // Kolom B: Nama Pasien
        $this->sheet->setCellValue('B' . $row, $patient['name'] ?? '');
        
        // Kolom C: NIK
        $this->sheet->setCellValue('C' . $row, $patient['nik'] ?? '');
        
        // Kolom D: Umur
        $this->sheet->setCellValue('D' . $row, $patient['age'] ?? '');
        
        // Kolom E: Jenis Kelamin
        $this->sheet->setCellValue('E' . $row, $patient['gender'] ?? '');
        
        // Kolom F: Alamat
        $this->sheet->setCellValue('F' . $row, $patient['address'] ?? '');
        
        // Kolom G: Tanggal Kunjungan
        $this->sheet->setCellValue('G' . $row, $patient['visit_date'] ?? '');
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            // Data Hipertensi
            $htData = $patient['ht_data'] ?? [];
            $this->sheet->setCellValue('H' . $row, $htData['systolic'] ?? '');
            $this->sheet->setCellValue('I' . $row, $htData['diastolic'] ?? '');
            $this->sheet->setCellValue('J' . $row, $htData['status'] ?? '');
            $this->sheet->setCellValue('K' . $row, $htData['medication'] ?? '');
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // Data Diabetes Melitus
            $dmData = $patient['dm_data'] ?? [];
            $colOffset = ($diseaseType === 'all') ? 4 : 0; // Offset kolom jika menampilkan kedua penyakit
            
            $this->sheet->setCellValue(chr(72 + $colOffset) . $row, $dmData['blood_sugar'] ?? ''); // H atau L
            $this->sheet->setCellValue(chr(73 + $colOffset) . $row, $dmData['hba1c'] ?? ''); // I atau M
            $this->sheet->setCellValue(chr(74 + $colOffset) . $row, $dmData['status'] ?? ''); // J atau N
            $this->sheet->setCellValue(chr(75 + $colOffset) . $row, $dmData['medication'] ?? ''); // K atau O
        }
        
        // Kolom terakhir: Keterangan
        $lastCol = ($diseaseType === 'all') ? 'P' : 'L';
        $this->sheet->setCellValue($lastCol . $row, $patient['notes'] ?? '');
    }
    
    /**
     * Format data summary
     */
    private function formatSummaryData(array $statistics, string $diseaseType, array $options): void
    {
        // Temukan area summary di template (biasanya di bagian atas atau bawah)
        $summaryStartRow = 3; // Sesuaikan dengan template
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htSummary = $statistics['ht_summary'] ?? [];
            $this->sheet->setCellValue('C' . $summaryStartRow, $htSummary['target'] ?? 0);
            $this->sheet->setCellValue('D' . $summaryStartRow, $htSummary['total_patients'] ?? 0);
            $this->sheet->setCellValue('E' . $summaryStartRow, $htSummary['standard_patients'] ?? 0);
            $this->sheet->setCellValue('F' . $summaryStartRow, $htSummary['non_standard_patients'] ?? 0);
            $this->sheet->setCellValue('G' . $summaryStartRow, $htSummary['male_patients'] ?? 0);
            $this->sheet->setCellValue('H' . $summaryStartRow, $htSummary['female_patients'] ?? 0);
            
            $achievement = $this->calculatePercentage(
                $htSummary['standard_patients'] ?? 0,
                $htSummary['target'] ?? 0
            );
            $this->sheet->setCellValue('I' . $summaryStartRow, $achievement / 100);
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmSummary = $statistics['dm_summary'] ?? [];
            $summaryRow = ($diseaseType === 'all') ? $summaryStartRow + 1 : $summaryStartRow;
            
            $this->sheet->setCellValue('C' . $summaryRow, $dmSummary['target'] ?? 0);
            $this->sheet->setCellValue('D' . $summaryRow, $dmSummary['total_patients'] ?? 0);
            $this->sheet->setCellValue('E' . $summaryRow, $dmSummary['standard_patients'] ?? 0);
            $this->sheet->setCellValue('F' . $summaryRow, $dmSummary['non_standard_patients'] ?? 0);
            $this->sheet->setCellValue('G' . $summaryRow, $dmSummary['male_patients'] ?? 0);
            $this->sheet->setCellValue('H' . $summaryRow, $dmSummary['female_patients'] ?? 0);
            
            $achievement = $this->calculatePercentage(
                $dmSummary['standard_patients'] ?? 0,
                $dmSummary['target'] ?? 0
            );
            $this->sheet->setCellValue('I' . $summaryRow, $achievement / 100);
        }
    }
    
    /**
     * Format data detail bulanan
     */
    private function formatMonthlyDetailData(array $statistics, string $diseaseType, int $month): void
    {
        // Implementasi untuk detail data bulanan
        // Sesuaikan dengan struktur template puskesmas.xlsx
        
        $monthlyData = $statistics['monthly_detail'][$month] ?? [];
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->fillMonthlyDetail($monthlyData['ht'] ?? [], 'ht', $month);
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $this->fillMonthlyDetail($monthlyData['dm'] ?? [], 'dm', $month);
        }
    }
    
    /**
     * Mengisi detail data bulanan
     */
    private function fillMonthlyDetail(array $monthlyData, string $diseaseType, int $month): void
    {
        // Area khusus untuk detail bulanan di template
        $detailStartRow = 50; // Sesuaikan dengan template
        $currentRow = $detailStartRow;
        
        // Header bulan
        $this->sheet->setCellValue('A' . $currentRow, 'Detail ' . $this->getMonthName($month));
        $currentRow += 2;
        
        // Data harian dalam bulan
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, date('Y'));
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayData = $monthlyData['daily'][$day] ?? [];
            
            $this->sheet->setCellValue('A' . $currentRow, $day);
            $this->sheet->setCellValue('B' . $currentRow, $dayData['total_visits'] ?? 0);
            $this->sheet->setCellValue('C' . $currentRow, $dayData['new_patients'] ?? 0);
            $this->sheet->setCellValue('D' . $currentRow, $dayData['follow_up_patients'] ?? 0);
            
            $currentRow++;
        }
    }
    
    /**
     * Format data detail triwulanan
     */
    private function formatQuarterlyDetailData(array $statistics, string $diseaseType, int $quarter): void
    {
        // Implementasi untuk detail data triwulanan
        // Sesuaikan dengan struktur template puskesmas.xlsx
        
        $quarterlyData = $statistics['quarterly_detail'][$quarter] ?? [];
        $quarterMonths = $this->getQuarterMonths($quarter);
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->fillQuarterlyDetail($quarterlyData['ht'] ?? [], 'ht', $quarter, $quarterMonths);
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $this->fillQuarterlyDetail($quarterlyData['dm'] ?? [], 'dm', $quarter, $quarterMonths);
        }
    }
    
    /**
     * Mengisi detail data triwulanan
     */
    private function fillQuarterlyDetail(array $quarterlyData, string $diseaseType, int $quarter, array $quarterMonths): void
    {
        // Area khusus untuk detail triwulanan di template
        $detailStartRow = 80; // Sesuaikan dengan template
        $currentRow = $detailStartRow;
        
        // Header triwulan
        $this->sheet->setCellValue('A' . $currentRow, 'Detail Triwulan ' . $quarter);
        $currentRow += 2;
        
        // Data bulanan dalam triwulan
        foreach ($quarterMonths as $month) {
            $monthData = $quarterlyData['monthly'][$month] ?? [];
            
            $this->sheet->setCellValue('A' . $currentRow, $this->getMonthName($month));
            $this->sheet->setCellValue('B' . $currentRow, $monthData['total_visits'] ?? 0);
            $this->sheet->setCellValue('C' . $currentRow, $monthData['new_patients'] ?? 0);
            $this->sheet->setCellValue('D' . $currentRow, $monthData['follow_up_patients'] ?? 0);
            $this->sheet->setCellValue('E' . $currentRow, $monthData['achievement_percentage'] ?? 0);
            
            $currentRow++;
        }
    }
    
    /**
     * Dapatkan bulan-bulan dalam triwulan
     */
    private function getQuarterMonths(int $quarter): array
    {
        switch ($quarter) {
            case 1:
                return [1, 2, 3]; // Jan, Feb, Mar
            case 2:
                return [4, 5, 6]; // Apr, May, Jun
            case 3:
                return [7, 8, 9]; // Jul, Aug, Sep
            case 4:
                return [10, 11, 12]; // Oct, Nov, Dec
            default:
                return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        }
    }
    
    /**
     * Dapatkan label periode
     */
    private function getPeriodLabel(int $year, ?int $month, ?int $quarter): string
    {
        if ($month) {
            return $this->getMonthName($month) . ' ' . $year;
        } elseif ($quarter) {
            return 'Triwulan ' . $quarter . ' Tahun ' . $year;
        } else {
            return 'Tahun ' . $year;
        }
    }
    
    /**
     * Apply number formats ke range yang sesuai
     */
    private function applyNumberFormats(): void
    {
        // Format angka untuk kolom data
        $this->applyNumberFormat($this->sheet->getParent(), 'C:H', NumberFormat::FORMAT_NUMBER);
        
        // Format persentase untuk kolom achievement
        $this->applyPercentageFormat($this->sheet->getParent(), 'I:I');
        
        // Apply borders
        $lastRow = $this->sheet->getHighestRow();
        $this->applyBorder($this->sheet->getParent(), 'A8:P' . $lastRow);
        
        // Apply alignment
        $this->applyAlignment($this->sheet->getParent(), 'A8:P' . $lastRow);
        
        // Format tanggal
        $this->applyDateFormat($this->sheet->getParent(), 'G:G');
    }
    
    /**
     * Apply date format
     */
    private function applyDateFormat(Spreadsheet $spreadsheet, string $range): void
    {
        $spreadsheet->getActiveSheet()->getStyle($range)->getNumberFormat()
            ->setFormatCode('dd/mm/yyyy');
    }
}