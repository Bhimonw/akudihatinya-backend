<?php

namespace App\Services;

use App\Models\Puskesmas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class StatisticsExportService
{
    /**
     * Export statistics to PDF
     */
    public function exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap = false, $reportType = 'monthly')
    {
        $view = $isRecap ? 'exports.statistics.recap' : 'exports.statistics.monthly';
        $data = [
            'statistics' => $statistics,
            'year' => $year,
            'month' => $month,
            'diseaseType' => $diseaseType,
            'reportType' => $reportType,
        ];

        $pdf = PDF::loadView($view, $data);
        return $pdf->download($filename);
    }

    /**
     * Export statistics to Excel
     */
    public function exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap = false, $reportType = 'monthly')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set title
        $title = sprintf(
            'Laporan %s %s %s',
            $isRecap ? 'Rekap' : 'Bulanan',
            $diseaseType === 'all' ? 'HT & DM' : strtoupper($diseaseType),
            $year
        );
        if ($month) {
            $title .= ' - ' . Carbon::create()->month($month)->format('F');
        }

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add data
        $row = 3;
        if (isset($statistics['ht'])) {
            $this->addDiseaseData($sheet, $row, 'HT', $statistics['ht']);
            $row += 10;
        }

        if (isset($statistics['dm'])) {
            $this->addDiseaseData($sheet, $row, 'DM', $statistics['dm']);
        }

        // Add monthly data sheet
        $this->addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap);

        // Save file
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/' . $filename);
        $writer->save($path);

        return response()->download($path)->deleteFileAfterSend();
    }

    /**
     * Export monitoring report to PDF
     */
    public function exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $data = [
            'patients' => $patientData['patients'],
            'puskesmas' => $puskesmas,
            'year' => $year,
            'month' => $month,
            'diseaseType' => $diseaseType,
            'daysInMonth' => $patientData['days_in_month'],
        ];

        $pdf = PDF::loadView('exports.monitoring.monthly', $data);
        return $pdf->download($filename);
    }

    /**
     * Export monitoring report to Excel
     */
    public function exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set title
        $title = sprintf(
            'Laporan Pemantauan %s %s %s',
            strtoupper($diseaseType),
            Carbon::create()->month($month)->format('F'),
            $year
        );

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add patient data
        $this->createMonitoringSheet($sheet, $patientData['patients'], $puskesmas, $year, $month, $diseaseType, $patientData['days_in_month']);

        // Save file
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/' . $filename);
        $writer->save($path);

        return response()->download($path)->deleteFileAfterSend();
    }

    /**
     * Add disease data to Excel sheet
     */
    private function addDiseaseData($sheet, $startRow, $diseaseType, $data)
    {
        $sheet->setCellValue('A' . $startRow, 'Data ' . $diseaseType);
        $sheet->mergeCells('A' . $startRow . ':H' . $startRow);
        $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);

        $row = $startRow + 2;
        $sheet->setCellValue('A' . $row, 'Target');
        $sheet->setCellValue('B' . $row, $data['target']);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Pasien');
        $sheet->setCellValue('B' . $row, $data['total_patients']);
        $row++;

        $sheet->setCellValue('A' . $row, 'Persentase Pencapaian');
        $sheet->setCellValue('B' . $row, $data['achievement_percentage'] . '%');
        $row++;

        $sheet->setCellValue('A' . $row, 'Pasien Standar');
        $sheet->setCellValue('B' . $row, $data['standard_patients']);
        $row++;

        $sheet->setCellValue('A' . $row, 'Pasien Non-Standar');
        $sheet->setCellValue('B' . $row, $data['non_standard_patients']);
        $row++;

        $sheet->setCellValue('A' . $row, 'Pasien Laki-laki');
        $sheet->setCellValue('B' . $row, $data['male_patients']);
        $row++;

        $sheet->setCellValue('A' . $row, 'Pasien Perempuan');
        $sheet->setCellValue('B' . $row, $data['female_patients']);
    }

    /**
     * Add monthly data sheet
     */
    private function addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap = false)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data Bulanan');

        // Set title
        $title = sprintf(
            'Data Bulanan %s %s',
            $diseaseType === 'all' ? 'HT & DM' : strtoupper($diseaseType),
            $year
        );

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add headers
        $headers = ['Bulan', 'Total Pasien', 'Pasien Standar', 'Pasien Non-Standar', 'Laki-laki', 'Perempuan'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $sheet->getStyle($col . '3')->getFont()->setBold(true);
            $col++;
        }

        // Add data
        $row = 4;
        if (isset($statistics['ht'])) {
            foreach ($statistics['ht']['monthly_data'] as $data) {
                $sheet->setCellValue('A' . $row, Carbon::create()->month($data['month'])->format('F'));
                $sheet->setCellValue('B' . $row, $data['total_patients']);
                $sheet->setCellValue('C' . $row, $data['standard_patients']);
                $sheet->setCellValue('D' . $row, $data['non_standard_patients']);
                $sheet->setCellValue('E' . $row, $data['male_patients']);
                $sheet->setCellValue('F' . $row, $data['female_patients']);
                $row++;
            }
        }

        if (isset($statistics['dm'])) {
            $row += 2;
            $sheet->setCellValue('A' . $row, 'Data DM');
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row += 2;

            foreach ($statistics['dm']['monthly_data'] as $data) {
                $sheet->setCellValue('A' . $row, Carbon::create()->month($data['month'])->format('F'));
                $sheet->setCellValue('B' . $row, $data['total_patients']);
                $sheet->setCellValue('C' . $row, $data['standard_patients']);
                $sheet->setCellValue('D' . $row, $data['non_standard_patients']);
                $sheet->setCellValue('E' . $row, $data['male_patients']);
                $sheet->setCellValue('F' . $row, $data['female_patients']);
                $row++;
            }
        }
    }

    /**
     * Create monitoring sheet
     */
    private function createMonitoringSheet($sheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        // Add headers
        $headers = ['No', 'Nama', 'Jenis Kelamin', 'Tanggal Lahir', 'Alamat', 'Telepon'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $sheet->getStyle($col . '3')->getFont()->setBold(true);
            $col++;
        }

        // Add attendance columns
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(count($headers) + $day);
            $sheet->setCellValue($col . '3', $day);
            $sheet->getStyle($col . '3')->getFont()->setBold(true);
        }

        // Add patient data
        $row = 4;
        foreach ($patients as $index => $patient) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $patient->name);
            $sheet->setCellValue('C' . $row, $patient->gender);
            $sheet->setCellValue('D' . $row, Carbon::parse($patient->birth_date)->format('d/m/Y'));
            $sheet->setCellValue('E' . $row, $patient->address);
            $sheet->setCellValue('F' . $row, $patient->phone);

            // Add attendance data
            $examinationDate = Carbon::parse($patient->examination_date)->day;
            $col = $this->getColLetter(count($headers) + $examinationDate);
            $sheet->setCellValue($col . $row, '✓');

            $row++;
        }

        // Auto-size columns
        foreach (range('A', $this->getColLetter(count($headers) + $daysInMonth)) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Get column letter from number
     */
    private function getColLetter($number)
    {
        $base = ord('A');
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr($base + ($number % 26)) . $letters;
            $number = floor($number / 26);
        }
        return $letters;
    }
}
