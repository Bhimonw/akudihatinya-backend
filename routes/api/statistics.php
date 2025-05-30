<?php

use App\Http\Controllers\API\Shared\DashboardStatisticsController;
use App\Http\Controllers\API\Shared\ExportStatisticsController;
use App\Http\Controllers\API\Shared\MonitoringStatisticsController;
use App\Http\Controllers\API\Shared\AdminStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin_or_puskesmas'])->prefix('statistics')->group(function () {
    // Dashboard statistics
    Route::get('/dashboard', [DashboardStatisticsController::class, 'index']);

    // Admin statistics
    Route::get('/admin', [AdminStatisticsController::class, 'index']);

    // Export routes
    Route::get('/export', [ExportStatisticsController::class, 'export']);
    Route::get('/export/ht', [ExportStatisticsController::class, 'exportHt']);
    Route::get('/export/dm', [ExportStatisticsController::class, 'exportDm']);

    // Monitoring routes
    Route::get('/monitoring', [MonitoringStatisticsController::class, 'export']);

    // Monthly statistics export shortcut
    Route::get('/export/{year}/{month}', [ExportStatisticsController::class, 'export'])
        ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}']);
});
