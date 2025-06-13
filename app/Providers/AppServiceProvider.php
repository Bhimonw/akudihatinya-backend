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
use App\Formatters\AdminQuarterlyFormatter;
use App\Services\StatisticsService;
use App\Formatters\AdminAllFormatter;
use App\Services\DiseaseStatisticsService;
use App\Repositories\PuskesmasRepository;
use App\Repositories\YearlyTargetRepository;
use App\Services\DashboardPdfService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repositories first
        $this->app->singleton(PuskesmasRepository::class, function ($app) {
            return new PuskesmasRepository();
        });

        $this->app->singleton(YearlyTargetRepository::class, function ($app) {
            return new YearlyTargetRepository();
        });

        // Register DiseaseStatisticsService with its dependency
        $this->app->singleton(DiseaseStatisticsService::class, function ($app) {
            return new DiseaseStatisticsService(
                $app->make(YearlyTargetRepository::class)
            );
        });

        // Register StatisticsService with all required dependencies
        $this->app->singleton(StatisticsService::class, function ($app) {
            return new StatisticsService(
                $app->make(PuskesmasRepository::class),
                $app->make(YearlyTargetRepository::class),
                $app->make(DiseaseStatisticsService::class)
            );
        });

        // Register services
        $this->app->singleton(StatisticsCacheService::class, function ($app) {
            return new StatisticsCacheService();
        });

        $this->app->singleton(ArchiveService::class, function ($app) {
            return new ArchiveService();
        });

        // Register DashboardPdfService with StatisticsService dependency
        $this->app->singleton(DashboardPdfService::class, function ($app) {
            return new DashboardPdfService(
                $app->make(StatisticsService::class)
            );
        });

        // Register formatters with StatisticsService dependency
        $this->app->singleton(AdminAllFormatter::class, function ($app) {
            return new AdminAllFormatter($app->make(StatisticsService::class));
        });

        $this->app->singleton(AdminMonthlyFormatter::class, function ($app) {
            return new AdminMonthlyFormatter($app->make(StatisticsService::class));
        });

        $this->app->singleton(AdminQuarterlyFormatter::class, function ($app) {
            return new AdminQuarterlyFormatter($app->make(StatisticsService::class));
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
        
        // Add PDF templates directory as a view location
        view()->addLocation(resource_path('pdf'));
    }
}
