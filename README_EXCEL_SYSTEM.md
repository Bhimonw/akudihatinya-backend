# Excel Export System - Quick Start Guide

## 🚀 Quick Start

### Installation

1. **Register Service Provider**
```php
// config/app.php
'providers' => [
    // ...
    App\Providers\ExcelServiceProvider::class,
],
```

2. **Publish Configuration**
```bash
php artisan vendor:publish --tag=excel-config
```

3. **Add to Facade (Optional)**
```php
// config/app.php
'aliases' => [
    // ...
    'Excel' => App\Facades\Excel::class,
],
```

### Basic Usage

#### 1. Using Facade (Recommended)

```php
use App\Facades\Excel;

// Yearly Report
$spreadsheet = Excel::yearly($puskesmasData, 2024);

// Monthly Report
$spreadsheet = Excel::monthly($puskesmasData, 2024, 6); // June 2024

// Quarterly Report
$spreadsheet = Excel::quarterly($puskesmasData, 2024, 2); // Q2 2024

// Puskesmas Specific Report
$spreadsheet = Excel::puskesmas($puskesmasData, 2024, 'Puskesmas ABC');
```

#### 2. Direct Download

```php
// Download immediately
return Excel::download('monthly', $puskesmasData, 2024, 6);

// Save to storage
$filePath = Excel::save('yearly', $puskesmasData, 2024);
```

#### 3. Controller Example

```php
class ReportController extends Controller
{
    public function exportMonthly(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('n'));
        
        // Get data from your model
        $puskesmasData = PuskesmasData::getMonthlyData($year, $month);
        
        // Validate data
        if (!Excel::validate($puskesmasData, 'monthly', $year, $month)) {
            return response()->json(['error' => 'Invalid data'], 400);
        }
        
        // Download Excel file
        return Excel::download('monthly', $puskesmasData, $year, $month);
    }
    
    public function exportYearly(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $puskesmasData = PuskesmasData::getYearlyData($year);
        
        return Excel::download('yearly', $puskesmasData, $year);
    }
}
```

## 📊 Data Structure

### Expected Data Format

```php
$puskesmasData = [
    [
        'nama_puskesmas' => 'Puskesmas ABC',
        'sasaran' => 500,
        'monthly_data' => [
            1 => [ // January
                'male' => 20,
                'female' => 25,
                'total' => 45,
                'standard' => 40,
                'non_standard' => 5,
                'percentage' => 88.89
            ],
            2 => [ // February
                'male' => 18,
                'female' => 22,
                'total' => 40,
                'standard' => 35,
                'non_standard' => 5,
                'percentage' => 87.5
            ],
            // ... months 3-12
        ]
    ],
    // ... more puskesmas data
];
```

## 🛠️ Command Line Usage

### Artisan Commands

```bash
# Generate yearly report
php artisan excel:export yearly 2024

# Generate monthly report
php artisan excel:export monthly 2024 --month=6

# Generate quarterly report
php artisan excel:export quarterly 2024 --quarter=2

# Generate puskesmas specific report
php artisan excel:export puskesmas 2024 --puskesmas="Puskesmas ABC"

# Use sample data for testing
php artisan excel:export monthly 2024 --month=6 --sample

# Validate data only (no export)
php artisan excel:export yearly 2024 --validate-only

# Custom output path
php artisan excel:export monthly 2024 --month=6 --output=/custom/path
```

## ⚙️ Configuration

### Environment Variables

```env
# Performance Settings
EXCEL_CACHE_ENABLED=true
EXCEL_CACHE_TTL=3600
EXCEL_MAX_ROWS=1000
EXCEL_MAX_COLUMNS=100
EXCEL_MAX_FILE_SIZE=50
EXCEL_MEMORY_LIMIT=512M
EXCEL_TIME_LIMIT=300

# Development Settings
EXCEL_DEBUG=false
EXCEL_PROFILE=false
EXCEL_SAVE_DEBUG=false
EXCEL_MOCK_DATA=false

# Queue Settings (Optional)
EXCEL_QUEUE_ENABLED=false
EXCEL_QUEUE_CONNECTION=default
EXCEL_QUEUE_NAME=excel-exports

# Notification Settings (Optional)
EXCEL_NOTIFICATIONS=false

# Backup Settings (Optional)
EXCEL_BACKUP_ENABLED=false
EXCEL_BACKUP_RETENTION=30
```

### Custom Configuration

```php
// config/excel.php
return [
    'styling' => [
        'colors' => [
            'header_background' => 'E6E6FA', // Custom header color
            'total_background' => 'FFE6E6',  // Custom total row color
        ],
        'fonts' => [
            'family' => 'Arial', // Custom font family
            'sizes' => [
                'title' => 16,   // Custom title size
                'header' => 12,  // Custom header size
            ],
        ],
    ],
    
    'templates' => [
        'all' => 'custom_yearly.xlsx',      // Custom template
        'monthly' => 'custom_monthly.xlsx',
    ],
    
    'performance' => [
        'limits' => [
            'max_rows_per_sheet' => 2000,    // Increase row limit
            'max_file_size_mb' => 100,       // Increase file size limit
        ],
    ],
];
```

## 🔧 Advanced Usage

### 1. Custom Styling

```php
use App\Formatters\Builders\ExcelStyleBuilder;

$styleBuilder = new ExcelStyleBuilder();

// Create custom header style
$customHeaderStyle = $styleBuilder
    ->backgroundColor('FF0000')  // Red background
    ->fontSize(14)
    ->bold()
    ->textColor('FFFFFF')        // White text
    ->border('thick')
    ->centerAlign()
    ->build();

// Apply to worksheet
$styleBuilder->applyToRange($worksheet, 'A1:Z1', $customHeaderStyle);
```

### 2. Custom Calculations

```php
use App\Formatters\Calculators\StatisticsCalculator;

// Calculate custom statistics
$monthlyTotal = StatisticsCalculator::calculateMonthTotal($data, 6);
$quarterlyTotal = StatisticsCalculator::calculateQuarterTotal($data, 2);
$yearlyTotal = StatisticsCalculator::calculateYearlyTotalFromAll($data);

// Advanced statistics
$average = StatisticsCalculator::calculateAverage([10, 20, 30, 40]);
$median = StatisticsCalculator::calculateMedian([10, 20, 30, 40]);
$stdDev = StatisticsCalculator::calculateStandardDeviation([10, 20, 30, 40]);
$growthRate = StatisticsCalculator::calculateGrowthRate(100, 120); // 20%
```

### 3. Data Validation

```php
use App\Formatters\Validators\ExcelDataValidator;

$validator = new ExcelDataValidator();

// Validate individual components
$isValidData = $validator->validatePuskesmasData($puskesmasData[0]);
$isValidYear = $validator->validateYear(2024);
$isValidMonth = $validator->validateMonth(6);
$isValidType = $validator->validateReportType('monthly');

// Validate complete export parameters
$isValidExport = $validator->validateExportParameters([
    'puskesmas_data' => $puskesmasData,
    'report_type' => 'monthly',
    'year' => 2024,
    'month' => 6
]);
```

### 4. Column Management

```php
use App\Formatters\Helpers\ColumnManager;

// Get columns for specific report types
$monthlyColumns = ColumnManager::getMonthlyColumns();
$quarterlyColumns = ColumnManager::getQuarterlyColumns();
$totalColumns = ColumnManager::getTotalColumns();

// Column operations
$nextColumn = ColumnManager::incrementColumn('A');     // 'B'
$prevColumn = ColumnManager::decrementColumn('B');     // 'A'
$columnLetter = ColumnManager::columnIndexToLetter(27); // 'AA'
$columnIndex = ColumnManager::columnLetterToIndex('AA'); // 27

// Validation
$isValidColumn = ColumnManager::isValidColumn('A');
$isValidRange = ColumnManager::isValidRange('A1:Z100');
```

## 🧪 Testing

### Running Tests

```bash
# Run all Excel tests
php artisan test tests/Unit/ExcelExportTest.php

# Run with coverage
php artisan test tests/Unit/ExcelExportTest.php --coverage

# Run specific test method
php artisan test --filter=test_can_create_yearly_excel_export
```

### Sample Test

```php
public function test_monthly_export_contains_correct_data()
{
    $data = $this->generateSampleData();
    $spreadsheet = Excel::monthly($data, 2024, 6);
    
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Check title
    $this->assertStringContainsString('JUNI', $worksheet->getCell('A1')->getValue());
    
    // Check data presence
    $this->assertEquals($data[0]['nama_puskesmas'], $worksheet->getCell('B10')->getValue());
    
    // Check calculations
    $expectedTotal = $data[0]['monthly_data'][6]['total'];
    $actualTotal = $worksheet->getCell('F10')->getValue();
    $this->assertEquals($expectedTotal, $actualTotal);
}
```

## 🚨 Troubleshooting

### Common Issues

#### 1. Memory Limit Exceeded
```php
// Increase memory limit in config/excel.php
'performance' => [
    'limits' => [
        'memory_limit' => '1024M', // Increase from 512M
    ],
],
```

#### 2. File Not Found
```bash
# Check if templates directory exists
ls -la storage/app/templates/

# Create if missing
mkdir -p storage/app/templates
```

#### 3. Permission Denied
```bash
# Fix storage permissions
chmod -R 755 storage/
chown -R www-data:www-data storage/
```

#### 4. Validation Errors
```php
// Enable debug mode to see detailed errors
'development' => [
    'debug_mode' => true,
    'save_debug_files' => true,
],
```

### Debug Commands

```bash
# Test with sample data
php artisan excel:export monthly 2024 --month=6 --sample --validate-only

# Check configuration
php artisan config:show excel

# Clear cache
php artisan config:clear
php artisan cache:clear
```

## 📈 Performance Tips

### 1. Enable Caching
```env
EXCEL_CACHE_ENABLED=true
EXCEL_CACHE_TTL=3600
```

### 2. Optimize Memory Usage
```php
'performance' => [
    'optimization' => [
        'read_only' => true,
        'read_data_only' => false,
        'calculate_formulas' => false,
    ],
],
```

### 3. Use Queue for Large Files
```env
EXCEL_QUEUE_ENABLED=true
EXCEL_QUEUE_CONNECTION=redis
```

### 4. Compress Output
```php
'export' => [
    'compress_output' => true,
],
```

## 🔗 API Routes Example

```php
// routes/api.php
Route::prefix('reports')->group(function () {
    Route::get('/excel/yearly/{year}', [ReportController::class, 'exportYearly']);
    Route::get('/excel/monthly/{year}/{month}', [ReportController::class, 'exportMonthly']);
    Route::get('/excel/quarterly/{year}/{quarter}', [ReportController::class, 'exportQuarterly']);
    Route::get('/excel/puskesmas/{year}/{puskesmas}', [ReportController::class, 'exportPuskesmas']);
    
    // Validation endpoints
    Route::post('/excel/validate', [ReportController::class, 'validateData']);
});
```

## 📚 Additional Resources

- [Full Documentation](EXCEL_REFACTORING_DOCUMENTATION.md)
- [Code Quality Recommendations](CODE_QUALITY_RECOMMENDATIONS.md)
- [PhpSpreadsheet Documentation](https://phpspreadsheet.readthedocs.io/)
- [Laravel Service Container](https://laravel.com/docs/container)

---

**Happy Coding! 🎉**

Jika ada pertanyaan atau masalah, silakan buka issue atau hubungi tim development.