<?php
use App\Http\Controllers\API\Shared\UserController;
use Illuminate\Support\Facades\Route;

// Profile management routes (now handled by UserController)
Route::middleware('auth:sanctum')->group(function () {
    // Profile routes using UserController
    Route::get('/me', [UserController::class, 'showProfile']);
    Route::put('/me', [UserController::class, 'updateProfile']);
    Route::patch('/me', [UserController::class, 'updateProfile']);
    Route::post('/me', [UserController::class, 'updateProfile']); // For form-data with file uploads
    Route::delete('/me/profile-picture', [UserController::class, 'deleteProfilePicture']);
    Route::get('/me/upload-status', [UserController::class, 'uploadStatus']);
    
    // Debug and testing routes
    Route::get('/me/test-upload', [UserController::class, 'testUpload']);
    Route::get('/me/diagnostics', [UserController::class, 'diagnostics']);
    
    // Legacy route for backward compatibility (now uses unified UserController)
    Route::post('/profile', [UserController::class, 'updateProfile']);
});