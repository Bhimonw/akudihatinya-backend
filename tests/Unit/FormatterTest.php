<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Formatters\AdminAllFormatter;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\PuskesmasFormatter;
use App\Formatters\BaseAdminFormatter;
use App\Formatters\ExcelExportFormatter;
use App\Services\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Mockery;

class FormatterTest extends TestCase
{
    protected $mockStatisticsService;
    protected $sampleData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock StatisticsService
        $this->mockStatisticsService = Mockery::mock(StatisticsService::class);
        
        // Sample data for testing
        $this->sampleData = [
            'puskesmas' => [
                [
                    'id' => 1,
                    'name' => 'Puskesmas Test 1',
                    'sasaran' => 1000,
                    'monthly_data' => [
                        1 => ['s' => 80, 'ts' => 20, 'total' => 100],
                        2 => ['s' => 85, 'ts' => 15, 'total' => 100],
                        3 => ['s' => 90, 'ts' => 10, 'total' => 100],
                        // ... more months
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'Puskesmas Test 2',
                    'sasaran' => 1200,
                    'monthly_data' => [
                        1 => ['s' => 75, 'ts' => 25, 'total' => 100],
                        2 => ['s' => 80, 'ts' => 20, 'total' => 100],
                        3 => ['s' => 85, 'ts' => 15, 'total' => 100],
                        // ... more months
                    ]
                ]
            ]
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function test_base_admin_formatter_exists()
    {
        $this->assertTrue(class_exists(BaseAdminFormatter::class));
    }

    /** @test */
    public function test_excel_export_formatter_exists()
    {
        $this->assertTrue(class_exists(ExcelExportFormatter::class));
    }

    /** @test */
    public function test_admin_all_formatter_exists()
    {
        $this->assertTrue(class_exists(AdminAllFormatter::class));
    }

    /** @test */
    public function test_admin_monthly_formatter_exists()
    {
        $this->assertTrue(class_exists(AdminMonthlyFormatter::class));
    }

    /** @test */
    public function test_admin_quarterly_formatter_exists()
    {
        $this->assertTrue(class_exists(AdminQuarterlyFormatter::class));
    }

    /** @test */
    public function test_puskesmas_formatter_exists()
    {
        $this->assertTrue(class_exists(PuskesmasFormatter::class));
    }

    /** @test */
    public function test_admin_all_formatter_can_be_instantiated()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $this->assertInstanceOf(AdminAllFormatter::class, $formatter);
        $this->assertInstanceOf(ExcelExportFormatter::class, $formatter);
    }

    /** @test */
    public function test_admin_monthly_formatter_can_be_instantiated()
    {
        $formatter = new AdminMonthlyFormatter($this->mockStatisticsService);
        $this->assertInstanceOf(AdminMonthlyFormatter::class, $formatter);
        $this->assertInstanceOf(ExcelExportFormatter::class, $formatter);
    }

    /** @test */
    public function test_admin_quarterly_formatter_can_be_instantiated()
    {
        $formatter = new AdminQuarterlyFormatter($this->mockStatisticsService);
        $this->assertInstanceOf(AdminQuarterlyFormatter::class, $formatter);
        $this->assertInstanceOf(ExcelExportFormatter::class, $formatter);
    }

    /** @test */
    public function test_puskesmas_formatter_can_be_instantiated()
    {
        $formatter = new PuskesmasFormatter($this->mockStatisticsService);
        $this->assertInstanceOf(PuskesmasFormatter::class, $formatter);
        $this->assertInstanceOf(ExcelExportFormatter::class, $formatter);
    }

    /** @test */
    public function test_admin_all_formatter_has_required_methods()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $requiredMethods = [
            'format',
            'getFilename',
            'validateInput',
            'getAllData',
            'setupHeaders',
            'fillData'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($formatter, $method), "Method {$method} not found in AdminAllFormatter");
        }
    }

    /** @test */
    public function test_admin_monthly_formatter_has_required_methods()
    {
        $formatter = new AdminMonthlyFormatter($this->mockStatisticsService);
        
        $requiredMethods = [
            'format',
            'getFilename',
            'validateInput',
            'getMonthlyData',
            'setupHeaders',
            'fillData'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($formatter, $method), "Method {$method} not found in AdminMonthlyFormatter");
        }
    }

    /** @test */
    public function test_admin_quarterly_formatter_has_required_methods()
    {
        $formatter = new AdminQuarterlyFormatter($this->mockStatisticsService);
        
        $requiredMethods = [
            'format',
            'getFilename',
            'validateInput',
            'getQuarterlyData',
            'setupHeaders',
            'fillData'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($formatter, $method), "Method {$method} not found in AdminQuarterlyFormatter");
        }
    }

    /** @test */
    public function test_puskesmas_formatter_has_required_methods()
    {
        $formatter = new PuskesmasFormatter($this->mockStatisticsService);
        
        $requiredMethods = [
            'format',
            'getFilename',
            'validateInput',
            'getPuskesmasSpecificData',
            'formatTemplate',
            'setupHeaders',
            'fillData'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($formatter, $method), "Method {$method} not found in PuskesmasFormatter");
        }
    }

    /** @test */
    public function test_base_admin_formatter_has_utility_methods()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $utilityMethods = [
            'getIndonesianMonthName',
            'getIndonesianQuarterName',
            'applyHeaderStyle',
            'applyDataStyle',
            'formatNumber',
            'formatPercentage',
            'mergeAndSetValue',
            'incrementColumn',
            'logActivity',
            'logError',
            'getAchievementStatus'
        ];
        
        foreach ($utilityMethods as $method) {
            $this->assertTrue(method_exists($formatter, $method), "Utility method {$method} not found");
        }
    }

    /** @test */
    public function test_validate_input_with_valid_data()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        // Should not throw exception with valid data
        $this->expectNotToPerformAssertions();
        $formatter->validateInput('ht', 2024);
    }

    /** @test */
    public function test_validate_input_with_invalid_disease_type()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->expectException(\InvalidArgumentException::class);
        $formatter->validateInput('invalid', 2024);
    }

    /** @test */
    public function test_validate_input_with_invalid_year()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->expectException(\InvalidArgumentException::class);
        $formatter->validateInput('ht', 2019); // Too old
    }

    /** @test */
    public function test_get_filename_for_admin_all_formatter()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $filename = $formatter->getFilename('ht', 2024);
        
        $this->assertStringContainsString('hipertensi', strtolower($filename));
        $this->assertStringContainsString('2024', $filename);
        $this->assertStringContainsString('all', strtolower($filename));
        $this->assertStringEndsWith('.xlsx', $filename);
    }

    /** @test */
    public function test_get_filename_for_admin_monthly_formatter()
    {
        $formatter = new AdminMonthlyFormatter($this->mockStatisticsService);
        
        $filename = $formatter->getFilename('dm', 2024);
        
        $this->assertStringContainsString('diabetes', strtolower($filename));
        $this->assertStringContainsString('2024', $filename);
        $this->assertStringContainsString('monthly', strtolower($filename));
        $this->assertStringEndsWith('.xlsx', $filename);
    }

    /** @test */
    public function test_get_filename_for_admin_quarterly_formatter()
    {
        $formatter = new AdminQuarterlyFormatter($this->mockStatisticsService);
        
        $filename = $formatter->getFilename('ht', 2024);
        
        $this->assertStringContainsString('hipertensi', strtolower($filename));
        $this->assertStringContainsString('2024', $filename);
        $this->assertStringContainsString('quarterly', strtolower($filename));
        $this->assertStringEndsWith('.xlsx', $filename);
    }

    /** @test */
    public function test_get_filename_for_puskesmas_formatter()
    {
        $formatter = new PuskesmasFormatter($this->mockStatisticsService);
        
        $filename = $formatter->getFilename('ht', 2024);
        
        $this->assertStringContainsString('hipertensi', strtolower($filename));
        $this->assertStringContainsString('2024', $filename);
        $this->assertStringContainsString('puskesmas', strtolower($filename));
        $this->assertStringEndsWith('.xlsx', $filename);
    }

    /** @test */
    public function test_indonesian_month_names()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $expectedMonths = [
            1 => 'JANUARI',
            2 => 'FEBRUARI', 
            3 => 'MARET',
            4 => 'APRIL',
            5 => 'MEI',
            6 => 'JUNI',
            7 => 'JULI',
            8 => 'AGUSTUS',
            9 => 'SEPTEMBER',
            10 => 'OKTOBER',
            11 => 'NOVEMBER',
            12 => 'DESEMBER'
        ];
        
        foreach ($expectedMonths as $monthNum => $expectedName) {
            $this->assertEquals($expectedName, $formatter->getIndonesianMonthName($monthNum));
        }
    }

    /** @test */
    public function test_indonesian_quarter_names()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $expectedQuarters = [
            1 => 'TRIWULAN I',
            2 => 'TRIWULAN II',
            3 => 'TRIWULAN III',
            4 => 'TRIWULAN IV'
        ];
        
        foreach ($expectedQuarters as $quarterNum => $expectedName) {
            $this->assertEquals($expectedName, $formatter->getIndonesianQuarterName($quarterNum));
        }
    }

    /** @test */
    public function test_format_number()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->assertEquals('1,000', $formatter->formatNumber(1000));
        $this->assertEquals('1,234', $formatter->formatNumber(1234));
        $this->assertEquals('0', $formatter->formatNumber(0));
        $this->assertEquals('0', $formatter->formatNumber(null));
    }

    /** @test */
    public function test_format_percentage()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->assertEquals('75.00%', $formatter->formatPercentage(75));
        $this->assertEquals('100.00%', $formatter->formatPercentage(100));
        $this->assertEquals('0.00%', $formatter->formatPercentage(0));
        $this->assertEquals('0.00%', $formatter->formatPercentage(null));
    }

    /** @test */
    public function test_increment_column()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->assertEquals('B', $formatter->incrementColumn('A'));
        $this->assertEquals('C', $formatter->incrementColumn('B'));
        $this->assertEquals('Z', $formatter->incrementColumn('Y'));
        $this->assertEquals('AA', $formatter->incrementColumn('Z'));
        $this->assertEquals('AB', $formatter->incrementColumn('AA'));
    }

    /** @test */
    public function test_get_achievement_status()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->assertEquals('Sangat Baik', $formatter->getAchievementStatus(95));
        $this->assertEquals('Baik', $formatter->getAchievementStatus(85));
        $this->assertEquals('Cukup', $formatter->getAchievementStatus(75));
        $this->assertEquals('Kurang', $formatter->getAchievementStatus(65));
        $this->assertEquals('Sangat Kurang', $formatter->getAchievementStatus(45));
    }

    /** @test */
    public function test_format_returns_spreadsheet_object()
    {
        // Mock the statistics service to return sample data
        $this->mockStatisticsService
            ->shouldReceive('getYearlyStatistics')
            ->with('ht', 2024)
            ->andReturn($this->sampleData);
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $result = $formatter->format('ht', 2024);
        
        $this->assertInstanceOf(Spreadsheet::class, $result);
    }

    /** @test */
    public function test_format_creates_worksheet_with_correct_name()
    {
        // Mock the statistics service
        $this->mockStatisticsService
            ->shouldReceive('getYearlyStatistics')
            ->with('ht', 2024)
            ->andReturn($this->sampleData);
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        $spreadsheet = $formatter->format('ht', 2024);
        
        $worksheet = $spreadsheet->getActiveSheet();
        $this->assertEquals('Laporan', $worksheet->getTitle());
    }

    /** @test */
    public function test_disease_type_mapping()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        // Test through reflection to access protected method
        $reflection = new \ReflectionClass($formatter);
        $method = $reflection->getMethod('getDiseaseTypeName');
        $method->setAccessible(true);
        
        $this->assertEquals('Hipertensi', $method->invoke($formatter, 'ht'));
        $this->assertEquals('Diabetes Melitus', $method->invoke($formatter, 'dm'));
    }

    /** @test */
    public function test_puskesmas_formatter_with_specific_puskesmas_id()
    {
        // Mock the statistics service for specific puskesmas
        $this->mockStatisticsService
            ->shouldReceive('getPuskesmasStatistics')
            ->with(1, 'ht', 2024)
            ->andReturn($this->sampleData['puskesmas'][0]);
        
        $formatter = new PuskesmasFormatter($this->mockStatisticsService);
        $result = $formatter->format('ht', 2024, ['puskesmas_id' => 1]);
        
        $this->assertInstanceOf(Spreadsheet::class, $result);
    }

    /** @test */
    public function test_puskesmas_formatter_template_mode()
    {
        $formatter = new PuskesmasFormatter($this->mockStatisticsService);
        $result = $formatter->formatTemplate('ht', 2024);
        
        $this->assertInstanceOf(Spreadsheet::class, $result);
    }

    /** @test */
    public function test_quarterly_formatter_aggregates_monthly_data()
    {
        // Mock the statistics service
        $this->mockStatisticsService
            ->shouldReceive('getYearlyStatistics')
            ->with('ht', 2024)
            ->andReturn($this->sampleData);
        
        $formatter = new AdminQuarterlyFormatter($this->mockStatisticsService);
        
        // Test through reflection to access protected method
        $reflection = new \ReflectionClass($formatter);
        $method = $reflection->getMethod('aggregateQuarterlyData');
        $method->setAccessible(true);
        
        $monthlyData = $this->sampleData['puskesmas'][0]['monthly_data'];
        $quarterlyData = $method->invoke($formatter, $monthlyData);
        
        $this->assertIsArray($quarterlyData);
        $this->assertArrayHasKey(1, $quarterlyData); // Q1
    }

    /** @test */
    public function test_error_handling_with_invalid_statistics_service_response()
    {
        // Mock service to return null/empty data
        $this->mockStatisticsService
            ->shouldReceive('getYearlyStatistics')
            ->with('ht', 2024)
            ->andReturn(null);
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $this->expectException(\Exception::class);
        $formatter->format('ht', 2024);
    }

    /** @test */
    public function test_memory_management_in_format_method()
    {
        // Mock the statistics service
        $this->mockStatisticsService
            ->shouldReceive('getYearlyStatistics')
            ->with('ht', 2024)
            ->andReturn($this->sampleData);
        
        $formatter = new AdminAllFormatter($this->mockStatisticsService);
        
        $memoryBefore = memory_get_usage();
        $spreadsheet = $formatter->format('ht', 2024);
        $memoryAfter = memory_get_usage();
        
        // Ensure memory usage is reasonable (less than 50MB increase)
        $memoryIncrease = $memoryAfter - $memoryBefore;
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory usage increased too much');
        
        // Cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}