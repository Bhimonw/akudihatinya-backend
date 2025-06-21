<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AdminAllFormatter extends BaseAdminFormatter
{
    protected $sheet;
    protected $currentRow = 9; // Start from row 9

    public function format($spreadsheet, $diseaseType, $year, $puskesmasId = null)
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders
        $this->replacePlaceholders($diseaseType, $year);

        // Get data using statistics service from base class
        $data = $this->getStatisticsData($diseaseType, $year, $puskesmasId);

        // Format the data
        $this->formatData($data);

        // Apply styles
        $this->applyStyles();

        return $spreadsheet;
    }

    protected function replacePlaceholders($diseaseType, $year)
    {
        $labels = [
            'dm' => 'Diabetes Melitus (DM)',
            'ht' => 'Hipertensi (HT)',
        ];
        $diseaseTypeLabel = $labels[$diseaseType] ?? $diseaseType;
        $highestRow = $this->sheet->getHighestRow();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($this->sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $cell = $this->sheet->getCell($cellCoordinate);
                $value = $cell->getValue();

                if (is_string($value)) {
                    $newValue = str_replace(['<tipe_penyakit>', '<tahun>'], [$diseaseTypeLabel, $year], $value);
                    if ($newValue !== $value) {
                        $cell->setValue($newValue);
                    }
                    if (strpos($newValue, '<mulai>') !== false) {
                        $cell->setValue(str_replace('<mulai>', '', $newValue));
                    }
                }
            }
        }
    }

    protected function formatData($data)
    {
        // Gunakan data summary yang sudah dihitung dari BaseAdminFormatter
        $totals = [
            'target' => $data['summary']['target'],
            'monthly_data' => $data['summary']['monthly_data']
        ];

        // Hitung persentase kumulatif untuk data summary
        $cumulativeStandard = 0;
        foreach ($totals['monthly_data'] as $month => &$monthData) {
            $cumulativeStandard += $monthData['standard'];
            $monthData['percentage'] = $totals['target'] > 0
                ? round(($cumulativeStandard / $totals['target']) * 100, 2)
                : 0;
        }

        // Second pass: format individual puskesmas data
        $startRow = $this->currentRow;
        foreach ($data['data'] as $index => $puskesmasData) {
            $this->sheet->setCellValue('A' . $this->currentRow, $index + 1); // Nomor
            $this->sheet->setCellValue('B' . $this->currentRow, $puskesmasData['puskesmas_name']); // Nama Puskesmas
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $this->sheet->setCellValue('C' . $this->currentRow, $diseaseData['target'] ?? 0); // Sasaran

            $this->formatMonthlyAndQuarterlyData($diseaseData['monthly_data'] ?? []);
            $this->formatYearlyAchievement($diseaseData);
            $this->currentRow++;
        }

        // Dinamis baris total
        $userCount = count($data['data']);
        $defaultRows = 25;
        $totalRow = $startRow + $userCount;
        if ($userCount < $defaultRows) {
            $this->sheet->removeRow($totalRow, $defaultRows - $userCount);
        } elseif ($userCount > $defaultRows) {
            $this->sheet->insertNewRowBefore($startRow + $defaultRows, $userCount - $defaultRows);
            $totalRow = $startRow + $userCount;
        }
        $this->currentRow = $totalRow;
        $this->sheet->setCellValue('A' . $this->currentRow, 'Total');
        $this->sheet->setCellValue('B' . $this->currentRow, '');
        $this->sheet->setCellValue('C' . $this->currentRow, $totals['target']);

        $this->formatMonthlyAndQuarterlyData($totals['monthly_data']);
        $this->formatYearlyAchievement(['monthly_data' => $totals['monthly_data']]);

        // Apply bold style to total row
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $this->sheet->getHighestColumn() . $this->currentRow)
            ->getFont()
            ->setBold(true);
    }

    protected function formatMonthlyAndQuarterlyData($monthlyData)
    {
        // Column mappings for monthly data
        $monthColumns = [
            1 => ['D', 'E', 'F', 'G', 'H'],     // Jan: L, P, Total, TS, %S
            2 => ['I', 'J', 'K', 'L', 'M'],     // Feb: L, P, Total, TS, %S
            3 => ['N', 'O', 'P', 'Q', 'R'],     // Mar: L, P, Total, TS, %S
            4 => ['V', 'W', 'X', 'Y', 'Z'],     // Apr: L, P, Total, TS, %S
            5 => ['AA', 'AB', 'AC', 'AD', 'AE'], // May: L, P, Total, TS, %S
            6 => ['AF', 'AG', 'AH', 'AI', 'AJ'], // Jun: L, P, Total, TS, %S
            7 => ['AN', 'AO', 'AP', 'AQ', 'AR'], // Jul: L, P, Total, TS, %S
            8 => ['AS', 'AT', 'AU', 'AV', 'AW'], // Aug: L, P, Total, TS, %S
            9 => ['AX', 'AY', 'AZ', 'BA', 'BB'], // Sep: L, P, Total, TS, %S
            10 => ['BF', 'BG', 'BH', 'BI', 'BJ'], // Oct: L, P, Total, TS, %S
            11 => ['BK', 'BL', 'BM', 'BN', 'BO'], // Nov: L, P, Total, TS, %S
            12 => ['BP', 'BQ', 'BR', 'BS', 'BT']  // Dec: L, P, Total, TS, %S
        ];

        $quarterColumns = [
            1 => ['S', 'T', 'U'],    // Triwulan I: Total, TS, %S
            2 => ['AK', 'AL', 'AM'], // Triwulan II: Total, TS, %S
            3 => ['BC', 'BD', 'BE'], // Triwulan III: Total, TS, %S
            4 => ['BU', 'BV', 'BW']  // Triwulan IV: Total, TS, %S
        ];

        // Hitung data kumulatif perbulan (akumulasi dari Januari hingga bulan tersebut)
        $cumulativeData = [];
        $cumulative = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];

        // Dapatkan target untuk perhitungan persentase kumulatif
        $target = 0;
        if (isset($this->data['data']) && is_array($this->data['data'])) {
            foreach ($this->data['data'] as $puskesmasData) {
                $diseaseData = $puskesmasData[$this->data['type']] ?? [];
                $target += $diseaseData['target'] ?? 0;
            }
        }

        for ($month = 1; $month <= 12; $month++) {
            $monthData = $monthlyData[$month] ?? [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];

            // Akumulasi data dari Januari hingga bulan ini
            $cumulative['male'] += $monthData['male'];
            $cumulative['female'] += $monthData['female'];
            $cumulative['standard'] += $monthData['standard'];
            $cumulative['non_standard'] += $monthData['non_standard'];
            $cumulative['total'] += $monthData['total'];

            // Hitung persentase kumulatif: akumulasi standar / target total
            $cumulativePercentage = $target > 0
                ? round(($cumulative['standard'] / $target) * 100, 2)
                : 0;

            // Simpan data kumulatif untuk bulan ini
            $cumulativeData[$month] = [
                'male' => $cumulative['male'],
                'female' => $cumulative['female'],
                'standard' => $cumulative['standard'],
                'non_standard' => $cumulative['non_standard'],
                'total' => $cumulative['total'],
                'percentage' => $cumulativePercentage // Gunakan persentase kumulatif
            ];
        }

        // Process data by quarters
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;

            // Fill monthly data dengan data kumulatif
            for ($month = $startMonth; $month <= $endMonth; $month++) {
                $monthData = $cumulativeData[$month];
                $columns = $monthColumns[$month];

                $this->sheet->setCellValue($columns[0] . $this->currentRow, $monthData['male']);      // L (Laki-laki Standar Kumulatif)
                $this->sheet->setCellValue($columns[1] . $this->currentRow, $monthData['female']);    // P (Perempuan Standar Kumulatif)
                $this->sheet->setCellValue($columns[2] . $this->currentRow, $monthData['standard']);  // Total (Total Standar Kumulatif)
                $this->sheet->setCellValue($columns[3] . $this->currentRow, $monthData['non_standard']); // TS (Tidak Standar Kumulatif)
                $this->sheet->setCellValue($columns[4] . $this->currentRow, $monthData['percentage']); // %S (tanpa %)
            }

            // Add quarterly data (menggunakan data kumulatif bulan terakhir di quarter)
            $lastMonthData = $cumulativeData[$endMonth];
            $cols = $quarterColumns[$quarter];

            $this->sheet->setCellValue($cols[0] . $this->currentRow, $lastMonthData['standard']);     // Total (Total Standar Kumulatif)
            $this->sheet->setCellValue($cols[1] . $this->currentRow, $lastMonthData['non_standard']); // TS (Tidak Standar Kumulatif)
            $this->sheet->setCellValue($cols[2] . $this->currentRow, $lastMonthData['percentage']); // %S (tanpa %)
        }
    }

    protected function formatYearlyAchievement($diseaseData)
    {
        $yearlyColumns = [
            'BX', // L (Laki-laki Standar)
            'BY', // P (Perempuan Standar)
            'BZ', // Total (Total Standar)
            'CA', // TS (Tidak Standar)
            'CB', // Total Pasien
            'CC'  // Persentase Tahunan
        ];

        // Get data from last available month (flexible, not hardcoded to December)
        $lastMonthData = null;
        for ($month = 12; $month >= 1; $month--) {
            if (isset($diseaseData['monthly_data'][$month]) && ($diseaseData['monthly_data'][$month]['total'] ?? 0) > 0) {
                $lastMonthData = $diseaseData['monthly_data'][$month];
                break;
            }
        }
        
        // Fallback to empty data if no month has data
        if (!$lastMonthData) {
            $lastMonthData = [
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'total' => 0,
                'percentage' => 0
            ];
        }

        $this->sheet->setCellValue($yearlyColumns[0] . $this->currentRow, $lastMonthData['male']);      // L (Laki-laki Standar)
        $this->sheet->setCellValue($yearlyColumns[1] . $this->currentRow, $lastMonthData['female']);    // P (Perempuan Standar)
        $this->sheet->setCellValue($yearlyColumns[2] . $this->currentRow, $lastMonthData['standard']);  // Total (Total Standar)
        $this->sheet->setCellValue($yearlyColumns[3] . $this->currentRow, $lastMonthData['non_standard']); // TS (Tidak Standar)
        $this->sheet->setCellValue($yearlyColumns[4] . $this->currentRow, $lastMonthData['total']);     // Total Pasien
        $this->sheet->setCellValue($yearlyColumns[5] . $this->currentRow, $lastMonthData['percentage']); // Persentase Tahunan (tanpa %)
    }

    protected function applyStyles()
    {
        // Apply number format to all numeric cells
        $numericRange = 'D9:CC' . $this->currentRow;
        $this->sheet->getStyle($numericRange)->getNumberFormat()->setFormatCode('#,##0');

        // Apply percentage format to percentage columns
        $percentageColumns = [
            'H',
            'M',
            'R',
            'Z',
            'AE',
            'AJ',
            'AR',
            'AW',
            'BB',
            'BJ',
            'BO',
            'BT', // Monthly percentages
            'U',
            'AM',
            'BE',
            'BW', // Quarterly percentages
            'CC' // Persentase Tahunan
        ];

        foreach ($percentageColumns as $col) {
            $this->sheet->getStyle($col . '9:' . $col . $this->currentRow)
                ->getNumberFormat()
                ->setFormatCode('0.00"%"');
        }

        // Apply borders only up to column CC
        $this->sheet->getStyle('A9:CC' . $this->currentRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells up to column CC
        $this->sheet->getStyle('A9:CC' . $this->currentRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Clear any data after column CC
        $highestColumn = $this->sheet->getHighestColumn();
        if ($highestColumn > 'CC') {
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            $ccColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('CC');
            $columnsToRemove = $highestColumnIndex - $ccColumnIndex;

            if ($columnsToRemove > 0) {
                $this->sheet->removeColumn('CD', $columnsToRemove);
            }
        }
    }
}
