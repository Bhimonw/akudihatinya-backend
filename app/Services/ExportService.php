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
use App\Models\MonthlyStatisticsCache;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Formatters\AdminAllFormatter;
use App\Services\StatisticsService;

class ExportService
{
    protected $statisticsService;
    protected $adminAllFormatter;

    public function __construct(StatisticsService $statisticsService, AdminAllFormatter $adminAllFormatter)
    {
        $this->statisticsService = $statisticsService;
        $this->adminAllFormatter = $adminAllFormatter;
    }

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
        $puskesmasQuery = Puskesmas::query();
        if ($puskesmasId) {
            $puskesmasQuery->where('id', $puskesmasId);
        }
        $puskesmasAll = $puskesmasQuery->get();

        $filename = "laporan_" . ($diseaseType === 'all' ? 'ht_dm' : $diseaseType) . "_" . $year;
        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmas->name));
        }

        return $this->exportToExcel($diseaseType, $year, $puskesmasId);
    }

    /**
     * Get report data for export
     */
    protected function getReportData($puskesmasId, $year, $diseaseType, $tableType = 'all')
    {
        $data = [
            'target' => 0,
            'monthly_data' => [],
            'quarterly_data' => [],
            'summary' => []
        ];

        // Get target
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', $diseaseType)
            ->first();

        if ($target) {
            $data['target'] = $target->target_count;
        }

        // Get monthly data from cache
        $monthlyStats = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('disease_type', $diseaseType)
            ->get();

        // Process monthly data
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = [
                'month' => $m,
                'month_name' => Carbon::createFromDate($year, $m, 1)->locale('id')->monthName,
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard' => 0,
                'non_standard' => 0,
                'percentage' => 0
            ];
        }

        foreach ($monthlyStats as $stat) {
            $month = $stat->month;
            $monthlyData[$month] = [
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->locale('id')->monthName,
                'male' => $stat->male_count,
                'female' => $stat->female_count,
                'total' => $stat->total_count,
                'standard' => $stat->standard_count,
                'non_standard' => $stat->non_standard_count,
                'percentage' => $data['target'] > 0 ? round(($stat->standard_count / $data['target']) * 100, 2) : 0
            ];
        }

        $data['monthly_data'] = $monthlyData;

        // Process quarterly data if needed
        if ($tableType === 'quarterly' || $tableType === 'all') {
            $quarterlyData = [];
            for ($q = 1; $q <= 4; $q++) {
                $startMonth = ($q - 1) * 3 + 1;
                $endMonth = $q * 3;

                // Get the last month's data in the quarter that has data
                $lastMonthWithData = null;
                for ($m = $endMonth; $m >= $startMonth; $m--) {
                    if ($monthlyData[$m]['total'] > 0) {
                        $lastMonthWithData = $m;
                        break;
                    }
                }

                if ($lastMonthWithData) {
                    $quarterlyData[$q] = $monthlyData[$lastMonthWithData];
                } else {
                    $quarterlyData[$q] = [
                        'male' => 0,
                        'female' => 0,
                        'total' => 0,
                        'standard' => 0,
                        'non_standard' => 0,
                        'percentage' => 0
                    ];
                }
            }
            $data['quarterly_data'] = $quarterlyData;
        }

        // Calculate summary data
        $data['summary'] = [
            'total_patients' => collect($monthlyData)->sum('total'),
            'standard_patients' => collect($monthlyData)->sum('standard'),
            'non_standard_patients' => collect($monthlyData)->sum('non_standard'),
            'target' => $data['target'],
            'achievement_percentage' => $data['target'] > 0 ?
                round((collect($monthlyData)->sum('standard') / $data['target']) * 100, 2) : 0
        ];

        return $data;
    }

    public function exportToPdf($puskesmasAll, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        // Get statistics data
        $statistics = $this->prepareStatisticsData($puskesmasAll, $year, $month, $diseaseType);

        // Prepare title and data based on export type
        if ($isRecap) {
            // Admin/Dinas export
            $title = "Laporan " . ($diseaseType === 'ht' ? 'Hipertensi (HT)' : 'Diabetes Mellitus (DM)') . " " . $year;
            $data = [
                'title' => $title,
                'year' => $year,
                'type' => $diseaseType,
                'statistics' => $statistics,
                'is_recap' => true,
                'generated_at' => Carbon::now()->format('d F Y H:i'),
                'generated_by' => Auth::user()->name,
                'user_role' => Auth::user()->is_admin ? 'Admin' : 'Petugas Puskesmas',
                'headers' => [
                    'puskesmas' => 'Puskesmas',
                    'target' => 'Sasaran',
                    'month' => 'Bulan',
                    'male' => 'Standar (Laki-laki)',
                    'female' => 'Standar (Perempuan)',
                    'non_standard' => 'Tidak Standar',
                    'standard' => 'Total Standar',
                    'percentage' => 'Persentase (%)'
                ]
            ];
            $view = 'exports.admin_statistics_pdf';
        } else {
            // Puskesmas export
            $puskesmasName = $statistics[0]['puskesmas_name'];
            $title = "Laporan " . $puskesmasName . " " .
                ($diseaseType === 'ht' ? 'Hipertensi (HT)' : 'Diabetes Mellitus (DM)') . " " . $year;
            $data = [
                'title' => $title,
                'year' => $year,
                'type' => $diseaseType,
                'statistics' => $statistics[0], // Only first puskesmas data
                'is_recap' => false,
                'generated_at' => Carbon::now()->format('d F Y H:i'),
                'generated_by' => Auth::user()->name,
                'user_role' => Auth::user()->is_admin ? 'Admin' : 'Petugas Puskesmas',
                'headers' => [
                    'month' => 'Bulan',
                    'male' => 'Standar (Laki-laki)',
                    'female' => 'Standar (Perempuan)',
                    'non_standard' => 'Tidak Standar',
                    'standard' => 'Total Standar',
                    'percentage' => 'Persentase (%)'
                ]
            ];
            $view = 'exports.puskesmas_statistics_pdf';
        }

        $pdf = PDF::loadView($view, $data);
        $pdf->setPaper('a4', 'landscape');

        $exportPath = storage_path('app/public/exports');
        if (!file_exists($exportPath)) {
            mkdir($exportPath, 0755, true);
        }

        $pdfFilename = $filename . '.pdf';
        \Storage::put('public/exports/' . $pdfFilename, $pdf->output());

        return \response()->download(storage_path('app/public/exports/' . $pdfFilename), $pdfFilename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    public function exportToExcel($diseaseType, $year, $puskesmasId = null, $tableType = 'all')
    {
        // Load template
        $templatePath = base_path('resources/views/exports/formatLaporanAkudihatinya/all.xlsx');
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($templatePath);

        // Format using AdminAllFormatter
        $spreadsheet = $this->adminAllFormatter->format($spreadsheet, $diseaseType, $year, $puskesmasId);

        // Save and return
        $filename = "laporan_" . ($diseaseType === 'all' ? 'ht_dm' : $diseaseType) . "_" . $year;
        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmas->name));
        }
        $filename .= ".xlsx";

        $exportPath = storage_path('app/public/exports/' . $filename);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($exportPath);

        return response()->download($exportPath)->deleteFileAfterSend(true);
    }

    private function applyExcelStyles($sheet, $lastRow)
    {
        // Apply number format to all numeric cells
        $numericRange = 'D9:' . $sheet->getHighestColumn() . $lastRow;
        $sheet->getStyle($numericRange)->getNumberFormat()->setFormatCode('#,##0');

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
            $sheet->getStyle($col . '9:' . $col . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('0.00"%"');
        }

        // Apply borders
        $sheet->getStyle('A9:' . $sheet->getHighestColumn() . $lastRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Center align all cells
        $sheet->getStyle('A9:' . $sheet->getHighestColumn() . $lastRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function prepareStatisticsData($puskesmasAll, $year, $month, $diseaseType)
    {
        $puskesmasIds = $puskesmasAll->pluck('id')->toArray();
        $monthlyStats = \App\Models\MonthlyStatisticsCache::where('year', $year)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get();

        $stats = $monthlyStats->where('disease_type', $diseaseType)->groupBy('puskesmas_id');
        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
                'target' => 0,
                'monthly_data' => [],
            ];

            $target = \App\Models\YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', $diseaseType)
                ->where('year', $year)
                ->first();
            $data['target'] = $target ? $target->target_count : 0;

            if (isset($stats[$puskesmas->id])) {
                foreach ($stats[$puskesmas->id] as $stat) {
                    $data['monthly_data'][$stat->month] = [
                        'male' => $stat->male_count,
                        'female' => $stat->female_count,
                        'standard' => $stat->standard_count,
                        'non_standard' => $stat->non_standard_count,
                        'percentage' => $data['target'] > 0 ?
                            round(($stat->standard_count / $data['target']) * 100, 2) : 0,
                    ];
                }
            }

            $statistics[] = $data;
        }

        return $statistics;
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
        $exportPath = storage_path('app/public/exports');
        if (!file_exists($exportPath)) {
            mkdir($exportPath, 0755, true);
        }
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
        $exportPath = storage_path('app/public/exports');
        if (!file_exists($exportPath)) {
            mkdir($exportPath, 0755, true);
        }
        $writer = new Xlsx($spreadsheet);
        $excelFilename = $filename . '.xlsx';
        $path = $exportPath . '/' . $excelFilename;
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
