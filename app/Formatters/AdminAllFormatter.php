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

        // Data saja, template sudah menyiapkan header/footer dan kolom
        $this->formatData($statistics, $diseaseType);

        return $spreadsheet;
    }

    /**
     * Format data statistik ke dalam spreadsheet
     */
    private function formatData(array $statistics, string $diseaseType): void
    {
        $startRow = 9; // Sesuai instruksi: all mulai baris 9
        $currentRow = $startRow;

        // Batas kolom terakhir berdasarkan header persentase paling kanan
        $lastAllowedCol = $this->getLastDataColumnByHeaders(['% CAPAIAN PELAYANAN SESUAI STANDAR', '% CAPAIAN', '%S']);

        // Hitung total untuk summary
        $totals = $this->calculateTotals($statistics, $diseaseType);

        // Format data per puskesmas
        foreach ($statistics as $index => $data) {
            $this->formatPuskesmasData($data, $currentRow, $index + 1, $diseaseType);
            $currentRow++;
        }

        // Biarkan footer/total ditangani oleh template jika ada

        // Apply number formatting
        $this->applyNumberFormats();
    }

    /**
     * Format data individual puskesmas
     */
    private function formatPuskesmasData(array $data, int $row, int $no, string $diseaseType): void
    {
        // Tentukan batas kolom data terakhir sesuai template agar tidak kelebihan input
        $lastAllowedCol = $this->getLastDataColumnByHeaders(['% CAPAIAN PELAYANAN SESUAI STANDAR', '% CAPAIAN', '%S']);

        // Kolom A: No dan B: Nama Puskesmas
        $this->safeSet('A', $row, $no, $lastAllowedCol);
        $this->safeSet('B', $row, $data['puskesmas_name'] ?? '', $lastAllowedCol);
        // Kolom C: Sasaran (target) untuk layanan yang dipilih
        $key = ($diseaseType === 'dm') ? 'dm' : 'ht';
        if ($diseaseType === 'all') {
            $target = intval(($data['ht']['target'] ?? 0)) + intval(($data['dm']['target'] ?? 0));
        } else {
            $target = intval($data[$key]['target'] ?? 0);
        }
        $this->safeSet('C', $row, $this->formatDataForExcel($target), $lastAllowedCol);

    // Data per Triwulan: setiap bulan 5 kolom: L, P, TOTAL (Pelayanan = S+TS), TS, %S
    // (Diselaraskan dengan dashboard: TOTAL sekarang = seluruh pelayanan (standard + non-standard) bulan itu,
    // sementara %S tetap memakai S/target, dan S tersirat = TOTAL - TS.)
        // Selaras dengan template: OKT/NOV/DES dsb dan TOTAL TW di akhir triwulan jika header-nya ada
        $currentCol = 'D';
        // Track latest month (across the entire year) that has data
        $latestYearL = 0;
        $latestYearP = 0;
        $latestYearTS = 0;

        // Detect presence of TOTAL TW header to decide whether to write those blocks
        $hasTotalTw = $this->hasHeaderLabel(['TOTAL TW', 'TOTAL TRI']);

        for ($q = 1; $q <= 4; $q++) {
            $qL_sum = 0; // for TOTAL TW if present
            $qP_sum = 0;
            $qTS_sum = 0;
            $latestMonthL = null; // latest values within the quarter
            $latestMonthP = null;
            $latestMonthTS = null;
            foreach ($this->getQuarterMonths($q) as $m) {
                if ($diseaseType === 'all') {
                    $mht = $data['ht']['monthly_data'][$m] ?? [];
                    $mdm = $data['dm']['monthly_data'][$m] ?? [];
                    $l = intval($mht['male'] ?? 0) + intval($mdm['male'] ?? 0);          // standard male
                    $p = intval($mht['female'] ?? 0) + intval($mdm['female'] ?? 0);      // standard female
                    $ts = intval($mht['non_standard'] ?? 0) + intval($mdm['non_standard'] ?? 0); // non-standard
                    $s = (intval($mht['standard'] ?? ($mht['male'] ?? 0) + ($mht['female'] ?? 0))
                        + intval($mdm['standard'] ?? ($mdm['male'] ?? 0) + ($mdm['female'] ?? 0))); // fallback jika 'standard' tidak ada
                } else {
                    $monthly = $data[$key]['monthly_data'][$m] ?? [];
                    $l = intval($monthly['male'] ?? 0);
                    $p = intval($monthly['female'] ?? 0);
                    $ts = intval($monthly['non_standard'] ?? 0);
                    $s = intval($monthly['standard'] ?? ($l + $p));
                }
                // TOTAL pelayanan sekarang = S + TS
                $totalPelayanan = $s + $ts;
                $pct = $target > 0 ? ($s / $target) : 0; // %S tetap berbasis S/target
                // Write L, P, TOTAL (pelayanan), TS, %
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($l), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($p), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($totalPelayanan), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($ts), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $pct, $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);

                // Accumulate for TOTAL TW and track latest month values within the quarter
                $qL_sum += $l;
                $qP_sum += $p;
                $qTS_sum += $ts;
                $latestMonthL = $l;
                $latestMonthP = $p;
                $latestMonthTS = $ts;

                // Track latest month WITH any data across the year
                if (($l + $p + $ts) > 0) {
                    $latestYearL = $l;
                    $latestYearP = $p;
                    $latestYearTS = $ts;
                }
            }

            // TOTAL TW block only if header exists in template
            if ($hasTotalTw) {
                // Untuk triwulan, S triwulan = total standard (L+P) triwulan.
                $qStandard = $qL_sum + $qP_sum;
                // Total pelayanan triwulan = S + TS triwulan
                $qTotalPelayanan = $qStandard + $qTS_sum;
                $qPct = $target > 0 ? ($qStandard / $target) : 0;
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($qL_sum), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($qP_sum), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($qTotalPelayanan), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $this->formatDataForExcel($qTS_sum), $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
                $this->safeSet($currentCol, $row, $qPct, $lastAllowedCol);
                $currentCol = $this->incrementColumn($currentCol);
            }

            // Quarter-level latest values are not used directly for annual; using latestYear* tracked above
        }

    // Annual final block (snapshot last month with data):
    // L, P = standard male/female terakhir; TOTAL (kolom) = TOTAL PELAYANAN = S + TS; TS = non standard; kolom berikutnya (TOTAL PELAYANAN lama) kita isi S (standard) agar tetap punya nilai S eksplisit.
    $annualStandard = $latestYearL + $latestYearP; // S
    $annualTotalPelayanan = $annualStandard + $latestYearTS; // S + TS

    // Kolom: L
    $this->safeSet($currentCol, $row, $this->formatDataForExcel($latestYearL), $lastAllowedCol); $currentCol = $this->incrementColumn($currentCol);
    // Kolom: P
    $this->safeSet($currentCol, $row, $this->formatDataForExcel($latestYearP), $lastAllowedCol); $currentCol = $this->incrementColumn($currentCol);
    // Kolom: TOTAL (pelayanan)
    $this->safeSet($currentCol, $row, $this->formatDataForExcel($annualTotalPelayanan), $lastAllowedCol); $currentCol = $this->incrementColumn($currentCol);
    // Kolom: TS (non standard)
    $this->safeSet($currentCol, $row, $this->formatDataForExcel($latestYearTS), $lastAllowedCol); $currentCol = $this->incrementColumn($currentCol);
    // Kolom: (sebelumnya TOTAL PELAYANAN) sekarang kita isi S (standard) supaya masih tersedia
    $this->safeSet($currentCol, $row, $this->formatDataForExcel($annualStandard), $lastAllowedCol); $currentCol = $this->incrementColumn($currentCol);
    // Kolom %S
    $finalPct = $target > 0 ? ($annualStandard / $target) : 0;
    $this->safeSet($currentCol, $row, $finalPct, $lastAllowedCol);
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
        // No-op: biarkan template mengatur format, border, dan alignment
    }
}
