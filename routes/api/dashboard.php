<?php

use App\Http\Controllers\API\Shared\StatisticsController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmas;

// Dashboard routes
Route::middleware(['auth:sanctum', AdminOrPuskesmas::class])->group(function () {
    // Admin dashboard
    Route::middleware(\App\Http\Middleware\IsAdmin::class)->prefix('admin')->group(function () {
        Route::get('/dashboard', [StatisticsController::class, 'adminStatistics']);
    });
    
    // Puskesmas dashboard
    Route::middleware(\App\Http\Middleware\IsPuskesmas::class)->prefix('puskesmas')->group(function () {
        Route::get('/dashboard', [StatisticsController::class, 'dashboardStatistics']);
    });
    Route::middleware(['auth:sanctum', AdminOrPuskesmas::class])->prefix('statistics')->group(function () {
        // General dashboard statistics (for both admin and puskesmas)
    Route::get('/dashboard-statistics', [StatisticsController::class, 'dashboardStatistics']);
    });
});