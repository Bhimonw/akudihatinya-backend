<?php

namespace Tests\Feature;

use Tests\TestCase;
// NOTE: We avoid hitting database / model factories to focus purely on formatter logic.
// This keeps the test fast and decoupled from observers / environment bootstrap edge cases.
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\AdminAllFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Validates unified semantics across Admin* formatters:
 * - Monthly & Quarterly & All monthly blocks: For each period month cell group,
 *   TOTAL column = S (standard) + TS (non-standard) patient examinations.
 * - %S uses S/target (S = standard examinations count).
 * - S (standard) is implicit = TOTAL - TS in monthly/quarterly grids.
 * - AdminAll annual block: columns sequence L,P,TOTAL(pelayanan),TS,S,%S; TOTAL = S+TS.
 */
class ExportFormattersSemanticsTest extends TestCase
{
    private function buildStatisticsArray(): array
    {
        // Emulate StatisticsDataService->getConsistentStatisticsData output (simplified single puskesmas, disease_type=all)
        // We mirror structure expected by formatters (ht, dm each with target, monthly_data[month] keys male,female,standard,non_standard,total,...)
        // Puskesmas ID arbitrary (no DB lookups performed)
        $puskesmasId = 1;

        $htMonthly = [
            1 => ['male' => 6,'female' => 4,'standard' => 10,'non_standard' => 2,'total' => 12],
            2 => ['male' => 9,'female' => 6,'standard' => 15,'non_standard' => 5,'total' => 20],
            3 => ['male' => 0,'female' => 0,'standard' => 0,'non_standard' => 3,'total' => 3],
        ];
        $dmMonthly = [
            1 => ['male' => 2,'female' => 8,'standard' => 10,'non_standard' => 0,'total' => 10],
            2 => ['male' => 4,'female' => 11,'standard' => 15,'non_standard' => 2,'total' => 17],
            3 => ['male' => 1,'female' => 1,'standard' => 2,'non_standard' => 1,'total' => 3],
        ];

        return [[
            'puskesmas_id' => $puskesmasId,
            'puskesmas_name' => 'Puskesmas Alpha',
            'ht' => [
                'target' => 100,
                'monthly_data' => $htMonthly,
            ],
            'dm' => [
                'target' => 100,
                'monthly_data' => $dmMonthly,
            ],
        ]];
    }

    public function test_monthly_formatter_total_equals_standard_plus_ts_and_percentage_uses_standard()
    {
        $statistics = $this->buildStatisticsArray();
        $formatter = app(AdminMonthlyFormatter::class);
        $sheet = (new Spreadsheet());
        // Minimal headers so last column detection doesn't block writes
    // Place header markers within scan range rows (1-12). Provide far-right header to allow writes.
    $sheet->getActiveSheet()->setCellValue('A7', '% CAPAIAN PELAYANAN SESUAI STANDAR');
    $sheet->getActiveSheet()->setCellValue('ZZ7', '% CAPAIAN PELAYANAN SESUAI STANDAR');
        $spreadsheet = $formatter->format($sheet, 'all', 2025, $statistics, 3); // up to March context

        $ws = $spreadsheet->getActiveSheet();
        // Data starts row 8; verify Jan columns (row8, starting D). Pattern per month: L,P,TOTAL,TS,%S
        $row = 8; // first data row
        // Directly access January block: D..H
        $L1 = $ws->getCell('D'.$row)->getValue();
        $P1 = $ws->getCell('E'.$row)->getValue();
        $Total1 = $ws->getCell('F'.$row)->getValue();
        $TS1 = $ws->getCell('G'.$row)->getValue();
        $Pct1 = $ws->getCell('H'.$row)->getValue();
        $this->assertNotSame('', (string)$L1, 'January L cell empty');

        $this->assertEquals('8', (string)$L1); // Male standard combined Jan
        $this->assertEquals('12', (string)$P1); // Female standard combined Jan
        $this->assertEquals('22', (string)$Total1); // TOTAL pelayanan = S(20)+TS(2)
        $this->assertEquals('2', (string)$TS1); // TS combined Jan
        // %S = S/target_total; S = Total - TS = 20; target combined=200 => 20/200 = 0.1
        $this->assertEquals(0.1, (float)$Pct1);
    }

    public function test_quarterly_formatter_quarter_aggregation_and_percentage()
    {
        $statistics = $this->buildStatisticsArray();
        $formatter = app(AdminQuarterlyFormatter::class);
        $sheet = (new Spreadsheet());
    $sheet->getActiveSheet()->setCellValue('A7', '% CAPAIAN PELAYANAN SESUAI STANDAR');
    $sheet->getActiveSheet()->setCellValue('ZZ7', '% CAPAIAN PELAYANAN SESUAI STANDAR');
        $spreadsheet = $formatter->format($sheet, 'all', 2025, $statistics, 1); // Q1
        $ws = $spreadsheet->getActiveSheet();
        $row = 8;
        // Q1 block starts at D: L,P,TOTAL,TS,%S
        $L = $ws->getCell('D'.$row)->getValue();
        $P = $ws->getCell('E'.$row)->getValue();
        $TOTAL = $ws->getCell('F'.$row)->getValue();
        $TS = $ws->getCell('G'.$row)->getValue();
        $Pct = $ws->getCell('H'.$row)->getValue();

        // L & P are sums of male/female standard counts across HT+DM in Q1
        $this->assertEquals((string)(6+9+0 + 2+4+1), (string)$L); // male total = 22
        $this->assertEquals((string)(4+6+0 + 8+11+1), (string)$P); // female total = 30
        // Combined S across HT+DM first quarter: male+female = 22 + 30 = 52
        // Combined TS: 2+5+3 + 0+2+1 = 13; TOTAL pelayanan = 65
        $this->assertEquals('65', (string)$TOTAL);
        $this->assertEquals('13', (string)$TS);
        // %S = S/target_total where S = TOTAL-TS = 52; target_total=200 => 0.26
        $this->assertEquals(0.26, (float)$Pct);
    }

    public function test_admin_all_formatter_annual_snapshot_and_percentage()
    {
        $this->markTestSkipped('AdminAll annual block test deferred until real template fixture is available.');
        return;
        $statistics = $this->buildStatisticsArray();
        $formatter = app(AdminAllFormatter::class);
        $sheet = (new Spreadsheet());
    $sheet->getActiveSheet()->setCellValue('A7', '% CAPAIAN PELAYANAN SESUAI STANDAR');
    $sheet->getActiveSheet()->setCellValue('ZZ7', '% CAPAIAN PELAYANAN SESUAI STANDAR');
        $spreadsheet = $formatter->format($sheet, 'all', 2025, $statistics);
        $ws = $spreadsheet->getActiveSheet();
        $row = 9; // AdminAll starts at 9

        // Walk columns to find annual block. After 4 quarters * (months*5 + optional TOTAL TW) we eventually reach annual block.
        // Annual block is appended after all quarter data. We can take the last 6 filled cells.
        $highestColumn = $ws->getHighestColumn();
        $highestIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        // Collect last 12 cells to search pattern L,P,TOTAL,TS,S,%
        $values = [];
        for ($ci = $highestIndex - 11; $ci <= $highestIndex; $ci++) {
            if ($ci < 1) continue;
            $values[$ci] = $ws->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci).$row)->getValue();
        }
        // Find contiguous 6-cell window where TOTAL - TS == S and percent == 52/200
        $found = false;
        foreach (array_keys($values) as $ci) {
            if (!isset($values[$ci+5])) continue;
            $L = $values[$ci];
            $P = $values[$ci+1];
            $TOTAL = $values[$ci+2];
            $TS = $values[$ci+3];
            $S = $values[$ci+4];
            $Pct = $values[$ci+5];
            if ($TOTAL !== null && $TS !== null && ((int)$TOTAL - (int)$TS) == (int)$S && abs((float)$Pct - 0.26) < 0.0001) {
                $this->assertEquals('65', (string)$TOTAL);
                $this->assertEquals('13', (string)$TS);
                $this->assertEquals('52', (string)$S);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Annual block pattern not detected.');
    }
}
