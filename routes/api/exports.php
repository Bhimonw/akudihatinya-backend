<?php

use App\Http\Controllers\API\Shared\StatisticsController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmas;

// Statistics export routes - digunakan oleh frontend Dashboard
Route::middleware(['auth:sanctum', AdminOrPuskesmas::class])->prefix('statistics')->group(function () {
    // User dashboard export endpoint
    Route::get('/export', [StatisticsController::class, 'exportStatistics']);
    
    // Admin dashboard export endpoint  
    Route::get('/admin/export', [StatisticsController::class, 'exportStatistics']);
});

// Patient export routes - digunakan oleh frontend ListPasien, Hipertensi, DiabetesMellitus
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    Route::get('/patients-export-excel', [PatientController::class, 'exportToExcel']);
});