<?php

namespace App\Formatters\Strategies;

use App\Formatters\Strategies\DataFormatting\DashboardDataStrategy;
use App\Formatters\Strategies\DataFormatting\AdminDataStrategy;
use App\Formatters\Strategies\DataFormatting\PuskesmasDataStrategy;
use App\Formatters\Strategies\Export\ExcelExportStrategy;
use App\Formatters\Strategies\Export\PdfExportStrategy;
use App\Formatters\Strategies\Calculation\CalculationStrategy;
use App\Formatters\Strategies\Validation\DataValidationStrategy;
use Illuminate\Support\Facades\Log;

/**
 * StrategyFactory - Factory untuk membuat strategy instances
 * Mengelola pembuatan dan konfigurasi strategy secara dinamis
 */
class StrategyFactory
{
    /**
     * @var array Strategy class mappings
     */
    private static array $strategyMap = [
        // Data formatting strategies
        'dashboard_data' => DashboardDataStrategy::class,
        'admin_data' => AdminDataStrategy::class,
        'puskesmas_data' => PuskesmasDataStrategy::class,
        
        // Export strategies
        'excel_export' => ExcelExportStrategy::class,
        'pdf_export' => PdfExportStrategy::class,
        
        // Processing strategies
        'calculation' => CalculationStrategy::class,
        'validation' => DataValidationStrategy::class,
    ];

    /**
     * @var array Strategy instances cache
     */
    private static array $instances = [];

    /**
     * @var array Strategy configurations
     */
    private static array $configurations = [];

    /**
     * Create a strategy instance
     *
     * @param string $strategyName Strategy name
     * @param array $config Strategy configuration
     * @param bool $singleton Whether to use singleton pattern
     * @return FormatterStrategyInterface
     * @throws \InvalidArgumentException
     */
    public static function create(string $strategyName, array $config = [], bool $singleton = true): FormatterStrategyInterface
    {
        if (!isset(self::$strategyMap[$strategyName])) {
            throw new \InvalidArgumentException("Unknown strategy: {$strategyName}");
        }

        $cacheKey = $strategyName . '_' . md5(serialize($config));

        // Return cached instance if singleton and exists
        if ($singleton && isset(self::$instances[$cacheKey])) {
            return self::$instances[$cacheKey];
        }

        $strategyClass = self::$strategyMap[$strategyName];
        
        // Create instance with configuration
        $instance = self::createInstance($strategyClass, $config);
        
        // Cache instance if singleton
        if ($singleton) {
            self::$instances[$cacheKey] = $instance;
        }

        Log::info("Strategy created: {$strategyName}", [
            'class' => $strategyClass,
            'config' => $config,
            'singleton' => $singleton
        ]);

        return $instance;
    }

    /**
     * Create strategy instance with dependency injection
     *
     * @param string $strategyClass Strategy class name
     * @param array $config Configuration
     * @return FormatterStrategyInterface
     */
    private static function createInstance(string $strategyClass, array $config): FormatterStrategyInterface
    {
        // Get constructor parameters
        $reflection = new \ReflectionClass($strategyClass);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return new $strategyClass();
        }

        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            // Check if config provides this parameter
            if (isset($config[$paramName])) {
                $args[] = $config[$paramName];
                continue;
            }

            // Handle dependency injection for common types
            if ($paramType && !$paramType->isBuiltin()) {
                $typeName = $paramType->getName();
                
                // Resolve common dependencies
                $dependency = self::resolveDependency($typeName, $config);
                if ($dependency !== null) {
                    $args[] = $dependency;
                    continue;
                }
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $args[] = null;
            } else {
                throw new \InvalidArgumentException(
                    "Cannot resolve parameter '{$paramName}' for strategy '{$strategyClass}'"
                );
            }
        }

        return new $strategyClass(...$args);
    }

    /**
     * Resolve common dependencies
     *
     * @param string $typeName Type name
     * @param array $config Configuration
     * @return mixed|null
     */
    private static function resolveDependency(string $typeName, array $config)
    {
        // Add common dependency resolutions here
        switch ($typeName) {
            case 'App\Services\StatisticsService':
                return app('App\Services\StatisticsService');
                
            case 'Illuminate\Contracts\Filesystem\Filesystem':
                return app('filesystem.disk');
                
            case 'Maatwebsite\Excel\Excel':
                return app('excel');
                
            default:
                // Try to resolve from Laravel container
                try {
                    return app($typeName);
                } catch (\Exception $e) {
                    return null;
                }
        }
    }

    /**
     * Create multiple strategies
     *
     * @param array $strategies Array of strategy configurations
     * @param bool $singleton Whether to use singleton pattern
     * @return array Array of strategy instances
     */
    public static function createMultiple(array $strategies, bool $singleton = true): array
    {
        $instances = [];
        
        foreach ($strategies as $name => $config) {
            if (is_numeric($name) && is_string($config)) {
                // Simple array of strategy names
                $instances[$config] = self::create($config, [], $singleton);
            } elseif (is_string($name) && is_array($config)) {
                // Named configurations
                $instances[$name] = self::create($name, $config, $singleton);
            } else {
                throw new \InvalidArgumentException('Invalid strategy configuration format');
            }
        }
        
        return $instances;
    }

    /**
     * Register a new strategy type
     *
     * @param string $name Strategy name
     * @param string $className Strategy class name
     * @return void
     */
    public static function register(string $name, string $className): void
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Strategy class does not exist: {$className}");
        }

        if (!is_subclass_of($className, FormatterStrategyInterface::class)) {
            throw new \InvalidArgumentException(
                "Strategy class must implement FormatterStrategyInterface: {$className}"
            );
        }

        self::$strategyMap[$name] = $className;
        
        Log::info("Strategy registered: {$name}", ['class' => $className]);
    }

    /**
     * Unregister a strategy type
     *
     * @param string $name Strategy name
     * @return void
     */
    public static function unregister(string $name): void
    {
        unset(self::$strategyMap[$name]);
        
        // Clear cached instances
        self::clearCache($name);
        
        Log::info("Strategy unregistered: {$name}");
    }

    /**
     * Check if strategy is registered
     *
     * @param string $name Strategy name
     * @return bool
     */
    public static function isRegistered(string $name): bool
    {
        return isset(self::$strategyMap[$name]);
    }

    /**
     * Get all registered strategies
     *
     * @return array
     */
    public static function getRegisteredStrategies(): array
    {
        return array_keys(self::$strategyMap);
    }

    /**
     * Get strategy class name
     *
     * @param string $name Strategy name
     * @return string|null
     */
    public static function getStrategyClass(string $name): ?string
    {
        return self::$strategyMap[$name] ?? null;
    }

    /**
     * Set global configuration for a strategy
     *
     * @param string $name Strategy name
     * @param array $config Configuration
     * @return void
     */
    public static function setConfiguration(string $name, array $config): void
    {
        self::$configurations[$name] = $config;
    }

    /**
     * Get global configuration for a strategy
     *
     * @param string $name Strategy name
     * @return array
     */
    public static function getConfiguration(string $name): array
    {
        return self::$configurations[$name] ?? [];
    }

    /**
     * Create strategy with global configuration
     *
     * @param string $strategyName Strategy name
     * @param array $additionalConfig Additional configuration
     * @param bool $singleton Whether to use singleton pattern
     * @return FormatterStrategyInterface
     */
    public static function createWithGlobalConfig(
        string $strategyName, 
        array $additionalConfig = [], 
        bool $singleton = true
    ): FormatterStrategyInterface {
        $globalConfig = self::getConfiguration($strategyName);
        $mergedConfig = array_merge($globalConfig, $additionalConfig);
        
        return self::create($strategyName, $mergedConfig, $singleton);
    }

    /**
     * Create a strategy chain
     *
     * @param array $strategyChain Array of strategy names or configurations
     * @param bool $singleton Whether to use singleton pattern
     * @return array Array of strategy instances in order
     */
    public static function createChain(array $strategyChain, bool $singleton = true): array
    {
        $chain = [];
        
        foreach ($strategyChain as $index => $strategy) {
            if (is_string($strategy)) {
                $chain[] = self::create($strategy, [], $singleton);
            } elseif (is_array($strategy) && isset($strategy['name'])) {
                $name = $strategy['name'];
                $config = $strategy['config'] ?? [];
                $chain[] = self::create($name, $config, $singleton);
            } else {
                throw new \InvalidArgumentException("Invalid strategy configuration at index {$index}");
            }
        }
        
        return $chain;
    }

    /**
     * Create strategies by category
     *
     * @param string $category Strategy category
     * @param array $config Configuration
     * @param bool $singleton Whether to use singleton pattern
     * @return array Array of strategy instances
     */
    public static function createByCategory(string $category, array $config = [], bool $singleton = true): array
    {
        $categories = [
            'data_formatting' => ['dashboard_data', 'admin_data', 'puskesmas_data'],
            'export' => ['excel_export', 'pdf_export'],
            'processing' => ['calculation', 'validation'],
            'all' => array_keys(self::$strategyMap)
        ];

        if (!isset($categories[$category])) {
            throw new \InvalidArgumentException("Unknown category: {$category}");
        }

        $strategies = [];
        foreach ($categories[$category] as $strategyName) {
            $strategies[$strategyName] = self::create($strategyName, $config, $singleton);
        }
        
        return $strategies;
    }

    /**
     * Create strategy for specific use case
     *
     * @param string $useCase Use case name
     * @param array $config Configuration
     * @param bool $singleton Whether to use singleton pattern
     * @return array Array of strategy instances
     */
    public static function createForUseCase(string $useCase, array $config = [], bool $singleton = true): array
    {
        $useCases = [
            'dashboard' => ['validation', 'calculation', 'dashboard_data'],
            'admin_panel' => ['validation', 'calculation', 'admin_data'],
            'puskesmas_detail' => ['validation', 'calculation', 'puskesmas_data'],
            'excel_export' => ['validation', 'calculation', 'excel_export'],
            'pdf_export' => ['validation', 'calculation', 'pdf_export'],
            'data_analysis' => ['validation', 'calculation'],
            'basic_formatting' => ['dashboard_data'],
            'comprehensive_export' => ['validation', 'calculation', 'excel_export', 'pdf_export']
        ];

        if (!isset($useCases[$useCase])) {
            throw new \InvalidArgumentException("Unknown use case: {$useCase}");
        }

        $strategies = [];
        foreach ($useCases[$useCase] as $strategyName) {
            $strategies[$strategyName] = self::create($strategyName, $config, $singleton);
        }
        
        return $strategies;
    }

    /**
     * Clear strategy cache
     *
     * @param string|null $strategyName Specific strategy or all if null
     * @return void
     */
    public static function clearCache(?string $strategyName = null): void
    {
        if ($strategyName) {
            // Clear specific strategy instances
            $keysToRemove = array_filter(
                array_keys(self::$instances),
                fn($key) => strpos($key, $strategyName . '_') === 0
            );
            
            foreach ($keysToRemove as $key) {
                unset(self::$instances[$key]);
            }
            
            Log::info("Strategy cache cleared: {$strategyName}");
        } else {
            // Clear all instances
            self::$instances = [];
            Log::info('All strategy caches cleared');
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public static function getCacheStats(): array
    {
        $stats = [
            'total_cached_instances' => count(self::$instances),
            'cached_strategies' => [],
            'memory_usage' => 0
        ];

        foreach (self::$instances as $key => $instance) {
            $strategyName = explode('_', $key)[0];
            if (!isset($stats['cached_strategies'][$strategyName])) {
                $stats['cached_strategies'][$strategyName] = 0;
            }
            $stats['cached_strategies'][$strategyName]++;
        }

        return $stats;
    }

    /**
     * Validate strategy configuration
     *
     * @param string $strategyName Strategy name
     * @param array $config Configuration
     * @return array Validation result
     */
    public static function validateConfiguration(string $strategyName, array $config): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Check if strategy exists
        if (!self::isRegistered($strategyName)) {
            $result['valid'] = false;
            $result['errors'][] = "Strategy '{$strategyName}' is not registered";
            return $result;
        }

        // Get strategy class
        $strategyClass = self::$strategyMap[$strategyName];
        
        try {
            // Try to create instance to validate configuration
            $reflection = new \ReflectionClass($strategyClass);
            $constructor = $reflection->getConstructor();
            
            if ($constructor) {
                $parameters = $constructor->getParameters();
                
                foreach ($parameters as $parameter) {
                    $paramName = $parameter->getName();
                    
                    // Check required parameters
                    if (!$parameter->isOptional() && !isset($config[$paramName])) {
                        $paramType = $parameter->getType();
                        
                        // Skip if we can resolve from container
                        if ($paramType && !$paramType->isBuiltin()) {
                            $dependency = self::resolveDependency($paramType->getName(), $config);
                            if ($dependency !== null) {
                                continue;
                            }
                        }
                        
                        $result['valid'] = false;
                        $result['errors'][] = "Required parameter '{$paramName}' is missing";
                    }
                }
            }
            
        } catch (\Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = "Configuration validation failed: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get factory information
     *
     * @return array Factory information
     */
    public static function getFactoryInfo(): array
    {
        return [
            'registered_strategies' => self::getRegisteredStrategies(),
            'strategy_classes' => self::$strategyMap,
            'cached_instances' => count(self::$instances),
            'global_configurations' => array_keys(self::$configurations),
            'cache_stats' => self::getCacheStats()
        ];
    }

    /**
     * Reset factory to default state
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instances = [];
        self::$configurations = [];
        
        // Reset to default strategy mappings
        self::$strategyMap = [
            'dashboard_data' => DashboardDataStrategy::class,
            'admin_data' => AdminDataStrategy::class,
            'puskesmas_data' => PuskesmasDataStrategy::class,
            'excel_export' => ExcelExportStrategy::class,
            'pdf_export' => PdfExportStrategy::class,
            'calculation' => CalculationStrategy::class,
            'validation' => DataValidationStrategy::class,
        ];
        
        Log::info('Strategy factory reset to default state');
    }
}