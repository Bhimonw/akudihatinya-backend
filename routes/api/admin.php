<?php

use App\Http\Controllers\API\Admin\YearlyTargetController;
use App\Http\Controllers\API\Shared\DashboardController;
use App\Http\Controllers\API\Shared\UserController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

// Admin routes
Route::middleware(['auth:sanctum', IsAdmin::class])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'dinasIndex']);
    
    // User management
    Route::resource('users', UserController::class)->except(['create', 'edit']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Yearly targets
    Route::resource('yearly-targets', YearlyTargetController::class)->except(['create', 'edit']);
});