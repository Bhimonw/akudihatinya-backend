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
 * FormatterContext - Context class untuk Strategy Pattern
 * Mengelola dan mengkoordinasikan berbagai formatter strategies
 */
class FormatterContext
{
    /**
     * @var array Registered strategies
     */
    private array $strategies = [];

    /**
     * @var array Default options
     */
    private array $defaultOptions = [
        'validate_input' => true,
        'calculate_data' => true,
        'log_operations' => true,
        'cache_results' => false,
        'parallel_processing' => false
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registerDefaultStrategies();
    }

    /**
     * Register default strategies
     */
    private function registerDefaultStrategies(): void
    {
        // Data formatting strategies
        $this->registerStrategy('dashboard_data', new DashboardDataStrategy());
        $this->registerStrategy('admin_data', new AdminDataStrategy());
        $this->registerStrategy('puskesmas_data', new PuskesmasDataStrategy());
        
        // Export strategies
        $this->registerStrategy('excel_export', new ExcelExportStrategy());
        $this->registerStrategy('pdf_export', new PdfExportStrategy());
        
        // Calculation strategy
        $this->registerStrategy('calculation', new CalculationStrategy());
        
        // Validation strategy
        $this->registerStrategy('validation', new DataValidationStrategy());
    }

    /**
     * Register a strategy
     *
     * @param string $name Strategy name
     * @param FormatterStrategyInterface $strategy Strategy instance
     * @return self
     */
    public function registerStrategy(string $name, FormatterStrategyInterface $strategy): self
    {
        $this->strategies[$name] = $strategy;
        return $this;
    }

    /**
     * Get a strategy by name
     *
     * @param string $name Strategy name
     * @return FormatterStrategyInterface
     * @throws \InvalidArgumentException
     */
    public function getStrategy(string $name): FormatterStrategyInterface
    {
        if (!isset($this->strategies[$name])) {
            throw new \InvalidArgumentException("Strategy '{$name}' not found");
        }

        return $this->strategies[$name];
    }

    /**
     * Check if strategy exists
     *
     * @param string $name Strategy name
     * @return bool
     */
    public function hasStrategy(string $name): bool
    {
        return isset($this->strategies[$name]);
    }

    /**
     * Get all registered strategies
     *
     * @return array
     */
    public function getStrategies(): array
    {
        return array_keys($this->strategies);
    }

    /**
     * Execute a single strategy
     *
     * @param string $strategyName Strategy name
     * @param array $data Input data
     * @param array $options Strategy options
     * @return array Result
     */
    public function executeStrategy(string $strategyName, array $data, array $options = []): array
    {
        $strategy = $this->getStrategy($strategyName);
        $options = array_merge($this->defaultOptions, $options);

        if ($options['log_operations']) {
            Log::info("Executing strategy: {$strategyName}", [
                'data_count' => count($data),
                'options' => $options
            ]);
        }

        try {
            $result = $strategy->execute($data, $options);
            
            if ($options['log_operations']) {
                Log::info("Strategy executed successfully: {$strategyName}");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            if ($options['log_operations']) {
                Log::error("Strategy execution failed: {$strategyName}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            throw $e;
        }
    }

    /**
     * Execute multiple strategies in sequence
     *
     * @param array $strategies Array of strategy configurations
     * @param array $data Input data
     * @param array $globalOptions Global options
     * @return array Combined results
     */
    public function executeStrategies(array $strategies, array $data, array $globalOptions = []): array
    {
        $results = [];
        $currentData = $data;
        $globalOptions = array_merge($this->defaultOptions, $globalOptions);

        foreach ($strategies as $config) {
            $strategyName = $config['name'] ?? null;
            $strategyOptions = array_merge($globalOptions, $config['options'] ?? []);
            $useResult = $config['use_result'] ?? false;

            if (!$strategyName) {
                throw new \InvalidArgumentException('Strategy name is required');
            }

            // Use result from previous strategy if specified
            if ($useResult && !empty($results)) {
                $lastResult = end($results);
                if (isset($lastResult['data'])) {
                    $currentData = $lastResult['data'];
                } elseif (isset($lastResult['results'])) {
                    $currentData = $lastResult['results'];
                }
            }

            $result = $this->executeStrategy($strategyName, $currentData, $strategyOptions);
            $results[$strategyName] = $result;
        }

        return [
            'success' => true,
            'strategies_executed' => array_keys($results),
            'results' => $results,
            'metadata' => [
                'executed_at' => now()->toISOString(),
                'total_strategies' => count($strategies),
                'global_options' => $globalOptions
            ]
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
        $strategies = [
            ['name' => 'validation', 'options' => ['validation_type' => 'basic']],
            ['name' => 'calculation', 'options' => ['calculation_type' => 'basic'], 'use_result' => false],
            ['name' => 'dashboard_data', 'options' => $options, 'use_result' => false]
        ];

        $result = $this->executeStrategies($strategies, $data, $options);
        
        return [
            'success' => true,
            'data' => $result['results']['dashboard_data']['data'] ?? [],
            'validation' => $result['results']['validation'] ?? [],
            'calculations' => $result['results']['calculation'] ?? [],
            'metadata' => $result['metadata']
        ];
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
        $strategies = [
            ['name' => 'validation', 'options' => ['validation_type' => 'comprehensive']],
            ['name' => 'calculation', 'options' => ['calculation_type' => 'comprehensive'], 'use_result' => false],
            ['name' => 'admin_data', 'options' => $options, 'use_result' => false]
        ];

        $result = $this->executeStrategies($strategies, $data, $options);
        
        return [
            'success' => true,
            'data' => $result['results']['admin_data']['data'] ?? [],
            'validation' => $result['results']['validation'] ?? [],
            'calculations' => $result['results']['calculation'] ?? [],
            'metadata' => $result['metadata']
        ];
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
        $strategies = [
            ['name' => 'validation', 'options' => ['validation_type' => 'comprehensive']],
            ['name' => 'calculation', 'options' => ['calculation_type' => 'comprehensive'], 'use_result' => false],
            ['name' => 'puskesmas_data', 'options' => $options, 'use_result' => false]
        ];

        $result = $this->executeStrategies($strategies, $data, $options);
        
        return [
            'success' => true,
            'data' => $result['results']['puskesmas_data']['data'] ?? [],
            'validation' => $result['results']['validation'] ?? [],
            'calculations' => $result['results']['calculation'] ?? [],
            'metadata' => $result['metadata']
        ];
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
        $strategies = [];
        
        // Add validation if requested
        if ($options['validate_input'] ?? true) {
            $strategies[] = ['name' => 'validation', 'options' => ['validation_type' => 'basic']];
        }
        
        // Add calculation if requested
        if ($options['calculate_data'] ?? true) {
            $strategies[] = ['name' => 'calculation', 'options' => ['calculation_type' => 'comprehensive'], 'use_result' => false];
        }
        
        // Add Excel export
        $strategies[] = ['name' => 'excel_export', 'options' => $options, 'use_result' => false];

        $result = $this->executeStrategies($strategies, $data, $options);
        
        return [
            'success' => true,
            'file_path' => $result['results']['excel_export']['file_path'] ?? null,
            'file_name' => $result['results']['excel_export']['file_name'] ?? null,
            'validation' => $result['results']['validation'] ?? null,
            'calculations' => $result['results']['calculation'] ?? null,
            'metadata' => $result['metadata']
        ];
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
        $strategies = [];
        
        // Add validation if requested
        if ($options['validate_input'] ?? true) {
            $strategies[] = ['name' => 'validation', 'options' => ['validation_type' => 'basic']];
        }
        
        // Add calculation if requested
        if ($options['calculate_data'] ?? true) {
            $strategies[] = ['name' => 'calculation', 'options' => ['calculation_type' => 'comprehensive'], 'use_result' => false];
        }
        
        // Add PDF export
        $strategies[] = ['name' => 'pdf_export', 'options' => $options, 'use_result' => false];

        $result = $this->executeStrategies($strategies, $data, $options);
        
        return [
            'success' => true,
            'file_path' => $result['results']['pdf_export']['file_path'] ?? null,
            'file_name' => $result['results']['pdf_export']['file_name'] ?? null,
            'validation' => $result['results']['validation'] ?? null,
            'calculations' => $result['results']['calculation'] ?? null,
            'metadata' => $result['metadata']
        ];
    }

    /**
     * Validate data only
     *
     * @param array $data Input data
     * @param array $options Validation options
     * @return array Validation result
     */
    public function validateData(array $data, array $options = []): array
    {
        return $this->executeStrategy('validation', $data, $options);
    }

    /**
     * Calculate data only
     *
     * @param array $data Input data
     * @param array $options Calculation options
     * @return array Calculation result
     */
    public function calculateData(array $data, array $options = []): array
    {
        return $this->executeStrategy('calculation', $data, $options);
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
        $strategies = [
            ['name' => 'validation', 'options' => ['validation_type' => $options['validation_type'] ?? 'comprehensive']],
            ['name' => 'calculation', 'options' => ['calculation_type' => $options['calculation_type'] ?? 'comprehensive'], 'use_result' => false]
        ];

        $result = $this->executeStrategies($strategies, $data, $options);
        
        return [
            'success' => true,
            'validation' => $result['results']['validation'],
            'calculations' => $result['results']['calculation'],
            'is_valid' => $result['results']['validation']['is_valid'] ?? false,
            'metadata' => $result['metadata']
        ];
    }

    /**
     * Get comprehensive data analysis
     *
     * @param array $data Input data
     * @param array $options Analysis options
     * @return array Analysis result
     */
    public function analyzeData(array $data, array $options = []): array
    {
        $analysisOptions = array_merge([
            'validation_type' => 'comprehensive',
            'calculation_type' => 'advanced',
            'include_achievement_analysis' => true,
            'include_trend_analysis' => true,
            'include_comparison' => true
        ], $options);

        $strategies = [
            ['name' => 'validation', 'options' => ['validation_type' => $analysisOptions['validation_type']]],
            ['name' => 'calculation', 'options' => $analysisOptions, 'use_result' => false]
        ];

        $result = $this->executeStrategies($strategies, $data, $analysisOptions);
        
        return [
            'success' => true,
            'validation' => $result['results']['validation'],
            'analysis' => $result['results']['calculation'],
            'is_valid' => $result['results']['validation']['is_valid'] ?? false,
            'recommendations' => $result['results']['calculation']['summary']['achievement']['recommendations'] ?? [],
            'metadata' => $result['metadata']
        ];
    }

    /**
     * Create a custom processing pipeline
     *
     * @param array $pipeline Pipeline configuration
     * @param array $data Input data
     * @param array $options Global options
     * @return array Pipeline result
     */
    public function executePipeline(array $pipeline, array $data, array $options = []): array
    {
        $strategies = [];
        
        foreach ($pipeline as $step) {
            if (is_string($step)) {
                $strategies[] = ['name' => $step];
            } elseif (is_array($step)) {
                $strategies[] = $step;
            } else {
                throw new \InvalidArgumentException('Invalid pipeline step configuration');
            }
        }

        return $this->executeStrategies($strategies, $data, $options);
    }

    /**
     * Get strategy performance metrics
     *
     * @param string $strategyName Strategy name
     * @return array Performance metrics
     */
    public function getStrategyMetrics(string $strategyName): array
    {
        // This would be implemented with actual performance tracking
        return [
            'strategy_name' => $strategyName,
            'execution_count' => 0,
            'average_execution_time' => 0,
            'success_rate' => 100,
            'last_executed' => null
        ];
    }

    /**
     * Get all strategies performance metrics
     *
     * @return array All metrics
     */
    public function getAllMetrics(): array
    {
        $metrics = [];
        
        foreach (array_keys($this->strategies) as $strategyName) {
            $metrics[$strategyName] = $this->getStrategyMetrics($strategyName);
        }
        
        return $metrics;
    }

    /**
     * Clear strategy cache (if caching is implemented)
     *
     * @param string|null $strategyName Specific strategy or all if null
     * @return self
     */
    public function clearCache(?string $strategyName = null): self
    {
        // This would be implemented with actual caching mechanism
        if ($strategyName) {
            Log::info("Clearing cache for strategy: {$strategyName}");
        } else {
            Log::info('Clearing all strategy caches');
        }
        
        return $this;
    }

    /**
     * Get context information
     *
     * @return array Context info
     */
    public function getContextInfo(): array
    {
        return [
            'registered_strategies' => $this->getStrategies(),
            'default_options' => $this->defaultOptions,
            'total_strategies' => count($this->strategies),
            'strategy_types' => [
                'data_formatting' => ['dashboard_data', 'admin_data', 'puskesmas_data'],
                'export' => ['excel_export', 'pdf_export'],
                'processing' => ['calculation', 'validation']
            ]
        ];
    }

    /**
     * Set default options
     *
     * @param array $options Default options
     * @return self
     */
    public function setDefaultOptions(array $options): self
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
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
}