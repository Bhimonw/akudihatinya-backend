<?php

use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Shared\ProfileController;
use Illuminate\Support\Facades\Route;

// User profile routes (protected)
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    // Current user profile - GET handled by UserController, PUT/POST by ProfileController
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [ProfileController::class, 'updateMe']);
    // Support POST with _method=PUT for multipart form data
    Route::post('/me', [ProfileController::class, 'updateMe']);
    
    // Alternative profile update with 800x800 validation
    Route::put('/me/alt', [ProfileController::class, 'updateMeAlternative']);
    Route::post('/me/alt', [ProfileController::class, 'updateMeAlternative']);
});

// Admin user management routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])->prefix('admin')->group(function () {
    // User management
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::resource('users', UserController::class)->except(['create', 'edit']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});