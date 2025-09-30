<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

/**
 * Formatter untuk template all.xlsx
 * Menangani laporan semua data (tahunan/rekap)
 */
class AdminAllFormatter extends BaseAdminFormatter
{
    /**
     * Format spreadsheet menggunakan template all.xlsx
     */
    public function format(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $statistics): Spreadsheet
    {
        $this->sheet = $spreadsheet->getActiveSheet();
        
        // Replace placeholders di template
        $replacements = [
            '{{YEAR}}' => $year,
            '{{DISEASE_TYPE}}' => $this->getDiseaseLabel($diseaseType),
            '{{GENERATED_DATE}}' => Carbon::now()->format('d/m/Y'),
            '{{GENERATED_TIME}}' => Carbon::now()->format('H:i:s')
        ];
        
        $this->replacePlaceholders($spreadsheet, $replacements);
        
        // Format data statistik
        $this->formatData($statistics, $diseaseType);
        
        // Apply styles
        $this->applyStyles($spreadsheet, 'A1:Z100', [
            'font' => ['name' => 'Arial', 'size' => 10],
        ]);
        
        return $spreadsheet;
    }
    
    /**
     * Format data statistik ke dalam spreadsheet
     */
    private function formatData(array $statistics, string $diseaseType): void
    {
        $startRow = 8; // Mulai dari baris 8 (sesuaikan dengan template)
        $currentRow = $startRow;
        
        // Hitung total untuk summary
        $totals = $this->calculateTotals($statistics, $diseaseType);
        
        // Format data per puskesmas
        foreach ($statistics as $index => $data) {
            $this->formatPuskesmasData($data, $currentRow, $index + 1, $diseaseType);
            $currentRow++;
        }
        
        // Format total di baris terakhir
        $this->formatTotalRow($totals, $currentRow, $diseaseType);
        
        // Format data bulanan dan triwulanan
        $this->formatMonthlyAndQuarterlyData($statistics, $diseaseType);
        
        // Format pencapaian tahunan
        $this->formatYearlyAchievement($statistics, $diseaseType);
        
        // Apply number formatting
        $this->applyNumberFormats();
    }
    
    /**
     * Format data individual puskesmas
     */
    private function formatPuskesmasData(array $data, int $row, int $no, string $diseaseType): void
    {
        // Kolom A: No
        $this->sheet->setCellValue('A' . $row, $no);
        
        // Kolom B: Nama Puskesmas
        $this->sheet->setCellValue('B' . $row, $data['puskesmas_name'] ?? '');
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            // Data Hipertensi
            $htData = $data['ht'] ?? [];
            $this->sheet->setCellValue('C' . $row, $this->formatDataForExcel($htData['target'] ?? 0));
            $this->sheet->setCellValue('D' . $row, $this->formatDataForExcel($htData['total_patients'] ?? 0));
            $this->sheet->setCellValue('E' . $row, $this->formatDataForExcel($htData['standard_patients'] ?? 0));
            $this->sheet->setCellValue('F' . $row, $this->formatDataForExcel($htData['non_standard_patients'] ?? 0));
            $this->sheet->setCellValue('G' . $row, $this->formatDataForExcel($htData['male_patients'] ?? 0));
            $this->sheet->setCellValue('H' . $row, $this->formatDataForExcel($htData['female_patients'] ?? 0));
            $this->sheet->setCellValue('I' . $row, ($htData['achievement_percentage'] ?? 0) / 100); // Convert to decimal for percentage format
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // Data Diabetes Melitus
            $dmData = $data['dm'] ?? [];
            $colOffset = ($diseaseType === 'all') ? 7 : 0; // Offset kolom jika menampilkan kedua penyakit
            
            $this->sheet->setCellValue(chr(67 + $colOffset) . $row, $this->formatDataForExcel($dmData['target'] ?? 0)); // C atau J
            $this->sheet->setCellValue(chr(68 + $colOffset) . $row, $this->formatDataForExcel($dmData['total_patients'] ?? 0)); // D atau K
            $this->sheet->setCellValue(chr(69 + $colOffset) . $row, $this->formatDataForExcel($dmData['standard_patients'] ?? 0)); // E atau L
            $this->sheet->setCellValue(chr(70 + $colOffset) . $row, $this->formatDataForExcel($dmData['non_standard_patients'] ?? 0)); // F atau M
            $this->sheet->setCellValue(chr(71 + $colOffset) . $row, $this->formatDataForExcel($dmData['male_patients'] ?? 0)); // G atau N
            $this->sheet->setCellValue(chr(72 + $colOffset) . $row, $this->formatDataForExcel($dmData['female_patients'] ?? 0)); // H atau O
            $this->sheet->setCellValue(chr(73 + $colOffset) . $row, ($dmData['achievement_percentage'] ?? 0) / 100); // I atau P
        }
    }
    
    /**
     * Hitung total untuk summary
     */
    private function calculateTotals(array $statistics, string $diseaseType): array
    {
        $totals = [
            'ht' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'non_standard_patients' => 0,
                'male_patients' => 0,
                'female_patients' => 0
            ],
            'dm' => [
                'target' => 0,
                'total_patients' => 0,
                'standard_patients' => 0,
                'non_standard_patients' => 0,
                'male_patients' => 0,
                'female_patients' => 0
            ]
        ];
        
        foreach ($statistics as $data) {
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $data['ht'] ?? [];
                $totals['ht']['target'] += intval($htData['target'] ?? 0);
                $totals['ht']['total_patients'] += intval($htData['total_patients'] ?? 0);
                $totals['ht']['standard_patients'] += intval($htData['standard_patients'] ?? 0);
                $totals['ht']['non_standard_patients'] += intval($htData['non_standard_patients'] ?? 0);
                $totals['ht']['male_patients'] += intval($htData['male_patients'] ?? 0);
                $totals['ht']['female_patients'] += intval($htData['female_patients'] ?? 0);
            }
            
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $data['dm'] ?? [];
                $totals['dm']['target'] += intval($dmData['target'] ?? 0);
                $totals['dm']['total_patients'] += intval($dmData['total_patients'] ?? 0);
                $totals['dm']['standard_patients'] += intval($dmData['standard_patients'] ?? 0);
                $totals['dm']['non_standard_patients'] += intval($dmData['non_standard_patients'] ?? 0);
                $totals['dm']['male_patients'] += intval($dmData['male_patients'] ?? 0);
                $totals['dm']['female_patients'] += intval($dmData['female_patients'] ?? 0);
            }
        }
        
        // Hitung persentase achievement
        $totals['ht']['achievement_percentage'] = $this->calculatePercentage(
            $totals['ht']['standard_patients'], 
            $totals['ht']['target']
        );
        $totals['dm']['achievement_percentage'] = $this->calculatePercentage(
            $totals['dm']['standard_patients'], 
            $totals['dm']['target']
        );
        
        return $totals;
    }
    
    /**
     * Format baris total
     */
    private function formatTotalRow(array $totals, int $row, string $diseaseType): void
    {
        $this->sheet->setCellValue('A' . $row, '');
        $this->sheet->setCellValue('B' . $row, 'TOTAL');
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->sheet->setCellValue('C' . $row, $totals['ht']['target']);
            $this->sheet->setCellValue('D' . $row, $totals['ht']['total_patients']);
            $this->sheet->setCellValue('E' . $row, $totals['ht']['standard_patients']);
            $this->sheet->setCellValue('F' . $row, $totals['ht']['non_standard_patients']);
            $this->sheet->setCellValue('G' . $row, $totals['ht']['male_patients']);
            $this->sheet->setCellValue('H' . $row, $totals['ht']['female_patients']);
            $this->sheet->setCellValue('I' . $row, $totals['ht']['achievement_percentage'] / 100);
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $colOffset = ($diseaseType === 'all') ? 7 : 0;
            $this->sheet->setCellValue(chr(67 + $colOffset) . $row, $totals['dm']['target']);
            $this->sheet->setCellValue(chr(68 + $colOffset) . $row, $totals['dm']['total_patients']);
            $this->sheet->setCellValue(chr(69 + $colOffset) . $row, $totals['dm']['standard_patients']);
            $this->sheet->setCellValue(chr(70 + $colOffset) . $row, $totals['dm']['non_standard_patients']);
            $this->sheet->setCellValue(chr(71 + $colOffset) . $row, $totals['dm']['male_patients']);
            $this->sheet->setCellValue(chr(72 + $colOffset) . $row, $totals['dm']['female_patients']);
            $this->sheet->setCellValue(chr(73 + $colOffset) . $row, $totals['dm']['achievement_percentage'] / 100);
        }
    }
    
    /**
     * Format data bulanan dan triwulanan
     */
    private function formatMonthlyAndQuarterlyData(array $statistics, string $diseaseType): void
    {
        // Implementasi untuk mengisi data bulanan ke kolom yang sesuai
        // Sesuaikan dengan struktur template all.xlsx
        
        foreach ($statistics as $index => $data) {
            $row = 8 + $index; // Sesuaikan dengan baris data
            
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htMonthlyData = $data['ht']['monthly_data'] ?? [];
                $this->fillMonthlyData($htMonthlyData, $row, 'ht');
            }
            
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmMonthlyData = $data['dm']['monthly_data'] ?? [];
                $this->fillMonthlyData($dmMonthlyData, $row, 'dm');
            }
        }
    }
    
    /**
     * Mengisi data bulanan ke kolom yang sesuai
     */
    private function fillMonthlyData(array $monthlyData, int $row, string $diseaseType): void
    {
        // Kolom untuk data bulanan (sesuaikan dengan template)
        $startCol = ($diseaseType === 'ht') ? 'Q' : 'AA'; // Contoh kolom mulai
        
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $monthlyData[$month] ?? [];
            $col = chr(ord($startCol) + $month - 1);
            
            // Isi data sesuai dengan struktur template
            $this->sheet->setCellValue($col . $row, $this->formatDataForExcel($monthData['total'] ?? 0));
        }
    }
    
    /**
     * Format pencapaian tahunan
     */
    private function formatYearlyAchievement(array $statistics, string $diseaseType): void
    {
        // Implementasi untuk mengisi data pencapaian tahunan
        // Sesuaikan dengan struktur template all.xlsx
    }
    
    /**
     * Apply number formats ke range yang sesuai
     */
    private function applyNumberFormats(): void
    {
        // Format angka untuk kolom data
        $this->applyNumberFormat($this->sheet->getParent(), 'C:H', NumberFormat::FORMAT_NUMBER);
        $this->applyNumberFormat($this->sheet->getParent(), 'J:O', NumberFormat::FORMAT_NUMBER);
        
        // Format persentase untuk kolom achievement
        $this->applyPercentageFormat($this->sheet->getParent(), 'I:I');
        $this->applyPercentageFormat($this->sheet->getParent(), 'P:P');
        
        // Apply borders
        $lastRow = $this->sheet->getHighestRow();
        $this->applyBorder($this->sheet->getParent(), 'A8:P' . $lastRow);
        
        // Apply alignment
        $this->applyAlignment($this->sheet->getParent(), 'A8:P' . $lastRow);
    }
}