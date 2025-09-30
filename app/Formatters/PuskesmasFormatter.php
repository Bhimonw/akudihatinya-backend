<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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
            '{{GENERATED_TIME}}' => date('H:i:s'),
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
        $startRow = 9; // Mulai dari baris 9 (data bulanan dimulai setelah header di row 7-8)

        // Format data bulanan dan triwulanan
        $this->formatMonthlyAndQuarterlyData($statistics, $diseaseType, $startRow);

        // Apply number formatting
        $this->applyNumberFormats();
    }

    /**
     * Format data bulanan dan triwulanan sesuai struktur template
     */
    private function formatMonthlyAndQuarterlyData(array $statistics, string $diseaseType, int $startRow): void
    {
        // Ambil data yang sesuai dengan disease type
        $data = null;
        if ($diseaseType === 'ht' && isset($statistics['ht'])) {
            $data = $statistics['ht'];
        } elseif ($diseaseType === 'dm' && isset($statistics['dm'])) {
            $data = $statistics['dm'];
        } elseif (isset($statistics[$diseaseType])) {
            $data = $statistics[$diseaseType];
        }

        if (! $data) {
            return;
        }

        $monthlyData = $data['monthly_data'] ?? [];

        // Array untuk quarterly totals
        $quarterlyTotals = [
            1 => ['male' => 0, 'female' => 0, 'total' => 0, 'standard' => 0, 'non_standard' => 0],
            2 => ['male' => 0, 'female' => 0, 'total' => 0, 'standard' => 0, 'non_standard' => 0],
            3 => ['male' => 0, 'female' => 0, 'total' => 0, 'standard' => 0, 'non_standard' => 0],
            4 => ['male' => 0, 'female' => 0, 'total' => 0, 'standard' => 0, 'non_standard' => 0],
        ];

        $currentRow = $startRow;

        // Format data per bulan dengan quarterly summaries
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $monthlyData[$month] ?? [];
            $quarter = ceil($month / 3);

            // Column B: L (Male for Standard)
            $male = intval($monthData['male'] ?? 0);
            $this->sheet->setCellValue('B'.$currentRow, $male);

            // Column C: P (Female for Standard)
            $female = intval($monthData['female'] ?? 0);
            $this->sheet->setCellValue('C'.$currentRow, $female);

            // Column D: TOTAL (L + P)
            $total = $male + $female;
            $this->sheet->setCellValue('D'.$currentRow, $total);

            // Column E: TS (Non-standard)
            $nonStandard = intval($monthData['non_standard'] ?? 0);
            $this->sheet->setCellValue('E'.$currentRow, $nonStandard);

            // Column F: %S (Percentage standard)
            $standard = intval($monthData['standard'] ?? 0);
            $totalPatients = $standard + $nonStandard;
            $percentageStandard = $totalPatients > 0 ? ($standard / $totalPatients) : 0;
            $this->sheet->setCellValue('F'.$currentRow, $percentageStandard);

            // Accumulate quarterly totals
            $quarterlyTotals[$quarter]['male'] += $male;
            $quarterlyTotals[$quarter]['female'] += $female;
            $quarterlyTotals[$quarter]['total'] += $total;
            $quarterlyTotals[$quarter]['standard'] += $standard;
            $quarterlyTotals[$quarter]['non_standard'] += $nonStandard;

            $currentRow++;

            // Insert quarterly summary after every 3 months
            if ($month % 3 == 0) {
                $qTotal = $quarterlyTotals[$quarter];

                // Column B: L
                $this->sheet->setCellValue('B'.$currentRow, $qTotal['male']);

                // Column C: P
                $this->sheet->setCellValue('C'.$currentRow, $qTotal['female']);

                // Column D: TOTAL
                $this->sheet->setCellValue('D'.$currentRow, $qTotal['total']);

                // Column E: TS
                $this->sheet->setCellValue('E'.$currentRow, $qTotal['non_standard']);

                // Column F: %S
                $qTotalPatients = $qTotal['standard'] + $qTotal['non_standard'];
                $qPercentage = $qTotalPatients > 0 ? ($qTotal['standard'] / $qTotalPatients) : 0;
                $this->sheet->setCellValue('F'.$currentRow, $qPercentage);

                $currentRow++;
            }
        }

        // Format yearly totals in columns H-M
        $this->formatYearlyTotals($data, $startRow);
    }

    /**
     * Format yearly totals dalam kolom H-M
     */
    private function formatYearlyTotals(array $data, int $startRow): void
    {
        $monthlyData = $data['monthly_data'] ?? [];

        // Initialize yearly totals
        $yearlyMale = 0;
        $yearlyFemale = 0;
        $yearlyStandard = 0;
        $yearlyNonStandard = 0;

        // Calculate yearly totals from all 12 months
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $monthlyData[$month] ?? [];
            $yearlyMale += intval($monthData['male'] ?? 0);
            $yearlyFemale += intval($monthData['female'] ?? 0);
            $yearlyStandard += intval($monthData['standard'] ?? 0);
            $yearlyNonStandard += intval($monthData['non_standard'] ?? 0);
        }

        $yearlyTotal = $yearlyMale + $yearlyFemale;
        $yearlyTotalPatients = $yearlyStandard + $yearlyNonStandard;
        $yearlyPercentage = $yearlyTotalPatients > 0 ? ($yearlyStandard / $yearlyTotalPatients) : 0;

        // Fill yearly data di baris pertama (JANUARI row - row 9)
        // Kolom H: S - L (Male for yearly standard)
        $this->sheet->setCellValue('H'.$startRow, $yearlyMale);

        // Kolom I: S - P (Female for yearly standard)
        $this->sheet->setCellValue('I'.$startRow, $yearlyFemale);

        // Kolom J: S - TOTAL
        $this->sheet->setCellValue('J'.$startRow, $yearlyTotal);

        // Kolom K: TS (Yearly non-standard)
        $this->sheet->setCellValue('K'.$startRow, $yearlyNonStandard);

        // Kolom L: TOTAL PELAYANAN
        $this->sheet->setCellValue('L'.$startRow, $yearlyTotalPatients);

        // Kolom M: % CAPAIAN PELAYANAN SESUAI STANDAR
        $this->sheet->setCellValue('M'.$startRow, $yearlyPercentage);
    }

    /**
     * Apply number formats ke range yang sesuai
     */
    private function applyNumberFormats(): void
    {
        // Format angka untuk kolom data
        $this->applyNumberFormat($this->sheet->getParent(), 'B:E', NumberFormat::FORMAT_NUMBER);
        $this->applyNumberFormat($this->sheet->getParent(), 'H:L', NumberFormat::FORMAT_NUMBER);

        // Format persentase untuk kolom %S dan achievement
        $this->applyPercentageFormat($this->sheet->getParent(), 'F:F');
        $this->applyPercentageFormat($this->sheet->getParent(), 'M:M');

        // Apply borders
        $lastRow = $this->sheet->getHighestRow();
        $this->applyBorder($this->sheet->getParent(), 'A7:M'.$lastRow);

        // Apply alignment
        $this->applyAlignment($this->sheet->getParent(), 'A7:M'.$lastRow);
    }
}
