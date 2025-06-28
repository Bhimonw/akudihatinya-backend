<?php

use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsPuskesmas;

// Examination routes (Puskesmas only)
Route::middleware(['auth:sanctum', IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    // Patient examination years management
    Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
    Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);
    
    // HT Examinations
    Route::resource('ht-examinations', HtExaminationController::class)->except(['create', 'edit']);
    
    // DM Examinations
    Route::resource('dm-examinations', DmExaminationController::class)->except(['create', 'edit']);
    Route::put('dm-examinations-batch', [DmExaminationController::class, 'updateBatch']);
});