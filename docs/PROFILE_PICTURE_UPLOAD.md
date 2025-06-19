# Profile Picture Upload Documentation

## Overview

Sistem upload foto profil yang telah diimplementasikan untuk aplikasi Akudihatinya Backend. Sistem ini memungkinkan pengguna untuk mengupload, menampilkan, dan mengelola foto profil mereka melalui API endpoints dengan penyimpanan di direktori `resources/img/`.

## Features

### ✅ Implemented Features

1. **File Upload Service**
   - Centralized file handling dengan `FileUploadService`
   - Validasi file yang komprehensif (format, ukuran)
   - Automatic file cleanup saat update/delete
   - Error handling dan logging yang robust
   - **Automatic image resizing to 200x200 pixels**

2. **API Endpoints**
   - `PUT /api/users/me` - Upload/update foto profil user yang sedang login
   - `GET /api/users/me` - Mendapatkan profil user dengan URL foto profil
   - `GET /img/{filename}` - Serving file gambar

3. **Validation & Security**
   - Format file: JPEG, PNG, JPG, GIF
   - Ukuran maksimal: 2MB
   - **Automatic resize to 200x200 pixels** (any input size accepted)
   - Rate limiting untuk mencegah abuse
   - Sanitized filename generation

4. **Storage Management**
   - File disimpan di `resources/img/` (bukan `storage/app/public`)
   - Path relatif disimpan di database
   - Automatic directory creation
   - Old file cleanup saat update
   - **Automatic URL generation via `asset()` helper**

5. **Image Processing**
   - **Automatic resize to 200x200 pixels using GD library**
   - Support untuk JPEG, PNG, GIF dengan preservasi transparansi
   - High-quality resampling
   - Memory management dan error handling

6. **Configuration**
   - Centralized config di `config/upload.php`
   - Environment-based settings
   - Easy maintenance dan customization

7. **Testing**
   - Unit tests untuk `FileUploadService`
   - Feature tests untuk upload endpoints
   - Validation testing

## Storage Configuration Changes

### Migration from storage/app/public to resources/img

Konfigurasi upload file profil telah diubah dari `storage/app/public` ke `resources/img` untuk memungkinkan file gambar profil disimpan langsung di direktori resources yang dapat diakses melalui asset URL.

#### Perubahan Implementasi di UserController.php

**File:** `app/Http/Controllers/API/Shared/UserController.php`

##### Method `updateMe()` - Upload Logic
```php
// Sebelum (menggunakan Storage)
$path = $request->file('profile_picture')->store('profile-pictures', 'public');
$data['profile_picture'] = $path;

// Sesudah (menggunakan resources/img)
$file = $request->file('profile_picture');
$fileName = time() . '_' . $file->getClientOriginalName();
$destinationPath = resource_path('img');

// Create directory if it doesn't exist
if (!file_exists($destinationPath)) {
    mkdir($destinationPath, 0755, true);
}

$moved = $file->move($destinationPath, $fileName);
$data['profile_picture'] = 'img/' . $fileName;

// Automatic resize to 200x200
$this->resizeImage($fullImagePath, 200, 200);
```

##### Old File Cleanup
```php
// Hapus file lama jika ada
$oldImagePath = resource_path($user->profile_picture);
if (file_exists($oldImagePath)) {
    unlink($oldImagePath);
}
```

#### UserResource.php - URL Generation
```php
'profile_picture' => $this->profile_picture ? asset($this->profile_picture) : null,
```

#### Web Route untuk Serving Images
**File:** `routes/web.php`
```php
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
```

## API Usage

### Upload Profile Picture

```http
PUT /api/users/me
```Content-Type: multipart/form-data
Authorization: Bearer {access_token}

profile_picture: {image_file}
name: "Updated Name" (optional)
```

**Response Success (200):**
```json
{
  "message": "Profil berhasil diperbarui",
  "user": {
    "id": 1,
    "username": "admin",
    "name": "Admin User",
    "profile_picture": "http://localhost:8000/img/1703123456_profile-image.jpg",
    "role": "admin",
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 10:35:00"
  }
}
```

**Response Error (422):**
```json
{
  "message": "File yang diupload bukan gambar yang valid atau ukuran terlalu besar"
}
```

### Get User Profile

```http
GET /api/users/me
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "username": "admin",
    "name": "Admin User",
    "profile_picture": "http://localhost:8000/img/1703123456_profile-image.jpg",
    "role": "admin",
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 10:35:00"
  }
}
```

## File Structure

```
app/
├── Http/
│   ├── Controllers/API/Shared/
│   │   └── UserController.php          # Updated with FileUploadService
│   ├── Middleware/
│   │   └── FileUploadRateLimit.php     # Rate limiting middleware
│   └── Requests/Admin/
│       ├── StoreUserRequest.php        # Enhanced validation
│       └── UpdateUserRequest.php       # Enhanced validation
├── Services/
│   └── FileUploadService.php           # Centralized file handling
config/
└── upload.php                          # Upload configuration
resources/
└── img/                                # Profile pictures storage
routes/
└── web.php                             # Image serving route
tests/
├── Feature/
│   └── ProfilePictureUploadTest.php    # Feature tests
└── Unit/
    └── FileUploadServiceTest.php       # Unit tests
```

## Configuration

### Environment Variables

Tambahkan ke `.env` file:

```env
# Upload Configuration
UPLOAD_DISK=local
UPLOAD_VIRUS_SCAN=false
UPLOAD_GENERATE_THUMBNAILS=false
```

### Upload Config (`config/upload.php`)

```php
'profile_pictures' => [
    'path' => 'img',
    'max_size' => 2048, // KB
    'allowed_mimes' => ['jpeg', 'png', 'jpg', 'gif', 'webp'],
    'dimensions' => [
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 2000,
        'max_height' => 2000,
    ],
],
```

## Security Considerations

1. **File Validation**
   - MIME type checking
   - File extension validation
   - Image dimension validation
   - File size limits

2. **Rate Limiting**
   - Maximum 10 upload attempts per hour per user
   - IP-based rate limiting untuk guest users

3. **File Storage**
   - Files stored outside web root
   - Controlled access melalui route
   - Sanitized filenames

4. **Error Handling**
   - Comprehensive logging
   - Graceful error responses
   - Transaction rollback pada failure

## Testing

### Run Unit Tests

```bash
php artisan test tests/Unit/FileUploadServiceTest.php
```

### Run Feature Tests

```bash
php artisan test tests/Feature/ProfilePictureUploadTest.php
```

### Run All Upload-Related Tests

```bash
php artisan test --filter="Upload"
```

## Troubleshooting

### Common Issues

1. **File tidak terupload**
   - Check file permissions pada `resources/img/`
   - Verify file size dan format
   - Check server upload limits (`upload_max_filesize`, `post_max_size`)

2. **Profile picture null di response**
   - Verify file berhasil tersimpan di `resources/img/`
   - Check database record untuk `profile_picture` field
   - Verify route `/img/{filename}` berfungsi

3. **Rate limiting issues**
   - Clear rate limiter cache: `php artisan cache:clear`
   - Adjust rate limits di middleware

### Debug Commands

```bash
# Check file permissions
ls -la resources/img/

# Check uploaded files
ls -la resources/img/

# Check logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear
```

## Future Enhancements

### Planned Features

1. **Image Processing**
   - Automatic thumbnail generation
   - Image compression
   - Multiple image sizes

2. **Cloud Storage**
   - AWS S3 integration
   - CDN support
   - Backup strategies

3. **Advanced Security**
   - Virus scanning
   - Content-based validation
   - Watermarking

4. **Performance**
   - Image caching
   - Lazy loading
   - Progressive image loading

## Maintenance

### Regular Tasks

1. **File Cleanup**
   - Remove orphaned files
   - Archive old profile pictures
   - Monitor disk usage

2. **Performance Monitoring**
   - Upload success rates
   - Response times
   - Error rates

3. **Security Audits**
   - Review uploaded files
   - Check for malicious content
   - Update validation rules

## Support

Untuk pertanyaan atau issues terkait sistem upload foto profil, silakan:

1. Check dokumentasi ini terlebih dahulu
2. Review logs di `storage/logs/laravel.log`
3. Run tests untuk verify functionality
4. Contact development team jika diperlukan