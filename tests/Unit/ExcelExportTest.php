<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Formatters\ExcelExportFormatter;
use App\Formatters\Helpers\ColumnManager;
use App\Formatters\Calculators\StatisticsCalculator;
use App\Formatters\Validators\ExcelDataValidator;
use App\Formatters\Builders\ExcelStyleBuilder;
use App\Constants\ExcelConstants;
use App\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExcelExportTest extends TestCase
{
    protected $sampleData;
    protected $formatter;
    protected $columnManager;
    protected $calculator;
    protected $validator;
    protected $styleBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize dependencies
        $this->columnManager = new ColumnManager();
        $this->calculator = new StatisticsCalculator();
        $this->validator = new ExcelDataValidator();
        $this->styleBuilder = new ExcelStyleBuilder();
        
        $this->formatter = new ExcelExportFormatter(
            $this->columnManager,
            $this->calculator,
            $this->validator,
            $this->styleBuilder
        );
        
        // Generate sample data
        $this->sampleData = $this->generateSampleData();
    }

    /** @test */
    public function it_can_create_yearly_excel_export()
    {
        $spreadsheet = $this->formatter->formatAllExcel($this->sampleData, 2024);
        
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $this->assertNotNull($spreadsheet->getActiveSheet());
        
        $worksheet = $spreadsheet->getActiveSheet();
        $this->assertEquals('LAPORAN TAHUNAN 2024', $worksheet->getCell('A1')->getValue());
    }

    /** @test */
    public function it_can_create_monthly_excel_export()
    {
        $spreadsheet = $this->formatter->formatMonthlyExcel($this->sampleData, 2024, 6);
        
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Check if the title contains the month
        $title = $worksheet->getCell('A1')->getValue();
        $this->assertStringContainsString('JUNI', $title);
        $this->assertStringContainsString('2024', $title);
    }

    /** @test */
    public function it_can_create_quarterly_excel_export()
    {
        $spreadsheet = $this->formatter->formatQuarterlyExcel($this->sampleData, 2024, 2);
        
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Check if the title contains the quarter
        $title = $worksheet->getCell('A1')->getValue();
        $this->assertStringContainsString('TRIWULAN II', $title);
        $this->assertStringContainsString('2024', $title);
    }

    /** @test */
    public function it_can_create_puskesmas_excel_export()
    {
        $puskesmasName = 'Puskesmas Test';
        $spreadsheet = $this->formatter->formatPuskesmasExcel($this->sampleData, 2024, $puskesmasName);
        
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Check if the title contains the puskesmas name
        $title = $worksheet->getCell('A1')->getValue();
        $this->assertStringContainsString($puskesmasName, $title);
    }

    /** @test */
    public function column_manager_can_get_monthly_columns()
    {
        $columns = ColumnManager::getMonthlyColumns();
        
        $this->assertIsArray($columns);
        $this->assertCount(12, $columns);
        $this->assertEquals('D', $columns[1]); // January should start at column D
    }

    /** @test */
    public function column_manager_can_get_quarterly_columns()
    {
        $columns = ColumnManager::getQuarterlyColumns();
        
        $this->assertIsArray($columns);
        $this->assertCount(4, $columns);
        $this->assertEquals('D', $columns[1]); // Q1 should start at column D
    }

    /** @test */
    public function column_manager_can_increment_column()
    {
        $this->assertEquals('B', ColumnManager::incrementColumn('A'));
        $this->assertEquals('Z', ColumnManager::incrementColumn('Y'));
        $this->assertEquals('AA', ColumnManager::incrementColumn('Z'));
        $this->assertEquals('AB', ColumnManager::incrementColumn('AA'));
    }

    /** @test */
    public function column_manager_can_convert_column_index()
    {
        $this->assertEquals('A', ColumnManager::columnIndexToLetter(1));
        $this->assertEquals('Z', ColumnManager::columnIndexToLetter(26));
        $this->assertEquals('AA', ColumnManager::columnIndexToLetter(27));
        
        $this->assertEquals(1, ColumnManager::columnLetterToIndex('A'));
        $this->assertEquals(26, ColumnManager::columnLetterToIndex('Z'));
        $this->assertEquals(27, ColumnManager::columnLetterToIndex('AA'));
    }

    /** @test */
    public function statistics_calculator_can_calculate_monthly_total()
    {
        $total = StatisticsCalculator::calculateMonthTotal($this->sampleData, 6);
        
        $this->assertIsArray($total);
        $this->assertArrayHasKey('male', $total);
        $this->assertArrayHasKey('female', $total);
        $this->assertArrayHasKey('total', $total);
        $this->assertArrayHasKey('standard', $total);
        $this->assertArrayHasKey('non_standard', $total);
        $this->assertArrayHasKey('percentage', $total);
    }

    /** @test */
    public function statistics_calculator_can_calculate_quarter_total()
    {
        $total = StatisticsCalculator::calculateQuarterTotal($this->sampleData, 2);
        
        $this->assertIsArray($total);
        $this->assertArrayHasKey('male', $total);
        $this->assertArrayHasKey('female', $total);
        $this->assertArrayHasKey('total', $total);
        $this->assertArrayHasKey('standard', $total);
        $this->assertArrayHasKey('non_standard', $total);
        $this->assertArrayHasKey('percentage', $total);
    }

    /** @test */
    public function statistics_calculator_can_calculate_standard_percentage()
    {
        $percentage = StatisticsCalculator::calculateStandardPercentage(80, 100);
        $this->assertEquals(80.0, $percentage);
        
        $percentage = StatisticsCalculator::calculateStandardPercentage(0, 0);
        $this->assertEquals(0.0, $percentage);
        
        $percentage = StatisticsCalculator::calculateStandardPercentage(75, 150);
        $this->assertEquals(50.0, $percentage);
    }

    /** @test */
    public function excel_data_validator_can_validate_puskesmas_data()
    {
        $validData = [
            'nama_puskesmas' => 'Test Puskesmas',
            'sasaran' => 100,
            'monthly_data' => [
                1 => ['male' => 10, 'female' => 15, 'total' => 25, 'standard' => 20, 'non_standard' => 5]
            ]
        ];
        
        $this->assertTrue($this->validator->validatePuskesmasData($validData));
        
        $invalidData = [
            'nama_puskesmas' => '', // Empty name
            'sasaran' => -1, // Negative sasaran
            'monthly_data' => []
        ];
        
        $this->assertFalse($this->validator->validatePuskesmasData($invalidData));
    }

    /** @test */
    public function excel_data_validator_can_validate_report_type()
    {
        $this->assertTrue($this->validator->validateReportType('all'));
        $this->assertTrue($this->validator->validateReportType('monthly'));
        $this->assertTrue($this->validator->validateReportType('quarterly'));
        $this->assertTrue($this->validator->validateReportType('puskesmas'));
        
        $this->assertFalse($this->validator->validateReportType('invalid'));
        $this->assertFalse($this->validator->validateReportType(''));
    }

    /** @test */
    public function excel_data_validator_can_validate_year()
    {
        $this->assertTrue($this->validator->validateYear(2024));
        $this->assertTrue($this->validator->validateYear(2000));
        
        $this->assertFalse($this->validator->validateYear(1999));
        $this->assertFalse($this->validator->validateYear(2051));
    }

    /** @test */
    public function excel_style_builder_can_create_styles()
    {
        $style = $this->styleBuilder
            ->headerStyle()
            ->backgroundColor('E6E6FA')
            ->fontSize(12)
            ->bold()
            ->border('thin')
            ->build();
        
        $this->assertIsArray($style);
        $this->assertArrayHasKey('font', $style);
        $this->assertArrayHasKey('fill', $style);
        $this->assertArrayHasKey('borders', $style);
    }

    /** @test */
    public function excel_constants_provides_correct_values()
    {
        $months = ExcelConstants::getMonthNames();
        $this->assertCount(12, $months);
        $this->assertEquals('JANUARI', $months[1]);
        $this->assertEquals('DESEMBER', $months[12]);
        
        $quarters = ExcelConstants::getQuarterNames();
        $this->assertCount(4, $quarters);
        $this->assertEquals('TRIWULAN I', $quarters[1]);
        $this->assertEquals('TRIWULAN IV', $quarters[4]);
    }

    /** @test */
    public function facade_can_create_exports()
    {
        // Test yearly export
        $spreadsheet = Excel::yearly($this->sampleData, 2024);
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        
        // Test monthly export
        $spreadsheet = Excel::monthly($this->sampleData, 2024, 6);
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        
        // Test quarterly export
        $spreadsheet = Excel::quarterly($this->sampleData, 2024, 2);
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
    }

    /** @test */
    public function facade_can_validate_data()
    {
        $isValid = Excel::validate($this->sampleData, 'yearly', 2024);
        $this->assertTrue($isValid);
        
        $isValid = Excel::validate([], 'invalid_type', 1999);
        $this->assertFalse($isValid);
    }

    /** @test */
    public function facade_provides_report_types()
    {
        $types = Excel::getReportTypes();
        $this->assertIsArray($types);
        $this->assertContains('yearly', $types);
        $this->assertContains('monthly', $types);
        $this->assertContains('quarterly', $types);
        $this->assertContains('puskesmas', $types);
    }

    /**
     * Generate sample data for testing.
     *
     * @return array
     */
    protected function generateSampleData()
    {
        return [
            [
                'nama_puskesmas' => 'Puskesmas Test 1',
                'sasaran' => 500,
                'monthly_data' => [
                    1 => ['male' => 20, 'female' => 25, 'total' => 45, 'standard' => 40, 'non_standard' => 5],
                    2 => ['male' => 18, 'female' => 22, 'total' => 40, 'standard' => 35, 'non_standard' => 5],
                    3 => ['male' => 22, 'female' => 28, 'total' => 50, 'standard' => 45, 'non_standard' => 5],
                    4 => ['male' => 19, 'female' => 21, 'total' => 40, 'standard' => 38, 'non_standard' => 2],
                    5 => ['male' => 25, 'female' => 30, 'total' => 55, 'standard' => 50, 'non_standard' => 5],
                    6 => ['male' => 23, 'female' => 27, 'total' => 50, 'standard' => 47, 'non_standard' => 3],
                    7 => ['male' => 21, 'female' => 24, 'total' => 45, 'standard' => 42, 'non_standard' => 3],
                    8 => ['male' => 26, 'female' => 29, 'total' => 55, 'standard' => 52, 'non_standard' => 3],
                    9 => ['male' => 24, 'female' => 26, 'total' => 50, 'standard' => 48, 'non_standard' => 2],
                    10 => ['male' => 27, 'female' => 33, 'total' => 60, 'standard' => 57, 'non_standard' => 3],
                    11 => ['male' => 22, 'female' => 28, 'total' => 50, 'standard' => 46, 'non_standard' => 4],
                    12 => ['male' => 25, 'female' => 30, 'total' => 55, 'standard' => 53, 'non_standard' => 2],
                ]
            ],
            [
                'nama_puskesmas' => 'Puskesmas Test 2',
                'sasaran' => 300,
                'monthly_data' => [
                    1 => ['male' => 15, 'female' => 18, 'total' => 33, 'standard' => 30, 'non_standard' => 3],
                    2 => ['male' => 12, 'female' => 16, 'total' => 28, 'standard' => 25, 'non_standard' => 3],
                    3 => ['male' => 17, 'female' => 20, 'total' => 37, 'standard' => 35, 'non_standard' => 2],
                    4 => ['male' => 14, 'female' => 19, 'total' => 33, 'standard' => 31, 'non_standard' => 2],
                    5 => ['male' => 16, 'female' => 21, 'total' => 37, 'standard' => 34, 'non_standard' => 3],
                    6 => ['male' => 18, 'female' => 22, 'total' => 40, 'standard' => 38, 'non_standard' => 2],
                    7 => ['male' => 13, 'female' => 17, 'total' => 30, 'standard' => 28, 'non_standard' => 2],
                    8 => ['male' => 19, 'female' => 23, 'total' => 42, 'standard' => 40, 'non_standard' => 2],
                    9 => ['male' => 16, 'female' => 20, 'total' => 36, 'standard' => 34, 'non_standard' => 2],
                    10 => ['male' => 20, 'female' => 25, 'total' => 45, 'standard' => 43, 'non_standard' => 2],
                    11 => ['male' => 17, 'female' => 21, 'total' => 38, 'standard' => 36, 'non_standard' => 2],
                    12 => ['male' => 18, 'female' => 22, 'total' => 40, 'standard' => 38, 'non_standard' => 2],
                ]
            ]
        ];
    }
}