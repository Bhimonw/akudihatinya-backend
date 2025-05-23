<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Import route files
require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/puskesmas.php';
require __DIR__ . '/api/statistics.php';
require __DIR__ . '/api/dashboard.php';

// User profile routes
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/me', [\App\Http\Controllers\API\Shared\UserController::class, 'me']);
    Route::put('/me', [\App\Http\Controllers\API\Shared\UserController::class, 'updateMe']);
});