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

    // Export endpoints
    Route::get('/export', [StatisticsController::class, 'exportStatistics']);

    // Export utility endpoints
    Route::get('/export/years', [StatisticsController::class, 'getAvailableYears']);
    Route::get('/export/puskesmas', [StatisticsController::class, 'getPuskesmasList']);
    Route::get('/export/options', [StatisticsController::class, 'getExportOptions']);

    // Puskesmas PDF export endpoints
    Route::post('/export/puskesmas-pdf', [StatisticsController::class, 'exportPuskesmasPdf']);
    Route::post('/export/puskesmas-quarterly-pdf', [StatisticsController::class, 'exportPuskesmasQuarterlyPdf']);

    // Monthly statistics export
    Route::get('/{year}/{month}/export', [StatisticsController::class, 'exportStatistics'])
        ->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');

    // Monitoring report export
    Route::get('/monitoring', [StatisticsController::class, 'exportMonitoringReport']);
    Route::get('/monitoring/{year}/{month}', [StatisticsController::class, 'exportMonitoringReport'])
        ->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');
});
