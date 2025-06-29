<?php

use App\Http\Controllers\API\Shared\UserController;
use Illuminate\Support\Facades\Route;

// User profile routes (protected)
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    // Current user profile
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateMe']);
    // Support POST with _method=PUT for multipart form data
    Route::post('/me', [UserController::class, 'updateMe']);
});

// Admin user management routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])->prefix('admin')->group(function () {
    // User management
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::resource('users', UserController::class)->except(['create', 'edit']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});