# 🚀 Implementation Guide - Optimalisasi Performa Akudihatinya Backend

## 📋 Daftar Isi

1. [Overview](#overview)
2. [File yang Dibuat/Dimodifikasi](#file-yang-dibuatdimodifikasi)
3. [Langkah Implementasi](#langkah-implementasi)
4. [Konfigurasi Environment](#konfigurasi-environment)
5. [Testing & Validasi](#testing--validasi)
6. [Monitoring & Maintenance](#monitoring--maintenance)
7. [Troubleshooting](#troubleshooting)

## 📖 Overview

Panduan ini menjelaskan cara mengimplementasikan optimalisasi performa yang telah dibuat untuk backend Akudihatinya. Optimalisasi mencakup:

- ✅ **Database Optimization**: Index baru, query optimization
- ✅ **Caching Strategy**: Redis caching, response caching
- ✅ **Code Optimization**: Service optimization, N+1 query fixes
- ✅ **Performance Monitoring**: Command untuk monitoring performa
- ✅ **Middleware Optimization**: Cache middleware dengan rate limiting

## 📁 File yang Dibuat/Dimodifikasi

### File Baru yang Dibuat:

1. **`AUDIT_OPTIMALISASI_REPORT.md`** - Laporan audit lengkap
2. **`app/Http/Controllers/PatientControllerOptimized.php`** - Controller yang dioptimalisasi
3. **`database/migrations/2025_01_20_000000_add_performance_indexes_optimization.php`** - Migration index performa
4. **`app/Services/OptimizedStatisticsService.php`** - Service statistik yang dioptimalisasi
5. **`app/Http/Middleware/OptimizedCacheMiddleware.php`** - Middleware caching
6. **`app/Console/Commands/OptimizePerformanceCommand.php`** - Command optimalisasi
7. **`config/optimization.php`** - Konfigurasi optimalisasi

### File yang Dimodifikasi:

1. **`app/Services/DiseaseStatisticsService.php`** - Ditambahkan caching dan optimalisasi

## 🔧 Langkah Implementasi

### Step 1: Database Migration

```bash
# Jalankan migration untuk menambahkan index performa
php artisan migrate

# Verifikasi index telah dibuat
php artisan tinker
>>> DB::select("SHOW INDEX FROM patients");
>>> DB::select("SHOW INDEX FROM ht_examinations");
>>> DB::select("SHOW INDEX FROM dm_examinations");
```

### Step 2: Environment Configuration

Tambahkan konfigurasi berikut ke file `.env`:

```env
# Cache Configuration
CACHE_DRIVER=redis
CACHE_DEFAULT_TTL=30
CACHE_LONG_TTL=120
CACHE_TAGS_ENABLED=true
RESPONSE_CACHE_ENABLED=true
STATISTICS_CACHE_ENABLED=true

# Redis Configuration (jika belum ada)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_secure_password_here
REDIS_PORT=6379
REDIS_DB=0

# Performance Monitoring
PERFORMANCE_METRICS_ENABLED=true
METRICS_COLLECT_MEMORY=true
METRICS_COLLECT_QUERIES=true
METRICS_COLLECT_CACHE=true

# Rate Limiting
RATE_LIMITING_ENABLED=true
RATE_LIMIT_STATISTICS=30
RATE_LIMIT_REPORTS=10
RATE_LIMIT_PATIENTS=60

# Database Optimization
DB_LOG_SLOW_QUERIES=true
DB_SLOW_QUERY_THRESHOLD=1000
```

### Step 3: Register Middleware

Tambahkan middleware ke `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        // ... existing middleware
        \App\Http\Middleware\OptimizedCacheMiddleware::class,
    ],
];
```

### Step 4: Register Command

Tambahkan command ke `app/Console/Kernel.php`:

```php
protected $commands = [
    \App\Console\Commands\OptimizePerformanceCommand::class,
];

protected function schedule(Schedule $schedule)
{
    // Cache warming setiap jam
    $schedule->command('optimize:performance --cache-warm')
             ->hourly()
             ->withoutOverlapping();
    
    // Database optimization setiap hari
    $schedule->command('optimize:performance --db-optimize')
             ->daily()
             ->at('02:00');
    
    // Performance monitoring setiap 5 menit
    $schedule->command('optimize:performance --monitor')
             ->everyFiveMinutes();
}
```

### Step 5: Service Provider Registration

Buat atau update `app/Providers/OptimizationServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\OptimizedStatisticsService;
use App\Services\DiseaseStatisticsService;

class OptimizationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(OptimizedStatisticsService::class);
        $this->app->singleton(DiseaseStatisticsService::class);
    }
    
    public function boot()
    {
        // Boot optimization services
    }
}
```

Daftarkan di `config/app.php`:

```php
'providers' => [
    // ... existing providers
    App\Providers\OptimizationServiceProvider::class,
],
```

### Step 6: Update Controller Usage

Ganti penggunaan `PatientController` dengan `PatientControllerOptimized` di routes:

```php
// routes/api.php
Route::get('/patients', [PatientControllerOptimized::class, 'index']);
```

## ⚙️ Konfigurasi Environment

### Production Environment

```env
# Production optimizations
APP_ENV=production
APP_DEBUG=false

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Database
DB_CONNECTION=mysql
# Gunakan connection pooling jika tersedia

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-secure-redis-password
REDIS_PORT=6379

# Performance
PERFORMANCE_METRICS_ENABLED=true
APM_ENABLED=true
```

### Development Environment

```env
# Development settings
APP_ENV=local
APP_DEBUG=true

# Cache (bisa menggunakan file untuk development)
CACHE_DRIVER=file

# Debug tools
TELESCOPE_ENABLED=true
DEBUGBAR_ENABLED=true
DEBUG_QUERIES=true
```

## 🧪 Testing & Validasi

### 1. Test Database Performance

```bash
# Jalankan performance optimization
php artisan optimize:performance --all

# Test query performance
php artisan tinker
>>> $start = microtime(true);
>>> App\Models\Patient::with('htExaminations')->where('puskesmas_id', 1)->get();
>>> echo (microtime(true) - $start) * 1000 . "ms";
```

### 2. Test Cache Performance

```bash
# Test cache warming
php artisan optimize:performance --cache-warm

# Verify cache is working
php artisan tinker
>>> Cache::get('test-key');
>>> Cache::put('test-key', 'test-value', 60);
>>> Cache::get('test-key');
```

### 3. Test API Performance

```bash
# Install Apache Bench (jika belum ada)
# Ubuntu: sudo apt-get install apache2-utils
# Windows: Download from Apache website

# Test API endpoint
ab -n 100 -c 10 http://localhost:8000/api/patients

# Test dengan authentication
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/statistics
```

### 4. Memory Usage Testing

```bash
# Monitor memory usage
php artisan optimize:performance --monitor

# Test memory dengan load testing
php artisan tinker
>>> memory_get_usage(true);
>>> // Run some operations
>>> memory_get_peak_usage(true);
```

## 📊 Monitoring & Maintenance

### Daily Monitoring Commands

```bash
# Check overall performance
php artisan optimize:performance --analyze

# Monitor cache performance
php artisan optimize:performance --monitor

# Check database optimization
php artisan optimize:performance --db-optimize
```

### Weekly Maintenance

```bash
# Clear and warm cache
php artisan cache:clear
php artisan optimize:performance --cache-warm

# Optimize database
php artisan optimize:performance --db-optimize

# Check for slow queries
# Review MySQL slow query log
tail -f /var/log/mysql/mysql-slow.log
```

### Performance Metrics to Monitor

1. **Response Time**: < 200ms untuk API calls
2. **Memory Usage**: < 80% dari limit
3. **Cache Hit Rate**: > 70%
4. **Database Query Time**: < 100ms average
5. **Concurrent Users**: Monitor dengan load testing

## 🔍 Troubleshooting

### Common Issues

#### 1. Cache Not Working

```bash
# Check Redis connection
redis-cli ping

# Check Laravel cache configuration
php artisan config:cache
php artisan cache:clear

# Verify cache driver
php artisan tinker
>>> config('cache.default');
```

#### 2. Slow Database Queries

```bash
# Enable query logging
php artisan tinker
>>> DB::enableQueryLog();
>>> // Run your queries
>>> DB::getQueryLog();

# Check indexes
php artisan tinker
>>> DB::select("SHOW INDEX FROM patients");
```

#### 3. Memory Issues

```bash
# Check memory limit
php -i | grep memory_limit

# Monitor memory usage
php artisan optimize:performance --analyze

# Increase memory limit if needed (php.ini)
memory_limit = 512M
```

#### 4. Rate Limiting Issues

```bash
# Check rate limit status
php artisan tinker
>>> RateLimiter::remaining('api:user:1', 60);

# Clear rate limits
>>> RateLimiter::clear('api:user:1');
```

### Performance Debugging

```bash
# Enable debug mode temporarily
php artisan tinker
>>> config(['app.debug' => true]);

# Use Laravel Telescope (development)
php artisan telescope:install
php artisan migrate

# Monitor with htop/top
htop

# Monitor MySQL processes
mysql -e "SHOW PROCESSLIST;"
```

## 📈 Expected Performance Improvements

Setelah implementasi, Anda dapat mengharapkan:

- **API Response Time**: Penurunan 40-60%
- **Database Query Count**: Penurunan 50-70%
- **Memory Usage**: Penurunan 20-30%
- **Cache Hit Rate**: Peningkatan hingga 80%+
- **Concurrent User Capacity**: Peningkatan 2-3x

## 🎯 Next Steps

1. **Monitoring Setup**: Implementasi APM tools (New Relic, Datadog)
2. **Load Testing**: Setup regular load testing dengan tools seperti Artillery atau K6
3. **CDN Integration**: Setup CDN untuk static assets
4. **Database Scaling**: Consider read replicas untuk scaling horizontal
5. **Queue Optimization**: Implementasi Laravel Horizon untuk queue monitoring

---

**📞 Support**: Jika mengalami masalah dalam implementasi, silakan refer ke dokumentasi Laravel atau hubungi tim development.

**🔄 Updates**: Panduan ini akan diupdate seiring dengan perkembangan optimalisasi sistem.