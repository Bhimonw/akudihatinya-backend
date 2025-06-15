<?php

use App\Http\Controllers\API\Shared\StatisticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmas;

// Akun sendiri (protected)
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/me', [\App\Http\Controllers\API\Shared\UserController::class, 'me']);
    Route::put('/me', [\App\Http\Controllers\API\Shared\UserController::class, 'updateMe']);
});

// Statistics routes with combined middleware
Route::middleware(['auth:sanctum', AdminOrPuskesmas::class])->prefix('statistics')->group(function () {
    // Statistics for Dashboard (used by dashboard controllers)
    Route::get('/dashboard-statistics', [StatisticsController::class, 'dashboardStatistics']);

    Route::get('/admin', [StatisticsController::class, 'adminStatistics']);
    Route::get('/admin/export', [StatisticsController::class, 'exportStatistics']);

    // Laporan Bulanan dan Tahunan
    Route::get('/', [StatisticsController::class, 'index']);
    Route::get('/ht', [StatisticsController::class, 'htStatistics']);
    Route::get('/dm', [StatisticsController::class, 'dmStatistics']);

    // Export laporan bulanan dan tahunan
    Route::get('/export', [StatisticsController::class, 'exportStatistics']);
    Route::get('/export/ht', [StatisticsController::class, 'exportHtStatistics']);
    Route::get('/export/dm', [StatisticsController::class, 'exportDmStatistics']);

    // Helper endpoints for export
    Route::get('/export/years', [StatisticsController::class, 'getAvailableYears']);
    Route::get('/export/puskesmas', [StatisticsController::class, 'getPuskesmasList']);
    Route::get('/export/options', [StatisticsController::class, 'getExportOptions']);

    // Monthly statistics export shortcuts
    Route::get('/{year}/{month}/export', [StatisticsController::class, 'exportStatistics'])
        ->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');

    /** @var StatisticsController $controller */
    Route::get('/ht/{year}/{month}/export', function ($year, $month) {
        $controller = app(StatisticsController::class);
        return $controller->exportStatistics(
            request()->merge(['year' => $year, 'month' => $month, 'type' => 'ht'])
        );
    })->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');

    /** @var StatisticsController $controller */
    Route::get('/dm/{year}/{month}/export', function ($year, $month) {
        $controller = app(StatisticsController::class);
        return $controller->exportStatistics(
            request()->merge(['year' => $year, 'month' => $month, 'type' => 'dm'])
        );
    })->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');

    // Laporan Pemantauan Pasien (dengan checklist kedatangan)
    Route::get('/monitoring', [StatisticsController::class, 'exportMonitoringReport']);

    /** @var StatisticsController $controller */
    Route::get('/monitoring/ht', function (Request $request) {
        $controller = app(StatisticsController::class);
        return $controller->exportMonitoringReport(
            $request->merge(['type' => 'ht'])
        );
    });

    /** @var StatisticsController $controller */
    Route::get('/monitoring/dm', function (Request $request) {
        $controller = app(StatisticsController::class);
        return $controller->exportMonitoringReport(
            $request->merge(['type' => 'dm'])
        );
    });

    // Monthly monitoring export shortcuts
    /** @var StatisticsController $controller */
    Route::get('/monitoring/{year}/{month}', function ($year, $month, Request $request) {
        $controller = app(StatisticsController::class);
        return $controller->exportMonitoringReport(
            $request->merge(['year' => $year, 'month' => $month])
        );
    })->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');

    /** @var StatisticsController $controller */
    Route::get('/monitoring/ht/{year}/{month}', function ($year, $month, Request $request) {
        $controller = app(StatisticsController::class);
        return $controller->exportMonitoringReport(
            $request->merge(['year' => $year, 'month' => $month, 'type' => 'ht'])
        );
    })->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');

    /** @var StatisticsController $controller */
    Route::get('/monitoring/dm/{year}/{month}', function ($year, $month, Request $request) {
        $controller = app(StatisticsController::class);
        return $controller->exportMonitoringReport(
            $request->merge(['year' => $year, 'month' => $month, 'type' => 'dm'])
        );
    })->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');
});
