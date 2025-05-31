<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Observers\HtExaminationObserver;
use App\Observers\DmExaminationObserver;
use App\Services\StatisticsCacheService;
use App\Services\ArchiveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        HtExamination::observe(HtExaminationObserver::class);
        DmExamination::observe(DmExaminationObserver::class);

        // Enable query logging in development
        if (config('app.debug')) {
            DB::enableQueryLog();
        }

        // Set default string length for MySQL
        Schema::defaultStringLength(191);

        // Add global scope to automatically eager load relationships
        Model::preventLazyLoading(!app()->isProduction());

        // Add global scope to automatically add withDefault() to belongsTo relationships
        Model::preventSilentlyDiscardingAttributes(!app()->isProduction());

        // Add global scope to automatically add withDefault() to belongsTo relationships
        Model::preventAccessingMissingAttributes(!app()->isProduction());
    }
}
