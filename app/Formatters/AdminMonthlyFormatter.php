<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AdminMonthlyFormatter extends BaseAdminFormatter
{
    protected $statisticsService;
    protected $sheet;
    protected $currentRow = 8; // Start from row 8

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function format($spreadsheet, $diseaseType, $year, $puskesmasId = null)
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders
        $this->replacePlaceholders($diseaseType, $year);

        // Ambil data statistik seragam dari base class
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
        $totals = [
            'target' => 0,
            'monthly_data' => array_fill(1, 12, [
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'total' => 0,
                'percentage' => 0
            ])
        ];

        foreach ($data['data'] as $puskesmasData) {
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $totals['target'] += $diseaseData['target'] ?? 0;
            if (isset($diseaseData['monthly_data'])) {
                foreach ($diseaseData['monthly_data'] as $month => $monthData) {
                    $totals['monthly_data'][$month]['male'] += $monthData['male'] ?? 0;
                    $totals['monthly_data'][$month]['female'] += $monthData['female'] ?? 0;
                    $totals['monthly_data'][$month]['standard'] += $monthData['standard'] ?? 0;
                    $totals['monthly_data'][$month]['non_standard'] += $monthData['non_standard'] ?? 0;
                    $totals['monthly_data'][$month]['total'] += $monthData['total'] ?? 0;
                }
            }
        }
        foreach ($totals['monthly_data'] as $month => &$monthData) {
            $monthData['percentage'] = $totals['target'] > 0
                ? round(($monthData['standard'] / $totals['target']) * 100, 2)
                : 0;
        }

        $startRow = $this->currentRow;
        foreach ($data['data'] as $index => $puskesmasData) {
            $this->sheet->setCellValue('A' . $this->currentRow, $index + 1);
            $this->sheet->setCellValue('B' . $this->currentRow, $puskesmasData['puskesmas_name']);
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $this->sheet->setCellValue('C' . $this->currentRow, $diseaseData['target'] ?? 0);
            $this->formatMonthlyData($diseaseData['monthly_data'] ?? []);
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
        $this->formatMonthlyData($totals['monthly_data']);
        // Kolom summary tahunan (misal: BL, BM, BN, BO, BP, BQ)
        $allMonthlyData = [];
        foreach ($data['data'] as $puskesmasData) {
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            if (isset($diseaseData['monthly_data'])) {
                $allMonthlyData[] = $diseaseData['monthly_data'];
            }
        }
        $yearlyCols = ['BL', 'BM', 'BN', 'BO', 'BP', 'BQ'];
        $yearlySummary = $this->getYearlySummary($allMonthlyData, $totals['target']);
        $this->sheet->setCellValue($yearlyCols[2] . $this->currentRow, $yearlySummary['standard']);
        $this->sheet->setCellValue($yearlyCols[4] . $this->currentRow, $yearlySummary['total']);
        $this->sheet->setCellValue($yearlyCols[5] . $this->currentRow, $yearlySummary['percentage']);
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $this->sheet->getHighestColumn() . $this->currentRow)
            ->getFont()
            ->setBold(true);
    }

    protected function formatMonthlyData($monthlyData)
    {
        // Column mappings for monthly data
        $monthColumns = [
            1 => ['D', 'E', 'F', 'G', 'H'],     // Jan: L, P, Total, TS, %S
            2 => ['I', 'J', 'K', 'L', 'M'],     // Feb: L, P, Total, TS, %S
            3 => ['N', 'O', 'P', 'Q', 'R'],     // Mar: L, P, Total, TS, %S
            4 => ['S', 'T', 'U', 'V', 'W'],     // Apr: L, P, Total, TS, %S
            5 => ['X', 'Y', 'Z', 'AA', 'AB'],   // May: L, P, Total, TS, %S
            6 => ['AC', 'AD', 'AE', 'AF', 'AG'], // Jun: L, P, Total, TS, %S
            7 => ['AH', 'AI', 'AJ', 'AK', 'AL'], // Jul: L, P, Total, TS, %S
            8 => ['AM', 'AN', 'AO', 'AP', 'AQ'], // Aug: L, P, Total, TS, %S
            9 => ['AR', 'AS', 'AT', 'AU', 'AV'], // Sep: L, P, Total, TS, %S
            10 => ['AW', 'AX', 'AY', 'AZ', 'BA'], // Oct: L, P, Total, TS, %S
            11 => ['BB', 'BC', 'BD', 'BE', 'BF'], // Nov: L, P, Total, TS, %S
            12 => ['BG', 'BH', 'BI', 'BJ', 'BK']  // Dec: L, P, Total, TS, %S
        ];

        // Fill monthly data
        for ($month = 1; $month <= 12; $month++) {
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
    }

    protected function formatYearlyAchievement($diseaseData)
    {
        $yearlyColumns = [
            'BL', // L (Laki-laki Standar)
            'BM', // P (Perempuan Standar)
            'BN', // Total (Total Standar)
            'BO', // TS (Tidak Standar)
            'BP', // Total Pasien
            'BQ'  // Persentase Tahunan
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
        $numericRange = 'D8:BQ' . $this->currentRow;
        $this->sheet->getStyle($numericRange)->getNumberFormat()->setFormatCode('#,##0');

        // Apply percentage format to percentage columns
        $percentageColumns = [
            'H',
            'M',
            'R',
            'W',
            'AB',
            'AG',
            'AL',
            'AQ',
            'AV',
            'BA',
            'BF',
            'BK', // Monthly percentages
            'BQ' // Persentase Tahunan
        ];

        foreach ($percentageColumns as $col) {
            $this->sheet->getStyle($col . '8:' . $col . $this->currentRow)
                ->getNumberFormat()
                ->setFormatCode('0.00"%"');
        }

        // Apply borders
        $this->sheet->getStyle('A8:BQ' . $this->currentRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells
        $this->sheet->getStyle('A8:BQ' . $this->currentRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Clear any data after column BQ
        $highestColumn = $this->sheet->getHighestColumn();
        if ($highestColumn > 'BQ') {
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            $bqColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('BQ');
            $columnsToRemove = $highestColumnIndex - $bqColumnIndex;

            if ($columnsToRemove > 0) {
                $this->sheet->removeColumn('BR', $columnsToRemove);
            }
        }
    }
}
