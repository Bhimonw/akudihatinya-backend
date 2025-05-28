<?php

use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
// Hapus baris ini: use App\Http\Controllers\API\Shared\DashboardController;
use App\Http\Middleware\IsPuskesmas;
use Illuminate\Support\Facades\Route;

// Puskesmas routes
Route::middleware(['auth:sanctum', IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    // Hapus baris ini: Route::get('/dashboard', [DashboardController::class, 'puskesmasIndex']);

    // Patients
    Route::resource('patients', PatientController::class)->except(['create', 'edit']);

    // Examination years
    Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
    Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);

    // HT Examinations
    Route::resource('ht-examinations', HtExaminationController::class)->except(['create', 'edit']);

    // DM Examinations
    Route::resource('dm-examinations', DmExaminationController::class)->except(['create', 'edit']);
});