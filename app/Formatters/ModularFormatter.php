<?php

namespace App\Formatters;

use App\Formatters\Strategies\FormatterContext;
use App\Formatters\Strategies\StrategyFactory;
use App\Formatters\Strategies\FormatterStrategyInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ModularFormatter - Facade utama untuk sistem formatter modular
 * Menggabungkan FormatterContext dan StrategyFactory untuk interface yang mudah digunakan
 */
class ModularFormatter
{
    /**
     * @var FormatterContext Context instance
     */
    private FormatterContext $context;

    /**
     * @var array Default options
     */
    private array $defaultOptions = [
        'cache_enabled' => true,
        'cache_ttl' => 3600, // 1 hour
        'validate_input' => true,
        'calculate_data' => true,
        'log_operations' => true,
        'performance_tracking' => true,
        'error_handling' => 'throw', // 'throw', 'log', 'silent'
        'parallel_processing' => false
    ];

    /**
     * @var array Performance metrics
     */
    private array $metrics = [];

    /**
     * Constructor
     *
     * @param array $options Global options
     */
    public function __construct(array $options = [])
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
        $this->context = new FormatterContext();
        $this->context->setDefaultOptions($this->defaultOptions);
        
        $this->initializeMetrics();
        
        if ($this->defaultOptions['log_operations']) {
            Log::info('ModularFormatter initialized', ['options' => $this->defaultOptions]);
        }
    }

    /**
     * Initialize performance metrics
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'operations_count' => 0,
            'total_execution_time' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'errors_count' => 0,
            'last_operation' => null
        ];
    }

    /**
     * Format dashboard data
     *
     * @param array $data Input data
     * @param array $options Options
     * @return array Formatted data
     */
    public function formatDashboardData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('formatDashboardData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('dashboard', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->formatDashboardData($data, $options);
            });
        });
    }

    /**
     * Format admin data
     *
     * @param array $data Input data
     * @param array $options Options
     * @return array Formatted data
     */
    public function formatAdminData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('formatAdminData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('admin', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->formatAdminData($data, $options);
            });
        });
    }

    /**
     * Format puskesmas data
     *
     * @param array $data Input data
     * @param array $options Options
     * @return array Formatted data
     */
    public function formatPuskesmasData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('formatPuskesmasData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('puskesmas', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->formatPuskesmasData($data, $options);
            });
        });
    }

    /**
     * Export to Excel
     *
     * @param array $data Input data
     * @param array $options Export options
     * @return array Export result
     */
    public function exportToExcel(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('exportToExcel', function() use ($data, $options) {
            // Don't cache file exports
            return $this->context->exportToExcel($data, $options);
        });
    }

    /**
     * Export to PDF
     *
     * @param array $data Input data
     * @param array $options Export options
     * @return array Export result
     */
    public function exportToPdf(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('exportToPdf', function() use ($data, $options) {
            // Don't cache file exports
            return $this->context->exportToPdf($data, $options);
        });
    }

    /**
     * Validate data
     *
     * @param array $data Input data
     * @param array $options Validation options
     * @return array Validation result
     */
    public function validateData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('validateData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('validation', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->validateData($data, $options);
            });
        });
    }

    /**
     * Calculate data
     *
     * @param array $data Input data
     * @param array $options Calculation options
     * @return array Calculation result
     */
    public function calculateData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('calculateData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('calculation', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->calculateData($data, $options);
            });
        });
    }

    /**
     * Process data with validation and calculation
     *
     * @param array $data Input data
     * @param array $options Processing options
     * @return array Processing result
     */
    public function processData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('processData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('process', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->processData($data, $options);
            });
        });
    }

    /**
     * Analyze data comprehensively
     *
     * @param array $data Input data
     * @param array $options Analysis options
     * @return array Analysis result
     */
    public function analyzeData(array $data, array $options = []): array
    {
        return $this->executeWithMetrics('analyzeData', function() use ($data, $options) {
            $cacheKey = $this->generateCacheKey('analysis', $data, $options);
            
            return $this->withCache($cacheKey, function() use ($data, $options) {
                return $this->context->analyzeData($data, $options);
            });
        });
    }

    /**
     * Execute custom pipeline
     *
     * @param array $pipeline Pipeline configuration
     * @param array $data Input data
     * @param array $options Global options
     * @return array Pipeline result
     */
    public function executePipeline(array $pipeline, array $data, array $options = []): array
    {
        return $this->executeWithMetrics('executePipeline', function() use ($pipeline, $data, $options) {
            $cacheKey = $this->generateCacheKey('pipeline_' . md5(serialize($pipeline)), $data, $options);
            
            return $this->withCache($cacheKey, function() use ($pipeline, $data, $options) {
                return $this->context->executePipeline($pipeline, $data, $options);
            });
        });
    }

    /**
     * Create and execute custom strategy
     *
     * @param string $strategyName Strategy name
     * @param array $data Input data
     * @param array $options Strategy options
     * @param array $config Strategy configuration
     * @return array Result
     */
    public function executeCustomStrategy(
        string $strategyName, 
        array $data, 
        array $options = [], 
        array $config = []
    ): array {
        return $this->executeWithMetrics('executeCustomStrategy', function() use ($strategyName, $data, $options, $config) {
            $strategy = StrategyFactory::create($strategyName, $config, false);
            $this->context->registerStrategy($strategyName . '_custom', $strategy);
            
            return $this->context->executeStrategy($strategyName . '_custom', $data, $options);
        });
    }

    /**
     * Batch process multiple datasets
     *
     * @param array $datasets Array of datasets
     * @param string $operation Operation to perform
     * @param array $options Options
     * @return array Batch results
     */
    public function batchProcess(array $datasets, string $operation, array $options = []): array
    {
        return $this->executeWithMetrics('batchProcess', function() use ($datasets, $operation, $options) {
            $results = [];
            $errors = [];
            
            foreach ($datasets as $index => $data) {
                try {
                    $result = $this->executeOperation($operation, $data, $options);
                    $results[$index] = $result;
                } catch (\Exception $e) {
                    $errors[$index] = [
                        'error' => $e->getMessage(),
                        'data_preview' => array_slice($data, 0, 3, true)
                    ];
                    
                    if ($this->defaultOptions['error_handling'] === 'throw') {
                        throw $e;
                    }
                }
            }
            
            return [
                'success' => empty($errors),
                'results' => $results,
                'errors' => $errors,
                'processed_count' => count($results),
                'error_count' => count($errors),
                'total_count' => count($datasets)
            ];
        });
    }

    /**
     * Execute operation by name
     *
     * @param string $operation Operation name
     * @param array $data Input data
     * @param array $options Options
     * @return array Result
     */
    private function executeOperation(string $operation, array $data, array $options): array
    {
        switch ($operation) {
            case 'formatDashboardData':
                return $this->formatDashboardData($data, $options);
            case 'formatAdminData':
                return $this->formatAdminData($data, $options);
            case 'formatPuskesmasData':
                return $this->formatPuskesmasData($data, $options);
            case 'validateData':
                return $this->validateData($data, $options);
            case 'calculateData':
                return $this->calculateData($data, $options);
            case 'processData':
                return $this->processData($data, $options);
            case 'analyzeData':
                return $this->analyzeData($data, $options);
            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }

    /**
     * Execute with performance metrics
     *
     * @param string $operation Operation name
     * @param callable $callback Callback to execute
     * @return array Result
     */
    private function executeWithMetrics(string $operation, callable $callback): array
    {
        $startTime = microtime(true);
        $this->metrics['operations_count']++;
        $this->metrics['last_operation'] = $operation;
        
        try {
            $result = $callback();
            
            $executionTime = microtime(true) - $startTime;
            $this->metrics['total_execution_time'] += $executionTime;
            
            if ($this->defaultOptions['performance_tracking']) {
                $result['performance'] = [
                    'execution_time' => $executionTime,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true)
                ];
            }
            
            if ($this->defaultOptions['log_operations']) {
                Log::info("Operation completed: {$operation}", [
                    'execution_time' => $executionTime,
                    'memory_usage' => memory_get_usage(true)
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->metrics['errors_count']++;
            
            if ($this->defaultOptions['log_operations']) {
                Log::error("Operation failed: {$operation}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            if ($this->defaultOptions['error_handling'] === 'throw') {
                throw $e;
            } elseif ($this->defaultOptions['error_handling'] === 'log') {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'operation' => $operation
                ];
            } else {
                return ['success' => false];
            }
        }
    }

    /**
     * Execute with cache
     *
     * @param string $cacheKey Cache key
     * @param callable $callback Callback to execute
     * @return array Result
     */
    private function withCache(string $cacheKey, callable $callback): array
    {
        if (!$this->defaultOptions['cache_enabled']) {
            return $callback();
        }
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->metrics['cache_hits']++;
            return $cached;
        }
        
        $this->metrics['cache_misses']++;
        $result = $callback();
        
        Cache::put($cacheKey, $result, $this->defaultOptions['cache_ttl']);
        
        return $result;
    }

    /**
     * Generate cache key
     *
     * @param string $operation Operation name
     * @param array $data Input data
     * @param array $options Options
     * @return string Cache key
     */
    private function generateCacheKey(string $operation, array $data, array $options): string
    {
        $dataHash = md5(serialize($data));
        $optionsHash = md5(serialize($options));
        
        return "modular_formatter:{$operation}:{$dataHash}:{$optionsHash}";
    }

    /**
     * Register custom strategy
     *
     * @param string $name Strategy name
     * @param string $className Strategy class name
     * @param array $config Default configuration
     * @return self
     */
    public function registerStrategy(string $name, string $className, array $config = []): self
    {
        StrategyFactory::register($name, $className);
        
        if (!empty($config)) {
            StrategyFactory::setConfiguration($name, $config);
        }
        
        // Create and register in context
        $strategy = StrategyFactory::create($name, $config);
        $this->context->registerStrategy($name, $strategy);
        
        return $this;
    }

    /**
     * Create strategy for specific use case
     *
     * @param string $useCase Use case name
     * @param array $config Configuration
     * @return array Strategy instances
     */
    public function createStrategiesForUseCase(string $useCase, array $config = []): array
    {
        return StrategyFactory::createForUseCase($useCase, $config);
    }

    /**
     * Get performance metrics
     *
     * @return array Performance metrics
     */
    public function getMetrics(): array
    {
        $metrics = $this->metrics;
        
        if ($metrics['operations_count'] > 0) {
            $metrics['average_execution_time'] = $metrics['total_execution_time'] / $metrics['operations_count'];
            $metrics['cache_hit_rate'] = $metrics['cache_hits'] / ($metrics['cache_hits'] + $metrics['cache_misses']) * 100;
            $metrics['error_rate'] = $metrics['errors_count'] / $metrics['operations_count'] * 100;
        }
        
        return $metrics;
    }

    /**
     * Reset metrics
     *
     * @return self
     */
    public function resetMetrics(): self
    {
        $this->initializeMetrics();
        return $this;
    }

    /**
     * Clear cache
     *
     * @param string|null $pattern Cache pattern to clear
     * @return self
     */
    public function clearCache(?string $pattern = null): self
    {
        if ($pattern) {
            // Clear specific cache pattern
            $keys = Cache::getRedis()->keys("modular_formatter:{$pattern}:*");
            if (!empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        } else {
            // Clear all formatter cache
            $keys = Cache::getRedis()->keys('modular_formatter:*');
            if (!empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        }
        
        return $this;
    }

    /**
     * Get system information
     *
     * @return array System information
     */
    public function getSystemInfo(): array
    {
        return [
            'formatter_version' => '1.0.0',
            'context_info' => $this->context->getContextInfo(),
            'factory_info' => StrategyFactory::getFactoryInfo(),
            'metrics' => $this->getMetrics(),
            'default_options' => $this->defaultOptions,
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true)
            ],
            'cache_stats' => [
                'enabled' => $this->defaultOptions['cache_enabled'],
                'ttl' => $this->defaultOptions['cache_ttl'],
                'hits' => $this->metrics['cache_hits'],
                'misses' => $this->metrics['cache_misses']
            ]
        ];
    }

    /**
     * Set default options
     *
     * @param array $options Options to set
     * @return self
     */
    public function setDefaultOptions(array $options): self
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
        $this->context->setDefaultOptions($this->defaultOptions);
        return $this;
    }

    /**
     * Get default options
     *
     * @return array Default options
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    /**
     * Enable/disable caching
     *
     * @param bool $enabled Whether to enable caching
     * @param int|null $ttl Cache TTL in seconds
     * @return self
     */
    public function setCaching(bool $enabled, ?int $ttl = null): self
    {
        $this->defaultOptions['cache_enabled'] = $enabled;
        
        if ($ttl !== null) {
            $this->defaultOptions['cache_ttl'] = $ttl;
        }
        
        return $this;
    }

    /**
     * Enable/disable performance tracking
     *
     * @param bool $enabled Whether to enable performance tracking
     * @return self
     */
    public function setPerformanceTracking(bool $enabled): self
    {
        $this->defaultOptions['performance_tracking'] = $enabled;
        return $this;
    }

    /**
     * Set error handling mode
     *
     * @param string $mode Error handling mode ('throw', 'log', 'silent')
     * @return self
     */
    public function setErrorHandling(string $mode): self
    {
        if (!in_array($mode, ['throw', 'log', 'silent'])) {
            throw new \InvalidArgumentException("Invalid error handling mode: {$mode}");
        }
        
        $this->defaultOptions['error_handling'] = $mode;
        return $this;
    }

    /**
     * Create a new instance with specific configuration
     *
     * @param array $options Configuration options
     * @return static New instance
     */
    public static function create(array $options = []): static
    {
        return new static($options);
    }

    /**
     * Create instance optimized for dashboard
     *
     * @return static Optimized instance
     */
    public static function forDashboard(): static
    {
        return new static([
            'cache_enabled' => true,
            'cache_ttl' => 1800, // 30 minutes
            'validate_input' => true,
            'calculate_data' => true,
            'performance_tracking' => true,
            'error_handling' => 'log'
        ]);
    }

    /**
     * Create instance optimized for admin operations
     *
     * @return static Optimized instance
     */
    public static function forAdmin(): static
    {
        return new static([
            'cache_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
            'validate_input' => true,
            'calculate_data' => true,
            'performance_tracking' => true,
            'error_handling' => 'throw',
            'log_operations' => true
        ]);
    }

    /**
     * Create instance optimized for exports
     *
     * @return static Optimized instance
     */
    public static function forExport(): static
    {
        return new static([
            'cache_enabled' => false, // Don't cache file operations
            'validate_input' => true,
            'calculate_data' => true,
            'performance_tracking' => true,
            'error_handling' => 'throw',
            'log_operations' => true
        ]);
    }

    /**
     * Create instance optimized for batch processing
     *
     * @return static Optimized instance
     */
    public static function forBatch(): static
    {
        return new static([
            'cache_enabled' => true,
            'cache_ttl' => 7200, // 2 hours
            'validate_input' => false, // Skip validation for performance
            'calculate_data' => true,
            'performance_tracking' => false,
            'error_handling' => 'log',
            'log_operations' => false,
            'parallel_processing' => true
        ]);
    }
}