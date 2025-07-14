<?php

use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsPuskesmasMiddleware;

// Examination routes (Puskesmas only)
Route::middleware(['auth:sanctum', IsPuskesmasMiddleware::class])->prefix('puskesmas')->group(function () {
    // Patients
    Route::resource('patients', PatientController::class)->except(['create', 'edit']);
    Route::get('/patients-export', [PatientController::class, 'export']);
    Route::get('/patients-export-excel', [PatientController::class, 'exportToExcel']);

    // Patient examination years management
    Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
    Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);
    
    // HT Examinations
    Route::resource('ht-examinations', HtExaminationController::class)->except(['create', 'edit']);
    
    // DM Examinations
    Route::resource('dm-examinations', DmExaminationController::class)->except(['create', 'edit']);
    Route::put('dm-examinations-batch', [DmExaminationController::class, 'updateBatch']);
});