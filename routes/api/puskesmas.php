<?php

use App\Http\Controllers\API\Puskesmas\PatientController;
use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\ProfileController;
use App\Http\Controllers\API\Shared\StatisticsController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsPuskesmas;

// Profile update (protected)
Route::middleware('auth:sanctum')->post('/profile', [ProfileController::class, 'update']);

// Puskesmas routes
Route::middleware(['auth:sanctum', IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    // Dashboard - using statistics controller
    Route::get('/dashboard', [StatisticsController::class, 'dashboardStatistics']);

    // Patients
    Route::resource('patients', PatientController::class)->except(['create', 'edit']);
    Route::get('/patients-export', [PatientController::class, 'export']);
    Route::get('/patients-export-excel', [PatientController::class, 'exportToExcel']);

    // Examination years
    Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
    Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);

    // HT Examinations
    Route::resource('ht-examinations', HtExaminationController::class)->except(['create', 'edit']);

    // DM Examinations
    Route::resource('dm-examinations', DmExaminationController::class)->except(['create', 'edit']);
    Route::put('dm-examinations-batch', [DmExaminationController::class, 'updateBatch']);
});
