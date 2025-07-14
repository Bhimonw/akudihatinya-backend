<?php

use App\Http\Controllers\API\Shared\StatisticsController;
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
