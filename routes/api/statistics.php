<?php

use App\Http\Controllers\API\Shared\StatisticsController;
use App\Http\Controllers\Api\StatisticsFormatterController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmasMiddleware;

// Statistics routes (data retrieval only)
Route::middleware(['auth:sanctum', AdminOrPuskesmasMiddleware::class])->prefix('statistics')->group(function () {
    // Admin statistics
    Route::get('/admin', [StatisticsController::class, 'adminStatistics']);
    
    // General statistics endpoints
    Route::get('/', [StatisticsController::class, 'index']);
    Route::get('/ht', [StatisticsController::class, 'htStatistics']);
    Route::get('/dm', [StatisticsController::class, 'dmStatistics']);
});

// Statistics Formatter routes (formatted data with all services)
Route::middleware(['auth:sanctum', AdminOrPuskesmasMiddleware::class])->prefix('statistics-formatter')->group(function () {
    // Dashboard statistics
    Route::get('/dashboard', [StatisticsFormatterController::class, 'getDashboardStatistics']);
    
    // Admin statistics with optimization
    Route::get('/admin', [StatisticsFormatterController::class, 'getAdminStatistics']);
    
    // Optimized statistics with caching
    Route::get('/optimized', [StatisticsFormatterController::class, 'getOptimizedStatistics']);
    
    // Disease-specific statistics
    Route::get('/ht', [StatisticsFormatterController::class, 'getHtStatistics']);
    Route::get('/dm', [StatisticsFormatterController::class, 'getDmStatistics']);
    
    // Monthly and summary data
    Route::get('/monthly', [StatisticsFormatterController::class, 'getMonthlyData']);
    Route::get('/summary', [StatisticsFormatterController::class, 'getSummaryStatistics']);
    
    // Chart data
    Route::get('/chart', [StatisticsFormatterController::class, 'getChartData']);
    
    // Real-time updates
    Route::post('/realtime/update', [StatisticsFormatterController::class, 'updateRealTimeStatistics']);
    
    // Parameter validation
    Route::post('/validate', [StatisticsFormatterController::class, 'validateParameters']);
    
    // PDF Export endpoints
    Route::get('/pdf/puskesmas', [StatisticsFormatterController::class, 'getPdfPuskesmasData']);
    Route::get('/pdf/quarters-recap', [StatisticsFormatterController::class, 'getPdfQuartersRecapData']);
    
    // Excel Export endpoints
    Route::get('/excel/all', [StatisticsFormatterController::class, 'getExcelAllData']);
    Route::get('/excel/monthly', [StatisticsFormatterController::class, 'getExcelMonthlyData']);
    Route::get('/excel/quarterly', [StatisticsFormatterController::class, 'getExcelQuarterlyData']);
    Route::get('/excel/puskesmas', [StatisticsFormatterController::class, 'getExcelPuskesmasData']);
});
