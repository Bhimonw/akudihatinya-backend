<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Observers\HtExaminationObserver;
use App\Observers\DmExaminationObserver;
use App\Services\Statistics\StatisticsCacheService;
use App\Services\System\ArchiveService;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Services\Statistics\StatisticsService;
use App\Formatters\AdminAllFormatter;
use App\Formatters\PuskesmasFormatter;
use App\Services\Statistics\DiseaseStatisticsService;
use App\Services\Statistics\StatisticsDataService;
use App\Services\Statistics\StatisticsAdminService;
use App\Services\Statistics\RealTimeStatisticsService;
use App\Services\Statistics\OptimizedStatisticsService;
use App\Services\Export\StatisticsExportService;
use App\Services\Export\PuskesmasExportService;
use App\Services\System\MonitoringReportService;
use App\Services\System\NewYearSetupService;
use App\Services\Profile\ProfileUpdateService;
use App\Services\Profile\ProfilePictureService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use App\Repositories\PuskesmasRepository;
use App\Repositories\YearlyTargetRepository;
use App\Services\Export\PdfService;
use App\Formatters\PdfFormatter;
use App\Formatters\StatisticsFormatter;

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

        // Register StatisticsDataService with StatisticsService dependency
        $this->app->singleton(StatisticsDataService::class, function ($app) {
            return new StatisticsDataService(
                $app->make(StatisticsService::class)
            );
        });

        // Register StatisticsAdminService with StatisticsService dependency
        $this->app->singleton(StatisticsAdminService::class, function ($app) {
            return new StatisticsAdminService(
                $app->make(StatisticsService::class)
            );
        });

        // Register RealTimeStatisticsService
        $this->app->singleton(RealTimeStatisticsService::class, function ($app) {
            return new RealTimeStatisticsService();
        });

        // Register OptimizedStatisticsService with dependencies
        $this->app->singleton(OptimizedStatisticsService::class, function ($app) {
            return new OptimizedStatisticsService(
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

        // Register Export Services
        $this->app->singleton(StatisticsExportService::class, function ($app) {
            return new StatisticsExportService(
                $app->make(PdfService::class),
                $app->make(PuskesmasExportService::class),
                $app->make(StatisticsDataService::class),
                $app->make(AdminAllFormatter::class),
                $app->make(AdminMonthlyFormatter::class),
                $app->make(AdminQuarterlyFormatter::class)
            );
        });

        $this->app->singleton(PuskesmasExportService::class, function ($app) {
            return new PuskesmasExportService(
                $app->make(StatisticsService::class),
                $app->make(PuskesmasFormatter::class)
            );
        });

        // Register System Services
        $this->app->singleton(MonitoringReportService::class, function ($app) {
            return new MonitoringReportService(
                $app->make(PdfService::class),
                $app->make(StatisticsDataService::class)
            );
        });

        $this->app->singleton(NewYearSetupService::class, function ($app) {
            return new NewYearSetupService();
        });

        // Register Profile Services
        $this->app->singleton(ProfileUpdateService::class, function ($app) {
            return new ProfileUpdateService(
                $app->make(ProfilePictureService::class)
            );
        });

        $this->app->singleton(ProfilePictureService::class, function ($app) {
            return new ProfilePictureService();
        });

        // Register PdfFormatter
        $this->app->singleton(PdfFormatter::class, function ($app) {
            return new PdfFormatter(
                $app->make(StatisticsService::class)
            );
        });

        // Register PdfService with StatisticsAdminService dependency
        $this->app->singleton(PdfService::class, function ($app) {
            return new PdfService(
                $app->make(StatisticsAdminService::class),
                $app->make(PdfFormatter::class)
            );
        });

        // Register formatters with StatisticsService dependency
        $this->app->singleton(AdminAllFormatter::class, function ($app) {
            return new AdminAllFormatter(
                $app->make(StatisticsService::class),
                $app->make(RealTimeStatisticsService::class)
            );
        });

        $this->app->singleton(AdminMonthlyFormatter::class, function ($app) {
            return new AdminMonthlyFormatter($app->make(StatisticsService::class));
        });

        $this->app->singleton(AdminQuarterlyFormatter::class, function ($app) {
            return new AdminQuarterlyFormatter($app->make(StatisticsService::class));
        });

        $this->app->singleton(PuskesmasFormatter::class, function ($app) {
            return new PuskesmasFormatter($app->make(StatisticsService::class));
        });

        // Register StatisticsFormatter with all statistics services
        $this->app->singleton(StatisticsFormatter::class, function ($app) {
            return new StatisticsFormatter(
                $app->make(StatisticsService::class),
                $app->make(StatisticsDataService::class),
                $app->make(StatisticsAdminService::class),
                $app->make(OptimizedStatisticsService::class),
                $app->make(RealTimeStatisticsService::class),
                $app->make(DiseaseStatisticsService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom rate limiter for login attempts: 10 per minute per IP + username
        RateLimiter::for('login', function (Request $request) {
            $username = (string) $request->input('username');
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($username.'|'.$request->ip());
        });

        // Export limiter: protect heavy PDF/Excel generation (5 per minute per user/IP)
        RateLimiter::for('exports', function (Request $request) {
            $key = ($request->user()->id ?? 'guest').'|'.$request->ip();
            return Limit::perMinute(5)->by($key);
        });
        // Register observers
        HtExamination::observe(HtExaminationObserver::class);
        DmExamination::observe(DmExaminationObserver::class);
        
        // Add PDF templates directory as a view location
        view()->addLocation(resource_path('pdf'));
        
        // Add PDF namespace for template access
        view()->addNamespace('pdf', resource_path('views/pdf'));

        // Share dynamically built frontend asset filenames with SPA view (robust discovery)
        try {
            // Updated path: build output langsung ke public/assets (tanpa subfolder spa)
            $frontendPath = public_path('assets');
            $cssFile = null; $jsFile = null;

            if (is_dir($frontendPath)) {
                $candidatesCss = glob($frontendPath . DIRECTORY_SEPARATOR . 'index-*.css') ?: [];
                $candidatesJs  = glob($frontendPath . DIRECTORY_SEPARATOR . 'index-*.js') ?: [];

                // Pick newest (mtime) to avoid stale multiple hashes after partial clean
                usort($candidatesCss, fn($a,$b)=> filemtime($b) <=> filemtime($a));
                usort($candidatesJs, fn($a,$b)=> filemtime($b) <=> filemtime($a));
                $cssFile = $candidatesCss[0] ?? null;
                $jsFile  = $candidatesJs[0] ?? null;
            }

            // Allow override via env (useful for CDN fronting) FRONTEND_CSS / FRONTEND_JS
            if (env('FRONTEND_CSS')) {
                $cssUrl = env('FRONTEND_CSS');
            } elseif ($cssFile) {
                $cssUrl = asset('assets/' . basename($cssFile));
            } else {
                $cssUrl = null; // purposely null so Blade can branch
            }

            if (env('FRONTEND_JS')) {
                $jsUrl = env('FRONTEND_JS');
            } elseif ($jsFile) {
                $jsUrl = asset('assets/' . basename($jsFile));
            } else {
                $jsUrl = null;
            }

            $versionSeed = ($cssFile && file_exists($cssFile) ? filemtime($cssFile) : '') . '|' . ($jsFile && file_exists($jsFile) ? filemtime($jsFile) : '');
            $version = $versionSeed ? substr(md5($versionSeed), 0, 12) : 'dev';

            view()->share('frontendAssets', [
                'css' => $cssUrl,
                'js' => $jsUrl,
                'version' => $version,
            ]);
        } catch (\Throwable $e) {
            view()->share('frontendAssets', [
                'css' => null,
                'js' => null,
                'version' => 'dev-error'
            ]);
        }
    }
}
