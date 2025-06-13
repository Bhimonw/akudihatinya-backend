<?php

namespace App\Formatters;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class PuskesmasFormatter extends BaseAdminFormatter
{
    protected $sheet;
    protected $currentRow = 8;

    public function format(Spreadsheet $spreadsheet, $diseaseType, $year, $puskesmasId = null)
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders in the template
        $this->replacePlaceholders($diseaseType, $year);

        // Get statistics data for the specific puskesmas
        $data = $this->getStatisticsData($diseaseType, $year, $puskesmasId);

        // Format the data into the spreadsheet
        $this->formatData($data);

        // Apply styles
        $this->applyStyles();

        return $spreadsheet;
    }

    protected function replacePlaceholders($diseaseType, $year)
    {
        // Replace year placeholder
        $this->replaceInSheet('{{YEAR}}', $year);

        // Replace disease type placeholder
        $diseaseLabel = $diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes Melitus';
        $this->replaceInSheet('{{DISEASE_TYPE}}', $diseaseLabel);

        // Replace current date
        $this->replaceInSheet('{{CURRENT_DATE}}', Carbon::now()->format('d F Y'));
    }

    protected function replaceInSheet($search, $replace)
    {
        foreach ($this->sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                if (is_string($value) && strpos($value, $search) !== false) {
                    $cell->setValue(str_replace($search, $replace, $value));
                }
            }
        }
    }

    protected function formatData($data)
    {
        if (empty($data['data'])) {
            return;
        }

        // Calculate totals for summary row
        $totals = [
            'target' => 0,
            'monthly_data' => []
        ];

        // Initialize monthly totals
        for ($month = 1; $month <= 12; $month++) {
            $totals['monthly_data'][$month] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        $startRow = $this->currentRow;
        foreach ($data['data'] as $index => $puskesmasData) {
            $this->sheet->setCellValue('A' . $this->currentRow, $index + 1);
            $this->sheet->setCellValue('B' . $this->currentRow, $puskesmasData['puskesmas_name']);
            $diseaseData = $puskesmasData[$data['type']] ?? [];
            $this->sheet->setCellValue('C' . $this->currentRow, $diseaseData['target'] ?? 0);

            // Add to totals
            $totals['target'] += $diseaseData['target'] ?? 0;

            // Format monthly data for this puskesmas
            $this->formatMonthlyData($diseaseData['monthly_data'] ?? []);

            // Add monthly data to totals
            foreach ($diseaseData['monthly_data'] ?? [] as $month => $monthData) {
                $totals['monthly_data'][$month]['male'] += $monthData['male'] ?? 0;
                $totals['monthly_data'][$month]['female'] += $monthData['female'] ?? 0;
                $totals['monthly_data'][$month]['total'] += $monthData['total'] ?? 0;
                $totals['monthly_data'][$month]['standard'] += $monthData['standard'] ?? 0;
                $totals['monthly_data'][$month]['non_standard'] += $monthData['non_standard'] ?? 0;
            }

            // Format yearly achievement
            $this->formatYearlyAchievement($diseaseData);
            $this->currentRow++;
        }

        // Calculate percentages for totals
        foreach ($totals['monthly_data'] as $month => &$monthData) {
            if ($monthData['total'] > 0) {
                $monthData['percentage'] = ($monthData['standard'] / $monthData['total']) * 100;
            }
        }

        // Handle dynamic rows (same as AdminMonthlyFormatter)
        $userCount = count($data['data']);
        $defaultRows = 25;
        $totalRow = $startRow + $userCount;
        if ($userCount < $defaultRows) {
            $this->sheet->removeRow($totalRow, $defaultRows - $userCount);
        } elseif ($userCount > $defaultRows) {
            $this->sheet->insertNewRowBefore($startRow + $defaultRows, $userCount - $defaultRows);
            $totalRow = $startRow + $userCount;
        }

        // Format total row
        $this->currentRow = $totalRow;
        $this->sheet->setCellValue('A' . $this->currentRow, 'Total');
        $this->sheet->setCellValue('B' . $this->currentRow, '');
        $this->sheet->setCellValue('C' . $this->currentRow, $totals['target']);
        $this->formatMonthlyData($totals['monthly_data']);

        // Format yearly summary for totals
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

        // Make total row bold
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $this->sheet->getHighestColumn() . $this->currentRow)
            ->getFont()
            ->setBold(true);
    }

    protected function formatMonthlyData($monthlyData)
    {
        // Column mappings for monthly data (same as AdminMonthlyFormatter)
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

        // Get data from last month (December) or calculate yearly totals
        $yearlyData = [
            'male' => 0,
            'female' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'total' => 0,
            'percentage' => 0
        ];

        // Calculate yearly totals from all months
        foreach ($diseaseData['monthly_data'] ?? [] as $monthData) {
            $yearlyData['male'] += $monthData['male'] ?? 0;
            $yearlyData['female'] += $monthData['female'] ?? 0;
            $yearlyData['standard'] += $monthData['standard'] ?? 0;
            $yearlyData['non_standard'] += $monthData['non_standard'] ?? 0;
            $yearlyData['total'] += $monthData['total'] ?? 0;
        }

        // Calculate percentage
        if ($yearlyData['total'] > 0) {
            $yearlyData['percentage'] = ($yearlyData['standard'] / $yearlyData['total']) * 100;
        }

        $this->sheet->setCellValue($yearlyColumns[0] . $this->currentRow, $yearlyData['male']);      // L (Laki-laki Standar)
        $this->sheet->setCellValue($yearlyColumns[1] . $this->currentRow, $yearlyData['female']);    // P (Perempuan Standar)
        $this->sheet->setCellValue($yearlyColumns[2] . $this->currentRow, $yearlyData['standard']);  // Total (Total Standar)
        $this->sheet->setCellValue($yearlyColumns[3] . $this->currentRow, $yearlyData['non_standard']); // TS (Tidak Standar)
        $this->sheet->setCellValue($yearlyColumns[4] . $this->currentRow, $yearlyData['total']);     // Total Pasien
        $this->sheet->setCellValue($yearlyColumns[5] . $this->currentRow, $yearlyData['percentage']); // Persentase Tahunan (tanpa %)
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
