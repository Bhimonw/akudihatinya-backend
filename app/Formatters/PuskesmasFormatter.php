<?php

namespace App\Formatters;

use App\Services\StatisticsService;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PuskesmasFormatter
{
    protected $statisticsService;
    protected $sheet;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function format($spreadsheet, $diseaseType, $year, $puskesmasId)
    {
        $this->sheet = $spreadsheet->getActiveSheet();

        // Replace placeholders
        $this->replacePlaceholders($diseaseType, $year, $puskesmasId);

        // Get statistics data for the specific puskesmas
        $data = $this->getStatisticsData($diseaseType, $year, $puskesmasId);

        // Format the data according to puskesmas structure
        $this->formatPuskesmasData($data, $diseaseType);

        // Apply styles
        $this->applyStyles();

        return $spreadsheet;
    }

    protected function replacePlaceholders($diseaseType, $year, $puskesmasId)
    {
        $labels = [
            'dm' => 'Diabetes Melitus',
            'ht' => 'Hipertensi',
        ];
        $diseaseTypeLabel = $labels[$diseaseType] ?? $diseaseType;

        // Get puskesmas name
        $puskesmas = Puskesmas::find($puskesmasId);
        $puskesmasName = $puskesmas ? $puskesmas->name : 'Unknown';

        // Get yearly target
        $yearlyTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', $diseaseType)
            ->first();
        $target = $yearlyTarget ? $yearlyTarget->target_count : 0;

        $highestRow = $this->sheet->getHighestRow();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($this->sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $cell = $this->sheet->getCell($cellCoordinate);
                $value = $cell->getValue();

                if (is_string($value)) {
                    $newValue = str_replace(
                        ['<tipe_penyakit>', '<disease_type>', '<tahun>', '<puskesmas>', '<sasaran>', '<mulai>'],
                        [$diseaseTypeLabel, $diseaseTypeLabel, $year, $puskesmasName, $target, ''],
                        $value
                    );
                    if ($newValue !== $value) {
                        $cell->setValue($newValue);
                    }
                }
            }
        }
    }

    protected function getStatisticsData($diseaseType, $year, $puskesmasId)
    {
        // Get statistics data from cache for the specific puskesmas
        if ($diseaseType === 'ht') {
            $diseaseData = $this->statisticsService->getHtStatisticsFromCache($puskesmasId, $year);
        } else {
            $diseaseData = $this->statisticsService->getDmStatisticsFromCache($puskesmasId, $year);
        }

        return [
            'puskesmas_id' => $puskesmasId,
            'puskesmas_name' => Puskesmas::find($puskesmasId)->name ?? 'Unknown',
            'disease_data' => $diseaseData,
            'disease_type' => $diseaseType,
            'target' => YearlyTarget::where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('disease_type', $diseaseType)
                ->first()?->target_count ?? 0
        ];
    }

    protected function formatPuskesmasData($data, $diseaseType)
    {
        $diseaseData = $data['disease_data'] ?? [];
        $monthlyData = $diseaseData['monthly_data'] ?? [];

        // Struktur data sesuai permintaan:
        // Baris 9-11: Data Januari-Maret (data asli per bulan)
        // Baris 12: Data Triwulan I (menggunakan data Maret)
        // Baris 13-15: Data April-Juni (data asli per bulan)
        // Baris 16: Data Triwulan II (menggunakan data Juni)
        // Baris 17-19: Data Juli-September (data asli per bulan)
        // Baris 20: Data Triwulan III (menggunakan data September)
        // Baris 21-23: Data Oktober-Desember (data asli per bulan)
        // Baris 24: Data Triwulan IV (menggunakan data Desember)
        // Baris 25: Data terakhir (menggunakan data Desember)

        $currentRow = 9;

        // Baris 9-11: Januari-Maret
        for ($month = 1; $month <= 3; $month++) {
            $this->fillMonthlyRow($currentRow, $month, $monthlyData[$month] ?? []);
            $currentRow++;
        }

        // Baris 12: Triwulan I (data Maret)
        $this->fillQuarterlyRow($currentRow, $monthlyData[3] ?? [], 'Triwulan I');
        $currentRow++;

        // Baris 13-15: April-Juni
        for ($month = 4; $month <= 6; $month++) {
            $this->fillMonthlyRow($currentRow, $month, $monthlyData[$month] ?? []);
            $currentRow++;
        }

        // Baris 16: Triwulan II (data Juni)
        $this->fillQuarterlyRow($currentRow, $monthlyData[6] ?? [], 'Triwulan II');
        $currentRow++;

        // Baris 17-19: Juli-September
        for ($month = 7; $month <= 9; $month++) {
            $this->fillMonthlyRow($currentRow, $month, $monthlyData[$month] ?? []);
            $currentRow++;
        }

        // Baris 20: Triwulan III (data September)
        $this->fillQuarterlyRow($currentRow, $monthlyData[9] ?? [], 'Triwulan III');
        $currentRow++;

        // Baris 21-23: Oktober-Desember
        for ($month = 10; $month <= 12; $month++) {
            $this->fillMonthlyRow($currentRow, $month, $monthlyData[$month] ?? []);
            $currentRow++;
        }

        // Baris 24: Triwulan IV (data Desember)
        $this->fillQuarterlyRow($currentRow, $monthlyData[12] ?? [], 'Triwulan IV');
        $currentRow++;

        // Baris 25: Data terakhir (data Desember)
        $this->fillYearlyRow($currentRow, $monthlyData[12] ?? []);

        // H10-M10: Rekap summary dengan data Desember
        $this->fillSummaryRow(10, $monthlyData[12] ?? []);
    }

    protected function fillMonthlyRow($row, $month, $monthData)
    {
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        // Kolom A: Bulan
        $this->sheet->setCellValue('A' . $row, $monthNames[$month] ?? '');

        // Data non-agregasi (data menurun per bulan)
        $this->sheet->setCellValue('B' . $row, $monthData['male'] ?? 0);
        $this->sheet->setCellValue('C' . $row, $monthData['female'] ?? 0);
        $this->sheet->setCellValue('D' . $row, $monthData['standard'] ?? 0);
        $this->sheet->setCellValue('E' . $row, $monthData['non_standard'] ?? 0);
        $this->sheet->setCellValue('F' . $row, $monthData['percentage'] ?? 0);
    }

    protected function fillQuarterlyRow($row, $monthData, $quarterName)
    {
        // Kolom A: Nama Triwulan
        $this->sheet->setCellValue('A' . $row, $quarterName);

        // Menggunakan data dari bulan terakhir triwulan
        $this->sheet->setCellValue('B' . $row, $monthData['male'] ?? 0);
        $this->sheet->setCellValue('C' . $row, $monthData['female'] ?? 0);
        $this->sheet->setCellValue('D' . $row, $monthData['standard'] ?? 0);
        $this->sheet->setCellValue('E' . $row, $monthData['non_standard'] ?? 0);
        $this->sheet->setCellValue('F' . $row, $monthData['percentage'] ?? 0);
    }

    protected function fillYearlyRow($row, $monthData)
    {
        // Kolom A: Data Terakhir
        $this->sheet->setCellValue('A' . $row, 'Data Terakhir');

        // Menggunakan data Desember
        $this->sheet->setCellValue('B' . $row, $monthData['male'] ?? 0);
        $this->sheet->setCellValue('C' . $row, $monthData['female'] ?? 0);
        $this->sheet->setCellValue('D' . $row, $monthData['standard'] ?? 0);
        $this->sheet->setCellValue('E' . $row, $monthData['non_standard'] ?? 0);
        $this->sheet->setCellValue('F' . $row, $monthData['percentage'] ?? 0);
    }

    protected function fillSummaryRow($row, $monthData)
    {
        // H10-M10: Rekap summary dengan data Desember
        $this->sheet->setCellValue('H' . $row, $monthData['male'] ?? 0);
        $this->sheet->setCellValue('I' . $row, $monthData['female'] ?? 0);
        $this->sheet->setCellValue('J' . $row, $monthData['standard'] ?? 0);
        $this->sheet->setCellValue('K' . $row, $monthData['non_standard'] ?? 0);
        $this->sheet->setCellValue('L' . $row, $monthData['total'] ?? 0);
        $this->sheet->setCellValue('M' . $row, $monthData['percentage'] ?? 0);
    }

    protected function applyStyles()
    {
        // Apply number format to all numeric cells
        $numericRange = 'B9:G25';
        $this->sheet->getStyle($numericRange)->getNumberFormat()->setFormatCode('#,##0');

        // Apply number format to summary cells
        $summaryRange = 'H10:M10';
        $this->sheet->getStyle($summaryRange)->getNumberFormat()->setFormatCode('#,##0');

        // Apply percentage format to percentage columns (same logic as column M)
        $percentageColumns = ['F'];
        foreach ($percentageColumns as $col) {
            $this->sheet->getStyle($col . '9:' . $col . '25')
                ->getNumberFormat()
                ->setFormatCode('0.00"%"');
        }

        // Apply percentage format to summary percentage
        $this->sheet->getStyle('M10')
            ->getNumberFormat()
            ->setFormatCode('0.00"%"');

        // Apply borders
        $this->sheet->getStyle('A9:F25')
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Apply borders to summary
        $this->sheet->getStyle('H10:M10')
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells
        $this->sheet->getStyle('A9:G25')
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Center align summary cells
        $this->sheet->getStyle('H10:M10')
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
}
