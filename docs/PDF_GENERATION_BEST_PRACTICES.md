# PDF Generation Best Practices

Dokumen ini menjelaskan best practices yang telah diimplementasikan untuk mengatasi error "Puskesmas not found" dan meningkatkan kualitas kode secara keseluruhan.

## 🚀 Implementasi yang Telah Dilakukan

### 1. Custom Exception Handling

**File:** `app/Exceptions/PuskesmasNotFoundException.php`

- **Tujuan:** Menangani error "Puskesmas not found" dengan lebih spesifik dan informatif
- **Fitur:**
  - Custom error response dengan format JSON yang konsisten
  - Context data untuk debugging
  - Suggestions untuk user
  - Correlation ID untuk tracking

```php
// Contoh penggunaan
throw new PuskesmasNotFoundException($puskesmasId, [
    'correlation_id' => $correlationId,
    'method' => __METHOD__
]);
```

### 2. Repository Pattern

**Files:**
- `app/Repositories/PuskesmasRepositoryInterface.php`
- `app/Repositories/PuskesmasRepository.php`
- `app/Providers/RepositoryServiceProvider.php`

**Manfaat:**
- Separation of concerns
- Easier testing dan mocking
- Caching built-in
- Consistent error handling

```php
// Penggunaan dengan caching
$puskesmas = $this->puskesmasRepository->findWithCache($id, 3600);

// Penggunaan dengan exception handling
$puskesmas = $this->puskesmasRepository->findOrFail($id);
```

### 3. Form Request Validation

**File:** `app/Http/Requests/Puskesmas/PuskesmasPdfRequest.php`

**Fitur:**
- Centralized validation logic
- Custom error messages dalam Bahasa Indonesia
- Role-based validation (admin vs puskesmas user)
- Auto-preparation of data

### 4. Enhanced Logging dengan Correlation ID

**Implementasi:**
- Setiap request PDF generation memiliki unique correlation ID
- Structured logging dengan context yang lengkap
- Tracking dari controller hingga formatter

```php
$correlationId = uniqid('pdf_export_', true);

Log::info('Starting PDF generation', [
    'correlation_id' => $correlationId,
    'user_id' => Auth::id(),
    'puskesmas_id' => $puskesmasId
]);
```

### 5. Centralized Configuration

**File:** `config/pdf.php`

**Konfigurasi meliputi:**
- Memory dan time limits
- Cache settings
- Template paths
- Error handling options
- Performance settings

### 6. Comprehensive Testing

**File:** `tests/Feature/PuskesmasPdfExportTest.php`

**Test scenarios:**
- Happy path untuk user dan admin
- Error handling untuk Puskesmas not found
- Validation testing
- Authorization testing

## 🔧 Cara Menggunakan

### 1. Generate PDF Puskesmas

```bash
# Endpoint
POST /api/statistics/export/puskesmas-pdf

# Request body
{
    "disease_type": "ht",
    "year": 2024,
    "puskesmas_id": 1  // Hanya untuk admin
}
```

### 2. Error Response Format

```json
{
    "success": false,
    "error": "puskesmas_not_found",
    "message": "Puskesmas dengan ID '999' tidak ditemukan",
    "data": {
        "puskesmas_id": 999,
        "suggestions": [
            "Pastikan ID Puskesmas yang digunakan valid",
            "Periksa apakah Puskesmas masih aktif dalam sistem",
            "Hubungi administrator jika masalah berlanjut"
        ]
    }
}
```

## 🛠️ Troubleshooting

### Error: "Puskesmas not found"

1. **Periksa ID Puskesmas:**
   ```sql
   SELECT id, name, is_active FROM puskesmas WHERE id = ?;
   ```

2. **Periksa log dengan correlation ID:**
   ```bash
   grep "correlation_id" storage/logs/laravel.log
   ```

3. **Clear cache jika diperlukan:**
   ```bash
   php artisan cache:clear
   ```

### Performance Issues

1. **Monitor memory usage:**
   - Default limit: 512M (configurable via `PDF_MEMORY_LIMIT`)
   
2. **Check time limits:**
   - Default: 300 seconds (configurable via `PDF_TIME_LIMIT`)

3. **Enable caching:**
   ```env
   PDF_CACHE_ENABLED=true
   PDF_CACHE_TTL=3600
   ```

## 📊 Monitoring dan Metrics

### Log Levels

- **INFO:** Normal operations, start/end of processes
- **WARNING:** Recoverable errors (Puskesmas not found)
- **ERROR:** Unexpected errors, system failures

### Key Metrics to Monitor

1. **PDF Generation Success Rate**
2. **Average Generation Time**
3. **Memory Usage**
4. **Cache Hit Rate**
5. **Error Rate by Type**

### Sample Log Analysis Query

```bash
# Count errors by type
grep "PDF generation failed" storage/logs/laravel.log | \
  grep -o '"error":"[^"]*"' | \
  sort | uniq -c

# Find slow PDF generations
grep "PDF generation completed" storage/logs/laravel.log | \
  grep -E "[0-9]{4,}ms"
```

## 🔄 Maintenance

### Regular Tasks

1. **Clear old PDF cache:**
   ```bash
   php artisan cache:forget puskesmas.*
   ```

2. **Monitor log file size:**
   ```bash
   du -h storage/logs/laravel.log
   ```

3. **Update configuration based on usage:**
   - Adjust memory limits
   - Tune cache TTL
   - Update validation rules

### Performance Optimization

1. **Enable Redis for caching:**
   ```env
   CACHE_DRIVER=redis
   ```

2. **Use queue for large PDF generation:**
   ```php
   dispatch(new GeneratePdfJob($puskesmasId, $diseaseType, $year));
   ```

## 📝 Development Guidelines

### Adding New PDF Types

1. Create new Form Request class
2. Add template configuration in `config/pdf.php`
3. Implement formatter class
4. Add comprehensive tests
5. Update documentation

### Error Handling Standards

1. Always use correlation IDs
2. Log with appropriate levels
3. Provide user-friendly error messages
4. Include context for debugging
5. Use custom exceptions when appropriate

### Testing Requirements

1. Unit tests for repositories
2. Feature tests for endpoints
3. Error scenario testing
4. Performance testing for large datasets
5. Integration testing with external services

## 🔗 Related Documentation

- [API Documentation](API_DOCUMENTATION.md)
- [Development Guide](DEVELOPMENT_GUIDE.md)
- [Deployment Guide](DEPLOYMENT_GUIDE.md)
- [Contributing Guidelines](../CONTRIBUTING.md)