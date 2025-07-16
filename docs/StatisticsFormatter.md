# Statistics Formatter Documentation

## Overview

StatisticsFormatter adalah sebuah formatter yang mengintegrasikan semua service statistik dalam aplikasi untuk menyediakan data yang konsisten dan teroptimasi untuk dashboard. Formatter ini menggabungkan fungsionalitas dari berbagai service statistik untuk memberikan pengalaman yang seamless.

## Services yang Diintegrasikan

### 1. StatisticsService
- Service utama untuk kalkulasi statistik HT dan DM
- Menyediakan data dasar dan summary statistics

### 2. StatisticsDataService
- Mengelola data statistik dengan konsistensi tinggi
- Menyediakan ranking dan pagination

### 3. StatisticsAdminService
- Menangani statistik khusus untuk admin dashboard
- Filtering berdasarkan tahun, bulan, dan jenis penyakit

### 4. OptimizedStatisticsService
- Optimasi performa dengan caching
- Eliminasi N+1 queries
- Intelligent caching dengan tags

### 5. RealTimeStatisticsService
- Pemrosesan data real-time
- Update cache otomatis
- Recalculation untuk data terbaru

### 6. DiseaseStatisticsService
- Kalkulasi spesifik untuk penyakit tertentu
- Penanganan target tahunan

## Fitur Utama

### 1. Dashboard Statistics
```php
$formatter->formatDashboardStatistics($year, $month, $diseaseType)
```
- Menggabungkan data dari semua service
- Format konsisten untuk dashboard
- Support filtering berdasarkan tahun, bulan, dan jenis penyakit

### 2. Admin Statistics dengan Optimasi
```php
$formatter->formatAdminStatistics($year, $month, $diseaseType, $page, $perPage)
```
- Menggunakan OptimizedStatisticsService untuk performa terbaik
- Pagination terintegrasi
- Caching otomatis

### 3. Statistik Teroptimasi
```php
$formatter->formatOptimizedStatistics($puskesmasIds, $year, $month, $diseaseType)
```
- Menggunakan caching dengan tags
- Bulk operations untuk multiple Puskesmas
- Memory efficient

### 4. Statistik Spesifik Penyakit
```php
$formatter->formatHtStatistics($year, $month)
$formatter->formatDmStatistics($year, $month)
```
- Data spesifik untuk HT dan DM
- Breakdown detail per bulan
- Achievement percentage

### 5. Data Bulanan
```php
$formatter->formatMonthlyData($year, $diseaseType)
```
- Trend data bulanan
- Perbandingan target vs achievement
- Format siap untuk chart

### 6. Summary Statistics
```php
$formatter->formatSummaryStatistics($year, $month, $diseaseType)
```
- Ringkasan statistik keseluruhan
- Key metrics dan KPI
- Grand total dan percentages

### 7. Chart Data
```php
$formatter->formatChartData($year, $diseaseType, $chartType)
```
- Data siap untuk berbagai jenis chart
- Support line, bar, pie charts
- Responsive data structure

### 8. Real-time Updates
```php
$formatter->updateRealTimeStatistics($puskesmasId, $year, $diseaseType)
```
- Update data secara real-time
- Cache invalidation otomatis
- Recalculation untuk data terbaru

### 9. Export Formatting
```php
$formatter->formatForPdfExport($data, $options)
$formatter->formatForExcelExport($data, $options)
```
- Format khusus untuk PDF dan Excel export
- Consistent formatting
- Optimized untuk large datasets

### 10. Validation
```php
$formatter->validateRequest($parameters)
```
- Validasi parameter request
- Error handling yang konsisten
- Input sanitization

## API Endpoints

Semua endpoint tersedia di prefix `/api/statistics-formatter/`:

### GET Endpoints
- `/dashboard` - Dashboard statistics
- `/admin` - Admin statistics dengan optimasi
- `/optimized` - Statistik teroptimasi dengan caching
- `/ht` - Statistik HT
- `/dm` - Statistik DM
- `/monthly` - Data bulanan
- `/summary` - Summary statistics
- `/chart` - Chart data

### POST Endpoints
- `/realtime/update` - Update real-time statistics
- `/validate` - Validasi parameter

### PDF Export Endpoints
- `GET /pdf/puskesmas` - Mendapatkan data terformat untuk export PDF statistik Puskesmas
  - Parameters: `puskesmas_id` (required), `year`, `disease_type`
- `GET /pdf/quarters-recap` - Mendapatkan data terformat untuk export PDF rekap triwulan
  - Parameters: `year`, `disease_type`

### Excel Export Endpoints
- `GET /excel/all` - Mendapatkan data terformat untuk export Excel laporan semua
  - Parameters: `year`, `disease_type`
- `GET /excel/monthly` - Mendapatkan data terformat untuk export Excel laporan bulanan
  - Parameters: `year`, `disease_type`
- `GET /excel/quarterly` - Mendapatkan data terformat untuk export Excel laporan triwulan
  - Parameters: `year`, `disease_type`
- `GET /excel/puskesmas` - Mendapatkan data terformat untuk export Excel template Puskesmas
  - Parameters: `puskesmas_id` (required), `year`, `disease_type`

## Parameter yang Didukung

### Query Parameters
- `year` - Tahun (default: tahun saat ini)
- `month` - Bulan (optional, 1-12)
- `disease_type` - Jenis penyakit (`all`, `ht`, `dm`)
- `page` - Halaman untuk pagination
- `per_page` - Jumlah item per halaman
- `puskesmas_ids` - Array ID Puskesmas
- `chart_type` - Jenis chart (`line`, `bar`, `pie`)

## Contoh Penggunaan

### 1. Mendapatkan Dashboard Statistics
```http
GET /api/statistics-formatter/dashboard?year=2024&disease_type=all
```

### 2. Admin Statistics dengan Pagination
```http
GET /api/statistics-formatter/admin?year=2024&month=12&page=1&per_page=10
```

### 3. Optimized Statistics untuk Multiple Puskesmas
```http
GET /api/statistics-formatter/optimized?puskesmas_ids[]=1&puskesmas_ids[]=2&year=2024
```

### 4. Chart Data untuk Visualization
```http
GET /api/statistics-formatter/chart?year=2024&disease_type=ht&chart_type=line
```

### 5. Update Real-time Statistics
```http
POST /api/statistics-formatter/realtime/update
Content-Type: application/json

{
    "puskesmas_id": 1,
    "year": 2024,
    "disease_type": "ht"
}
```

### 6. Mendapatkan Data PDF Puskesmas
```http
GET /api/statistics-formatter/pdf/puskesmas?puskesmas_id=1&year=2024&disease_type=ht
```

Response:
```json
{
    "success": true,
    "message": "PDF puskesmas data retrieved successfully",
    "data": {
        "puskesmas_name": "Puskesmas ABC",
        "year": 2024,
        "disease_type": "ht",
        "disease_label": "Hipertensi",
        "monthly_data": {
            "1": {"male": 10, "female": 15, "standard": 20, "non_standard": 5, "total": 25, "percentage": 80.0},
            "2": {"male": 12, "female": 18, "standard": 24, "non_standard": 6, "total": 30, "percentage": 80.0}
        },
        "yearly_target": 300,
        "yearly_total": {"male": 120, "female": 180, "standard": 240, "non_standard": 60, "total": 300},
        "achievement_percentage": 80.0,
        "generated_at": "01/12/2024 10:30:00"
    }
}
```

### 7. Mendapatkan Data Excel Quarterly
```http
GET /api/statistics-formatter/excel/quarterly?year=2024&disease_type=dm
```

Response:
```json
{
    "success": true,
    "message": "Excel quarterly data retrieved successfully",
    "data": [
        {
            "nama_puskesmas": "Puskesmas ABC",
            "sasaran": 200,
            "quarterly_data": {
                "1": {"male": 30, "female": 45, "standard": 60, "non_standard": 15, "total": 75, "percentage": 80.0},
                "2": {"male": 25, "female": 35, "standard": 48, "non_standard": 12, "total": 60, "percentage": 80.0},
                "3": {"male": 20, "female": 30, "standard": 40, "non_standard": 10, "total": 50, "percentage": 80.0},
                "4": {"male": 15, "female": 25, "standard": 32, "non_standard": 8, "total": 40, "percentage": 80.0}
            }
        }
    ]
}
```

### 8. Mendapatkan Data Excel Template Puskesmas
```http
GET /api/statistics-formatter/excel/puskesmas?puskesmas_id=1&year=2024&disease_type=all
```

Response:
```json
{
    "success": true,
    "message": "Excel puskesmas template data retrieved successfully",
    "data": {
        "puskesmas_name": "Puskesmas ABC",
        "year": 2024,
        "ht_data": {
            "monthly_data": {...},
            "yearly_target": 300,
            "yearly_total": {...}
        },
        "dm_data": {
            "monthly_data": {...},
            "yearly_target": 250,
            "yearly_total": {...}
        }
    }
}
```

## Response Format

Semua response menggunakan format JSON yang konsisten:

```json
{
    "success": true,
    "data": {
        // Data statistik yang diformat
    },
    "meta": {
        // Metadata seperti pagination, cache info, dll
    }
}
```

## Error Handling

Error response format:

```json
{
    "success": false,
    "message": "Error message",
    "error": "Detailed error information"
}
```

## Performance Features

### 1. Intelligent Caching
- Cache dengan tags untuk invalidation yang tepat
- TTL yang dapat dikonfigurasi
- Cache warming untuk data yang sering diakses

### 2. Query Optimization
- Eliminasi N+1 queries
- Bulk operations
- Efficient joins dan aggregations

### 3. Memory Management
- Chunking untuk large datasets
- Lazy loading
- Memory efficient data structures

## Security

### 1. Authentication
- Semua endpoint memerlukan authentication (`auth:sanctum`)
- Role-based access control

### 2. Authorization
- Middleware `AdminOrPuskesmasMiddleware`
- Data filtering berdasarkan user role

### 3. Input Validation
- Parameter validation
- SQL injection prevention
- XSS protection

## Monitoring dan Logging

### 1. Performance Monitoring
- Query execution time
- Cache hit/miss ratio
- Memory usage tracking

### 2. Error Logging
- Detailed error logs
- Performance bottleneck detection
- Cache performance metrics

## Best Practices

### 1. Penggunaan Cache
- Gunakan endpoint optimized untuk data yang sering diakses
- Implementasikan cache warming untuk data kritikal
- Monitor cache hit ratio

### 2. Pagination
- Selalu gunakan pagination untuk dataset besar
- Sesuaikan `per_page` berdasarkan kebutuhan UI

### 3. Real-time Updates
- Gunakan real-time updates hanya ketika diperlukan
- Implementasikan debouncing untuk multiple updates

### 4. Error Handling
- Selalu handle error response
- Implementasikan retry mechanism untuk network errors
- Provide fallback data ketika service tidak tersedia

## Troubleshooting

### 1. Performance Issues
- Check cache configuration
- Monitor database query performance
- Verify index usage

### 2. Data Inconsistency
- Verify cache invalidation
- Check real-time update triggers
- Validate data synchronization

### 3. Memory Issues
- Reduce `per_page` size
- Implement data chunking
- Monitor memory usage patterns

## Future Enhancements

### 1. Advanced Caching
- Distributed caching
- Cache clustering
- Predictive cache warming

### 2. Real-time Features
- WebSocket integration
- Push notifications
- Live data streaming

### 3. Analytics
- Advanced analytics
- Predictive modeling
- Machine learning integration

## Conclusion

StatisticsFormatter menyediakan solusi komprehensif untuk mengelola dan memformat data statistik dalam aplikasi. Dengan mengintegrasikan semua service statistik, formatter ini memberikan:

- **Konsistensi** dalam format data
- **Performa** yang optimal dengan caching
- **Fleksibilitas** dalam penggunaan
- **Skalabilitas** untuk pertumbuhan data
- **Maintainability** dengan arsitektur yang bersih

Dengan menggunakan StatisticsFormatter, developer dapat fokus pada logic bisnis tanpa perlu khawatir tentang kompleksitas integrasi berbagai service statistik.