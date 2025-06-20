<?php

use App\Http\Controllers\API\Shared\DashboardController;
use App\Http\Controllers\API\Shared\UserController;
use App\Http\Controllers\API\Admin\YearlyTargetController;
use Illuminate\Support\Facades\Route;

// Admin routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])->prefix('admin')->group(function () {
    // Dashboard API for Dinas (admin)
    Route::get('/dashboard', [DashboardController::class, 'dinasIndex']);

    // User management
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::resource('users', UserController::class)->except(['create', 'edit']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Yearly targets
    Route::get('/yearly-targets', [YearlyTargetController::class, 'index']);
    Route::post('/yearly-targets', [YearlyTargetController::class, 'store']);
    Route::put('/yearly-targets', [YearlyTargetController::class, 'update']);
    Route::delete('/yearly-targets', [YearlyTargetController::class, 'destroy']);
});
