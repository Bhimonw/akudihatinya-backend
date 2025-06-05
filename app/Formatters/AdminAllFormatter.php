<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AdminAllFormatter
{
    protected $statisticsService;
    protected $sheet;
    protected $currentRow = 9;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function format($spreadsheet, $diseaseType, $year, $puskesmasId = null)
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders
        $this->replacePlaceholders($diseaseType, $year);

        // Get data using statistics service
        if ($puskesmasId) {
            // Single puskesmas data
            $data = [
                'data' => [
                    [
                        'puskesmas_id' => $puskesmasId,
                        'puskesmas_name' => \App\Models\Puskesmas::find($puskesmasId)->name,
                        'ht' => $this->statisticsService->getHtStatisticsFromCache($puskesmasId, $year),
                        'dm' => $this->statisticsService->getDmStatisticsFromCache($puskesmasId, $year)
                    ]
                ],
                'type' => $diseaseType
            ];
        } else {
            // All puskesmas data using admin service
            $request = new \Illuminate\Http\Request([
                'year' => $year,
                'type' => $diseaseType
            ]);
            $data = app(\App\Services\StatisticsAdminService::class)->getAdminStatistics($request);
        }

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
        foreach ($data['data'] as $index => $puskesmasData) {
            $this->sheet->setCellValue('A' . $this->currentRow, $index + 1); // Nomor
            $this->sheet->setCellValue('B' . $this->currentRow, $puskesmasData['puskesmas_name']); // Nama Puskesmas
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $this->sheet->setCellValue('C' . $this->currentRow, $diseaseData['target'] ?? 0); // Sasaran
            $this->formatMonthlyAndQuarterlyData($diseaseData['monthly_data'] ?? []);
            $this->formatYearlyAchievement($diseaseData);
            $this->currentRow++;
        }
        // Setelah data, pastikan baris berikutnya kosong hingga baris 34
        while ($this->currentRow < 34) {
            $this->currentRow++;
        }
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

        // Process data by quarters
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;

            // Fill monthly data
            for ($month = $startMonth; $month <= $endMonth; $month++) {
                $monthData = $monthlyData[$month] ?? [
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                    'percentage' => 0
                ];
                $columns = $monthColumns[$month];

                $this->sheet->setCellValue($columns[0] . $this->currentRow, $monthData['male']);      // L (Laki-laki Standar)
                $this->sheet->setCellValue($columns[1] . $this->currentRow, $monthData['female']);    // P (Perempuan Standar)
                $this->sheet->setCellValue($columns[2] . $this->currentRow, $monthData['standard']);  // Total (Total Standar)
                $this->sheet->setCellValue($columns[3] . $this->currentRow, $monthData['non_standard']); // TS (Tidak Standar)
                $this->sheet->setCellValue($columns[4] . $this->currentRow, $monthData['percentage']); // %S (tanpa %)
            }

            // Add quarterly data
            $lastMonthData = $monthlyData[$endMonth] ?? [
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
            $cols = $quarterColumns[$quarter];

            $this->sheet->setCellValue($cols[0] . $this->currentRow, $lastMonthData['standard']);     // Total (Total Standar)
            $this->sheet->setCellValue($cols[1] . $this->currentRow, $lastMonthData['non_standard']); // TS (Tidak Standar)
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

        // Get data from last month (December)
        $lastMonthData = $diseaseData['monthly_data'][12] ?? [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0,
            'percentage' => 0
        ];

        $this->sheet->setCellValue($yearlyColumns[0] . $this->currentRow, $lastMonthData['male']);      // L (Laki-laki Standar)
        $this->sheet->setCellValue($yearlyColumns[1] . $this->currentRow, $lastMonthData['female']);    // P (Perempuan Standar)
        $this->sheet->setCellValue($yearlyColumns[2] . $this->currentRow, $lastMonthData['standard']);  // Total (Total Standar)
        $this->sheet->setCellValue($yearlyColumns[3] . $this->currentRow, $lastMonthData['non_standard']); // TS (Tidak Standar)
        $this->sheet->setCellValue($yearlyColumns[4] . $this->currentRow, $lastMonthData['total']);     // Total Pasien (dari bulan terakhir)
        $this->sheet->setCellValue($yearlyColumns[5] . $this->currentRow, $lastMonthData['percentage']); // Persentase Tahunan (tanpa %)
    }

    protected function applyStyles()
    {
        // Apply number format to all numeric cells
        $numericRange = 'D9:' . $this->sheet->getHighestColumn() . $this->currentRow;
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

        // Apply borders
        $this->sheet->getStyle('A9:' . $this->sheet->getHighestColumn() . $this->currentRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells
        $this->sheet->getStyle('A9:' . $this->sheet->getHighestColumn() . $this->currentRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
}
