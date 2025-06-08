<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Helpers\QuarterHelper;

class AdminQuarterlyFormatter extends BaseAdminFormatter
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
            'quarterly_data' => array_fill(1, 4, [
                'male' => 0,
                'female' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'total' => 0,
                'percentage' => 0
            ])
        ];
        $allMonthlyData = [];

        // Agregasi total target dan kumpulkan monthly_data
        foreach ($data['data'] as $puskesmasData) {
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $totals['target'] += $diseaseData['target'] ?? 0;
            if (isset($diseaseData['monthly_data'])) {
                $allMonthlyData[] = $diseaseData['monthly_data'];
            }
        }
        // Hitung total summary triwulan seluruh puskesmas
        $quarterTotals = \App\Helpers\QuarterHelper::getQuarterTotals($allMonthlyData, $totals['target']);

        // Isi data per puskesmas
        $startRow = $this->currentRow;
        foreach ($data['data'] as $index => $puskesmasData) {
            $this->sheet->setCellValue('A' . $this->currentRow, $index + 1);
            $this->sheet->setCellValue('B' . $this->currentRow, $puskesmasData['puskesmas_name']);
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $this->sheet->setCellValue('C' . $this->currentRow, $diseaseData['target'] ?? 0);
            $this->formatQuarterlyData($diseaseData['monthly_data'] ?? []);
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

        // Kolom per triwulan: S L, S P, S Total, TS, %S
        $quarterColumns = [
            1 => ['D', 'E', 'F', 'G', 'H'],
            2 => ['I', 'J', 'K', 'L', 'M'],
            3 => ['N', 'O', 'P', 'Q', 'R'],
            4 => ['S', 'T', 'U', 'V', 'W']
        ];
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $q = $quarterTotals[$quarter];
            $cols = $quarterColumns[$quarter];
            $this->sheet->setCellValue($cols[0] . $this->currentRow, $q['male']);
            $this->sheet->setCellValue($cols[1] . $this->currentRow, $q['female']);
            $this->sheet->setCellValue($cols[2] . $this->currentRow, $q['standard']);
            $this->sheet->setCellValue($cols[3] . $this->currentRow, $q['non_standard']);
            $percent = $totals['target'] > 0 ? round(($q['standard'] / $totals['target']) * 100, 2) : 0;
            $this->sheet->setCellValue($cols[4] . $this->currentRow, $percent);
        }

        // Kolom capaian tahunan (misal: X, Y, Z, AA, AB, AC)
        $decSummary = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0
        ];
        foreach ($allMonthlyData as $monthlyData) {
            if (isset($monthlyData[12])) {
                $decSummary['male'] += $monthlyData[12]['male'] ?? 0;
                $decSummary['female'] += $monthlyData[12]['female'] ?? 0;
                $decSummary['standard'] += $monthlyData[12]['standard'] ?? 0;
                $decSummary['non_standard'] += $monthlyData[12]['non_standard'] ?? 0;
                $decSummary['total'] += $monthlyData[12]['total'] ?? 0;
            }
        }
        $yearlyCols = ['X', 'Y', 'Z', 'AA', 'AB', 'AC'];
        $this->sheet->setCellValue($yearlyCols[0] . $this->currentRow, $decSummary['male']);
        $this->sheet->setCellValue($yearlyCols[1] . $this->currentRow, $decSummary['female']);
        $this->sheet->setCellValue($yearlyCols[2] . $this->currentRow, $decSummary['standard']);
        $this->sheet->setCellValue($yearlyCols[3] . $this->currentRow, $decSummary['non_standard']);
        $this->sheet->setCellValue($yearlyCols[4] . $this->currentRow, $decSummary['total']);
        $yearlySummary = $this->getYearlySummary($allMonthlyData, $totals['target']);
        $this->sheet->setCellValue($yearlyCols[5] . $this->currentRow, $yearlySummary['percentage']);
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $this->sheet->getHighestColumn() . $this->currentRow)
            ->getFont()
            ->setBold(true);
    }

    protected function formatQuarterlyData($monthlyData)
    {
        // Column mappings for quarterly data
        $quarterColumns = [
            1 => ['D', 'E', 'F', 'G', 'H'],     // Triwulan I: L, P, Total, TS, %S
            2 => ['I', 'J', 'K', 'L', 'M'],     // Triwulan II: L, P, Total, TS, %S
            3 => ['N', 'O', 'P', 'Q', 'R'],     // Triwulan III: L, P, Total, TS, %S
            4 => ['S', 'T', 'U', 'V', 'W']      // Triwulan IV: L, P, Total, TS, %S
        ];

        // Process quarterly data
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = QuarterHelper::getQuarterSummary($monthlyData, $quarter);
            $columns = $quarterColumns[$quarter];
            $this->sheet->setCellValue($columns[0] . $this->currentRow, $quarterData['male']);      // L (Laki-laki Standar)
            $this->sheet->setCellValue($columns[1] . $this->currentRow, $quarterData['female']);    // P (Perempuan Standar)
            $this->sheet->setCellValue($columns[2] . $this->currentRow, $quarterData['standard']);  // Total (Total Standar)
            $this->sheet->setCellValue($columns[3] . $this->currentRow, $quarterData['non_standard']); // TS (Tidak Standar)
            $this->sheet->setCellValue($columns[4] . $this->currentRow, $quarterData['percentage']); // %S (tanpa %)
        }
    }

    protected function formatYearlyAchievement($diseaseData)
    {
        $yearlyColumns = [
            'X', // L (Laki-laki Standar)
            'Y', // P (Perempuan Standar)
            'Z', // Total (Total Standar)
            'AA', // TS (Tidak Standar)
            'AB', // Total Pasien
            'AC'  // Persentase Tahunan
        ];

        // Get data from last quarter (Q4) - data terakhir triwulan
        $lastQuarterData = $diseaseData['monthly_data'][12] ?? [
            'male' => 0,
            'female' => 0,
            'standard' => 0,     // S Total
            'non_standard' => 0, // TS
            'total' => 0,
            'percentage' => 0
        ];

        $this->sheet->setCellValue($yearlyColumns[0] . $this->currentRow, $lastQuarterData['male']);      // L (Laki-laki Standar)
        $this->sheet->setCellValue($yearlyColumns[1] . $this->currentRow, $lastQuarterData['female']);    // P (Perempuan Standar)
        $this->sheet->setCellValue($yearlyColumns[2] . $this->currentRow, $lastQuarterData['standard']);  // Total (Total Standar)
        $this->sheet->setCellValue($yearlyColumns[3] . $this->currentRow, $lastQuarterData['non_standard']); // TS (Tidak Standar)
        $this->sheet->setCellValue($yearlyColumns[4] . $this->currentRow, $lastQuarterData['total']);     // Total Pasien
        $this->sheet->setCellValue($yearlyColumns[5] . $this->currentRow, $lastQuarterData['percentage']); // Persentase Tahunan (tanpa %)
    }

    protected function applyStyles()
    {
        // Apply number format to all numeric cells
        $numericRange = 'D8:AC' . $this->currentRow;
        $this->sheet->getStyle($numericRange)->getNumberFormat()->setFormatCode('#,##0');

        // Apply percentage format to percentage columns
        $percentageColumns = [
            'H',
            'M',
            'R',
            'W', // Quarterly percentages
            'AC' // Persentase Tahunan
        ];

        foreach ($percentageColumns as $col) {
            $this->sheet->getStyle($col . '8:' . $col . $this->currentRow)
                ->getNumberFormat()
                ->setFormatCode('0.00"%"');
        }

        // Apply borders
        $this->sheet->getStyle('A8:AC' . $this->currentRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells
        $this->sheet->getStyle('A8:AC' . $this->currentRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Clear any data after column AC
        $highestColumn = $this->sheet->getHighestColumn();
        if ($highestColumn > 'AC') {
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            $acColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('AC');
            $columnsToRemove = $highestColumnIndex - $acColumnIndex;

            if ($columnsToRemove > 0) {
                $this->sheet->removeColumn('AD', $columnsToRemove);
            }
        }
    }
}
