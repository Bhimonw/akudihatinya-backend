<?php

use App\Http\Controllers\API\Shared\StatisticsController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmasMiddleware;

// Dashboard routes
Route::middleware(['auth:sanctum', AdminOrPuskesmasMiddleware::class])->group(function () {
    // Admin dashboard
    Route::middleware(\App\Http\Middleware\IsAdminMiddleware::class)->prefix('admin')->group(function () {
        Route::get('/dashboard', [StatisticsController::class, 'adminStatistics']);
    });
    
    // Puskesmas dashboard
    Route::middleware(\App\Http\Middleware\IsPuskesmasMiddleware::class)->prefix('puskesmas')->group(function () {
        Route::get('/dashboard', [StatisticsController::class, 'dashboardStatistics']);
    });
    
    Route::middleware(['auth:sanctum', AdminOrPuskesmasMiddleware::class])->prefix('statistics')->group(function () {
        // General dashboard statistics (for both admin and puskesmas)
    Route::get('/dashboard-statistics', [StatisticsController::class, 'dashboardStatistics']);
    });
});