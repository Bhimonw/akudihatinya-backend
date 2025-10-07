<?php
// Quick smoke test to generate sample Excel files for Monthly, Quarterly, and All formatters
// Usage: php scripts/verify-excel-mappings.php

require __DIR__ . '/../vendor/autoload.php';

use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\AdminAllFormatter;
use App\Formatters\PuskesmasFormatter;
use App\Services\Statistics\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Create a lightweight mock of StatisticsService (no methods used by formatters in direct mode)
$statisticsService = Mockery::mock(StatisticsService::class);

function sampleStatistics(): array
{
    $stats = [];
    for ($i = 1; $i <= 3; $i++) {
        $entry = [
            'puskesmas_name' => "Puskesmas Demo {$i}",
            'ht' => ['target' => 120 + $i],
            'dm' => ['target' => 80 + $i],
            'monthly_data' => []
        ];
        for ($m = 1; $m <= 12; $m++) {
            $htS = rand(5, 15);
            $htTS = rand(0, 10);
            $dmS = rand(3, 10);
            $dmTS = rand(0, 8);

            // Distribute totals across male/female to simulate L/P values
            $htTotal = $htS + $htTS;
            $dmTotal = $dmS + $dmTS;
            $htMale = rand(0, $htTotal);
            $htFemale = max($htTotal - $htMale, 0);
            $dmMale = rand(0, $dmTotal);
            $dmFemale = max($dmTotal - $dmMale, 0);
            $entry['monthly_data'][$m] = [
                'ht' => [
                    'standard_patients' => $htS,
                    'non_standard_patients' => $htTS,
                    'male_patients' => $htMale,
                    'female_patients' => $htFemale,
                ],
                'dm' => [
                    'standard_patients' => $dmS,
                    'non_standard_patients' => $dmTS,
                    'male_patients' => $dmMale,
                    'female_patients' => $dmFemale,
                ],
            ];
        }
        $stats[] = $entry;
    }
    return $stats;
}

$year = (int)date('Y');
$diseaseType = 'all';
$statistics = sampleStatistics();

$exportDir = __DIR__ . '/../public/exports';
if (!is_dir($exportDir)) {
    @mkdir($exportDir, 0777, true);
}

// Helper to save spreadsheet
function saveSpreadsheet(Spreadsheet $spreadsheet, string $path): void
{
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($path);
}

// Monthly
$monthly = new Spreadsheet();
$monFormatter = new AdminMonthlyFormatter($statisticsService);
$monthly = $monFormatter->format($monthly, $diseaseType, $year, $statistics, null);
saveSpreadsheet($monthly, $exportDir . '/verify_monthly.xlsx');

echo "Saved: {$exportDir}/verify_monthly.xlsx\n";

// Quarterly
$quarterly = new Spreadsheet();
$qFormatter = new AdminQuarterlyFormatter($statisticsService);
$quarterly = $qFormatter->format($quarterly, $diseaseType, $year, $statistics, null);
saveSpreadsheet($quarterly, $exportDir . '/verify_quarterly.xlsx');

echo "Saved: {$exportDir}/verify_quarterly.xlsx\n";

// All
$all = new Spreadsheet();
$aFormatter = new AdminAllFormatter($statisticsService);
$all = $aFormatter->format($all, $diseaseType, $year, $statistics);
saveSpreadsheet($all, $exportDir . '/verify_all.xlsx');

echo "Saved: {$exportDir}/verify_all.xlsx\n";

// Puskesmas (single)
$puskesmas = new Spreadsheet();
$pFormatter = new PuskesmasFormatter($statisticsService);
$pData = [
    'ht_summary' => ['target' => 120],
    'dm_summary' => ['target' => 100],
    'patients' => []
];
$puskesmas = $pFormatter->format($puskesmas, $diseaseType, $year, $pData, [
    'puskesmas_name' => 'Puskesmas Demo 1'
]);
saveSpreadsheet($puskesmas, $exportDir . '/verify_puskesmas.xlsx');

echo "Saved: {$exportDir}/verify_puskesmas.xlsx\n";
