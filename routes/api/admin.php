<?php

use App\Http\Controllers\API\Admin\YearlyTargetController;
use Illuminate\Support\Facades\Route;

// Admin-specific routes (yearly targets management)
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdminMiddleware::class])->prefix('admin')->group(function () {
    // Yearly targets management
    Route::get('/yearly-targets', [YearlyTargetController::class, 'index']);
    Route::post('/yearly-targets', [YearlyTargetController::class, 'store']);
    Route::put('/yearly-targets', [YearlyTargetController::class, 'update']);
    Route::delete('/yearly-targets', [YearlyTargetController::class, 'destroy']);
});
