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

        // Hanya input data ke tabel (header/footer sudah ada di template)
        $this->formatData($statistics, $diseaseType, $month);

        return $spreadsheet;
    }

    /**
     * Format data statistik ke dalam spreadsheet
     */
    private function formatData(array $statistics, string $diseaseType, ?int $month): void
    {
        // Data mulai dari baris 8 (sesuai template monthly.xlsx)
        $startRow = 8;
        $currentRow = $startRow;

        // Batas kolom terakhir berdasarkan header '% CAPAIAN PELAYANAN SESUAI STANDAR'
        $lastAllowedCol = $this->getLastDataColumnByHeaders(['% CAPAIAN PELAYANAN SESUAI STANDAR', '% CAPAIAN']);

        // Hitung total untuk summary
        $totals = $this->calculateTotals($statistics, $diseaseType, $month);

        // Format data per puskesmas
        foreach ($statistics as $index => $data) {
            $this->formatPuskesmasData($data, $currentRow, $index + 1, $diseaseType, $month);
            $currentRow++;
        }

        // Tidak menambahkan baris total; kolom total per baris sudah tersedia di akhir.

        // Format data harian jika ada
        if ($month) {
            $this->formatDailyData($statistics, $diseaseType, $month);
        }

        // Jangan override format/style template
    }

    /**
     * Format data individual puskesmas
     */
    private function formatPuskesmasData(array $data, int $row, int $no, string $diseaseType, ?int $month): void
    {
        // Tentukan batas kolom terakhir sesuai template agar tidak kelebihan input
        $lastAllowedCol = $this->getLastDataColumnByHeaders(['% CAPAIAN PELAYANAN SESUAI STANDAR', '% CAPAIAN']);

        // Kolom A: No
        $this->safeSet('A', $row, $no);

        // Kolom B: Nama Puskesmas
        $this->safeSet('B', $row, $data['puskesmas_name'] ?? '', $lastAllowedCol);

        // Kolom C: Sasaran (target) berdasarkan jenis layanan
        $key = ($diseaseType === 'dm') ? 'dm' : 'ht';
        $target = 0;
        if ($diseaseType === 'all') {
            $target = intval(($data['ht']['target'] ?? 0)) + intval(($data['dm']['target'] ?? 0));
        } else {
            $target = intval($data[$key]['target'] ?? 0);
        }
        $this->safeSet('C', $row, $this->formatDataForExcel($target), $lastAllowedCol);

        // Mulai kolom D untuk bulan Januari, setiap bulan 5 kolom: L, P, TOTAL(=Pelayanan=S+TS), TS, %S
        // Diselaraskan dengan dashboard (Opsi B): TOTAL sekarang menampilkan seluruh pelayanan (standard + non-standard)
        // S (standard) implisit = TOTAL - TS.
        $currentCol = 'D';
        for ($m = 1; $m <= 12; $m++) {
            if ($diseaseType === 'all') {
                $mht = $data['ht']['monthly_data'][$m] ?? [];
                $mdm = $data['dm']['monthly_data'][$m] ?? [];
                $l = intval($mht['male'] ?? 0) + intval($mdm['male'] ?? 0);
                $p = intval($mht['female'] ?? 0) + intval($mdm['female'] ?? 0);
                $ts = intval($mht['non_standard'] ?? 0) + intval($mdm['non_standard'] ?? 0); // non-standard
                $s = (intval($mht['standard'] ?? ($mht['male'] ?? 0) + ($mht['female'] ?? 0))
                    + intval($mdm['standard'] ?? ($mdm['male'] ?? 0) + ($mdm['female'] ?? 0))); // fallback jika key 'standard' tidak ada
                $total = $s + $ts; // TOTAL pelayanan
            } else {
                $monthly = $data[$key]['monthly_data'][$m] ?? [];
                $l = intval($monthly['male'] ?? 0);
                $p = intval($monthly['female'] ?? 0);
                $ts = intval($monthly['non_standard'] ?? 0);
                $s = intval($monthly['standard'] ?? ($l + $p));
                $total = $s + $ts; // pelayanan
            }
            // %S memakai S = (TOTAL - TS)
            $pct = $target > 0 ? (($total - $ts) / $target) : 0;

            // L
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($l), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            // P
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($p), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            // TOTAL
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($total), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            $this->safeSet($currentCol, $row, $this->formatDataForExcel($ts), $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
            $this->safeSet($currentCol, $row, $pct, $lastAllowedCol);
            $currentCol = $this->incrementColumn($currentCol);
        }

        // Total capaian tahun (agregat sederhana):
        // L & P = sum standard male/female, TOTAL = sum pelayanan (S+TS), TS = sum non-standard
        $sumL = 0;
        $sumP = 0;
        $sumTS = 0;
        $sumService = 0; // TOTAL pelayanan agregat
        for ($m = 1; $m <= 12; $m++) {
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
                $monthly = $data[$key]['monthly_data'][$m] ?? [];
                $sumL += intval($monthly['male'] ?? 0);
                $sumP += intval($monthly['female'] ?? 0);
                $sumTS += intval($monthly['non_standard'] ?? 0);
                $sPart = intval($monthly['standard'] ?? ($monthly['male'] ?? 0) + ($monthly['female'] ?? 0));
                $sumService += $sPart + intval($monthly['non_standard'] ?? 0);
            }
        }
        $sumTotal = $sumService; // TOTAL pelayanan agregat
        // Total capaian tahun: L, P, TOTAL, TS
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumL), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumP), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumTotal), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($sumTS), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        // Kolom TOTAL PELAYANAN lama sekarang isi S agregat (agar S eksplisit)
        $aggregateS = $sumService - $sumTS; // = sum standard examinations (L+P agregat lama)
        $this->safeSet($currentCol, $row, $this->formatDataForExcel($aggregateS), $lastAllowedCol);
        $currentCol = $this->incrementColumn($currentCol);
        $finalPct = $target > 0 ? ($aggregateS / $target) : 0;
        $this->safeSet($currentCol, $row, $finalPct, $lastAllowedCol);
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
        // Pada layout baru tidak ada grid harian di monthly.xlsx. Tidak melakukan apa-apa di sini.
        return;
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
        // No-op to respect template's styles
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
