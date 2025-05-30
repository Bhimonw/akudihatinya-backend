<?php

namespace App\Providers;

use App\Services\StatisticsCalculationService;
use App\Services\StatisticsExportService;
use Illuminate\Support\ServiceProvider;

class StatisticsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(StatisticsCalculationService::class, function ($app) {
            return new StatisticsCalculationService();
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
