<?php

use App\Http\Controllers\API\Shared\ProfileController;
use Illuminate\Support\Facades\Route;

// Profile management routes
Route::middleware('auth:sanctum')->group(function () {
    // Profile update (for puskesmas users)
    Route::post('/profile', [ProfileController::class, 'update']);
});