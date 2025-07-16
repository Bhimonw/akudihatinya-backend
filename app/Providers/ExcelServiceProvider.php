<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Formatters\ExcelExportFormatter;
use App\Formatters\Helpers\ColumnManager;
use App\Formatters\Calculators\StatisticsCalculator;
use App\Formatters\Validators\ExcelDataValidator;
use App\Formatters\Builders\ExcelStyleBuilder;
use App\Constants\ExcelConstants;
use Illuminate\Contracts\Foundation\Application;

class ExcelServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register Excel configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/excel.php', 'excel'
        );

        // Register ExcelConstants as singleton
        $this->app->singleton(ExcelConstants::class, function (Application $app) {
            return new ExcelConstants();
        });

        // Register ColumnManager as singleton
        $this->app->singleton(ColumnManager::class, function (Application $app) {
            return new ColumnManager();
        });

        // Register StatisticsCalculator as singleton
        $this->app->singleton(StatisticsCalculator::class, function (Application $app) {
            return new StatisticsCalculator();
        });

        // Register ExcelDataValidator as singleton
        $this->app->singleton(ExcelDataValidator::class, function (Application $app) {
            return new ExcelDataValidator(
                config('excel.validation', []),
                config('excel.performance.limits', [])
            );
        });

        // Register ExcelStyleBuilder as singleton
        $this->app->singleton(ExcelStyleBuilder::class, function (Application $app) {
            return new ExcelStyleBuilder(
                config('excel.styling', [])
            );
        });

        // Register ExcelExportFormatter with dependencies
        $this->app->singleton(ExcelExportFormatter::class, function (Application $app) {
            return new ExcelExportFormatter(
                $app->make(ColumnManager::class),
                $app->make(StatisticsCalculator::class),
                $app->make(ExcelDataValidator::class),
                $app->make(ExcelStyleBuilder::class)
            );
        });

        // Register aliases for easier access
        $this->app->alias(ExcelExportFormatter::class, 'excel.formatter');
        $this->app->alias(ColumnManager::class, 'excel.column_manager');
        $this->app->alias(StatisticsCalculator::class, 'excel.calculator');
        $this->app->alias(ExcelDataValidator::class, 'excel.validator');
        $this->app->alias(ExcelStyleBuilder::class, 'excel.style_builder');
        $this->app->alias(ExcelConstants::class, 'excel.constants');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/excel.php' => config_path('excel.php'),
            ], 'excel-config');

            // Publish template files if they exist
            $templatePath = base_path('resources/templates/excel');
            if (is_dir($templatePath)) {
                $this->publishes([
                    $templatePath => storage_path('app/templates'),
                ], 'excel-templates');
            }
        }

        // Register custom validation rules if needed
        $this->registerValidationRules();

        // Register custom macros for collections
        $this->registerCollectionMacros();

        // Set up event listeners for Excel operations
        $this->registerEventListeners();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            ExcelExportFormatter::class,
            ColumnManager::class,
            StatisticsCalculator::class,
            ExcelDataValidator::class,
            ExcelStyleBuilder::class,
            ExcelConstants::class,
            'excel.formatter',
            'excel.column_manager',
            'excel.calculator',
            'excel.validator',
            'excel.style_builder',
            'excel.constants',
        ];
    }

    /**
     * Register custom validation rules for Excel data.
     *
     * @return void
     */
    protected function registerValidationRules()
    {
        \Illuminate\Support\Facades\Validator::extend('excel_column', function ($attribute, $value, $parameters, $validator) {
            return ColumnManager::isValidColumn($value);
        }, 'The :attribute must be a valid Excel column.');

        \Illuminate\Support\Facades\Validator::extend('excel_range', function ($attribute, $value, $parameters, $validator) {
            return ColumnManager::isValidRange($value);
        }, 'The :attribute must be a valid Excel range.');

        \Illuminate\Support\Facades\Validator::extend('puskesmas_data', function ($attribute, $value, $parameters, $validator) {
            $validator = app(ExcelDataValidator::class);
            return $validator->validatePuskesmasData($value);
        }, 'The :attribute contains invalid puskesmas data.');
    }

    /**
     * Register custom collection macros for Excel operations.
     *
     * @return void
     */
    protected function registerCollectionMacros()
    {
        \Illuminate\Support\Collection::macro('toExcelData', function () {
            return $this->map(function ($item) {
                if (is_array($item)) {
                    return array_values($item);
                }
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return array_values($item->toArray());
                }
                return [$item];
            })->toArray();
        });

        \Illuminate\Support\Collection::macro('calculateTotals', function ($columns = []) {
            $calculator = app(StatisticsCalculator::class);
            return $calculator->calculateTotalsFromCollection($this, $columns);
        });

        \Illuminate\Support\Collection::macro('validateForExcel', function () {
            $validator = app(ExcelDataValidator::class);
            return $this->every(function ($item) use ($validator) {
                return $validator->validateDataStructure($item);
            });
        });
    }

    /**
     * Register event listeners for Excel operations.
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        // Listen for Excel export events
        \Illuminate\Support\Facades\Event::listen('excel.export.started', function ($data) {
            \Illuminate\Support\Facades\Log::info('Excel export started', [
                'type' => $data['type'] ?? 'unknown',
                'timestamp' => now(),
                'memory_usage' => memory_get_usage(true),
            ]);
        });

        \Illuminate\Support\Facades\Event::listen('excel.export.completed', function ($data) {
            \Illuminate\Support\Facades\Log::info('Excel export completed', [
                'type' => $data['type'] ?? 'unknown',
                'file_size' => $data['file_size'] ?? 0,
                'duration' => $data['duration'] ?? 0,
                'timestamp' => now(),
                'memory_peak' => memory_get_peak_usage(true),
            ]);
        });

        \Illuminate\Support\Facades\Event::listen('excel.export.failed', function ($data) {
            \Illuminate\Support\Facades\Log::error('Excel export failed', [
                'type' => $data['type'] ?? 'unknown',
                'error' => $data['error'] ?? 'Unknown error',
                'timestamp' => now(),
                'memory_usage' => memory_get_usage(true),
            ]);
        });
    }
}