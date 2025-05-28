<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Puskesmas\ProfileController;
use App\Http\Controllers\API\Shared\UserController;
use Illuminate\Support\Facades\Route;

// Public routes (no CSRF protection needed)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
        Route::get('/user', 'user');
        Route::post('/change-password', 'changePassword');
    });

    // Profile
    Route::post('/profile', [ProfileController::class, 'update']);

    // Akun sendiri
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/me', [UserController::class, 'updateMe']);
    });
});