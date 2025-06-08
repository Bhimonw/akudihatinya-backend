<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\DmExamination;
use App\Models\HtExamination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Auth;
use App\Formatters\AdminAllFormatter;
use App\Services\DashboardPdfService;
use App\Models\MonthlyStatisticsCache;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Formatters\AdminMonthlyFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Formatters\AdminQuarterlyFormatter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportService
{
    protected $statisticsService;
    protected $adminAllFormatter;
    protected $adminMonthlyFormatter;
    protected $adminQuarterlyFormatter;

    public function __construct(
        StatisticsService $statisticsService,
        AdminAllFormatter $adminAllFormatter,
        AdminMonthlyFormatter $adminMonthlyFormatter,
        AdminQuarterlyFormatter $adminQuarterlyFormatter
    ) {
        $this->statisticsService = $statisticsService;
        $this->adminAllFormatter = $adminAllFormatter;
        $this->adminMonthlyFormatter = $adminMonthlyFormatter;
        $this->adminQuarterlyFormatter = $adminQuarterlyFormatter;
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

    public function exportToExcel($diseaseType = 'dm', $year, $puskesmasId = null, $tableType = 'all')
    {
        // Ensure disease type is 'dm' if not specified or invalid
        $diseaseType = in_array($diseaseType, ['dm', 'ht']) ? $diseaseType : 'dm';

        // Load the appropriate template based on table type
        $templatePath = resource_path('templates/');
        $templateFile = match ($tableType) {
            'monthly' => 'monthly.xlsx',
            'quarterly' => 'quarterly.xlsx',
            default => 'all.xlsx'
        };
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath . $templateFile);

        // Pilih formatter sesuai tableType
        $formatter = match ($tableType) {
            'monthly' => $this->adminMonthlyFormatter,
            'quarterly' => $this->adminQuarterlyFormatter,
            default => $this->adminAllFormatter
        };
        $spreadsheet = $formatter->format($spreadsheet, $diseaseType, $year, $puskesmasId);

        // Generate filename
        $filename = sprintf(
            'laporan_%s_%d_%s.xlsx',
            $diseaseType,
            $year,
            $tableType
        );

        // Create reports directory if it doesn't exist
        $reportsPath = storage_path('app/public/reports');
        if (!is_dir($reportsPath)) {
            mkdir($reportsPath, 0755, true);
        }

        // Save file
        $finalPath = $reportsPath . DIRECTORY_SEPARATOR . $filename;
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($finalPath);

        return response()->download($finalPath)->deleteFileAfterSend(true);
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
        
        // Set headers
        $headers = ['No', 'Puskesmas', 'Target'];
        $monthNames = [
            'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'
        ];
        
        // Add month headers
        foreach ($monthNames as $month) {
            $headers[] = $month . ' (L)';
            $headers[] = $month . ' (P)';
            $headers[] = $month . ' (Total)';
            $headers[] = $month . ' (TS)';
            $headers[] = $month . ' (%)';
        }
        
        // Set header row
        $row = 3;
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $sheet->getStyleByColumnAndRow($col, $row)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        
        // Add data rows
        $dataRow = 4;
        foreach ($statistics as $index => $puskesmasData) {
            $col = 1;
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $index + 1); // No
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $puskesmasData['puskesmas_name']); // Puskesmas
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $puskesmasData['target']); // Target
            
            // Add monthly data
            for ($month = 1; $month <= 12; $month++) {
                $monthData = $puskesmasData['monthly_data'][$month] ?? [
                    'male' => 0,
                    'female' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                    'percentage' => 0
                ];
                
                $sheet->setCellValueByColumnAndRow($col++, $dataRow, $monthData['male']); // L
                $sheet->setCellValueByColumnAndRow($col++, $dataRow, $monthData['female']); // P
                $sheet->setCellValueByColumnAndRow($col++, $dataRow, $monthData['standard']); // Total
                $sheet->setCellValueByColumnAndRow($col++, $dataRow, $monthData['non_standard']); // TS
                $sheet->setCellValueByColumnAndRow($col++, $dataRow, $monthData['percentage']); // %
            }
            
            $dataRow++;
        }
        
        // Apply styling
        $lastRow = $dataRow - 1;
        $lastCol = $col - 1;
        
        // Apply borders
        $range = 'A3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $lastRow;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Auto-size columns
        for ($i = 1; $i <= $lastCol; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    public function createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring');
        
        // Set title
        $diseaseLabel = $diseaseType === 'ht' ? 'Hipertensi (HT)' : 'Diabetes Mellitus (DM)';
        $monthName = $this->getMonthName($month);
        $title = "Monitoring Pasien {$diseaseLabel} - {$puskesmas->name} - {$monthName} {$year}";
        
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $daysInMonth) . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Set headers
        $headers = ['No', 'No. RM', 'Nama Pasien', 'Jenis Kelamin', 'Umur'];
        
        // Add day headers
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $headers[] = $day;
        }
        
        // Set header row
        $headerRow = 3;
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $headerRow, $header);
            $sheet->getStyleByColumnAndRow($col, $headerRow)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow($col, $headerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        
        // Add patient data
        $dataRow = 4;
        foreach ($patients as $index => $patient) {
            $col = 1;
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $index + 1); // No
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $patient['medical_record_number'] ?? ''); // No. RM
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $patient['name']); // Nama Pasien
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $patient['gender'] === 'male' ? 'L' : 'P'); // Jenis Kelamin
            $sheet->setCellValueByColumnAndRow($col++, $dataRow, $patient['age'] ?? ''); // Umur
            
            // Add examination data for each day
            $examinations = $patient['examinations'] ?? [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $hasExam = false;
                foreach ($examinations as $exam) {
                    $examDate = \Carbon\Carbon::parse($exam['examination_date']);
                    if ($examDate->day == $day && $examDate->month == $month && $examDate->year == $year) {
                        $sheet->setCellValueByColumnAndRow($col, $dataRow, '✓');
                        $hasExam = true;
                        break;
                    }
                }
                if (!$hasExam) {
                    $sheet->setCellValueByColumnAndRow($col, $dataRow, '');
                }
                $col++;
            }
            
            $dataRow++;
        }
        
        // Apply styling
        $lastRow = $dataRow - 1;
        $lastCol = 5 + $daysInMonth;
        
        // Apply borders
        $range = 'A3:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $lastRow;
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);  // No
        $sheet->getColumnDimension('B')->setWidth(12); // No. RM
        $sheet->getColumnDimension('C')->setWidth(25); // Nama Pasien
        $sheet->getColumnDimension('D')->setWidth(8);  // Jenis Kelamin
        $sheet->getColumnDimension('E')->setWidth(8);  // Umur
        
        // Set day columns width
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $day);
            $sheet->getColumnDimension($colLetter)->setWidth(4);
        }
        
        // Center align all cells
        $sheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }

    /**
     * Export dashboard to PDF with puskesmas filter support
     */
    public function exportDashboardToPdf($diseaseType, $year, $puskesmasId = null)
    {
        try {
            // Gunakan DashboardPdfService yang sudah diupdate
            $dashboardPdfService = app(DashboardPdfService::class);
            $preparedData = $dashboardPdfService->prepareData($year, $diseaseType, $puskesmasId);

            // Filter data berdasarkan puskesmas jika diperlukan
            if ($puskesmasId) {
                $preparedData['puskesmas_data'] = array_filter(
                    $preparedData['puskesmas_data'],
                    function ($puskesmas) use ($puskesmasId) {
                        // Asumsi ada field puskesmas_id atau bisa dicocokkan dengan nama
                        return isset($puskesmas['puskesmas_id']) && $puskesmas['puskesmas_id'] == $puskesmasId;
                    }
                );

                // Recalculate grand total untuk filtered data
                if (!empty($preparedData['puskesmas_data'])) {
                    $preparedData['grand_total'] = $dashboardPdfService->getGrandTotals(
                        array_values($preparedData['puskesmas_data']),
                        [] // Empty raw data since we're recalculating
                    );
                }
            }

            $main_title = 'LAPORAN REKAPITULASI DATA PELAYANAN KESEHATAN';
            // Safely access disease_type_label with a default value
            $disease_type_label = $preparedData['disease_type_label'] ?? 'Unknown Disease';

            $export_meta = [
                'generated_by' => Auth::user()->name,
                'user_role' => Auth::user()->isAdmin() ? 'Admin' : 'Petugas Puskesmas',
                'generated_at' => now()->format('d F Y H:i'),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.dashboard_pdf', [
                'title' => $main_title,
                'main_title' => $main_title,
                'disease_type_label' => $disease_type_label,
                'year' => $year,
                'months' => $preparedData['months'] ?? [],
                'puskesmasData' => $preparedData['puskesmas_data'] ?? [], // Ensure this is passed
                'grandTotalData' => $preparedData['grand_total'] ?? [], // Ensure this is passed
                'export_meta' => $export_meta,
            ]);

            $pdf->setPaper('a4', 'landscape');
            $filename = 'laporan_' . strtolower(str_replace([' ', '(', ')'], ['_', '', ''], $disease_type_label)) . '_' . $year;
            if ($puskesmasId) {
                $puskesmasName = \App\Models\Puskesmas::find($puskesmasId)->name ?? 'puskesmas';
                $filename .= '_' . strtolower(str_replace(' ', '_', $puskesmasName));
            }
            $filename .= '.pdf';

            $exportPath = storage_path('app/public/exports');
            if (!file_exists($exportPath)) {
                mkdir($exportPath, 0755, true);
            }
            $pdfPath = $exportPath . DIRECTORY_SEPARATOR . $filename;
            $pdf->save($pdfPath);

            if (!file_exists($pdfPath)) {
                throw new \Exception("Failed to generate PDF file");
            }

            return response()->download($pdfPath, $filename, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            \Log::error('PDF Export Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Gagal generate PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
