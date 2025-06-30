<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

// Route untuk mengakses gambar profile dengan validasi keamanan
Route::get('storage/profile-pictures/{filename}', function ($filename) {
    // Validasi nama file untuk keamanan
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
        abort(404);
    }
    
    // Cek apakah file ada di storage
    if (!Storage::disk('public')->exists('profile-pictures/' . $filename)) {
        abort(404);
    }
    
    $path = Storage::disk('public')->path('profile-pictures/' . $filename);
    $mimeType = Storage::disk('public')->mimeType('profile-pictures/' . $filename);
    
    // Validasi tipe file yang diizinkan
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes)) {
        abort(403, 'File type not allowed');
    }
    
    return response()->file($path, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000', // Cache 1 tahun
    ]);
})->name('profile.picture');

// Fallback route untuk backward compatibility
Route::get('img/{filename}', function ($filename) {
    return redirect()->route('profile.picture', ['filename' => $filename]);
})->name('img.redirect');
