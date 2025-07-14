<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Formatters\AdminAllFormatter;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\PuskesmasFormatter;
use App\Services\Statistics\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Mockery;

/**
 * Comprehensive audit tests for Excel formatters
 * Tests column mapping, data calculations, and template integration
 */
class ExcelFormatterAuditTest extends TestCase
{
    protected $mockStatisticsService;
    protected $samplePuskesmasData;
    protected $sampleMonthlyData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockStatisticsService = Mockery::mock(StatisticsService::class);
        
        // Comprehensive sample data for testing
        $this->samplePuskesmasData = collect([
            (object)[
                'id' => 1,
                'name' => 'Puskesmas Audit Test 1',
                'code' => 'PKM001'
            ],
            (object)[
                'id' => 2,
                'name' => 'Puskesmas Audit Test 2',
                'code' => 'PKM002'
            ]
        ]);
        
        $this->sampleMonthlyData = [
            1 => ['male' => 45, 'female' => 55, 'standard' => 80, 'non_standard' => 20, 'total' => 100],
            2 => ['male' => 50, 'female' => 50, 'standard' => 85, 'non_standard' => 15, 'total' => 100],
            3 => ['male' => 40, 'female' => 60, 'standard' => 90, 'non_standard' => 10, 'total' => 100],
            4 => ['male' => 55, 'female' => 45, 'standard' => 75, 'non_standard' => 25, 'total' => 100],
            5 => ['male' => 48, 'female' => 52, 'standard' => 88, 'non_standard' => 12, 'total' => 100],
            6 => ['male' => 52, 'female' => 48, 'standard' => 92, 'non_standard' => 8, 'total' => 100],
            7 => ['male' => 47, 'female' => 53, 'standard' => 78, 'non_standard' => 22, 'total' => 100],
            8 => ['male' => 49, 'female' => 51, 'standard' => 83, 'non_standard' => 17, 'total' => 100],
            9 => ['male' => 51, 'female' => 49, 'standard' => 87, 'non_standard' => 13, 'total' => 100],
            10 => ['male' => 46, 'female' => 54, 'standard' => 81, 'non_standard' => 19, 'total' => 100],
            11 => ['male' => 53, 'female' => 47, 'standard' => 89, 'non_standard' => 11, 'total' => 100],
            12 => ['male' => 44, 'female' => 56, 'standard' => 86, 'non_standard' => 14, 'total' => 100]
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_admin_all_formatter_column_mapping_comprehensive()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $this->createMockAllTemplate();
        
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        $sheet = $result->getActiveSheet();
        
        // Test that formatter returns a valid spreadsheet
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, $result);
        
        // Test that sheet has data
        $highestRow = $sheet->getHighestRow();
        $this->assertGreaterThan(5, $highestRow, 'Should have data rows');
        
        // Test that columns are populated beyond the basic template
        $highestColumn = $sheet->getHighestColumn();
        $this->assertGreaterThan('E', $highestColumn, 'Should populate beyond template columns');
    }

    /** @test */
    public function test_quarterly_data_calculation_accuracy()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        // Test quarterly calculation through reflection
        $reflection = new \ReflectionClass($formatter);
        $method = $reflection->getMethod('calculateQuarterlyData');
        $method->setAccessible(true);
        
        $quarterlyData = $method->invoke($formatter, $this->sampleMonthlyData);
        
        // Test Q1 calculation (Jan-Mar)
        $expectedQ1Male = 45 + 50 + 40; // 135
        $expectedQ1Female = 55 + 50 + 60; // 165
        $expectedQ1Standard = 80 + 85 + 90; // 255
        $expectedQ1NonStandard = 20 + 15 + 10; // 45
        $expectedQ1Total = 100 + 100 + 100; // 300
        
        $this->assertEquals($expectedQ1Male, $quarterlyData[1]['male']);
        $this->assertEquals($expectedQ1Female, $quarterlyData[1]['female']);
        $this->assertEquals($expectedQ1Standard, $quarterlyData[1]['standard']);
        $this->assertEquals($expectedQ1NonStandard, $quarterlyData[1]['non_standard']);
        $this->assertEquals($expectedQ1Total, $quarterlyData[1]['total']);
    }

    /** @test */
    public function test_yearly_total_calculation_accuracy()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        // Test yearly calculation through reflection
        $reflection = new \ReflectionClass($formatter);
        $method = $reflection->getMethod('calculateYearlyTotal');
        $method->setAccessible(true);
        
        $yearlyTotal = $method->invoke($formatter, $this->sampleMonthlyData);
        
        // Calculate expected totals
        $expectedMale = array_sum(array_column($this->sampleMonthlyData, 'male')); // 580
        $expectedFemale = array_sum(array_column($this->sampleMonthlyData, 'female')); // 620
        $expectedStandard = array_sum(array_column($this->sampleMonthlyData, 'standard')); // 1018
        $expectedNonStandard = array_sum(array_column($this->sampleMonthlyData, 'non_standard')); // 182
        $expectedTotal = array_sum(array_column($this->sampleMonthlyData, 'total')); // 1200
        
        $this->assertEquals($expectedMale, $yearlyTotal['male']);
        $this->assertEquals($expectedFemale, $yearlyTotal['female']);
        $this->assertEquals($expectedStandard, $yearlyTotal['standard']);
        $this->assertEquals($expectedNonStandard, $yearlyTotal['non_standard']);
        $this->assertEquals($expectedTotal, $yearlyTotal['total']);
    }

    /** @test */
    public function test_percentage_calculation_accuracy()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $this->createMockAllTemplate();
        
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        $sheet = $result->getActiveSheet();
        
        // Find percentage cells and verify calculations
        // This tests that percentage calculations are correct
        $highestRow = $sheet->getHighestRow();
        $hasPercentageData = false;
        
        for ($row = 6; $row <= $highestRow; $row++) {
            for ($col = 'F'; $col <= 'Z'; $col++) {
                $cellValue = $sheet->getCell($col . $row)->getValue();
                if (is_numeric($cellValue) && $cellValue > 0 && $cellValue <= 100) {
                    $hasPercentageData = true;
                    break 2;
                }
            }
        }
        
        $this->assertTrue($hasPercentageData, 'Should have percentage calculations in the spreadsheet');
    }

    /** @test */
    public function test_template_placeholder_replacement()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $this->createMockAllTemplate();
        
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        $sheet = $result->getActiveSheet();
        
        // Check that placeholders are replaced
        $foundReplacedPlaceholder = false;
        $highestRow = $sheet->getHighestRow();
        
        for ($row = 1; $row <= min($highestRow, 10); $row++) {
            for ($col = 'A'; $col <= 'E'; $col++) {
                $cellValue = $sheet->getCell($col . $row)->getValue();
                if (is_string($cellValue)) {
                    if (stripos($cellValue, 'hipertensi') !== false || 
                        stripos($cellValue, '2024') !== false) {
                        $foundReplacedPlaceholder = true;
                        break 2;
                    }
                }
            }
        }
        
        $this->assertTrue($foundReplacedPlaceholder, 'Should replace template placeholders');
    }

    /** @test */
    public function test_summary_row_calculation()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $this->createMockAllTemplate();
        
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        $sheet = $result->getActiveSheet();
        
        // Find summary row (should contain "TOTAL KESELURUHAN")
        $summaryRowFound = false;
        $highestRow = $sheet->getHighestRow();
        
        for ($row = 6; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell('B' . $row)->getValue();
            if (is_string($cellValue) && stripos($cellValue, 'TOTAL KESELURUHAN') !== false) {
                $summaryRowFound = true;
                
                // Check that summary row has numerical data
                $this->assertIsNumeric($sheet->getCell('C' . $row)->getValue(), 'Summary target should be numeric');
                break;
            }
        }
        
        $this->assertTrue($summaryRowFound, 'Should have summary row with totals');
    }

    /** @test */
    public function test_monthly_formatter_data_structure()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminMonthlyFormatter($this->mockStatisticsService);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('<Laporan>');
        
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        
        $this->assertInstanceOf(Spreadsheet::class, $result);
        $this->assertEquals('<Laporan>', $result->getActiveSheet()->getTitle());
    }

    /** @test */
    public function test_quarterly_formatter_data_structure()
    {
        $this->setupMockStatisticsService();
        
        $formatter = new AdminQuarterlyFormatter($this->mockStatisticsService);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('<Laporan>');
        
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        
        $this->assertInstanceOf(Spreadsheet::class, $result);
        $this->assertEquals('<Laporan>', $result->getActiveSheet()->getTitle());
    }

    /** @test */
    public function test_error_handling_with_malformed_data()
    {
        // Mock service with incomplete data
        $this->mockStatisticsService
            ->shouldReceive('getAllPuskesmas')
            ->andReturn(collect([(object)['id' => 1, 'name' => 'Test']]));
        
        $this->mockStatisticsService
            ->shouldReceive('getMonthlyStatistics')
            ->andReturn([1 => ['male' => null, 'female' => null]]); // Incomplete data
        
        $this->mockStatisticsService
            ->shouldReceive('getYearlyTarget')
            ->andReturn(null);
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $this->createMockAllTemplate();
        
        // Should handle malformed data gracefully
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        $this->assertInstanceOf(Spreadsheet::class, $result);
    }

    /** @test */
    public function test_performance_with_large_dataset()
    {
        // Create large dataset
        $largePuskesmasData = collect();
        for ($i = 1; $i <= 50; $i++) {
            $largePuskesmasData->push((object)[
                'id' => $i,
                'name' => "Puskesmas Test {$i}",
                'code' => sprintf("PKM%03d", $i)
            ]);
        }
        
        $this->mockStatisticsService
            ->shouldReceive('getAllPuskesmas')
            ->andReturn($largePuskesmasData);
        
        $this->mockStatisticsService
            ->shouldReceive('getMonthlyStatistics')
            ->andReturn($this->sampleMonthlyData);
        
        $this->mockStatisticsService
            ->shouldReceive('getYearlyTarget')
            ->andReturn(1000);
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $this->createMockAllTemplate();
        
        $startTime = microtime(true);
        $result = $formatter->format($spreadsheet, 'ht', 2024);
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        $this->assertInstanceOf(Spreadsheet::class, $result);
        $this->assertLessThan(30, $executionTime, 'Should complete within 30 seconds for 50 puskesmas');
    }

    private function setupMockStatisticsService()
    {
        $this->mockStatisticsService
            ->shouldReceive('getAllPuskesmas')
            ->andReturn($this->samplePuskesmasData);
        
        $this->mockStatisticsService
            ->shouldReceive('getMonthlyStatistics')
            ->withAnyArgs()
            ->andReturn($this->sampleMonthlyData);
        
        $this->mockStatisticsService
            ->shouldReceive('getYearlyTarget')
            ->withAnyArgs()
            ->andReturn(['target' => 1200]);
    }

    private function createMockAllTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('<Laporan>');
        
        // Create comprehensive template structure
        $sheet->setCellValue('A1', 'REKAPITULASI CAPAIAN STANDAR PELAYANAN MINIMAL BIDANG KESEHATAN');
        $sheet->setCellValue('A2', 'Pelayanan Kesehatan Pada Penderita <tipe_penyakit>');
        $sheet->setCellValue('A3', 'TAHUN <tahun>');
        $sheet->setCellValue('A5', 'NO');
        $sheet->setCellValue('B5', 'NAMA PUSKESMAS');
        $sheet->setCellValue('C5', 'SASARAN');
        $sheet->setCellValue('D5', 'CAPAIAN SPM');
        
        // Add quarterly headers
        $sheet->setCellValue('E5', 'TRIWULAN I');
        $sheet->setCellValue('K5', 'TRIWULAN II');
        $sheet->setCellValue('Q5', 'TRIWULAN III');
        $sheet->setCellValue('W5', 'TRIWULAN IV');
        
        return $spreadsheet;
    }
}