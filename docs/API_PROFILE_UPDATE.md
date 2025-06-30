# API Profile Update Documentation

## Overview
Dokumentasi ini menjelaskan endpoint untuk update profil user yang telah diperbaiki dengan implementasi best practices.

## Endpoints

### 1. Update Profile (ProfileController)
**Endpoint:** `PUT /api/profile`  
**Middleware:** `auth:sanctum`  
**Accessible by:** Admin, Puskesmas

#### Request Headers
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

#### Request Body
| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| name | string | No | min:2, max:255, regex:/^[\p{L}\p{N}\s]+$/u | Nama user (huruf, angka, spasi) |
| password | string | No | min:8, confirmed, regex pattern | Password baru |
| password_confirmation | string | No | required_with:password | Konfirmasi password |
| puskesmas_name | string | No | min:2, max:255, regex:/^[\p{L}\p{N}\s]+$/u | Nama puskesmas (hanya untuk role puskesmas) |
| profile_picture | file | No | image, max:2MB, dimensions:50x50-2000x2000 | Foto profil |

#### Response Success (200)
```json
{
    "message": "Profil berhasil diupdate",
    "user": {
        "id": 1,
        "username": "user123",
        "name": "Updated Name",
        "role": "puskesmas",
        "profile_picture": "path/to/image.jpg",
        "puskesmas": {
            "id": 1,
            "name": "Updated Puskesmas Name"
        }
    }
}
```

#### Response Error (422)
```json
{
    "message": "Data tidak valid",
    "errors": {
        "name": ["Nama hanya boleh mengandung huruf, angka, dan spasi."]
    }
}
```

### 2. Update Me (UserController)
**Endpoint:** `PUT /api/me`  
**Middleware:** `auth:sanctum`  
**Accessible by:** Admin, Puskesmas

#### Request Headers
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

#### Request Body
| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| username | string | No | min:3, max:255, unique, regex:/^[a-zA-Z0-9._-]+$/ | Username unik |
| name | string | No | min:2, max:255, regex:/^[\p{L}\p{N}\s]+$/u | Nama user |
| password | string | No | min:8, confirmed, regex pattern | Password baru |
| password_confirmation | string | No | required_with:password | Konfirmasi password |
| profile_picture | file | No | image, max:2MB, dimensions:50x50-2000x2000 | Foto profil |
| _method | string | No | in:PUT,PATCH | Method spoofing |

## Validation Rules

### Name Field
- **Pattern:** `/^[\p{L}\p{N}\s]+$/u`
- **Allowed:** Letters (any language), numbers, spaces
- **Length:** 2-255 characters
- **Examples:** 
  - ✅ "John Doe", "Ahmad 123", "Puskesmas Kota 1"
  - ❌ "John@Doe", "Ahmad_123", "Test!"

### Username Field
- **Pattern:** `/^[a-zA-Z0-9._-]+$/`
- **Allowed:** Alphanumeric, dots, underscores, hyphens
- **Length:** 3-255 characters
- **Must be unique**
- **Examples:**
  - ✅ "john.doe", "user_123", "admin-user"
  - ❌ "john doe", "user@123", "test!"

### Password Field
- **Pattern:** `/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/`
- **Requirements:** At least one lowercase, uppercase, and digit
- **Length:** 8-255 characters
- **Must be confirmed**
- **Examples:**
  - ✅ "Password123", "MySecret1"
  - ❌ "password", "PASSWORD", "12345678"

### Profile Picture Field
- **Types:** jpeg, png, jpg, gif, webp
- **Size:** Maximum 2MB
- **Dimensions:** 50x50 to 2000x2000 pixels

## Role-Based Access

### Admin Role
- Can update: `name`, `password`, `profile_picture`
- Cannot update: `puskesmas_name` (not applicable)

### Puskesmas Role
- Can update: `name`, `password`, `profile_picture`, `puskesmas_name`
- `puskesmas_name` updates the associated Puskesmas entity

## Independent Field Updates

Semua field dapat diupdate secara independen:
- Update hanya `name` tanpa field lain ✅
- Update hanya `password` tanpa field lain ✅
- Update hanya `puskesmas_name` (untuk role puskesmas) ✅
- Update hanya `profile_picture` tanpa field lain ✅
- Update kombinasi field ✅

## Error Handling

### Validation Errors (422)
```json
{
    "message": "Data tidak valid",
    "errors": {
        "field_name": ["Error message in Indonesian"]
    }
}
```

### Server Errors (500)
```json
{
    "message": "Gagal memperbarui profil",
    "error": "Error details (only in debug mode)"
}
```

### File Upload Errors (422)
```json
{
    "message": "Gagal mengupload foto profil",
    "error": "Specific upload error message"
}
```

## Security Features

1. **Authentication Required:** All endpoints require valid Bearer token
2. **Role-Based Validation:** Fields are filtered based on user role
3. **Password Hashing:** Passwords are automatically hashed using bcrypt
4. **File Validation:** Strict validation for uploaded images
5. **Input Sanitization:** All inputs are validated and sanitized
6. **SQL Injection Protection:** Using Eloquent ORM with parameter binding
7. **CSRF Protection:** Built-in Laravel CSRF protection

## Performance Optimizations

1. **Database Transactions:** All updates wrapped in transactions
2. **Eager Loading:** Relationships loaded efficiently
3. **Service Layer:** Business logic separated from controllers
4. **Validation Caching:** Validation rules reused via traits
5. **File Storage:** Optimized file upload handling

## Code Quality Improvements

1. **Constants:** Validation patterns stored in constants
2. **Traits:** Reusable validation rules
3. **Services:** Dedicated service for profile updates
4. **Testing:** Comprehensive unit tests included
5. **Documentation:** Complete API documentation
6. **Error Messages:** Consistent Indonesian error messages
7. **Logging:** Detailed logging for debugging and monitoring