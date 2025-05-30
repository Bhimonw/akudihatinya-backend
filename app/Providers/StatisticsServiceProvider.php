<?php

namespace App\Providers;

use App\Services\StatisticsCalculationService;
use App\Services\StatisticsExportService;
use App\Services\StandardPatientCalculationService;
use Illuminate\Support\ServiceProvider;

class StatisticsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(StandardPatientCalculationService::class, function ($app) {
            return new StandardPatientCalculationService();
        });

        $this->app->singleton(StatisticsCalculationService::class, function ($app) {
            return new StatisticsCalculationService(
                $app->make(StandardPatientCalculationService::class)
            );
        });

        $this->app->singleton(StatisticsExportService::class, function ($app) {
            return new StatisticsExportService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
