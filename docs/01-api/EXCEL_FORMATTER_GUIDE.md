# Excel Formatter System - Panduan Lengkap

Sistem Excel Formatter untuk export laporan kesehatan dalam format Excel sesuai dengan spesifikasi `all.xlsx`, `monthly.xlsx`, `quarterly.xlsx`, dan `puskesmas.xlsx`.

## 📋 Daftar Isi

1. [Overview](#overview)
2. [Struktur File](#struktur-file)
3. [Instalasi dan Konfigurasi](#instalasi-dan-konfigurasi)
4. [Penggunaan](#penggunaan)
5. [API Endpoints](#api-endpoints)
6. [Contoh Implementasi](#contoh-implementasi)
7. [Troubleshooting](#troubleshooting)
8. [Maintenance](#maintenance)

## 🎯 Overview

Sistem ini menyediakan formatter untuk menghasilkan laporan Excel dengan format yang konsisten untuk:

- **all.xlsx**: Laporan tahunan komprehensif dengan data bulanan, triwulan, dan total tahunan
- **monthly.xlsx**: Laporan bulanan dengan klasifikasi S (Standar) dan TS (Tidak Standar)
- **quarterly.xlsx**: Laporan triwulan dengan agregasi data per 3 bulan
- **puskesmas.xlsx**: Template laporan individual per puskesmas

### Fitur Utama

✅ **Format Excel Konsisten**: Mengikuti template yang telah ditentukan  
✅ **Data Validation**: Validasi input dan data sebelum export  
✅ **Styling Otomatis**: Penerapan styling Excel secara otomatis  
✅ **Multiple Disease Types**: Support untuk Hipertensi (HT) dan Diabetes Melitus (DM)  
✅ **Batch Export**: Export multiple laporan sekaligus  
✅ **Stream Download**: Download langsung tanpa menyimpan file  
✅ **File Management**: Cleanup otomatis file lama  
✅ **Error Handling**: Penanganan error yang komprehensif  
✅ **Logging**: Logging aktivitas untuk monitoring  

## 📁 Struktur File

```
app/
├── Formatters/
│   ├── BaseAdminFormatter.php          # Base class untuk semua formatter
│   ├── ExcelExportFormatter.php        # Core formatter dengan PhpSpreadsheet
│   ├── AdminAllFormatter.php           # Formatter untuk all.xlsx
│   ├── AdminMonthlyFormatter.php       # Formatter untuk monthly.xlsx
│   ├── AdminQuarterlyFormatter.php     # Formatter untuk quarterly.xlsx
│   ├── PuskesmasFormatter.php          # Formatter untuk puskesmas.xlsx
│   └── README.md                       # Dokumentasi formatter
├── Services/
│   └── ExcelExportService.php          # Service layer untuk export
├── Http/Controllers/
│   └── ExcelExportController.php       # Controller untuk API endpoints
routes/
└── excel-export.php                    # Routes untuk Excel export
```

## ⚙️ Instalasi dan Konfigurasi

### 1. Dependencies

Pastikan dependencies berikut sudah terinstall:

```bash
composer require phpoffice/phpspreadsheet
```

### 2. Service Provider Registration

Formatter sudah terdaftar di `AppServiceProvider.php`:

```php
// app/Providers/AppServiceProvider.php
public function register()
{
    // ... existing code ...
    
    $this->app->singleton(AdminAllFormatter::class, function ($app) {
        return new AdminAllFormatter($app->make(StatisticsService::class));
    });
    
    $this->app->singleton(AdminMonthlyFormatter::class, function ($app) {
        return new AdminMonthlyFormatter($app->make(StatisticsService::class));
    });
    
    $this->app->singleton(AdminQuarterlyFormatter::class, function ($app) {
        return new AdminQuarterlyFormatter($app->make(StatisticsService::class));
    });
    
    $this->app->singleton(PuskesmasFormatter::class, function ($app) {
        return new PuskesmasFormatter($app->make(StatisticsService::class));
    });
}
```

### 3. Routes Registration

Tambahkan routes di `routes/api.php`:

```php
// routes/api.php
require __DIR__.'/excel-export.php';
```

### 4. Storage Configuration

Pastikan storage directory dapat diakses:

```bash
php artisan storage:link
```

## 🚀 Penggunaan

### Basic Usage

```php
use App\Services\Export\StatisticsExportService;

// Inject service
$excelService = app(StatisticsExportService::class);

// Export all.xlsx
$result = $excelService->exportAll('ht', 2024);

// Export monthly.xlsx
$result = $excelService->exportMonthly('dm', 2024);

// Export quarterly.xlsx
$result = $excelService->exportQuarterly('ht', 2024);

// Export puskesmas.xlsx
$result = $excelService->exportPuskesmas(1, 'ht', 2024);
```

### Advanced Usage

```php
// Batch export semua laporan
$result = $excelService->exportBatch('ht', 2024);

// Stream download langsung
return $excelService->streamDownload('ht', 2024, 'all');

// Cleanup file lama
$result = $excelService->cleanupOldFiles(30); // 30 hari
```

## 🌐 API Endpoints

### Authentication

Semua endpoints memerlukan autentikasi menggunakan Sanctum:

```
Authorization: Bearer {token}
```

### Export Endpoints

#### 1. Get Export Info
```http
GET /api/excel-export/info
```

**Response:**
```json
{
  "success": true,
  "data": {
    "export_types": {
      "all": {
        "name": "Laporan Tahunan Komprehensif",
        "description": "Rekap data bulanan, triwulan, dan total tahunan"
      }
    },
    "disease_types": {
      "ht": "Hipertensi",
      "dm": "Diabetes Melitus"
    },
    "available_years": [2020, 2021, 2022, 2023, 2024, 2025]
  }
}
```

#### 2. Export All Report
```http
POST /api/excel-export/all
Content-Type: application/json

{
  "disease_type": "ht",
  "year": 2024,
  "download": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Export berhasil",
  "data": {
    "filename": "laporan_hipertensi_2024_all.xlsx",
    "file_path": "exports/excel/2024/01/laporan_hipertensi_2024_all.xlsx",
    "download_url": "/storage/exports/excel/2024/01/laporan_hipertensi_2024_all.xlsx"
  }
}
```

#### 3. Export Monthly Report
```http
POST /api/excel-export/monthly
Content-Type: application/json

{
  "disease_type": "dm",
  "year": 2024
}
```

#### 4. Export Quarterly Report
```http
POST /api/excel-export/quarterly
Content-Type: application/json

{
  "disease_type": "ht",
  "year": 2024
}
```

#### 5. Export Puskesmas Report
```http
POST /api/excel-export/puskesmas
Content-Type: application/json

{
  "puskesmas_id": 1,
  "disease_type": "ht",
  "year": 2024
}
```

#### 6. Batch Export
```http
POST /api/excel-export/batch
Content-Type: application/json

{
  "disease_type": "ht",
  "year": 2024
}
```

**Response:**
```json
{
  "success": true,
  "message": "Batch export berhasil",
  "data": {
    "results": {
      "all": { "success": true, "filename": "..." },
      "monthly": { "success": true, "filename": "..." },
      "quarterly": { "success": true, "filename": "..." },
      "template": { "success": true, "filename": "..." }
    },
    "summary": {
      "total_files": 4,
      "successful": 4,
      "failed": 0
    }
  }
}
```

### Direct Download Endpoints

#### 1. Download All Report
```http
GET /api/excel-download/all/{diseaseType}/{year}
```

Contoh:
```http
GET /api/excel-download/all/ht/2024
```

#### 2. Download Monthly Report
```http
GET /api/excel-download/monthly/dm/2024
```

#### 3. Download Quarterly Report
```http
GET /api/excel-download/quarterly/ht/2024
```

#### 4. Download Puskesmas Report
```http
GET /api/excel-download/puskesmas/{puskesmasId}/ht/2024
```

### Management Endpoints

#### 1. Download Exported File
```http
GET /api/excel-export/download?file_path=exports/excel/2024/01/filename.xlsx&filename=custom_name.xlsx
```

#### 2. Cleanup Old Files (Admin Only)
```http
DELETE /api/excel-export/cleanup
Content-Type: application/json

{
  "days_old": 30
}
```

## 💡 Contoh Implementasi

### Frontend JavaScript

```javascript
// Export dan download langsung
async function exportAndDownload(reportType, diseaseType, year) {
  try {
    const response = await fetch(`/api/excel-export/${reportType}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        disease_type: diseaseType,
        year: year,
        download: true
      })
    });
    
    if (response.ok) {
      // File akan otomatis ter-download
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `laporan_${diseaseType}_${year}_${reportType}.xlsx`;
      a.click();
    }
  } catch (error) {
    console.error('Export failed:', error);
  }
}

// Export dan simpan ke server
async function exportToServer(reportType, diseaseType, year) {
  try {
    const response = await fetch(`/api/excel-export/${reportType}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        disease_type: diseaseType,
        year: year,
        download: false
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('File saved:', result.data.download_url);
      // Tampilkan link download atau simpan info file
    }
  } catch (error) {
    console.error('Export failed:', error);
  }
}

// Batch export
async function batchExport(diseaseType, year) {
  try {
    const response = await fetch('/api/excel-export/batch', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        disease_type: diseaseType,
        year: year
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('Batch export completed:', result.data.summary);
      // Tampilkan hasil batch export
    }
  } catch (error) {
    console.error('Batch export failed:', error);
  }
}
```

### Laravel Blade Template

```blade
{{-- resources/views/admin/reports/export.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Export Laporan Excel</h4>
                </div>
                <div class="card-body">
                    <form id="exportForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Jenis Penyakit</label>
                                    <select name="disease_type" class="form-control">
                                        <option value="ht">Hipertensi</option>
                                        <option value="dm">Diabetes Melitus</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Tahun</label>
                                    <select name="year" class="form-control">
                                        @for($year = 2020; $year <= date('Y') + 1; $year++)
                                            <option value="{{ $year }}" {{ $year == date('Y') ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Jenis Laporan</label>
                                    <select name="report_type" class="form-control">
                                        <option value="all">Laporan Tahunan Komprehensif</option>
                                        <option value="monthly">Laporan Bulanan</option>
                                        <option value="quarterly">Laporan Triwulan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-primary" onclick="exportReport()">Export & Download</button>
                                <button type="button" class="btn btn-secondary" onclick="exportToServer()">Export ke Server</button>
                                <button type="button" class="btn btn-info" onclick="batchExport()">Batch Export</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportReport() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Direct download
    window.open(`/api/excel-download/${data.report_type}/${data.disease_type}/${data.year}`);
}

function exportToServer() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    fetch(`/api/excel-export/${data.report_type}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Authorization': 'Bearer {{ auth()->user()->createToken("export")->plainTextToken }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Export berhasil! File tersimpan di: ' + result.data.download_url);
        } else {
            alert('Export gagal: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat export');
    });
}

function batchExport() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    fetch('/api/excel-export/batch', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Authorization': 'Bearer {{ auth()->user()->createToken("export")->plainTextToken }}'
        },
        body: JSON.stringify({
            disease_type: data.disease_type,
            year: data.year
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert(`Batch export berhasil! ${result.data.summary.successful} dari ${result.data.summary.total_files} file berhasil di-export.`);
        } else {
            alert('Batch export gagal: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat batch export');
    });
}
</script>
@endsection
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Memory Limit Error
```
Fatal error: Allowed memory size exhausted
```

**Solution:**
```php
// Tambahkan di awal method format()
ini_set('memory_limit', '512M');
```

#### 2. File Permission Error
```
fopen(): failed to open stream: Permission denied
```

**Solution:**
```bash
sudo chmod -R 775 storage/
sudo chown -R www-data:www-data storage/
```

#### 3. PhpSpreadsheet Not Found
```
Class 'PhpOffice\PhpSpreadsheet\Spreadsheet' not found
```

**Solution:**
```bash
composer require phpoffice/phpspreadsheet
composer dump-autoload
```

#### 4. Service Not Registered
```
Target class [App\Formatters\AdminAllFormatter] does not exist.
```

**Solution:**
Pastikan formatter terdaftar di `AppServiceProvider.php` dan jalankan:
```bash
php artisan config:clear
php artisan cache:clear
```

### Debug Mode

Untuk debugging, aktifkan logging di formatter:

```php
// Tambahkan di method format()
Log::debug('ExcelFormatter: Starting format', [
    'disease_type' => $diseaseType,
    'year' => $year,
    'memory_usage' => memory_get_usage(true)
]);
```

### Performance Optimization

1. **Memory Management:**
```php
// Gunakan di akhir method format()
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
gc_collect_cycles();
```

2. **Caching:**
```php
// Cache data yang sering digunakan
$cacheKey = "statistics_{$diseaseType}_{$year}";
$data = Cache::remember($cacheKey, 3600, function() use ($diseaseType, $year) {
    return $this->statisticsService->getYearlyData($diseaseType, $year);
});
```

## 🔄 Maintenance

### Regular Tasks

#### 1. Cleanup Old Files
```bash
# Via artisan command (buat command baru)
php artisan excel:cleanup --days=30

# Via API
curl -X DELETE "/api/excel-export/cleanup" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"days_old": 30}'
```

#### 2. Monitor Storage Usage
```bash
# Check storage usage
du -sh storage/app/exports/

# Check available space
df -h
```

#### 3. Log Monitoring
```bash
# Check export logs
tail -f storage/logs/laravel.log | grep "ExcelExport"

# Check error logs
grep "ERROR" storage/logs/laravel.log | grep "Excel"
```

### Updates dan Versioning

#### 1. Update Formatter
Ketika mengupdate formatter, pastikan:
- Backup file lama
- Test dengan data sample
- Update dokumentasi
- Inform user tentang perubahan

#### 2. Database Migration
Jika ada perubahan struktur data:
```bash
php artisan make:migration update_statistics_table_for_excel_export
```

#### 3. Version Control
Gunakan semantic versioning untuk formatter:
- Major: Breaking changes
- Minor: New features
- Patch: Bug fixes

### Backup Strategy

1. **Code Backup:**
```bash
# Backup formatter files
tar -czf formatters_backup_$(date +%Y%m%d).tar.gz app/Formatters/
```

2. **Export Files Backup:**
```bash
# Backup export files
tar -czf exports_backup_$(date +%Y%m%d).tar.gz storage/app/exports/
```

3. **Database Backup:**
```bash
# Backup statistics data
mysqldump -u username -p database_name statistics > statistics_backup_$(date +%Y%m%d).sql
```

---

## 📞 Support

Jika mengalami masalah atau butuh bantuan:

1. **Check Logs:** Periksa `storage/logs/laravel.log`
2. **Debug Mode:** Aktifkan `APP_DEBUG=true` untuk development
3. **Documentation:** Baca dokumentasi di `app/Formatters/README.md`
4. **Issue Tracking:** Gunakan sistem issue tracking untuk melaporkan bug

---

**Created by:** Excel Formatter System  
**Version:** 1.0.0  
**Last Updated:** {{ date('Y-m-d') }}