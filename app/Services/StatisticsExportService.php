<?php

namespace App\Services;

use App\Models\Puskesmas;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StatisticsExportService
{
    /**
     * Export monitoring report to PDF
     */
    public function exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $monthName = $this->getMonthName($month);

        // Set judul
        $title = "Laporan Pemantauan ";
        if ($diseaseType === 'ht') {
            $title .= "Pasien Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title .= "Pasien Diabetes Mellitus (DM)";
        } else {
            $title .= "Pasien Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }

        $data = [
            'title' => $title,
            'subtitle' => "Bulan $monthName Tahun $year",
            'puskesmas' => $puskesmas,
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'disease_type' => $diseaseType,
            'patient_data' => $patientData
        ];

        $pdf = Pdf::loadView('exports.monitoring', $data);
        return $pdf->download($filename . '.pdf');
    }

    /**
     * Export monitoring report to Excel
     */
    public function exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $spreadsheet = new Spreadsheet();
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Create HT sheet if needed
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->createMonitoringSheet($spreadsheet, $patientData['ht'], $puskesmas, $year, $month, 'ht', $daysInMonth);
        }

        // Create DM sheet if needed
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $this->createMonitoringSheet($spreadsheet, $patientData['dm'], $puskesmas, $year, $month, 'dm', $daysInMonth);
        }

        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        // Create writer and save
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/' . $filename . '.xlsx');
        $writer->save($path);

        return response()->download($path)->deleteFileAfterSend();
    }

    /**
     * Create monitoring sheet in Excel
     */
    protected function createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);  // No
        $sheet->getColumnDimension('B')->setWidth(15); // No. RM
        $sheet->getColumnDimension('C')->setWidth(30); // Nama
        $sheet->getColumnDimension('D')->setWidth(10); // JK
        $sheet->getColumnDimension('E')->setWidth(10); // Umur

        // Set header style
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA']
            ]
        ];

        // Set header
        $sheet->setCellValue('A1', 'No');
        $sheet->setCellValue('B1', 'No. RM');
        $sheet->setCellValue('C1', 'Nama');
        $sheet->setCellValue('D1', 'JK');
        $sheet->setCellValue('E1', 'Umur');

        // Add day columns
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter($day + 5);
            $sheet->setCellValue($col . '1', $day);
            $sheet->getColumnDimension($col)->setWidth(3);
        }

        // Add total column
        $totalCol = $this->getColLetter($daysInMonth + 6);
        $sheet->setCellValue($totalCol . '1', 'Total');
        $sheet->getColumnDimension($totalCol)->setWidth(8);

        // Apply header style
        $sheet->getStyle('A1:' . $totalCol . '1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($patients as $index => $patient) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $patient['medical_record_number']);
            $sheet->setCellValue('C' . $row, $patient['patient_name']);
            $sheet->setCellValue('D' . $row, $patient['gender']);
            $sheet->setCellValue('E' . $row, $patient['age']);

            // Add attendance data
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $col = $this->getColLetter($day + 5);
                $sheet->setCellValue($col . $row, $patient['attendance'][$day] ? '✓' : '');
            }

            // Add total
            $sheet->setCellValue($totalCol . $row, $patient['visit_count']);

            $row++;
        }

        // Add borders to data
        $sheet->getStyle('A2:' . $totalCol . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells
        $sheet->getStyle('A1:' . $totalCol . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C2:C' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    /**
     * Get column letter from number
     */
    protected function getColLetter($number)
    {
        $temp = '';
        while ($number > 0) {
            $temp = chr(($number - 1) % 26 + 65) . $temp;
            $number = floor(($number - 1) / 26);
        }
        return $temp;
    }

    /**
     * Get month name in Indonesian
     */
    protected function getMonthName($month)
    {
        $months = [
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

        return $months[$month] ?? '';
    }
}
