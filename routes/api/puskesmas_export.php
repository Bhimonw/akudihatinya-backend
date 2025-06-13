<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Shared\PuskesmasExportController;

/*
|--------------------------------------------------------------------------
| Puskesmas Export API Routes
|--------------------------------------------------------------------------
|
| Here are the routes for puskesmas statistics export functionality.
| These routes are protected by authentication middleware.
|
*/

Route::middleware(['auth:sanctum'])->prefix('puskesmas')->group(function () {
    // Export routes
    Route::get('/export', [PuskesmasExportController::class, 'exportStatistics']);
    Route::get('/export/ht', [PuskesmasExportController::class, 'exportHtStatistics']);
    Route::get('/export/dm', [PuskesmasExportController::class, 'exportDmStatistics']);

    // Helper routes
    Route::get('/export/years', [PuskesmasExportController::class, 'getAvailableYears']);
    Route::get('/export/puskesmas', [PuskesmasExportController::class, 'getPuskesmasList']);
    Route::get('/export/options', [PuskesmasExportController::class, 'getExportOptions']);
});
