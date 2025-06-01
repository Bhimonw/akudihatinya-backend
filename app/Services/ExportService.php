<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Auth;

class ExportService
{
    /**
     * Generate PDF report for HT or DM statistics
     */
    public function generatePdfReport($diseaseType, $year, $puskesmasId = null)
    {
        $data = $this->getReportData($diseaseType, $year, $puskesmasId);

        $pdf = PDF::loadView('exports.report_pdf', [
            'data' => $data,
            'disease_type' => $diseaseType,
            'year' => $year,
        ]);

        return $pdf->download("laporan_{$diseaseType}_{$year}.pdf");
    }

    /**
     * Generate Excel report for HT or DM statistics
     */
    public function generateExcelReport($diseaseType, $year, $puskesmasId = null)
    {
        $data = $this->getReportData($diseaseType, $year, $puskesmasId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $sheet->setCellValue('A1', 'Laporan ' . ($diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes Mellitus'));
        $sheet->setCellValue('A2', 'Tahun ' . $year);

        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            $sheet->setCellValue('A3', $puskesmas->name);
        } else {
            $sheet->setCellValue('A3', 'Seluruh Puskesmas');
        }

        // Set column headers
        $sheet->setCellValue('A5', 'Bulan');
        $sheet->setCellValue('B5', 'Laki-laki');
        $sheet->setCellValue('C5', 'Perempuan');
        $sheet->setCellValue('D5', 'Total');
        $sheet->setCellValue('E5', 'Pasien Standar');
        $sheet->setCellValue('F5', 'Pasien Terkendali');

        // Fill data
        $row = 6;
        foreach ($data['monthly_data'] as $index => $month) {
            $sheet->setCellValue('A' . $row, $month['month_name']);
            $sheet->setCellValue('B' . $row, $month['male']);
            $sheet->setCellValue('C' . $row, $month['female']);
            $sheet->setCellValue('D' . $row, $month['total']);
            $sheet->setCellValue('E' . $row, $month['standard_patients']);
            $sheet->setCellValue('F' . $row, $month['controlled_patients']);
            $row++;
        }

        // Add summary
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Total Pasien');
        $sheet->setCellValue('D' . $row, $data['summary']['total_patients']);

        $row++;
        $sheet->setCellValue('A' . $row, 'Total Pasien Standar');
        $sheet->setCellValue('D' . $row, $data['summary']['standard_patients']);

        $row++;
        $sheet->setCellValue('A' . $row, 'Total Pasien Terkendali');
        $sheet->setCellValue('D' . $row, $data['summary']['controlled_patients']);

        $row++;
        $sheet->setCellValue('A' . $row, 'Sasaran');
        $sheet->setCellValue('D' . $row, $data['summary']['target']);

        $row++;
        $sheet->setCellValue('A' . $row, 'Persentase Capaian');
        $sheet->setCellValue('D' . $row, $data['summary']['achievement_percentage'] . '%');

        // Format the document
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create writer and save file
        $writer = new Xlsx($spreadsheet);
        $filename = "laporan_{$diseaseType}_{$year}.xlsx";
        $tempPath = storage_path('app/temp/' . $filename);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * Get report data for export
     */
    private function getReportData($diseaseType, $year, $puskesmasId = null)
    {
        $data = [
            'monthly_data' => [],
            'summary' => [],
        ];

        // Process monthly data
        for ($month = 1; $month <= 12; $month++) {
            $monthData = [
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->locale('id')->monthName,
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard_patients' => 0,
                'controlled_patients' => 0,
            ];

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

            if ($diseaseType === 'ht') {
                $query = HtExamination::whereBetween('examination_date', [$startDate, $endDate]);

                if ($puskesmasId) {
                    $query->where('puskesmas_id', $puskesmasId);
                }

                $examinations = $query->get();

                // Count unique patients by gender
                $patientIds = $examinations->pluck('patient_id')->unique();
                $patients = Patient::whereIn('id', $patientIds)->get();

                $monthData['male'] = $patients->where('gender', 'male')->count();
                $monthData['female'] = $patients->where('gender', 'female')->count();
                $monthData['total'] = $patientIds->count();

                // Standard and controlled calculations would go here
                // These are simplified for this example
                $monthData['standard_patients'] = 0;
                $monthData['controlled_patients'] = 0;
            } else { // DM
                $query = DmExamination::whereBetween('examination_date', [$startDate, $endDate]);

                if ($puskesmasId) {
                    $query->where('puskesmas_id', $puskesmasId);
                }

                $examinations = $query->get();

                // Count unique patients by gender
                $patientIds = $examinations->pluck('patient_id')->unique();
                $patients = Patient::whereIn('id', $patientIds)->get();

                $monthData['male'] = $patients->where('gender', 'male')->count();
                $monthData['female'] = $patients->where('gender', 'female')->count();
                $monthData['total'] = $patientIds->count();

                // Standard and controlled calculations would go here
                // These are simplified for this example
                $monthData['standard_patients'] = 0;
                $monthData['controlled_patients'] = 0;
            }

            $data['monthly_data'][] = $monthData;
        }

        // Summary data
        $targetQuery = YearlyTarget::where('disease_type', $diseaseType)
            ->where('year', $year);

        if ($puskesmasId) {
            $targetQuery->where('puskesmas_id', $puskesmasId);

            $target = $targetQuery->first();
            $targetValue = $target ? $target->target_count : 0;

            $patientsQuery = Patient::where('puskesmas_id', $puskesmasId);
            if ($diseaseType === 'ht') {
                $patientsQuery->where('has_ht', true);
            } else {
                $patientsQuery->where('has_dm', true);
            }

            $patientsCount = $patientsQuery->count();
        } else {
            $targetValue = $targetQuery->sum('target_count');

            if ($diseaseType === 'ht') {
                $patientsCount = Patient::where('has_ht', true)->count();
            } else {
                $patientsCount = Patient::where('has_dm', true)->count();
            }
        }

        $achievementPercentage = $targetValue > 0 ? round(($patientsCount / $targetValue) * 100, 2) : 0;

        // Standard and controlled calculations would be more complex in a real implementation
        $standardPatients = 0;
        $controlledPatients = 0;

        $data['summary'] = [
            'total_patients' => $patientsCount,
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
            'target' => $targetValue,
            'achievement_percentage' => $achievementPercentage,
        ];

        return $data;
    }

    public function exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        $title = "";
        $reportTypeLabel = $reportType === "laporan_tahunan"
            ? "Laporan Tahunan"
            : "Laporan Bulanan";
        if ($diseaseType === 'ht') {
            $title = "$reportTypeLabel Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title = "$reportTypeLabel Diabetes Mellitus (DM)";
        } else {
            $title = "$reportTypeLabel Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }
        if ($isRecap) {
            $title = "Rekap " . $title;
        }
        if (!$isRecap) {
            $puskesmasName = $statistics[0]['puskesmas_name'];
            $title .= " - " . $puskesmasName;
        }
        if ($month !== null) {
            $monthName = $this->getMonthName($month);
            $subtitle = "Bulan $monthName Tahun $year";
        } else {
            $subtitle = "Tahun $year";
        }
        $data = [
            'title' => $title,
            'subtitle' => $subtitle,
            'year' => $year,
            'month' => $month,
            'month_name' => $month !== null ? $this->getMonthName($month) : null,
            'type' => $diseaseType,
            'statistics' => $statistics,
            'is_recap' => $isRecap,
            'report_type' => $reportType,
            'generated_at' => Carbon::now()->format('d F Y H:i'),
            'generated_by' => Auth::user()->name,
            'user_role' => Auth::user()->is_admin ? 'Admin' : 'Petugas Puskesmas',
        ];
        $pdf = PDF::loadView('exports.statistics_pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdfFilename = $filename . '.pdf';
        \Storage::put('public/exports/' . $pdfFilename, $pdf->output());
        return \response()->download(storage_path('app/public/exports/' . $pdfFilename), $pdfFilename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    public function exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $reportTypeLabel = $reportType === "laporan_tahunan"
            ? "Laporan Tahunan"
            : "Laporan Bulanan";
        if ($diseaseType === 'ht') {
            $title = "$reportTypeLabel Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title = "$reportTypeLabel Diabetes Mellitus (DM)";
        } else {
            $title = "$reportTypeLabel Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }
        if ($isRecap) {
            $title = "Rekap " . $title;
        }
        if (!$isRecap) {
            $puskesmasName = $statistics[0]['puskesmas_name'];
            $title .= " - " . $puskesmasName;
        }
        if ($month !== null) {
            $monthName = $this->getMonthName($month);
            $title .= " - Bulan $monthName Tahun $year";
        } else {
            $title .= " - Tahun $year";
        }
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $exportInfo = "Diekspor oleh: " . Auth::user()->name . " (" .
            (Auth::user()->is_admin ? "Admin" : "Petugas Puskesmas") . ")";
        $sheet->setCellValue('A2', $exportInfo);
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->setCellValue('A3', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A3:K3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('A4', '');
        $row = 5;
        if ($isRecap) {
            $sheet->setCellValue('A' . $row, 'No');
            $sheet->setCellValue('B' . $row, 'Puskesmas');
            $col = 'C';
        } else {
            $col = 'A';
        }
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $sheet->setCellValue($col++ . $row, 'Target HT');
            $sheet->setCellValue($col++ . $row, 'Total Pasien HT');
            $sheet->setCellValue($col++ . $row, 'Pencapaian HT (%)');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Tidak Standar HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Laki-laki HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Perempuan HT');
        }
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $sheet->setCellValue($col++ . $row, 'Target DM');
            $sheet->setCellValue($col++ . $row, 'Total Pasien DM');
            $sheet->setCellValue($col++ . $row, 'Pencapaian DM (%)');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Tidak Standar DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Laki-laki DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Perempuan DM');
        }
        $lastCol = --$col;
        $headerColStart = $isRecap ? 'A' : 'A';
        $headerRange = $headerColStart . $row . ':' . $lastCol . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        foreach ($statistics as $index => $stat) {
            $row++;
            if ($isRecap) {
                $sheet->setCellValue('A' . $row, $stat['ranking']);
                $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
                $col = 'C';
            } else {
                $col = 'A';
            }
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $sheet->setCellValue($col++ . $row, $stat['ht']['target'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['total_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['achievement_percentage'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['non_standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['male_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['female_patients'] ?? 0);
            }
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $sheet->setCellValue($col++ . $row, $stat['dm']['target'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['total_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['achievement_percentage'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['non_standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['male_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['female_patients'] ?? 0);
            }
        }
        if ($month === null) {
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $this->addMonthlyDataSheet($spreadsheet, $statistics, 'ht', $year, $isRecap);
            }
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $this->addMonthlyDataSheet($spreadsheet, $statistics, 'dm', $year, $isRecap);
            }
        }
        $writer = new Xlsx($spreadsheet);
        $excelFilename = $filename . '.xlsx';
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);
        return \response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function getMonthName($month)
    {
        return Carbon::create()->month($month)->locale('id')->monthName;
    }

    public function addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap = false)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data Bulanan ' . strtoupper($diseaseType));
        $title = $diseaseType === 'ht'
            ? "Data Bulanan Hipertensi (HT) - Tahun " . $year
            : "Data Bulanan Diabetes Mellitus (DM) - Tahun " . $year;
        if ($isRecap) {
            $title = "Rekap " . $title;
        }
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        // ... kode lanjutan addMonthlyDataSheet jika ada ...
    }

    public function exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $data = [
            'patients' => $patientData,
            'puskesmas' => $puskesmas,
            'year' => $year,
            'month' => $month,
            'disease_type' => $diseaseType,
            'days_in_month' => $daysInMonth,
            'month_name' => $this->getMonthName($month),
            'generated_at' => Carbon::now()->format('d F Y H:i'),
            'generated_by' => Auth::user()->name,
        ];
        $pdf = PDF::loadView('exports.monitoring_pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdfFilename = $filename . '.pdf';
        \Storage::put('public/exports/' . $pdfFilename, $pdf->output());
        return \response()->download(storage_path('app/public/exports/' . $pdfFilename), $pdfFilename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    public function exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $spreadsheet = new Spreadsheet();
        $this->createMonitoringSheet($spreadsheet, $patientData, $puskesmas, $year, $month, $diseaseType, $daysInMonth);
        $writer = new Xlsx($spreadsheet);
        $excelFilename = $filename . '.xlsx';
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);
        return \response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring');
        // ... kode lanjutan createMonitoringSheet jika ada ...
    }
}
