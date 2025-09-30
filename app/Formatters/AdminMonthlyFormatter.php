<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Formatter untuk template monthly.xlsx
 * Menangani laporan bulanan
 */
class AdminMonthlyFormatter extends BaseAdminFormatter
{
    /**
     * Format spreadsheet menggunakan template monthly.xlsx
     */
    public function format(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $statistics, int $month = null): Spreadsheet
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders di template
        $replacements = [
            '{{DISEASE_TYPE}}' => $this->getDiseaseLabel($diseaseType),
            '{{YEAR}}' => $year,
            '{{MONTH}}' => $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : '',
            '{{MONTH_NAME}}' => $month ? $this->getMonthName($month) : 'Semua Bulan',
            '{{PERIOD}}' => $month ? $this->getMonthName($month) . ' ' . $year : 'Tahun ' . $year,
            '{{GENERATED_DATE}}' => date('d/m/Y'),
            '{{GENERATED_TIME}}' => date('H:i:s')
        ];

        $this->replacePlaceholders($spreadsheet, $replacements);

        // Ensure headers align with data columns
        $this->ensureListHeaders($diseaseType, 7);

        // Format data statistik
        $this->formatData($statistics, $diseaseType, $month);

        // Apply styles
        $this->applyStyles($spreadsheet, 'A1:Z100', [
            'font' => ['name' => 'Arial', 'size' => 10],
        ]);

        return $spreadsheet;
    }

    /**
     * Format data statistik ke dalam spreadsheet
     */
    private function formatData(array $statistics, string $diseaseType, ?int $month): void
    {
        $startRow = 8; // Mulai dari baris 8 (sesuaikan dengan template)
        $currentRow = $startRow;

        // Hitung total untuk summary
        $totals = $this->calculateTotals($statistics, $diseaseType, $month);

        // Format data per puskesmas
        foreach ($statistics as $index => $data) {
            $this->formatPuskesmasData($data, $currentRow, $index + 1, $diseaseType, $month);
            $currentRow++;
        }

        // Format total di baris terakhir
        $this->formatTotalRow($totals, $currentRow, $diseaseType);

        // Format data harian jika ada
        if ($month) {
            $this->formatDailyData($statistics, $diseaseType, $month);
        }

        // Apply number formatting
        $this->applyNumberFormats();
    }

    /**
     * Format data individual puskesmas
     */
    private function formatPuskesmasData(array $data, int $row, int $no, string $diseaseType, ?int $month): void
    {
        // Kolom A: No
        $this->sheet->setCellValue('A' . $row, $no);

        // Kolom B: Nama Puskesmas
        $this->sheet->setCellValue('B' . $row, $data['puskesmas_name'] ?? '');

        // Ambil data untuk bulan tertentu atau total
        $monthlyData = $month ? ($data['monthly_data'][$month] ?? []) : $data;

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            // Data Hipertensi
            $htData = $monthlyData['ht'] ?? $data['ht'] ?? [];
            $this->sheet->setCellValue('C' . $row, $this->formatDataForExcel($htData['target'] ?? 0));
            $this->sheet->setCellValue('D' . $row, $this->formatDataForExcel($htData['total_patients'] ?? 0));
            $this->sheet->setCellValue('E' . $row, $this->formatDataForExcel($htData['standard_patients'] ?? 0));
            $this->sheet->setCellValue('F' . $row, $this->formatDataForExcel($htData['non_standard_patients'] ?? 0));
            $this->sheet->setCellValue('G' . $row, $this->formatDataForExcel($htData['male_patients'] ?? 0));
            $this->sheet->setCellValue('H' . $row, $this->formatDataForExcel($htData['female_patients'] ?? 0));

            // Hitung achievement percentage
            $achievement = $this->calculatePercentage(
                $htData['standard_patients'] ?? 0,
                $htData['target'] ?? 0
            );
            $this->sheet->setCellValue('I' . $row, $achievement / 100);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // Data Diabetes Melitus
            $dmData = $monthlyData['dm'] ?? $data['dm'] ?? [];
            $colOffset = ($diseaseType === 'all') ? 7 : 0; // Offset kolom jika menampilkan kedua penyakit

            $this->sheet->setCellValue(chr(67 + $colOffset) . $row, $this->formatDataForExcel($dmData['target'] ?? 0)); // C atau J
            $this->sheet->setCellValue(chr(68 + $colOffset) . $row, $this->formatDataForExcel($dmData['total_patients'] ?? 0)); // D atau K
            $this->sheet->setCellValue(chr(69 + $colOffset) . $row, $this->formatDataForExcel($dmData['standard_patients'] ?? 0)); // E atau L
            $this->sheet->setCellValue(chr(70 + $colOffset) . $row, $this->formatDataForExcel($dmData['non_standard_patients'] ?? 0)); // F atau M
            $this->sheet->setCellValue(chr(71 + $colOffset) . $row, $this->formatDataForExcel($dmData['male_patients'] ?? 0)); // G atau N
            $this->sheet->setCellValue(chr(72 + $colOffset) . $row, $this->formatDataForExcel($dmData['female_patients'] ?? 0)); // H atau O

            // Hitung achievement percentage
            $achievement = $this->calculatePercentage(
                $dmData['standard_patients'] ?? 0,
                $dmData['target'] ?? 0
            );
            $this->sheet->setCellValue(chr(73 + $colOffset) . $row, $achievement / 100); // I atau P
        }
    }

    /**
     * Hitung total untuk summary
     */
    private function calculateTotals(array $statistics, string $diseaseType, ?int $month): array
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
            // Ambil data untuk bulan tertentu atau total
            $monthlyData = $month ? ($data['monthly_data'][$month] ?? []) : $data;

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $monthlyData['ht'] ?? $data['ht'] ?? [];
                $totals['ht']['target'] += intval($htData['target'] ?? 0);
                $totals['ht']['total_patients'] += intval($htData['total_patients'] ?? 0);
                $totals['ht']['standard_patients'] += intval($htData['standard_patients'] ?? 0);
                $totals['ht']['non_standard_patients'] += intval($htData['non_standard_patients'] ?? 0);
                $totals['ht']['male_patients'] += intval($htData['male_patients'] ?? 0);
                $totals['ht']['female_patients'] += intval($htData['female_patients'] ?? 0);
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $monthlyData['dm'] ?? $data['dm'] ?? [];
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
     * Format data harian untuk bulan tertentu
     */
    private function formatDailyData(array $statistics, string $diseaseType, int $month): void
    {
        // Implementasi untuk mengisi data harian ke kolom yang sesuai
        // Sesuaikan dengan struktur template monthly.xlsx

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, date('Y'));

        // Tentukan baris awal untuk header tanggal
        $headerRow = 7;

        // Isi header tanggal
        $currentCol = 'J';
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $this->sheet->setCellValue($currentCol . $headerRow, $day);
            $currentCol = $this->incrementColumn($currentCol);
        }

        foreach ($statistics as $index => $data) {
            $row = 8 + $index; // Sesuaikan dengan baris data

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htDailyData = $data['ht']['daily_data'][$month] ?? [];
                // Kolom untuk data harian HT dimulai dari kolom J
                $this->fillDailyData($htDailyData, $row, 'J', $daysInMonth);
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmDailyData = $data['dm']['daily_data'][$month] ?? [];
                // Kolom untuk data harian DM dimulai dari kolom yang sesuai
                // Sesuaikan dengan template
                $dmStartCol = $this->incrementColumn(chr(ord('J') + $daysInMonth));
                $this->fillDailyData($dmDailyData, $row, $dmStartCol, $daysInMonth);
            }
        }

        // Tambahkan header untuk data harian
        $this->sheet->setCellValue('J6', 'DATA HARIAN');
        $this->sheet->mergeCells('J6:' . chr(ord('J') + $daysInMonth - 1) . '6');

        // Tambahkan header untuk DM jika diperlukan
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmStartCol = $this->incrementColumn(chr(ord('J') + $daysInMonth));
            $this->sheet->setCellValue($dmStartCol . '6', 'DATA HARIAN DM');
            $this->sheet->mergeCells($dmStartCol . '6:' . chr(ord($dmStartCol) + $daysInMonth - 1) . '6');
        }
    }

    /**
     * Mengisi data harian ke kolom yang sesuai
     */
    private function fillDailyData(array $dailyData, int $row, string $startCol, int $daysInMonth): void
    {
        // Gunakan startCol yang sudah ditentukan dari parameter
        $currentCol = $startCol;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayData = $dailyData[$day] ?? [];

            // Isi data sesuai dengan struktur template
            $standardPatients = $dayData['standard_patients'] ?? 0;
            $this->sheet->setCellValue($currentCol . $row, $this->formatDataForExcel($standardPatients));

            // Pindah ke kolom berikutnya
            $currentCol = $this->incrementColumn($currentCol);
        }
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

    /**
     * Format data untuk minggu dalam bulan
     */
    private function formatWeeklyData(array $statistics, string $diseaseType, int $month): void
    {
        // Implementasi untuk data mingguan jika diperlukan
        // Sesuaikan dengan struktur template monthly.xlsx
    }
}
