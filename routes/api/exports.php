<?php

use App\Http\Controllers\API\Shared\StatisticsController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmas;

// Export routes
Route::middleware(['auth:sanctum', AdminOrPuskesmas::class])->prefix('exports')->group(function () {
    // Statistics export endpoints
    Route::get('/statistics', [StatisticsController::class, 'exportStatistics']);
    Route::get('/statistics/admin', [StatisticsController::class, 'exportStatistics']);
    
    // Export utility endpoints
    Route::get('/years', [StatisticsController::class, 'getAvailableYears']);
    Route::get('/puskesmas', [StatisticsController::class, 'getPuskesmasList']);
    Route::get('/options', [StatisticsController::class, 'getExportOptions']);
    
    // Puskesmas PDF export endpoints
    Route::post('/puskesmas-pdf', [StatisticsController::class, 'exportPuskesmasPdf']);
    Route::post('/puskesmas-quarterly-pdf', [StatisticsController::class, 'exportPuskesmasQuarterlyPdf']);
    
    // Monthly statistics export
    Route::get('/{year}/{month}', [StatisticsController::class, 'exportStatistics'])
        ->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');
    
    // Monitoring report export
    Route::get('/monitoring', [StatisticsController::class, 'exportMonitoringReport']);
    Route::get('/monitoring/{year}/{month}', [StatisticsController::class, 'exportMonitoringReport'])
        ->where('year', '[0-9]{4}')
        ->where('month', '[0-9]{1,2}');
});

// Patient export routes (Puskesmas only)
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    Route::get('/patients-export', [PatientController::class, 'export']);
    Route::get('/patients-export-excel', [PatientController::class, 'exportToExcel']);
});