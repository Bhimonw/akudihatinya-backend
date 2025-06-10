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
                    // In the prepareStatisticsData method, around line 370-375
                    $data['monthly_data'][$stat->month] = [
                        'male' => $stat->male_count,
                        'female' => $stat->female_count,
                        'standard' => $stat->standard_count,
                        'non_standard' => $stat->non_standard_count,
                        'total' => $stat->total_count, // This line should be present
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



    protected function prepareDataForPdf($diseaseType, $year, $puskesmasId = null)
    {
        $data = $this->getReportData($puskesmasId, $year, $diseaseType);

        $formattedData = [];
        $grandTotalData = [
            'target' => 0,
            'monthly' => array_fill(1, 12, ['male' => 0, 'female' => 0, 'total' => 0, 'non_standard' => 0, 'percentage' => 0]),
            'quarterly' => array_fill(1, 4, ['total' => 0, 'non_standard' => 0, 'percentage' => 0]),
            'total_patients' => 0,
            'total_yearly_standard' => 0,
            'yearly_achievement_percentage' => 0,
        ];

        $diseaseTypeLabel = $this->getDiseaseTypeLabel($diseaseType);
        $months = $this->getDefaultMonths(); // Ensure months are always available

        if (!empty($data['data'])) {
            foreach ($data['data'] as $puskesmas) {
                $formattedPuskesmas = [
                    'name' => $puskesmas['puskesmas_name'], // Changed from puskesmas_name to name
                    'target' => $puskesmas['target'] ?? 0,
                    'monthly' => [], // Changed from monthly_data to monthly
                    'quarterly' => [], // Changed from quarterly_data to quarterly
                    'total_pasien' => $puskesmas['total_patients'] ?? 0, // Changed from total_patients to total_pasien
                    'total_yearly_standard' => $puskesmas['total_yearly_standard'] ?? 0,
                    'persen_capaian_tahunan' => $puskesmas['yearly_achievement_percentage'] ?? 0, // Changed from yearly_achievement_percentage to persen_capaian_tahunan
                ];

                // Format monthly data
                foreach ($months as $monthNumber => $monthName) {
                    $monthData = $puskesmas['monthly_statistics'][$monthNumber] ?? ['male' => 0, 'female' => 0, 'total' => 0, 'non_standard' => 0, 'percentage' => 0];
                    $formattedPuskesmas['monthly'][$monthNumber] = [
                        'l' => $monthData['male'],
                        'p' => $monthData['female'],
                        'total' => $monthData['total'],
                        'ts' => $monthData['non_standard'],
                        'ps' => $monthData['percentage'],
                    ];

                    // Accumulate monthly grand totals
                    $grandTotalData['monthly'][$monthNumber]['male'] += $monthData['male'];
                    $grandTotalData['monthly'][$monthNumber]['female'] += $monthData['female'];
                    $grandTotalData['monthly'][$monthNumber]['total'] += $monthData['total'];
                    $grandTotalData['monthly'][$monthNumber]['non_standard'] += $monthData['non_standard'];
                }

                // Format quarterly data (using data from the last month of each quarter)
                $quarterMonths = [3, 6, 9, 12];
                foreach ($quarterMonths as $quarterIndex => $monthNumber) {
                    $quarterData = $puskesmas['monthly_statistics'][$monthNumber] ?? ['total' => 0, 'non_standard' => 0, 'percentage' => 0];
                    $formattedPuskesmas['quarterly'][$quarterIndex + 1] = [
                        'total' => $quarterData['total'],
                        'non_standard' => $quarterData['non_standard'],
                        'percentage' => $quarterData['percentage'],
                    ];

                    // Accumulate quarterly grand totals
                    $grandTotalData['quarterly'][$quarterIndex + 1]['total'] += $quarterData['total'];
                    $grandTotalData['quarterly'][$quarterIndex + 1]['non_standard'] += $quarterData['non_standard'];
                }

                // Accumulate grand totals
                $grandTotalData['target'] += $formattedPuskesmas['target'];
                $grandTotalData['total_patients'] += $formattedPuskesmas['total_pasien']; // Use total_pasien for accumulation
                $grandTotalData['total_yearly_standard'] += $formattedPuskesmas['total_yearly_standard'];

                $formattedData[] = $formattedPuskesmas;
            }

            // Calculate percentages for monthly grand totals
            foreach ($grandTotalData['monthly'] as $monthNumber => $monthData) {
                $grandTotalData['monthly'][$monthNumber]['percentage'] = $monthData['total'] > 0 ? round(($monthData['total'] - $monthData['non_standard']) / $monthData['total'] * 100, 2) : 0;
            }

            // Calculate percentages for quarterly grand totals
            foreach ($grandTotalData['quarterly'] as $quarterNumber => $quarterData) {
                $grandTotalData['quarterly'][$quarterNumber]['percentage'] = $quarterData['total'] > 0 ? round(($quarterData['total'] - $quarterData['non_standard']) / $quarterData['total'] * 100, 2) : 0;
            }

            // Calculate yearly achievement percentage for grand total based on total_yearly_standard and target
            $grandTotalData['yearly_achievement_percentage'] = $grandTotalData['target'] > 0 ? round($grandTotalData['total_yearly_standard'] / $grandTotalData['target'] * 100, 2) : 0;
        }

        return [
            'puskesmasData' => $formattedData,
            'grandTotalData' => $grandTotalData,
            'diseaseTypeLabel' => $diseaseTypeLabel,
            'months' => $months,
        ];
    }

    protected function getDiseaseTypeLabel($diseaseType)
    {
        switch ($diseaseType) {
            case 'ht':
                return 'Hipertensi';
            case 'dm':
                return 'Diabetes Melitus';
            default:
                return 'Unknown';
        }
    }

    private function getDefaultMonths(): array
    {
        return [
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
    }

    /**
     * Create monthly data sheet for yearly report
     */
    public function createMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap = false)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data Bulanan ' . $year);

        // Set headers
        $headers = ['No', 'Puskesmas', 'Target'];
        $months = $this->getDefaultMonths();
        foreach ($months as $month) {
            $headers[] = $month;
        }
        $headers[] = 'Total';
        $headers[] = 'Persentase';

        // Write headers
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        // Write data
        $row = 2;
        foreach ($statistics as $index => $stat) {
            $sheet->setCellValueByColumnAndRow(1, $row, $index + 1);
            $sheet->setCellValueByColumnAndRow(2, $row, $stat['puskesmas_name']);
            $sheet->setCellValueByColumnAndRow(3, $row, $stat['target'] ?? 0);

            $col = 4;
            $total = 0;
            for ($month = 1; $month <= 12; $month++) {
                $monthData = $stat['monthly_data'][$month] ?? ['standard' => 0];
                $value = $monthData['standard'] ?? 0;
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $total += $value;
                $col++;
            }

            $sheet->setCellValueByColumnAndRow($col, $row, $total);
            $percentage = ($stat['target'] ?? 0) > 0 ? round(($total / $stat['target']) * 100, 2) : 0;
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $percentage . '%');

            $row++;
        }

        // Apply basic styling
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')
            ->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . $row)
            ->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        return $spreadsheet;
    }

    /**
     * Export monitoring sheet for patient attendance
     */
    public function exportMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring ' . $this->getMonthName($month) . ' ' . $year);

        // Set title
        $title = 'LAPORAN MONITORING KEHADIRAN PASIEN ' . strtoupper($diseaseType) . ' - ' . $puskesmas->name;
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . $this->getColLetter($daysInMonth + 5) . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Set period info
        $periodText = 'Periode: ' . $this->getMonthName($month) . ' ' . $year;
        $sheet->setCellValue('A2', $periodText);
        $sheet->mergeCells('A2:' . $this->getColLetter($daysInMonth + 5) . '2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Set headers
        $headers = ['No', 'Nama Pasien', 'Jenis Kelamin', 'Umur', 'Alamat'];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $headers[] = $day;
        }
        $headers[] = 'Total Hadir';
        $headers[] = 'Persentase';

        $row = 4;
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $col++;
        }

        // Write patient data
        $row = 5;
        foreach ($patients as $index => $patient) {
            $sheet->setCellValueByColumnAndRow(1, $row, $index + 1);
            $sheet->setCellValueByColumnAndRow(2, $row, $patient['name']);
            $sheet->setCellValueByColumnAndRow(3, $row, $patient['gender'] === 'male' ? 'L' : 'P');
            $sheet->setCellValueByColumnAndRow(4, $row, $patient['age'] ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $row, $patient['address'] ?? '-');

            $col = 6;
            $totalAttendance = 0;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $attended = isset($patient['attendance'][$day]) ? '✓' : '-';
                if ($attended === '✓') $totalAttendance++;
                $sheet->setCellValueByColumnAndRow($col, $row, $attended);
                $col++;
            }

            $sheet->setCellValueByColumnAndRow($col, $row, $totalAttendance);
            $percentage = $daysInMonth > 0 ? round(($totalAttendance / $daysInMonth) * 100, 2) : 0;
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $percentage . '%');

            $row++;
        }

        // Apply styling
        $sheet->getStyle('A4:' . $sheet->getHighestColumn() . '4')
            ->getFont()->setBold(true);
        $sheet->getStyle('A4:' . $sheet->getHighestColumn() . ($row - 1))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A4:' . $sheet->getHighestColumn() . ($row - 1))
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Helper to get Excel column letter from number
     */
    private function getColLetter($number)
    {
        $letter = '';
        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)) . $letter;
            $number = intval($number / 26);
        }
        return $letter;
    }
}
