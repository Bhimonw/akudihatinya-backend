<?php

namespace App\Constants;

/**
 * Konstanta untuk validasi yang digunakan di seluruh aplikasi
 * Memastikan konsistensi dan kemudahan maintenance
 */
class ValidationConstants
{
    // Regex patterns
    public const NAME_REGEX = '/^[\p{L}\p{N}\s]+$/u'; // Letters, numbers, spaces (Unicode)
    public const USERNAME_REGEX = '/^[a-zA-Z0-9._-]+$/'; // Alphanumeric, dots, underscores, hyphens
    public const PASSWORD_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'; // At least one lowercase, uppercase, digit
    
    // Field lengths
    public const NAME_MIN_LENGTH = 2;
    public const NAME_MAX_LENGTH = 255;
    public const USERNAME_MIN_LENGTH = 3;
    public const USERNAME_MAX_LENGTH = 255;
    public const PASSWORD_MIN_LENGTH = 8;
    public const PASSWORD_MAX_LENGTH = 255;
    
    // Profile Picture Validation
    public const PROFILE_PICTURE_MAX_SIZE = 2048; // 2MB in KB
    public const PROFILE_PICTURE_MIMES = ['jpeg', 'png', 'jpg', 'gif', 'webp'];
    public const PROFILE_PICTURE_MIN_WIDTH = 100;
    public const PROFILE_PICTURE_MIN_HEIGHT = 100;
    public const PROFILE_PICTURE_MAX_WIDTH = 2000;
    public const PROFILE_PICTURE_MAX_HEIGHT = 2000;
    
    // Alternative validation for 800x800 support
    public const PROFILE_PICTURE_ALT_MAX_WIDTH = 800;
    public const PROFILE_PICTURE_ALT_MAX_HEIGHT = 800;
    
    // Profile Picture Error Messages
    public const PROFILE_PICTURE_IMAGE_ERROR = 'File harus berupa gambar.';
    public const PROFILE_PICTURE_MIMES_ERROR = 'Format gambar harus jpeg, png, jpg, gif, atau webp.';
    public const PROFILE_PICTURE_MAX_SIZE_ERROR = 'Ukuran gambar maksimal 2MB.';
    public const PROFILE_PICTURE_DIMENSIONS_ERROR = 'Dimensi gambar harus minimal 100x100 piksel dan maksimal 2000x2000 piksel.';
    public const PROFILE_PICTURE_ALT_DIMENSIONS_ERROR = 'Dimensi gambar harus minimal 100x100 piksel dan maksimal 800x800 piksel.';
    
    // Error messages
    public const ERROR_MESSAGES = [
        'name.regex' => 'Nama hanya boleh mengandung huruf, angka, dan spasi.',
        'name.min' => 'Nama minimal :min karakter.',
        'name.max' => 'Nama maksimal :max karakter.',
        'username.regex' => 'Username hanya boleh mengandung huruf, angka, titik, underscore, dan strip.',
        'username.min' => 'Username minimal :min karakter.',
        'username.max' => 'Username maksimal :max karakter.',
        'username.unique' => 'Username sudah digunakan.',
        'password.regex' => 'Password harus mengandung minimal 1 huruf kecil, 1 huruf besar, dan 1 angka.',
        'password.min' => 'Password minimal :min karakter.',
        'password.confirmed' => 'Konfirmasi password tidak cocok.',
        'puskesmas_name.regex' => 'Nama Puskesmas hanya boleh mengandung huruf, angka, dan spasi.',
        'puskesmas_name.min' => 'Nama Puskesmas minimal :min karakter.',
        'puskesmas_name.max' => 'Nama Puskesmas maksimal :max karakter.',
        'profile_picture.image' => 'File harus berupa gambar.',
        'profile_picture.mimes' => 'Format gambar harus: jpeg, png, jpg, gif, atau webp.',
        'profile_picture.max' => 'Ukuran gambar maksimal 2MB.',
        'profile_picture.dimensions' => 'Dimensi gambar harus antara 50x50 hingga 2000x2000 pixel.',
    ];
}