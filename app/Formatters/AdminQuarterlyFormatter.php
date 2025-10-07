<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Formatter untuk template quarterly.xlsx
 * Menangani laporan triwulanan
 */
class AdminQuarterlyFormatter extends BaseAdminFormatter
{
    /**
     * Format spreadsheet menggunakan template quarterly.xlsx
     */
    public function format(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $statistics, int $quarter = null): Spreadsheet
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders di template
        $replacements = [
            '{{DISEASE_TYPE}}' => $this->getDiseaseLabel($diseaseType),
            '{{YEAR}}' => $year,
            '{{QUARTER}}' => $quarter ? 'Triwulan ' . $quarter : 'Semua Triwulan',
            '{{PERIOD}}' => $quarter ? 'Triwulan ' . $quarter . ' Tahun ' . $year : 'Tahun ' . $year,
            '{{GENERATED_DATE}}' => date('d/m/Y'),
            '{{GENERATED_TIME}}' => date('H:i:s')
        ];

        $this->replacePlaceholders($spreadsheet, $replacements);

        // Data saja, template sudah menyediakan header/footer
        $this->formatData($statistics, $diseaseType, $quarter);

        return $spreadsheet;
    }

    /**
     * Format data statistik ke dalam spreadsheet
     */
    private function formatData(array $statistics, string $diseaseType, ?int $quarter): void
    {
        $startRow = 8; // Data mulai di baris 8 sesuai template
        $currentRow = $startRow;

        // Hitung total untuk summary
        $totals = $this->calculateTotals($statistics, $diseaseType, $quarter);

        // Format data per puskesmas
        foreach ($statistics as $index => $data) {
            $this->formatPuskesmasData($data, $currentRow, $index + 1, $diseaseType, $quarter);
            $currentRow++;
        }

        // Format total di baris terakhir
        $this->formatTotalRow($totals, $currentRow, $diseaseType);

        // Tidak ada grid bulanan tambahan di layout baru

        // Hormati format bawaan template
    }

    /**
     * Format data individual puskesmas
     */
    private function formatPuskesmasData(array $data, int $row, int $no, string $diseaseType, ?int $quarter): void
    {
        // Tentukan batas kolom terakhir dari template (kolom persentase paling kanan)
        $lastAllowedCol = $this->getLastDataColumnByHeaders(['% CAPAIAN PELAYANAN SESUAI STANDAR', '% CAPAIAN', '%S']);
        // Kolom A: No
        $this->safeSet('A', $row, $no, $lastAllowedCol);
        // Kolom B: Nama Puskesmas
        $this->safeSet('B', $row, $data['puskesmas_name'] ?? '', $lastAllowedCol);
        // Kolom C: Sasaran
        $key = ($diseaseType === 'dm') ? 'dm' : 'ht';
        if ($diseaseType === 'all') {
            $target = intval(($data['ht']['target'] ?? 0)) + intval(($data['dm']['target'] ?? 0));
        } else {
            $target = intval($data[$key]['target'] ?? 0);
        }
        $this->safeSet('C', $row, $this->formatDataForExcel($target), $lastAllowedCol);

        // Kolom D.. set per triwulan: L, P, TOTAL(=Pelayanan=S+TS), TS, %S (S implisit = TOTAL - TS)
        $currentCol = 'D';
        for ($q = 1; $q <= 4; $q++) {
            $months = $this->getQuarterMonths($q);
            $sumL = 0;
            $sumP = 0;
            $sumTS = 0;
            $sumService = 0;
            foreach ($months as $m) {
                if ($diseaseType === 'all') {
                    $mht = $data['ht']['monthly_data'][$m] ?? [];
                    $mdm = $data['dm']['monthly_data'][$m] ?? [];
                    $sumL += intval($mht['male'] ?? 0) + intval($mdm['male'] ?? 0);
                    $sumP += intval($mht['female'] ?? 0) + intval($mdm['female'] ?? 0);
                    $sumTS += intval($mht['non_standard'] ?? 0) + intval($mdm['non_standard'] ?? 0);
                    $sPart = (intval($mht['standard'] ?? ($mht['male'] ?? 0) + ($mht['female'] ?? 0))
                        + intval($mdm['standard'] ?? ($mdm['male'] ?? 0) + ($mdm['female'] ?? 0)));
                    $sumService += $sPart + intval($mht['non_standard'] ?? 0) + intval($mdm['non_standard'] ?? 0);
                } else {
                    $mdata = $data[$key]['monthly_data'][$m] ?? [];
                    $sumL += intval($mdata['male'] ?? 0);
                    $sumP += intval($mdata['female'] ?? 0);
                    $sumTS += intval($mdata['non_standard'] ?? 0);
                    $sPart = intval($mdata['standard'] ?? ($mdata['male'] ?? 0) + ($mdata['female'] ?? 0));
                    $sumService += $sPart + intval($mdata['non_standard'] ?? 0);
                }
            }
            $sumTotal = $sumService; // TOTAL pelayanan triwulan
            // L
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumL), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            // P
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumP), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            // TOTAL
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumTotal), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            // TS
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumTS), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            // % S: gunakan S = (TOTAL - TS)
            $pct = $target > 0 ? (($sumTotal - $sumTS) / $target) : 0;
            $this->safeSet($currentCol, $row, $pct, $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
        }

        // Total capaian tahun (L,P,TOTAL pelayanan, TS), kolom 'TOTAL PELAYANAN' lama diisi S agar S eksplisit
        $sumL = 0;
        $sumP = 0;
        $yearTS = 0;
        $yearService = 0; // SUM(S+TS)
        for ($m = 1; $m <= 12; $m++) {
            if ($diseaseType === 'all') {
                $mht = $data['ht']['monthly_data'][$m] ?? [];
                $mdm = $data['dm']['monthly_data'][$m] ?? [];
                $sumL += intval($mht['male'] ?? 0) + intval($mdm['male'] ?? 0);
                $sumP += intval($mht['female'] ?? 0) + intval($mdm['female'] ?? 0);
                $yearTS += intval($mht['non_standard'] ?? 0) + intval($mdm['non_standard'] ?? 0);
                $sPart = (intval($mht['standard'] ?? ($mht['male'] ?? 0) + ($mht['female'] ?? 0))
                    + intval($mdm['standard'] ?? ($mdm['male'] ?? 0) + ($mdm['female'] ?? 0)));
                $yearService += $sPart + intval($mht['non_standard'] ?? 0) + intval($mdm['non_standard'] ?? 0);
            } else {
                $mdata = $data[$key]['monthly_data'][$m] ?? [];
                $sumL += intval($mdata['male'] ?? 0);
                $sumP += intval($mdata['female'] ?? 0);
                $yearTS += intval($mdata['non_standard'] ?? 0);
                $sPart = intval($mdata['standard'] ?? ($mdata['male'] ?? 0) + ($mdata['female'] ?? 0));
                $yearService += $sPart + intval($mdata['non_standard'] ?? 0);
            }
        }
        $yearTotal = $yearService; // TOTAL pelayanan
        // L
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumL), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        // P
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumP), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        // TOTAL
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($yearTotal), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        // TS
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($yearTS), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        $yearSExplicit = $yearTotal - $yearTS; // S
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($yearSExplicit), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        $finalPct = $target > 0 ? ($yearSExplicit / $target) : 0;
        $this->safeSet($currentCol, $row, $finalPct, $lastAllowedCol);
    }

    /**
     * Ambil data untuk triwulan tertentu
     */
    private function getQuarterlyData(array $data, int $quarter): array
    {
        $quarterMonths = $this->getQuarterMonths($quarter);
        $quarterlyData = [
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

        foreach ($quarterMonths as $month) {
            $monthlyData = $data['monthly_data'][$month] ?? [];

            // Aggregate HT data
            if (isset($monthlyData['ht'])) {
                $htData = $monthlyData['ht'];
                $quarterlyData['ht']['target'] += intval($htData['target'] ?? 0);
                $quarterlyData['ht']['total_patients'] += intval($htData['total_patients'] ?? 0);
                $quarterlyData['ht']['standard_patients'] += intval($htData['standard_patients'] ?? 0);
                $quarterlyData['ht']['non_standard_patients'] += intval($htData['non_standard_patients'] ?? 0);
                $quarterlyData['ht']['male_patients'] += intval($htData['male_patients'] ?? 0);
                $quarterlyData['ht']['female_patients'] += intval($htData['female_patients'] ?? 0);
            }

            // Aggregate DM data
            if (isset($monthlyData['dm'])) {
                $dmData = $monthlyData['dm'];
                $quarterlyData['dm']['target'] += intval($dmData['target'] ?? 0);
                $quarterlyData['dm']['total_patients'] += intval($dmData['total_patients'] ?? 0);
                $quarterlyData['dm']['standard_patients'] += intval($dmData['standard_patients'] ?? 0);
                $quarterlyData['dm']['non_standard_patients'] += intval($dmData['non_standard_patients'] ?? 0);
                $quarterlyData['dm']['male_patients'] += intval($dmData['male_patients'] ?? 0);
                $quarterlyData['dm']['female_patients'] += intval($dmData['female_patients'] ?? 0);
            }
        }

        return $quarterlyData;
    }

    /**
     * Dapatkan bulan-bulan dalam triwulan
     */
    protected function getQuarterMonths(int $quarter): array
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
     * Hitung total untuk summary
     */
    private function calculateTotals(array $statistics, string $diseaseType, ?int $quarter): array
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
            // Ambil data untuk triwulan tertentu atau total
            $quarterlyData = $quarter ? $this->getQuarterlyData($data, $quarter) : $data;

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htData = $quarterlyData['ht'] ?? $data['ht'] ?? [];
                $totals['ht']['target'] += intval($htData['target'] ?? 0);
                $totals['ht']['total_patients'] += intval($htData['total_patients'] ?? 0);
                $totals['ht']['standard_patients'] += intval($htData['standard_patients'] ?? 0);
                $totals['ht']['non_standard_patients'] += intval($htData['non_standard_patients'] ?? 0);
                $totals['ht']['male_patients'] += intval($htData['male_patients'] ?? 0);
                $totals['ht']['female_patients'] += intval($htData['female_patients'] ?? 0);
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmData = $quarterlyData['dm'] ?? $data['dm'] ?? [];
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

    // Using incrementColumn from BaseAdminFormatter

    /**
     * Format data bulanan dalam triwulan
     */
    private function formatQuarterlyMonthlyData(array $statistics, string $diseaseType, int $quarter): void
    {
        // Dapatkan bulan-bulan dalam triwulan
        $months = $this->getQuarterMonths($quarter);
        $monthNames = [
            1 => 'JANUARI',
            2 => 'FEBRUARI',
            3 => 'MARET',
            4 => 'APRIL',
            5 => 'MEI',
            6 => 'JUNI',
            7 => 'JULI',
            8 => 'AGUSTUS',
            9 => 'SEPTEMBER',
            10 => 'OKTOBER',
            11 => 'NOVEMBER',
            12 => 'DESEMBER'
        ];

        // Tentukan baris awal untuk data puskesmas
        $startRow = 8; // Sesuaikan dengan template quarterly.xlsx

        // Format data untuk setiap puskesmas
        foreach ($statistics as $index => $data) {
            $row = $startRow + $index;
            $puskesmasName = $data['puskesmas_name'] ?? '';

            // Kolom untuk data puskesmas
            $this->sheet->setCellValue('A' . $row, $index + 1); // No
            $this->sheet->setCellValue('B' . $row, $puskesmasName); // Nama Puskesmas
            $this->sheet->setCellValue('C' . $row, $data['ht']['target'] ?? 0); // Sasaran

            // Format data untuk setiap bulan dalam triwulan
            $currentCol = 'D'; // Mulai dari kolom D untuk Januari

            foreach ($months as $month) {
                $monthlyData = $data['monthly_data'][$month] ?? [];
                $htData = $monthlyData['ht'] ?? [];

                // Kolom S (Standard/Terkendali)
                $this->sheet->setCellValue($currentCol . $row, $htData['standard_patients'] ?? 0);
                $currentCol = $this->incrementColumn($currentCol);

                // Kolom TS (Non-Standard/Tidak Terkendali)
                $this->sheet->setCellValue($currentCol . $row, $htData['non_standard_patients'] ?? 0);
                $currentCol = $this->incrementColumn($currentCol);

                // Kolom %S (Persentase Standard)
                $target = $htData['target'] ?? 0;
                $standard = $htData['standard_patients'] ?? 0;
                $percentage = ($target > 0) ? ($standard / $target) * 100 : 0;
                $this->sheet->setCellValue($currentCol . $row, $percentage / 100); // Format sebagai persentase
                $currentCol = $this->incrementColumn($currentCol);
            }

            // Kolom TOTAL TW untuk S, TS, %S
            $totalStandard = 0;
            $totalNonStandard = 0;
            $totalTarget = 0;

            foreach ($months as $month) {
                $monthlyData = $data['monthly_data'][$month] ?? [];
                $htData = $monthlyData['ht'] ?? [];
                $totalStandard += $htData['standard_patients'] ?? 0;
                $totalNonStandard += $htData['non_standard_patients'] ?? 0;
                $totalTarget += $htData['target'] ?? 0;
            }

            // Kolom S untuk TOTAL TW
            $this->sheet->setCellValue($currentCol . $row, $totalStandard);
            $currentCol = $this->incrementColumn($currentCol);

            // Kolom TS untuk TOTAL TW
            $this->sheet->setCellValue($currentCol . $row, $totalNonStandard);
            $currentCol = $this->incrementColumn($currentCol);

            // Kolom %S untuk TOTAL TW
            $totalPercentage = ($totalTarget > 0) ? ($totalStandard / $totalTarget) * 100 : 0;
            $this->sheet->setCellValue($currentCol . $row, $totalPercentage / 100);
            $currentCol = $this->incrementColumn($currentCol);

            // Kolom untuk bulan berikutnya (April dalam contoh)
            if ($quarter == 1) {
                $nextMonth = 4; // April setelah Triwulan I
                $nextMonthData = $data['monthly_data'][$nextMonth] ?? [];
                $nextHtData = $nextMonthData['ht'] ?? [];

                // Kolom L (Laki-laki)
                $this->sheet->setCellValue($currentCol . $row, $nextHtData['male_patients'] ?? 0);
                $currentCol = $this->incrementColumn($currentCol);

                // Kolom P (Perempuan)
                $this->sheet->setCellValue($currentCol . $row, $nextHtData['female_patients'] ?? 0);
                $currentCol = $this->incrementColumn($currentCol);

                // Kolom TOTAL
                $total = ($nextHtData['male_patients'] ?? 0) + ($nextHtData['female_patients'] ?? 0);
                $this->sheet->setCellValue($currentCol . $row, $total);
                $currentCol = $this->incrementColumn($currentCol);

                // Kolom TS
                $this->sheet->setCellValue($currentCol . $row, $nextHtData['non_standard_patients'] ?? 0);
                $currentCol = $this->incrementColumn($currentCol);

                // Kolom %S
                $nextTarget = $nextHtData['target'] ?? 0;
                $nextStandard = $nextHtData['standard_patients'] ?? 0;
                $nextPercentage = ($nextTarget > 0) ? ($nextStandard / $nextTarget) * 100 : 0;
                $this->sheet->setCellValue($currentCol . $row, $nextPercentage / 100);
            }
        }

        // Isi header bulan di baris atas
        $headerRow = $startRow - 2; // Baris untuk header TRIWULAN I
        $this->sheet->setCellValue('D' . $headerRow, 'TRIWULAN I');

        $subHeaderRow = $startRow - 1; // Baris untuk nama bulan
        $currentCol = 'D';
        foreach ($months as $month) {
            // Setiap bulan memiliki 3 kolom (S, TS, %S)
            $this->sheet->setCellValue($currentCol . $subHeaderRow, $monthNames[$month]);
            $this->sheet->mergeCells($currentCol . $subHeaderRow . ':' . $this->incrementColumn($this->incrementColumn($currentCol)) . $subHeaderRow);
            $currentCol = $this->incrementColumn($this->incrementColumn($this->incrementColumn($currentCol)));
        }

        // Header untuk TOTAL TW
        $this->sheet->setCellValue($currentCol . $subHeaderRow, 'TOTAL TW I');
        $this->sheet->mergeCells($currentCol . $subHeaderRow . ':' . $this->incrementColumn($this->incrementColumn($currentCol)) . $subHeaderRow);
        $currentCol = $this->incrementColumn($this->incrementColumn($this->incrementColumn($currentCol)));

        // Header untuk bulan berikutnya
        if ($quarter == 1) {
            $this->sheet->setCellValue($currentCol . $headerRow, 'APRIL');
            $this->sheet->mergeCells($currentCol . $headerRow . ':' . $this->incrementColumn($this->incrementColumn($this->incrementColumn($this->incrementColumn($currentCol)))) . $headerRow);
        }

        // Isi sub-header untuk kolom S, TS, %S
        $subHeaderRow2 = $startRow - 1;
        $currentCol = 'D';

        // Untuk setiap bulan dalam triwulan
        foreach ($months as $month) {
            $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'S');
            $currentCol = $this->incrementColumn($currentCol);

            $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'TS');
            $currentCol = $this->incrementColumn($currentCol);

            $this->sheet->setCellValue($currentCol . $subHeaderRow2, '%S');
            $currentCol = $this->incrementColumn($currentCol);
        }

        // Sub-header untuk TOTAL TW
        $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'S');
        $currentCol = $this->incrementColumn($currentCol);

        $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'TS');
        $currentCol = $this->incrementColumn($currentCol);

        $this->sheet->setCellValue($currentCol . $subHeaderRow2, '%S');
        $currentCol = $this->incrementColumn($currentCol);

        // Sub-header untuk bulan berikutnya
        if ($quarter == 1) {
            $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'L');
            $currentCol = $this->incrementColumn($currentCol);

            $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'P');
            $currentCol = $this->incrementColumn($currentCol);

            $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'TOTAL');
            $currentCol = $this->incrementColumn($currentCol);

            $this->sheet->setCellValue($currentCol . $subHeaderRow2, 'TS');
            $currentCol = $this->incrementColumn($currentCol);

            $this->sheet->setCellValue($currentCol . $subHeaderRow2, '%S');
        }
    }

    /**
     * Mengisi data bulanan triwulan ke kolom yang sesuai
     */
    private function fillQuarterlyMonthlyData(array $monthlyData, int $row, string $diseaseType, array $quarterMonths): void
    {
        // Kolom untuk data bulanan triwulan (sesuaikan dengan template)
        $startCol = ($diseaseType === 'ht') ? 'Q' : 'T'; // Contoh kolom mulai

        foreach ($quarterMonths as $index => $month) {
            $monthData = $monthlyData[$month] ?? [];
            $col = chr(ord($startCol) + $index);

            // Isi data sesuai dengan struktur template
            $this->sheet->setCellValue($col . $row, $this->formatDataForExcel($monthData['total_patients'] ?? 0));
        }
    }

    /**
     * Apply number formats ke range yang sesuai
     */
    private function applyNumberFormats(): void
    {
        // No-op, biarkan template mengatur format angka dan border
    }

    /**
     * Format perbandingan antar triwulan
     */
    private function formatQuarterComparison(array $statistics, string $diseaseType): void
    {
        // Implementasi untuk perbandingan antar triwulan jika diperlukan
        // Sesuaikan dengan struktur template quarterly.xlsx
    }
}
