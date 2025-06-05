<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Observers\HtExaminationObserver;
use App\Observers\DmExaminationObserver;
use App\Services\StatisticsCacheService;
use App\Services\ArchiveService;
use App\Formatters\AdminMonthlyFormatter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services
        $this->app->singleton(StatisticsCacheService::class, function ($app) {
            return new StatisticsCacheService();
        });

        $this->app->singleton(ArchiveService::class, function ($app) {
            return new ArchiveService();
        });

        // Register formatters
        $this->app->singleton(AdminMonthlyFormatter::class, function ($app) {
            return new AdminMonthlyFormatter($app->make(\App\Services\StatisticsService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        HtExamination::observe(HtExaminationObserver::class);
        DmExamination::observe(DmExaminationObserver::class);
    }
}
