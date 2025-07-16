# Excel Export System - Refactoring Documentation

## Overview

Sistem Excel Export telah dirapikan dan direstrukturisasi untuk meningkatkan maintainability, testability, dan extensibility. Refactoring ini mengikuti prinsip SOLID dan design patterns yang baik.

## Struktur Baru

### 1. Constants (`app/Constants/ExcelConstants.php`)

**Tujuan**: Memusatkan semua konstanta terkait Excel export

**Fitur**:
- Pemetaan kolom bulanan dan triwulanan
- Nama bulan dan triwulan dalam bahasa Indonesia
- Konfigurasi styling (warna, font, border)
- Label dan template
- Batasan kinerja dan validasi

**Penggunaan**:
```php
// Mendapatkan nama bulan
$monthName = ExcelConstants::getMonthName(6); // "JUNI"

// Mendapatkan kolom bulanan
$columns = ExcelConstants::getMonthlyColumns();

// Mendapatkan konfigurasi warna
$colors = ExcelConstants::getColors();
```

### 2. Column Manager (`app/Formatters/Helpers/ColumnManager.php`)

**Tujuan**: Mengelola operasi dan pemetaan kolom Excel

**Fitur**:
- Konversi antara indeks dan huruf kolom
- Operasi increment/decrement kolom
- Validasi kolom dan range
- Mendapatkan kolom berdasarkan tipe laporan

**Penggunaan**:
```php
// Increment kolom
$nextColumn = ColumnManager::incrementColumn('A'); // "B"

// Konversi indeks ke huruf
$letter = ColumnManager::columnIndexToLetter(27); // "AA"

// Mendapatkan kolom untuk bulan tertentu
$column = ColumnManager::getColumnForMonth(6); // Kolom untuk Juni

// Validasi range
$isValid = ColumnManager::isValidRange('A1:Z100');
```

### 3. Statistics Calculator (`app/Formatters/Calculators/StatisticsCalculator.php`)

**Tujuan**: Memusatkan logika perhitungan statistik

**Fitur**:
- Perhitungan total bulanan, triwulanan, dan tahunan
- Perhitungan persentase standar
- Statistik lanjutan (rata-rata, median, standar deviasi)
- Tingkat pertumbuhan

**Penggunaan**:
```php
// Hitung total bulanan
$monthlyTotal = StatisticsCalculator::calculateMonthTotal($data, 6);

// Hitung total triwulanan
$quarterlyTotal = StatisticsCalculator::calculateQuarterTotal($data, 2);

// Hitung persentase standar
$percentage = StatisticsCalculator::calculateStandardPercentage(80, 100);

// Hitung rata-rata
$average = StatisticsCalculator::calculateAverage([10, 20, 30, 40]);
```

### 4. Data Validator (`app/Formatters/Validators/ExcelDataValidator.php`)

**Tujuan**: Memvalidasi data sebelum export

**Fitur**:
- Validasi struktur data puskesmas
- Validasi parameter export
- Validasi tipe data dan range
- Sanitasi input

**Penggunaan**:
```php
$validator = new ExcelDataValidator();

// Validasi data puskesmas
$isValid = $validator->validatePuskesmasData($puskesmasData);

// Validasi parameter export
$isValid = $validator->validateExportParameters([
    'report_type' => 'monthly',
    'year' => 2024,
    'month' => 6
]);

// Validasi tahun
$isValid = $validator->validateYear(2024);
```

### 5. Style Builder (`app/Formatters/Builders/ExcelStyleBuilder.php`)

**Tujuan**: Membangun styling Excel secara terstruktur

**Fitur**:
- Fluent interface untuk styling
- Preset style untuk header, judul, footer
- Konfigurasi font, warna, border
- Auto-sizing dan formatting

**Penggunaan**:
```php
$styleBuilder = new ExcelStyleBuilder();

// Membuat style header
$headerStyle = $styleBuilder
    ->headerStyle()
    ->backgroundColor('E6E6FA')
    ->fontSize(12)
    ->bold()
    ->border('thin')
    ->build();

// Menerapkan style ke worksheet
$styleBuilder->applyToRange($worksheet, 'A1:Z1', $headerStyle);
```

### 6. Configuration (`config/excel.php`)

**Tujuan**: Konfigurasi terpusat untuk sistem Excel

**Fitur**:
- Template files dan paths
- Styling configuration
- Performance settings
- Validation rules
- Localization
- Error handling
- Security settings

**Penggunaan**:
```php
// Mendapatkan konfigurasi template
$templatePath = config('excel.templates.monthly');

// Mendapatkan konfigurasi styling
$colors = config('excel.styling.colors');

// Mendapatkan batasan kinerja
$maxRows = config('excel.performance.limits.max_rows_per_sheet');
```

### 7. Service Provider (`app/Providers/ExcelServiceProvider.php`)

**Tujuan**: Mendaftarkan semua komponen ke Laravel container

**Fitur**:
- Dependency injection setup
- Configuration publishing
- Custom validation rules
- Collection macros
- Event listeners

**Registrasi**:
```php
// Tambahkan ke config/app.php
'providers' => [
    // ...
    App\Providers\ExcelServiceProvider::class,
],
```

### 8. Facade (`app/Facades/Excel.php`)

**Tujuan**: Interface yang mudah untuk mengakses Excel export

**Fitur**:
- Shortcut methods untuk berbagai tipe laporan
- Download dan save functionality
- Validation helpers
- Configuration access

**Penggunaan**:
```php
use App\Facades\Excel;

// Export tahunan
$spreadsheet = Excel::yearly($data, 2024);

// Export bulanan
$spreadsheet = Excel::monthly($data, 2024, 6);

// Download langsung
return Excel::download('monthly', $data, 2024, 6);

// Simpan ke storage
$filePath = Excel::save('quarterly', $data, 2024, 2);

// Validasi data
$isValid = Excel::validate($data, 'yearly', 2024);
```

### 9. Artisan Command (`app/Console/Commands/ExcelExportCommand.php`)

**Tujuan**: Command line interface untuk Excel export

**Fitur**:
- Generate export dari command line
- Sample data untuk testing
- Validation only mode
- Performance metrics
- File information

**Penggunaan**:
```bash
# Export tahunan
php artisan excel:export yearly 2024

# Export bulanan
php artisan excel:export monthly 2024 --month=6

# Export triwulanan
php artisan excel:export quarterly 2024 --quarter=2

# Validasi saja
php artisan excel:export yearly 2024 --validate-only

# Menggunakan sample data
php artisan excel:export monthly 2024 --month=6 --sample
```

### 10. Unit Tests (`tests/Unit/ExcelExportTest.php`)

**Tujuan**: Memastikan semua komponen berfungsi dengan baik

**Coverage**:
- ExcelExportFormatter methods
- ColumnManager operations
- StatisticsCalculator calculations
- ExcelDataValidator validations
- ExcelStyleBuilder styling
- Facade functionality

**Menjalankan Tests**:
```bash
# Jalankan semua tests
php artisan test

# Jalankan test Excel saja
php artisan test tests/Unit/ExcelExportTest.php

# Dengan coverage
php artisan test --coverage
```

## Migrasi dari Kode Lama

### 1. Update ExcelExportFormatter

Kode lama:
```php
$formatter = new ExcelExportFormatter();
$spreadsheet = $formatter->formatAllExcel($data, 2024);
```

Kode baru:
```php
// Menggunakan dependency injection
$formatter = app(ExcelExportFormatter::class);
$spreadsheet = $formatter->formatAllExcel($data, 2024);

// Atau menggunakan facade
$spreadsheet = Excel::yearly($data, 2024);
```

### 2. Update Controller

Kode lama:
```php
public function exportExcel(Request $request)
{
    $formatter = new ExcelExportFormatter();
    $spreadsheet = $formatter->formatAllExcel($data, $year);
    
    // Manual file handling...
}
```

Kode baru:
```php
public function exportExcel(Request $request)
{
    // Validasi data
    if (!Excel::validate($data, $type, $year, $month, $quarter)) {
        return response()->json(['error' => 'Invalid data'], 400);
    }
    
    // Download langsung
    return Excel::download($type, $data, $year, $month, $quarter);
}
```

### 3. Update Constants Usage

Kode lama:
```php
$monthColumns = [
    1 => 'D', 2 => 'I', 3 => 'N', // hardcoded
    // ...
];
```

Kode baru:
```php
$monthColumns = ExcelConstants::getMonthlyColumns();
```

## Keuntungan Refactoring

### 1. **Maintainability**
- Kode terorganisir dalam kelas-kelas yang fokus
- Separation of concerns yang jelas
- Mudah untuk menemukan dan mengubah kode

### 2. **Testability**
- Setiap komponen dapat ditest secara terpisah
- Dependency injection memudahkan mocking
- Unit tests yang komprehensif

### 3. **Extensibility**
- Mudah menambah tipe laporan baru
- Plugin architecture untuk styling
- Configuration-driven behavior

### 4. **Performance**
- Caching configuration
- Memory management yang lebih baik
- Performance monitoring

### 5. **Developer Experience**
- Facade untuk kemudahan penggunaan
- Artisan command untuk testing
- Comprehensive documentation

### 6. **Error Handling**
- Validation di setiap layer
- Structured error messages
- Logging dan monitoring

## Best Practices

### 1. **Dependency Injection**
```php
// Good
class ReportController extends Controller
{
    public function __construct(
        private ExcelExportFormatter $formatter,
        private ExcelDataValidator $validator
    ) {}
}

// Avoid
class ReportController extends Controller
{
    public function export()
    {
        $formatter = new ExcelExportFormatter(); // Hard dependency
    }
}
```

### 2. **Configuration Usage**
```php
// Good
$maxRows = config('excel.performance.limits.max_rows_per_sheet');

// Avoid
$maxRows = 1000; // Magic number
```

### 3. **Error Handling**
```php
// Good
try {
    $spreadsheet = Excel::yearly($data, $year);
    return Excel::download('yearly', $data, $year);
} catch (ValidationException $e) {
    return response()->json(['error' => $e->getMessage()], 400);
} catch (Exception $e) {
    Log::error('Excel export failed', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'Export failed'], 500);
}
```

### 4. **Testing**
```php
// Good
public function test_can_create_monthly_export()
{
    $data = $this->generateSampleData();
    $spreadsheet = Excel::monthly($data, 2024, 6);
    
    $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
    $this->assertStringContainsString('JUNI', $spreadsheet->getActiveSheet()->getCell('A1')->getValue());
}
```

## Deployment Checklist

### 1. **Registrasi Service Provider**
```php
// config/app.php
'providers' => [
    // ...
    App\Providers\ExcelServiceProvider::class,
],
```

### 2. **Publish Configuration**
```bash
php artisan vendor:publish --tag=excel-config
```

### 3. **Update Environment Variables**
```env
EXCEL_CACHE_ENABLED=true
EXCEL_CACHE_TTL=3600
EXCEL_MAX_ROWS=1000
EXCEL_MAX_FILE_SIZE=50
EXCEL_DEBUG=false
```

### 4. **Run Tests**
```bash
php artisan test tests/Unit/ExcelExportTest.php
```

### 5. **Clear Cache**
```bash
php artisan config:cache
php artisan route:cache
```

## Troubleshooting

### 1. **Memory Issues**
- Periksa `excel.performance.limits.memory_limit`
- Enable caching dengan `excel.performance.cache.enabled`
- Gunakan `read_only` mode untuk file besar

### 2. **Performance Issues**
- Monitor dengan `excel.development.profile_performance`
- Adjust `max_rows_per_sheet` dan `max_columns_per_sheet`
- Enable compression dengan `excel.export.compress_output`

### 3. **Validation Errors**
- Check logs untuk detail error
- Gunakan `--validate-only` flag di command
- Periksa data structure dengan validator

### 4. **Template Issues**
- Pastikan template files ada di `storage/app/templates`
- Check permissions pada directory template
- Validate template dengan `excel.security.validate_templates`

## Future Enhancements

### 1. **Queue Support**
- Async export untuk file besar
- Progress tracking
- Email notification

### 2. **API Integration**
- RESTful API untuk export
- Webhook notifications
- Rate limiting

### 3. **Advanced Features**
- Chart generation
- Conditional formatting
- Data visualization

### 4. **Multi-language Support**
- Dynamic localization
- RTL support
- Custom date formats

Dengan refactoring ini, sistem Excel Export menjadi lebih robust, maintainable, dan siap untuk pengembangan future. Semua komponen telah ditest dan didokumentasikan dengan baik.