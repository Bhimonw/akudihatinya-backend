<?php

use App\Http\Controllers\API\Puskesmas\PatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsPuskesmasMiddleware;

// Patient management routes (Puskesmas only)
Route::middleware(['auth:sanctum', IsPuskesmasMiddleware::class])->prefix('puskesmas')->group(function () {
    // Patient CRUD operations
    Route::resource('patients', PatientController::class)->except(['create', 'edit']);
});