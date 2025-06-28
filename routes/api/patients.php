<?php

use App\Http\Controllers\API\Puskesmas\PatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsPuskesmas;

// Patient management routes (Puskesmas only)
Route::middleware(['auth:sanctum', IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    // Patient CRUD operations
    Route::resource('patients', PatientController::class)->except(['create', 'edit']);
});