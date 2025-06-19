<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;

// Route untuk mengakses gambar dari resources/img
Route::get('img/{filename}', function ($filename) {
    $path = resource_path('img/' . $filename);
    
    if (!file_exists($path)) {
        abort(404);
    }
    
    $file = file_get_contents($path);
    $type = mime_content_type($path);
    
    return Response::make($file, 200, [
        'Content-Type' => $type,
        'Content-Disposition' => 'inline; filename="' . $filename . '"'
    ]);
})->name('img');
