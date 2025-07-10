<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Services\DiseaseStatisticsService;
use App\Services\OptimizedStatisticsService;
use App\Http\Middleware\OptimizedCacheMiddleware;
use App\Models\Puskesmas;
use Carbon\Carbon;

/**
 * Performance Optimization Command
 * 
 * Features:
 * - Database optimization
 * - Cache warming
 * - Performance monitoring
 * - Index analysis
 * - Memory optimization
 */
class OptimizePerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'optimize:performance 
                            {--cache-warm : Warm up application cache}
                            {--db-optimize : Optimize database queries and indexes}
                            {--analyze : Analyze current performance}
                            {--monitor : Show performance monitoring}
                            {--all : Run all optimizations}';

    /**
     * The console command description.
     */
    protected $description = 'Optimize application performance including cache, database, and monitoring';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Performance Optimization...');
        
        if ($this->option('all')) {
            $this->runAllOptimizations();
        } else {
            if ($this->option('cache-warm')) {
                $this->warmUpCache();
            }
            
            if ($this->option('db-optimize')) {
                $this->optimizeDatabase();
            }
            
            if ($this->option('analyze')) {
                $this->analyzePerformance();
            }
            
            if ($this->option('monitor')) {
                $this->showPerformanceMonitoring();
            }
        }
        
        $this->info('✅ Performance optimization completed!');
    }
    
    /**
     * Run all optimizations
     */
    private function runAllOptimizations()
    {
        $this->info('Running all optimizations...');
        
        $this->optimizeDatabase();
        $this->warmUpCache();
        $this->analyzePerformance();
        $this->showPerformanceMonitoring();
    }
    
    /**
     * Warm up application cache
     */
    private function warmUpCache()
    {
        $this->info('🔥 Warming up cache...');
        
        $startTime = microtime(true);
        
        try {
            // Clear existing cache
            $this->line('  - Clearing existing cache...');
            Cache::flush();
            
            // Warm up configuration cache
            $this->line('  - Warming configuration cache...');
            Artisan::call('config:cache');
            
            // Warm up route cache
            $this->line('  - Warming route cache...');
            Artisan::call('route:cache');
            
            // Warm up view cache
            $this->line('  - Warming view cache...');
            Artisan::call('view:cache');
            
            // Warm up statistics cache
            $this->warmUpStatisticsCache();
            
            // Warm up response cache
            $this->warmUpResponseCache();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("✅ Cache warmed up successfully in {$duration}ms");
            
        } catch (\Exception $e) {
            $this->error('❌ Cache warm-up failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Warm up statistics cache
     */
    private function warmUpStatisticsCache()
    {
        $this->line('  - Warming statistics cache...');
        
        $diseaseStatisticsService = app(DiseaseStatisticsService::class);
        
        // Get all puskesmas
        $puskesmasIds = Puskesmas::pluck('id')->toArray();
        $currentYear = Carbon::now()->year;
        $diseaseTypes = ['ht', 'dm'];
        
        // Limit to first 10 puskesmas for warming to avoid timeout
        $limitedPuskesmasIds = array_slice($puskesmasIds, 0, 10);
        
        $diseaseStatisticsService->preWarmCache(
            $limitedPuskesmasIds,
            [$currentYear, $currentYear - 1],
            $diseaseTypes
        );
        
        $this->line('    ✓ Statistics cache warmed for ' . count($limitedPuskesmasIds) . ' puskesmas');
    }
    
    /**
     * Warm up response cache
     */
    private function warmUpResponseCache()
    {
        $this->line('  - Warming response cache...');
        
        try {
            OptimizedCacheMiddleware::warmUpCache();
            $this->line('    ✓ Response cache warmed');
        } catch (\Exception $e) {
            $this->line('    ⚠ Response cache warming skipped: ' . $e->getMessage());
        }
    }
    
    /**
     * Optimize database
     */
    private function optimizeDatabase()
    {
        $this->info('🗄️ Optimizing database...');
        
        $startTime = microtime(true);
        
        try {
            // Analyze table statistics
            $this->analyzeTableStatistics();
            
            // Optimize tables
            $this->optimizeTables();
            
            // Check slow queries
            $this->checkSlowQueries();
            
            // Analyze indexes
            $this->analyzeIndexes();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("✅ Database optimized successfully in {$duration}ms");
            
        } catch (\Exception $e) {
            $this->error('❌ Database optimization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze table statistics
     */
    private function analyzeTableStatistics()
    {
        $this->line('  - Analyzing table statistics...');
        
        $tables = [
            'patients',
            'ht_examinations',
            'dm_examinations',
            'monthly_statistics_cache',
            'yearly_targets',
            'users'
        ];
        
        foreach ($tables as $table) {
            try {
                DB::statement("ANALYZE TABLE {$table}");
                $this->line("    ✓ Analyzed {$table}");
            } catch (\Exception $e) {
                $this->line("    ⚠ Failed to analyze {$table}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Optimize tables
     */
    private function optimizeTables()
    {
        $this->line('  - Optimizing tables...');
        
        $tables = [
            'patients',
            'ht_examinations',
            'dm_examinations',
            'monthly_statistics_cache'
        ];
        
        foreach ($tables as $table) {
            try {
                DB::statement("OPTIMIZE TABLE {$table}");
                $this->line("    ✓ Optimized {$table}");
            } catch (\Exception $e) {
                $this->line("    ⚠ Failed to optimize {$table}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Check slow queries
     */
    private function checkSlowQueries()
    {
        $this->line('  - Checking slow queries...');
        
        try {
            // Enable slow query log temporarily
            DB::statement("SET GLOBAL slow_query_log = 'ON'");
            DB::statement("SET GLOBAL long_query_time = 1");
            
            $this->line('    ✓ Slow query logging enabled (queries > 1s)');
        } catch (\Exception $e) {
            $this->line('    ⚠ Could not enable slow query logging: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze indexes
     */
    private function analyzeIndexes()
    {
        $this->line('  - Analyzing indexes...');
        
        try {
            $indexes = DB::select("
                SELECT 
                    TABLE_NAME,
                    INDEX_NAME,
                    CARDINALITY,
                    SUB_PART,
                    NULLABLE
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('patients', 'ht_examinations', 'dm_examinations', 'monthly_statistics_cache')
                ORDER BY TABLE_NAME, INDEX_NAME
            ");
            
            $indexCount = count($indexes);
            $this->line("    ✓ Found {$indexCount} indexes");
            
            // Check for unused indexes (simplified)
            $this->checkUnusedIndexes();
            
        } catch (\Exception $e) {
            $this->line('    ⚠ Index analysis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check for unused indexes
     */
    private function checkUnusedIndexes()
    {
        try {
            $unusedIndexes = DB::select("
                SELECT 
                    object_schema,
                    object_name,
                    index_name
                FROM performance_schema.table_io_waits_summary_by_index_usage 
                WHERE index_name IS NOT NULL
                AND count_star = 0
                AND object_schema = DATABASE()
                ORDER BY object_schema, object_name
            ");
            
            if (count($unusedIndexes) > 0) {
                $this->line('    ⚠ Found ' . count($unusedIndexes) . ' potentially unused indexes');
            } else {
                $this->line('    ✓ No unused indexes found');
            }
        } catch (\Exception $e) {
            $this->line('    ⚠ Could not check unused indexes: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze current performance
     */
    private function analyzePerformance()
    {
        $this->info('📊 Analyzing performance...');
        
        try {
            // Memory usage
            $this->analyzeMemoryUsage();
            
            // Database performance
            $this->analyzeDatabasePerformance();
            
            // Cache performance
            $this->analyzeCachePerformance();
            
            // Application metrics
            $this->analyzeApplicationMetrics();
            
        } catch (\Exception $e) {
            $this->error('❌ Performance analysis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze memory usage
     */
    private function analyzeMemoryUsage()
    {
        $this->line('  - Memory Usage:');
        
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        $this->line('    Current: ' . $this->formatBytes($memoryUsage));
        $this->line('    Peak: ' . $this->formatBytes($peakMemory));
        $this->line('    Limit: ' . $memoryLimit);
    }
    
    /**
     * Analyze database performance
     */
    private function analyzeDatabasePerformance()
    {
        $this->line('  - Database Performance:');
        
        try {
            $status = DB::select('SHOW STATUS LIKE "Queries"')[0];
            $this->line('    Total Queries: ' . $status->Value);
            
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"')[0];
            $this->line('    Active Connections: ' . $connections->Value);
            
            $uptime = DB::select('SHOW STATUS LIKE "Uptime"')[0];
            $this->line('    Uptime: ' . gmdate('H:i:s', $uptime->Value));
            
        } catch (\Exception $e) {
            $this->line('    ⚠ Could not retrieve database stats: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze cache performance
     */
    private function analyzeCachePerformance()
    {
        $this->line('  - Cache Performance:');
        
        try {
            $stats = OptimizedCacheMiddleware::getCacheStatistics();
            
            foreach ($stats as $key => $value) {
                $this->line('    ' . ucfirst(str_replace('_', ' ', $key)) . ': ' . $value);
            }
        } catch (\Exception $e) {
            $this->line('    ⚠ Could not retrieve cache stats: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze application metrics
     */
    private function analyzeApplicationMetrics()
    {
        $this->line('  - Application Metrics:');
        
        try {
            $diseaseStatisticsService = app(DiseaseStatisticsService::class);
            $metrics = $diseaseStatisticsService->getPerformanceMetrics();
            
            foreach ($metrics as $key => $value) {
                if (is_numeric($value)) {
                    $value = is_int($value) ? number_format($value) : $value;
                }
                $this->line('    ' . ucfirst(str_replace('_', ' ', $key)) . ': ' . $value);
            }
        } catch (\Exception $e) {
            $this->line('    ⚠ Could not retrieve app metrics: ' . $e->getMessage());
        }
    }
    
    /**
     * Show performance monitoring
     */
    private function showPerformanceMonitoring()
    {
        $this->info('📈 Performance Monitoring Dashboard');
        $this->line('');
        
        // Create a simple monitoring table
        $headers = ['Metric', 'Value', 'Status'];
        $rows = [];
        
        // Memory metrics
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
        
        $rows[] = [
            'Memory Usage',
            $this->formatBytes($memoryUsage) . ' / ' . $this->formatBytes($memoryLimit),
            $memoryPercent < 80 ? '✅ Good' : ($memoryPercent < 90 ? '⚠️ Warning' : '❌ Critical')
        ];
        
        // Cache status
        $cacheDriver = config('cache.default');
        $rows[] = [
            'Cache Driver',
            $cacheDriver,
            $cacheDriver === 'redis' ? '✅ Optimal' : '⚠️ Consider Redis'
        ];
        
        // Database connections
        try {
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"')[0];
            $maxConnections = DB::select('SHOW VARIABLES LIKE "max_connections"')[0];
            $connectionPercent = ($connections->Value / $maxConnections->Value) * 100;
            
            $rows[] = [
                'DB Connections',
                $connections->Value . ' / ' . $maxConnections->Value,
                $connectionPercent < 70 ? '✅ Good' : ($connectionPercent < 85 ? '⚠️ Warning' : '❌ Critical')
            ];
        } catch (\Exception $e) {
            $rows[] = ['DB Connections', 'N/A', '⚠️ Cannot check'];
        }
        
        $this->table($headers, $rows);
        
        // Recommendations
        $this->showRecommendations();
    }
    
    /**
     * Show performance recommendations
     */
    private function showRecommendations()
    {
        $this->line('');
        $this->info('💡 Performance Recommendations:');
        
        $recommendations = [
            'Use Redis for caching in production',
            'Enable OPcache for PHP optimization',
            'Implement database connection pooling',
            'Use CDN for static assets',
            'Enable gzip compression',
            'Implement API response caching',
            'Monitor slow queries regularly',
            'Use database indexes effectively',
            'Implement background job processing',
            'Use Laravel Horizon for queue monitoring'
        ];
        
        foreach ($recommendations as $index => $recommendation) {
            $this->line('  ' . ($index + 1) . '. ' . $recommendation);
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($memoryLimit)
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }
}