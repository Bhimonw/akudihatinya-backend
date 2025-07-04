<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;

// Route untuk mengakses gambar dari public/img
Route::get('img/{filename}', function ($filename) {
    $path = public_path('img' . DIRECTORY_SEPARATOR . $filename);
    
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

// Route untuk Vue.js SPA - harus di bagian paling akhir
// Menangkap semua route yang tidak ditangani oleh route lain
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*')->name('spa');
