# PDF Formatter Implementation

## Overview
Implementasi formatter PDF khusus untuk template `all_quarters_recap_pdf.blade.php` yang digunakan untuk menggenerate PDF melalui endpoint `/api/statistics/admin/export`.

## Files Created/Modified

### 1. AllQuartersRecapPdfFormatter.php
**Location:** `app/Formatters/AllQuartersRecapPdfFormatter.php`

**Purpose:** Formatter khusus untuk memformat data statistik kesehatan menjadi struktur yang sesuai dengan template `all_quarters_recap_pdf.blade.php`.

**Key Features:**
- Format data untuk semua kuartal dalam satu tahun
- Support untuk multiple disease types (HT, DM, atau keduanya)
- Kalkulasi otomatis untuk persentase pencapaian
- Struktur data yang konsisten dengan template Blade

**Main Methods:**
- `formatAllQuartersRecapData($year, $diseaseType)` - Method utama untuk format data
- `formatQuarterData($quarterNum, $quarterInfo, $year, $diseaseType)` - Format data per kuartal
- `formatPuskesmasData($puskesmas, $quarterNum, $year, $diseaseType)` - Format data per puskesmas

### 2. PdfService.php (Modified)
**Location:** `app/Services/PdfService.php`

**Changes Made:**
- Added dependency injection for `AllQuartersRecapPdfFormatter`
- Updated `generateStatisticsPdfFromTemplate()` method to use new formatter
- Updated `generateQuarterlyRecapPdf()` method to use new formatter
- Simplified template selection logic (always use `all_quarters_recap_pdf`)

## API Endpoint Usage

> **Note:** Untuk dokumentasi lengkap API endpoints, lihat [API_DOCUMENTATION.md](./API_DOCUMENTATION.md#statistics-endpoints)

### Primary Endpoint
```
GET /api/statistics/export
```

### Key Parameters for PDF Generation
- `year` (required) - Tahun laporan
- `disease_type` (required) - Jenis penyakit: `ht`, `dm`, atau `all`
- `format` (required) - Format output: `pdf`
- `table_type` (optional) - Jenis tabel: `all`, `quarterly`, `monthly`, `puskesmas`

### Quick Example
```bash
# Export PDF menggunakan AllQuartersRecapPdfFormatter
http://localhost:8000/api/statistics/export?year=2024&disease_type=all&format=pdf
```

## Data Structure

Formatter menghasilkan struktur data sebagai berikut:

```php
[
    'quarters_data' => [
        [
            'quarter' => 'I',
            'months' => ['Januari', 'Februari', 'Maret'],
            'year' => 2024,
            'disease_type' => 'ht',
            'disease_label' => 'HIPERTENSI',
            'puskesmas_data' => [
                [
                    'name' => 'Puskesmas A',
                    'target' => 1000,
                    'monthly' => [...],
                    'quarterly' => [...],
                    'total_patients' => 850,
                    'achievement_percentage' => 85.0
                ]
            ],
            'grand_total' => [
                'target' => 5000,
                'monthly' => [...],
                'quarterly' => [...],
                'total_patients' => 4250,
                'achievement_percentage' => 85.0
            ]
        ]
    ],
    'year' => 2024,
    'disease_type' => 'all',
    'generated_at' => '25/12/2024 10:30:00'
]
```

## Template Compatibility

Formatter ini dirancang khusus untuk template `resources/pdf/all_quarters_recap_pdf.blade.php` dengan struktur data yang sesuai:

- `$quarter_data['puskesmas_data']` - Data per puskesmas
- `$quarter_data['grand_total']` - Total keseluruhan
- `$puskesmas['target']` - Target puskesmas
- `$puskesmas['monthly']` - Data bulanan
- `$puskesmas['quarterly']` - Data kuartalan

## Error Handling

- Validasi parameter input
- Logging error untuk debugging
- Fallback ke data kosong jika service gagal
- Exception handling dengan pesan error yang informatif

## Testing

Untuk testing implementasi:

1. Pastikan server Laravel berjalan
2. Akses endpoint dengan parameter yang valid
3. Periksa log aplikasi untuk error
4. Verifikasi struktur PDF yang dihasilkan

## Dependencies

- `StatisticsAdminService` - Untuk mengambil data statistik
- `Barryvdh\DomPDF\Facade\Pdf` - Untuk generate PDF
- `YearlyTarget` model - Untuk data target tahunan
- Template Blade `all_quarters_recap_pdf.blade.php`

## Notes

- Formatter mendukung multiple disease types dalam satu request
- Data diformat per kuartal untuk konsistensi dengan template
- Kalkulasi persentase dilakukan otomatis
- Memory limit dan time limit sudah diset untuk handle data besar
- Paper size diset ke A4 landscape untuk template yang ada