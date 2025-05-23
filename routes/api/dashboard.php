<?php

use App\Http\Controllers\API\Shared\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsPuskesmas;

// Dashboard routes
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard API for Dinas (admin)
    Route::middleware(IsAdmin::class)->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dinasIndex']);
    });

    // Dashboard API for Puskesmas
    Route::middleware(IsPuskesmas::class)->prefix('puskesmas')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'puskesmasIndex']);
    });
});