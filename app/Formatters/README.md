# Excel Export Formatters

Formatter untuk export laporan Excel berdasarkan template `all.xlsx`, `monthly.xlsx`, `quarterly.xlsx`, dan `puskesmas.xlsx`.

## Struktur Formatter

### 1. BaseAdminFormatter
**File:** `BaseAdminFormatter.php`

Base class yang menyediakan fungsi-fungsi umum untuk semua formatter:
- Styling Excel (header, border, font)
- Validasi input
- Helper functions (format angka, persentase, dll)
- Logging aktivitas

### 2. ExcelExportFormatter
**File:** `ExcelExportFormatter.php`

Formatter utama yang mengimplementasikan struktur Excel sesuai spesifikasi:
- Setup header bertingkat (baris 0-6)
- Mapping kolom untuk data bulanan dan triwulan
- Perhitungan data agregat
- Styling dan formatting

### 3. AdminAllFormatter
**File:** `AdminAllFormatter.php`

**Tujuan:** Rekap Tahunan Komprehensif (`all.xlsx`)

**Fitur:**
- Data bulanan (Januari-Desember)
- Data triwulan (I-IV)
- Total tahunan
- Persentase capaian

**Penggunaan:**
```php
$formatter = new AdminAllFormatter($statisticsService);
$spreadsheet = $formatter->format('ht', 2024);
```

### 4. AdminMonthlyFormatter
**File:** `AdminMonthlyFormatter.php`

**Tujuan:** Laporan Bulanan (`monthly.xlsx`)

**Fitur:**
- Detail data per bulan
- Klasifikasi S (Standar) dan TS (Tidak Standar)
- Persentase standar per bulan
- Total tahunan dan % capaian

**Penggunaan:**
```php
$formatter = new AdminMonthlyFormatter($statisticsService);
$spreadsheet = $formatter->format('dm', 2024);
```

### 5. AdminQuarterlyFormatter
**File:** `AdminQuarterlyFormatter.php`

**Tujuan:** Laporan Triwulan (`quarterly.xlsx`)

**Fitur:**
- Ringkasan per triwulan
- Data S/TS per triwulan
- Total tahunan
- Analisis performa per triwulan

**Penggunaan:**
```php
$formatter = new AdminQuarterlyFormatter($statisticsService);
$spreadsheet = $formatter->format('both', 2024);
```

### 6. PuskesmasFormatter
**File:** `PuskesmasFormatter.php`

**Tujuan:** Template Per Puskesmas (`puskesmas.xlsx`)

**Fitur:**
- Template individual untuk setiap puskesmas
- Data bulanan dalam format tabel sederhana
- Informasi capaian dan status
- Template kosong untuk puskesmas baru

**Penggunaan:**
```php
// Untuk puskesmas spesifik
$formatter = new PuskesmasFormatter($statisticsService);
$spreadsheet = $formatter->format('ht', 2024, ['puskesmas_id' => 1]);

// Untuk template kosong
$spreadsheet = $formatter->formatTemplate('ht', 2024);
```

## Parameter Input

### Disease Type (Jenis Penyakit)
- `'ht'` - Hipertensi
- `'dm'` - Diabetes Melitus
- `'both'` - Hipertensi dan Diabetes Melitus

### Year (Tahun)
- Integer tahun laporan (2020 - tahun sekarang + 1)
- Default: tahun sekarang

### Additional Data
- Array data tambahan sesuai kebutuhan formatter
- Untuk PuskesmasFormatter: `['puskesmas_id' => int]`

## Struktur Excel Output

### Header Structure (Semua File)
```
Baris 0: Judul (Pelayanan Kesehatan Pada Penderita [Jenis Penyakit])
Baris 2: Header tingkat 1 (NO, NAMA PUSKESMAS, SASARAN, ...)
Baris 3: Header tingkat 2 (Periode: TRIWULAN/BULAN)
Baris 4: Header tingkat 3 (Bulan: JANUARI, FEBRUARI, ...)
Baris 5: Header tingkat 4 (Kategori: L, P, TOTAL, TS, %S)
Baris 7+: Data puskesmas
```

### Kolom Mapping
- **A:** NO
- **B:** NAMA PUSKESMAS
- **C:** SASARAN
- **D-BK:** Data bulanan (5 kolom per bulan: L, P, TOTAL, TS, %S)
- **BL-CE:** Data triwulan (5 kolom per triwulan)
- **CF-CJ:** Total tahunan

## Data Structure

### Puskesmas Data Format
```php
[
    'id' => int,
    'nama_puskesmas' => string,
    'sasaran' => int,
    'monthly_data' => [
        1 => ['male' => int, 'female' => int, 'standard' => int, 'non_standard' => int],
        2 => [...],
        // ... untuk bulan 1-12
    ]
]
```

### Monthly Statistics Format
```php
[
    'male_count' => int,
    'female_count' => int,
    'standard_service_count' => int,
    'non_standard_service_count' => int
]
```

## Styling Features

### Colors
- **Header Background:** `#E6E6FA` (Light Purple)
- **Total Row Background:** `#FFFF99` (Light Yellow)
- **Achievement Status:**
  - Sangat Baik (≥100%): `#008000` (Green)
  - Baik (80-99%): `#0066CC` (Blue)
  - Cukup (60-79%): `#FF8C00` (Orange)
  - Kurang (<60%): `#FF0000` (Red)

### Fonts
- **Title:** Bold, 14pt
- **Subtitle:** Bold, 12pt
- **Header:** Bold, 10pt
- **Data:** 9pt

### Borders
- Thin borders untuk semua data
- Auto-size columns
- Center alignment untuk header

## Error Handling

### Validation
- Disease type validation
- Year range validation
- Puskesmas ID validation (untuk PuskesmasFormatter)

### Logging
- Success operations logged dengan level INFO
- Errors logged dengan level ERROR
- Warnings untuk non-critical issues

### Fallbacks
- Empty data arrays jika service error
- Default values untuk missing data
- Safe cell operations dengan try-catch

## Integration dengan Services

### StatisticsService Methods Required
```php
// Get all puskesmas
getAllPuskesmas(): array

// Get puskesmas by ID
getPuskesmasById(int $id): ?array

// Get monthly statistics
getMonthlyStatistics(int $puskesmasId, int $year, int $month, string $diseaseType): array
getDetailedMonthlyStatistics(int $puskesmasId, int $year, int $month, string $diseaseType): array

// Get yearly target
getYearlyTarget(int $puskesmasId, int $year, string $diseaseType): array
```

## File Naming Convention

### AdminAllFormatter
`Laporan_Tahunan_{DiseaseLabel}_{Year}.xlsx`

### AdminMonthlyFormatter
`Laporan_Bulanan_{DiseaseLabel}_{Year}.xlsx`

### AdminQuarterlyFormatter
`Laporan_Triwulan_{DiseaseLabel}_{Year}.xlsx`

### PuskesmasFormatter
- Dengan data: `Laporan_{PuskesmasName}_{DiseaseLabel}_{Year}.xlsx`
- Template: `Template_Puskesmas_{DiseaseLabel}_{Year}.xlsx`

## Usage Examples

### Basic Usage
```php
use App\Formatters\AdminAllFormatter;
use App\Services\StatisticsService;

$statisticsService = app(StatisticsService::class);
$formatter = new AdminAllFormatter($statisticsService);

// Generate laporan tahunan hipertensi 2024
$spreadsheet = $formatter->format('ht', 2024);

// Save to file
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$filename = $formatter->getFilename('ht', 2024);
$writer->save(storage_path('app/exports/' . $filename));
```

### Export Service Integration
```php
use App\Formatters\AdminMonthlyFormatter;

class ExportController
{
    public function exportMonthly(Request $request)
    {
        $diseaseType = $request->get('disease_type', 'ht');
        $year = $request->get('year', date('Y'));
        
        $formatter = new AdminMonthlyFormatter(app(StatisticsService::class));
        $spreadsheet = $formatter->format($diseaseType, $year);
        
        $filename = $formatter->getFilename($diseaseType, $year);
        
        return response()->streamDownload(function() use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename);
    }
}
```

## Testing

### Unit Tests
```php
use Tests\TestCase;
use App\Formatters\AdminAllFormatter;

class AdminAllFormatterTest extends TestCase
{
    public function test_format_generates_valid_spreadsheet()
    {
        $formatter = new AdminAllFormatter($this->mockStatisticsService());
        $spreadsheet = $formatter->format('ht', 2024);
        
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $this->assertEquals('Laporan', $spreadsheet->getActiveSheet()->getTitle());
    }
}
```

## Performance Considerations

### Memory Usage
- Large datasets dapat menggunakan memory tinggi
- Gunakan streaming untuk file besar
- Clear objects setelah selesai

### Optimization
- Batch database queries
- Cache frequently accessed data
- Use appropriate data structures

## Maintenance

### Adding New Formatters
1. Extend `BaseAdminFormatter` atau `ExcelExportFormatter`
2. Implement required abstract methods
3. Add specific formatting logic
4. Update documentation

### Modifying Existing Formatters
1. Test dengan data sample
2. Verify Excel output structure
3. Update unit tests
4. Document changes

## Dependencies

- `phpoffice/phpspreadsheet` - Excel manipulation
- `illuminate/support` - Laravel helpers
- `App\Services\StatisticsService` - Data source

## License

Sesuai dengan lisensi proyek Akudihatinya.