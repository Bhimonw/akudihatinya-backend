# 📚 API Reference Guide

<p align="center">
  <img src="https://img.shields.io/badge/API-REST-blue?style=for-the-badge" alt="REST API">
  <img src="https://img.shields.io/badge/Auth-Sanctum-green?style=for-the-badge" alt="Laravel Sanctum">
  <img src="https://img.shields.io/badge/Format-JSON-orange?style=for-the-badge" alt="JSON">
</p>

<h3>🔌 Complete API Documentation</h3>
<p><em>Comprehensive reference for all API endpoints, authentication, and data structures</em></p>

---

## 📋 Table of Contents

1. [Authentication](#authentication)
2. [Base Configuration](#base-configuration)
3. [Admin APIs](#admin-apis)
4. [Puskesmas APIs](#puskesmas-apis)
5. [Statistics APIs](#statistics-apis)
6. [Dashboard APIs](#dashboard-apis)
7. [User Profile APIs](#user-profile-apis)
8. [Export APIs](#export-apis)
9. [Response Structures](#response-structures)
10. [Error Handling](#error-handling)
11. [Real-Time Features](#real-time-features)
12. [System Architecture](#system-architecture)

---

## 🔐 Authentication

### Authentication Method
All API endpoints use **Laravel Sanctum** for authentication with Bearer tokens.

```http
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

### Login Endpoint

**POST** `/api/auth/login`

```json
{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin",
      "puskesmas_id": null
    },
    "token": "1|abc123def456...",
    "token_type": "Bearer"
  }
}
```

### Logout Endpoint

**POST** `/api/auth/logout`

**Response:**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

---

## ⚙️ Base Configuration

### Base URLs
- **Development**: `http://localhost:8000/api`
- **Production**: `https://your-domain.com/api`

### Common Headers
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

### Rate Limiting
- **General APIs**: 60 requests per minute
- **Authentication**: 5 requests per minute
- **Statistics**: 30 requests per minute

---

## 👑 Admin APIs

### Yearly Targets Management

#### Get Yearly Targets
**GET** `/api/admin/yearly-targets`

**Query Parameters:**
- `puskesmas_id` (optional): Filter by Puskesmas ID
- `disease_type` (optional): Filter by disease type (`ht`, `dm`)
- `year` (optional): Filter by year
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 10)

**Behavior:**
- **List Mode**: Returns paginated list when not all identifying parameters are provided
- **Show Mode**: Returns specific target when all parameters (`puskesmas_id`, `disease_type`, `year`) are provided

**Examples:**
```http
# List all targets
GET /api/admin/yearly-targets

# Filter by year
GET /api/admin/yearly-targets?year=2024

# Filter by disease type
GET /api/admin/yearly-targets?disease_type=ht

# Get specific target (Show Mode)
GET /api/admin/yearly-targets?puskesmas_id=1&disease_type=ht&year=2024
```

**List Mode Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "puskesmas_id": 1,
        "disease_type": "ht",
        "year": 2024,
        "target_count": 150,
        "puskesmas": {
          "id": 1,
          "name": "Puskesmas A"
        }
      }
    ],
    "total": 50,
    "per_page": 10,
    "last_page": 5
  }
}
```

**Show Mode Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 150,
    "puskesmas": {
      "id": 1,
      "name": "Puskesmas A",
      "address": "Jl. Kesehatan No. 1",
      "phone": "021-1234567",
      "email": "puskesmasa@example.com"
    }
  }
}
```

#### Create Yearly Target
**POST** `/api/admin/yearly-targets`

**Request Body:**
```json
{
  "puskesmas_id": 1,
  "disease_type": "ht",
  "year": 2024,
  "target_count": 150
}
```

**Response:**
```json
{
  "success": true,
  "message": "Yearly target created successfully",
  "data": {
    "id": 1,
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 150
  }
}
```

#### Update Yearly Target
**PUT** `/api/admin/yearly-targets/{id}`

**Request Body:**
```json
{
  "target_count": 200
}
```

#### Delete Yearly Target
**DELETE** `/api/admin/yearly-targets/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Yearly target deleted successfully"
}
```

### Puskesmas Management

#### Get All Puskesmas
**GET** `/api/admin/puskesmas`

#### Create Puskesmas
**POST** `/api/admin/puskesmas`

**Request Body:**
```json
{
  "name": "Puskesmas Baru",
  "address": "Jl. Kesehatan No. 123",
  "phone": "021-1234567",
  "email": "puskesmas@example.com"
}
```

#### Update Puskesmas
**PUT** `/api/admin/puskesmas/{id}`

#### Delete Puskesmas
**DELETE** `/api/admin/puskesmas/{id}`

### User Management

#### Get All Users
**GET** `/api/admin/users`

#### Create User
**POST** `/api/admin/users`

**Request Body:**
```json
{
  "name": "User Baru",
  "email": "user@example.com",
  "password": "password123",
  "role": "puskesmas",
  "puskesmas_id": 1
}
```

---

## 🏥 Puskesmas APIs

### Patient Management

#### Get Patients
**GET** `/api/puskesmas/patients`

**Query Parameters:**
- `search` (optional): Search by name or NIK
- `page` (optional): Page number
- `per_page` (optional): Items per page

#### Create Patient
**POST** `/api/puskesmas/patients`

**Request Body:**
```json
{
  "name": "John Doe",
  "nik": "1234567890123456",
  "birth_date": "1980-01-01",
  "gender": "male",
  "address": "Jl. Contoh No. 123",
  "phone": "081234567890"
}
```

#### Update Patient
**PUT** `/api/puskesmas/patients/{id}`

#### Delete Patient
**DELETE** `/api/puskesmas/patients/{id}`

### Examination Management

#### HT Examinations

**GET** `/api/puskesmas/ht-examinations`
**POST** `/api/puskesmas/ht-examinations`

**Request Body:**
```json
{
  "patient_id": 1,
  "systolic": 140,
  "diastolic": 90,
  "examination_date": "2024-01-15"
}
```

**PUT** `/api/puskesmas/ht-examinations/{id}`
**DELETE** `/api/puskesmas/ht-examinations/{id}`

#### DM Examinations

**GET** `/api/puskesmas/dm-examinations`
**POST** `/api/puskesmas/dm-examinations`

**Request Body:**
```json
{
  "patient_id": 1,
  "examination_type": "gdp",
  "result": 120.5,
  "examination_date": "2024-01-15"
}
```

**PUT** `/api/puskesmas/dm-examinations/{id}`
**DELETE** `/api/puskesmas/dm-examinations/{id}`

---

## 📊 Statistics APIs

### Dashboard Statistics

#### Get Dashboard Statistics
**GET** `/api/statistics/dashboard-statistics`

**Query Parameters:**
- `year` (optional): Filter by year (default: current year)
- `disease_type` (optional): Filter by disease type (`ht`, `dm`, `all`)
- `month` (optional): Filter by specific month (1-12)

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "year": "2024",
    "disease_type": "all",
    "month": null,
    "month_name": null,
    "total_puskesmas": 15,
    "summary": {
      "ht": {
        "target": "1518",
        "total_patients": "1366",
        "standard_patients": "1100",
        "non_standard_patients": "266",
        "male_patients": "511",
        "female_patients": "589",
        "achievement_percentage": 72.46,
        "monthly_data": {
          "1": {
            "target": "127",
            "male": "45",
            "female": "52",
            "total": "97",
            "standard": "78",
            "non_standard": "19",
            "percentage": 61.42
          }
          // ... data untuk bulan 2-12
        }
      },
      "dm": {
        "target": "892",
        "total_patients": "756",
        "standard_patients": "612",
        "non_standard_patients": "144",
        "male_patients": "298",
        "female_patients": "314",
        "achievement_percentage": 68.61,
        "monthly_data": {
          // ... monthly data structure sama seperti HT
        }
      }
    }
  }
}
```

### Monthly Statistics

#### Get Monthly Statistics
**GET** `/api/statistics/monthly`

**Query Parameters:**
- `year` (required): Year
- `month` (required): Month (1-12)
- `disease_type` (optional): Disease type (`ht`, `dm`)
- `puskesmas_id` (optional): Specific Puskesmas ID

### Yearly Statistics

#### Get Yearly Statistics
**GET** `/api/statistics/yearly`

**Query Parameters:**
- `year` (required): Year
- `disease_type` (optional): Disease type (`ht`, `dm`)
- `puskesmas_id` (optional): Specific Puskesmas ID

---

## 👤 User Profile APIs

### Get Current User Profile
**GET** `/api/users/me`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "puskesmas",
    "puskesmas_id": 1,
    "profile_picture": "http://localhost:8000/img/1642123456_profile.jpg",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "puskesmas": {
      "id": 1,
      "name": "Puskesmas A",
      "address": "Jl. Kesehatan No. 1",
      "phone": "021-1234567",
      "email": "puskesmasa@example.com"
    }
  }
}
```

### Update User Profile
**PUT** `/api/users/me`

**Request Body (Form Data):**
```
name: "John Doe Updated"
email: "john.updated@example.com"
profile_picture: [File] (optional)
```

**Features:**
- **File Upload**: Support JPEG, PNG, JPG, GIF
- **File Size**: Maximum 2MB
- **Auto Resize**: Automatically resized to 200x200 pixels
- **Storage**: Files stored in `resources/img/`
- **URL Generation**: Automatic URL generation via `asset()` helper
- **Old File Cleanup**: Previous profile picture automatically deleted

**Response:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "John Doe Updated",
    "email": "john.updated@example.com",
    "profile_picture": "http://localhost:8000/img/1642123456_new_profile.jpg",
    "updated_at": "2024-01-15T10:35:00.000000Z"
  }
}
```

### Profile Picture Serving
**GET** `/img/{filename}`

**Example:**
```
GET /img/1642123456_profile.jpg
```

**Response:** Binary image file

---

## 📤 Export APIs

### Puskesmas PDF Export
**POST** `/api/statistics/export/puskesmas-pdf`

**Request Body:**
```json
{
  "disease_type": "ht",
  "year": 2024,
  "puskesmas_id": 1
}
```

**Validation Rules:**
- `disease_type`: Required, must be `ht` or `dm`
- `year`: Required, integer, minimum 2020, maximum current year
- `puskesmas_id`: Required for admin users, optional for puskesmas users

**Response:** PDF file download

**Error Handling:**
```json
{
  "success": false,
  "error": "puskesmas_not_found",
  "message": "Puskesmas not found"
}
```

### Puskesmas Quarterly PDF Export
**POST** `/api/statistics/export/puskesmas-quarterly-pdf`

**Request Body:**
```json
{
  "disease_type": "dm",
  "year": 2024,
  "quarter": 1,
  "puskesmas_id": 1
}
```

**Validation Rules:**
- `quarter`: Required, integer, must be 1, 2, 3, or 4
- Other rules same as regular PDF export

### Export Options
**GET** `/api/statistics/export/options`

**Response:**
```json
{
  "success": true,
  "data": {
    "years": [2020, 2021, 2022, 2023, 2024],
    "disease_types": [
      {"value": "ht", "label": "Hipertensi"},
      {"value": "dm", "label": "Diabetes Mellitus"}
    ],
    "quarters": [
      {"value": 1, "label": "Q1 (Jan-Mar)"},
      {"value": 2, "label": "Q2 (Apr-Jun)"},
      {"value": 3, "label": "Q3 (Jul-Sep)"},
      {"value": 4, "label": "Q4 (Oct-Dec)"}
    ]
  }
}
```

### Get Available Years
**GET** `/api/statistics/export/years`

**Response:**
```json
{
  "success": true,
  "data": [2020, 2021, 2022, 2023, 2024]
}
```

### Get Puskesmas List
**GET** `/api/statistics/export/puskesmas`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Puskesmas A",
      "address": "Jl. Kesehatan No. 1"
    },
    {
      "id": 2,
      "name": "Puskesmas B",
      "address": "Jl. Kesehatan No. 2"
    }
  ]
}
```

---

## 🎯 Dashboard APIs

### Dashboard Response Structure

#### Admin Dashboard (All Puskesmas)

**GET** `/api/admin/dashboard`

**Response:**
```json
{
  "success": true,
  "data": {
    "year": "2025",
    "disease_type": "all",
    "month": null,
    "month_name": null,
    "total_puskesmas": 15,
    "summary": {
      "ht": {
        "target": "1518",
        "total_patients": "1366",
        "standard_patients": "1100",
        "non_standard_patients": "266",
        "male_patients": "511",
        "female_patients": "589",
        "achievement_percentage": 72.46,
        "monthly_data": {
          "1": {
            "target": "127",
            "male": "45",
            "female": "52",
            "total": "97",
            "standard": "78",
            "non_standard": "19",
            "percentage": 61.42
          }
          // ... bulan 2-12
        }
      },
      "dm": {
        // ... struktur sama seperti HT
      }
    },
    "puskesmas_data": [
      {
        "id": 1,
        "name": "Puskesmas A",
        "ht": {
          "target": "120",
          "total_patients": "95",
          "achievement_percentage": 79.17
        },
        "dm": {
          "target": "80",
          "total_patients": "65",
          "achievement_percentage": 81.25
        }
      }
      // ... data puskesmas lainnya
    ]
  }
}
```

#### Puskesmas Dashboard (Single Puskesmas)

**GET** `/api/puskesmas/dashboard`

**Response:**
```json
{
  "success": true,
  "data": {
    "year": "2025",
    "disease_type": "all",
    "month": null,
    "month_name": null,
    "puskesmas": {
      "id": 1,
      "name": "Puskesmas A",
      "address": "Jl. Kesehatan No. 1",
      "phone": "021-1234567",
      "email": "puskesmasa@example.com"
    },
    "summary": {
      "ht": {
        "target": "120",
        "total_patients": "95",
        "standard_patients": "78",
        "non_standard_patients": "17",
        "male_patients": "42",
        "female_patients": "53",
        "achievement_percentage": 79.17,
        "monthly_data": {
          // ... struktur monthly data
        }
      },
      "dm": {
        // ... struktur sama seperti HT
      }
    }
  }
}
```

### Dashboard Metadata

#### Response Metadata Fields

| Field | Type | Description |
|-------|------|-------------|
| `year` | string | Tahun data yang ditampilkan |
| `disease_type` | string | Jenis penyakit (`ht`, `dm`, `all`) |
| `month` | integer\|null | Bulan spesifik (1-12) atau null untuk semua bulan |
| `month_name` | string\|null | Nama bulan dalam bahasa Indonesia |
| `total_puskesmas` | integer | Total jumlah Puskesmas (hanya untuk admin) |
| `puskesmas` | object\|null | Data Puskesmas (hanya untuk user Puskesmas) |

#### Summary Data Structure

| Field | Type | Description |
|-------|------|-------------|
| `target` | string | Target tahunan |
| `total_patients` | string | Total pasien |
| `standard_patients` | string | Pasien dengan kontrol standar |
| `non_standard_patients` | string | Pasien dengan kontrol tidak standar |
| `male_patients` | string | Pasien laki-laki |
| `female_patients` | string | Pasien perempuan |
| `achievement_percentage` | float | Persentase pencapaian target |
| `monthly_data` | object | Data bulanan (bulan 1-12) |

#### Monthly Data Structure

| Field | Type | Description |
|-------|------|-------------|
| `target` | string | Target bulanan (target tahunan / 12) |
| `male` | string | Pasien laki-laki bulan ini |
| `female` | string | Pasien perempuan bulan ini |
| `total` | string | Total pasien bulan ini |
| `standard` | string | Pasien kontrol standar bulan ini |
| `non_standard` | string | Pasien kontrol tidak standar bulan ini |
| `percentage` | float | Persentase pencapaian target bulanan |

---

## 📄 Response Structures

### Standard Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data here
  }
}
```

### Paginated Response

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      // Array of items
    ],
    "first_page_url": "http://localhost:8000/api/endpoint?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://localhost:8000/api/endpoint?page=5",
    "links": [
      // Pagination links
    ],
    "next_page_url": "http://localhost:8000/api/endpoint?page=2",
    "path": "http://localhost:8000/api/endpoint",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 50
  }
}
```

---

## ❌ Error Handling

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Internal Server Error |

### Common Error Examples

#### Validation Error (422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

#### Authentication Error (401)
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

#### Authorization Error (403)
```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

#### Not Found Error (404)
```json
{
  "success": false,
  "message": "Resource not found."
}
```

#### Rate Limit Error (429)
```json
{
  "success": false,
  "message": "Too Many Attempts.",
  "retry_after": 60
}
```

---

## 🔧 API Testing

### Using cURL

```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Get dashboard statistics
curl -X GET http://localhost:8000/api/statistics/dashboard-statistics \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create yearly target
curl -X POST http://localhost:8000/api/admin/yearly-targets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"puskesmas_id":1,"disease_type":"ht","year":2024,"target_count":150}'
```

### Using Postman

1. **Set Base URL**: `http://localhost:8000/api`
2. **Add Authorization Header**: `Bearer YOUR_TOKEN`
3. **Set Content-Type**: `application/json`
4. **Import Collection**: Use the provided Postman collection file

---

## 🚀 Feature Implementation Details

### 1. Real-Time Statistics System

Sistem ini mengimplementasikan perhitungan statistik real-time untuk data examination yang dijalankan saat ada input dari user (puskesmas), sehingga tidak membebani dashboard dan data akan lebih cepat dalam penyajiannya.

#### Pre-calculated Statistics
Data examination langsung dihitung dan disimpan sebagai angka di database dengan kolom tambahan:
- `is_controlled`: Boolean untuk menentukan apakah hasil examination terkontrol
- `is_first_visit_this_month`: Boolean untuk menentukan apakah ini kunjungan pertama pasien di bulan tersebut
- `is_standard_patient`: Boolean untuk menentukan apakah pasien termasuk "standard" (rutin kontrol)
- `patient_gender`: Gender pasien (disimpan untuk menghindari join)

#### Real-Time Processing Flow
```
User Input → Observer (creating) → Set year/month
           → Observer (created) → RealTimeStatisticsService.processExaminationData()
```

### 2. Examination CRUD Improvements

#### Masalah yang Diperbaiki:
- **Method `store()`**: Sebelumnya melakukan DELETE + INSERT, sekarang INSERT murni
- **Method `update()`**: Sebelumnya DELETE + INSERT, sekarang UPDATE yang sebenarnya
- **Kehilangan History**: Data pemeriksaan lama tidak hilang lagi
- **ID Berubah**: ID pemeriksaan tetap konsisten

#### Solusi yang Diimplementasikan:

**Store Method - INSERT Murni:**
```php
// SEBELUM: DELETE + INSERT
DmExamination::where(...)->delete();
$examination = DmExamination::create($data);

// SESUDAH: INSERT Murni
$examination = DmExamination::create($data);
```

**Update Method - UPDATE Sebenarnya:**
```php
// SEBELUM: DELETE + INSERT
DmExamination::where(...)->delete();
DmExamination::create($newData);

// SESUDAH: UPDATE yang sebenarnya
$dmExamination->update($newData);
```

### 3. Profile Picture Upload System

#### Features:
- **File Upload Service**: Centralized file handling dengan `FileUploadService`
- **Validation & Security**: Format file JPEG, PNG, JPG, GIF dengan ukuran maksimal 2MB
- **Automatic Image Resizing**: Resize otomatis ke 200x200 pixels
- **Storage Management**: File disimpan di `resources/img/`

#### API Endpoints:
- `PUT /api/users/me` - Upload/update foto profil user yang sedang login
- `GET /api/users/me` - Mendapatkan profil user dengan URL foto profil
- `GET /img/{filename}` - Serving file gambar

#### Example Request:
```bash
curl -X PUT \
  http://localhost:8000/api/users/me \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: multipart/form-data' \
  -F 'profile_picture=@/path/to/image.jpg'
```

### 4. Statistics Controller Migration

Migrasi dari `DashboardController` ke `StatisticsController` dengan implementasi `RealTimeStatisticsService`.

#### Route Changes:

**Admin Routes:**
```php
// SEBELUM:
Route::get('/dashboard', [DashboardController::class, 'dinasIndex']);

// SESUDAH:
Route::get('/dashboard', [StatisticsController::class, 'adminStatistics']);
```

**Puskesmas Routes:**
```php
// SEBELUM:
Route::get('/dashboard', [DashboardController::class, 'puskesmasIndex']);

// SESUDAH:
Route::get('/dashboard', [StatisticsController::class, 'dashboardStatistics']);
```

### 5. PDF Generation Best Practices

#### Custom Exception Handling:
- `PuskesmasNotFoundException` untuk error handling yang spesifik
- Custom error response dengan format JSON yang konsisten
- Context data untuk debugging dan correlation ID untuk tracking

#### Repository Pattern Implementation:
- `PuskesmasRepositoryInterface` dan `PuskesmasRepository`
- Separation of concerns dan easier testing
- Built-in caching dan consistent error handling

#### Form Request Validation:
- `PuskesmasPdfRequest` untuk validasi input PDF generation
- Centralized validation rules

## 📝 API Changelog

### Version 1.3.0 (Latest)
- Added real-time statistics system with pre-calculated data
- Implemented profile picture upload with automatic resizing
- Fixed examination CRUD operations (proper INSERT/UPDATE)
- Migrated to StatisticsController with RealTimeStatisticsService
- Added PDF generation best practices and custom exception handling
- Enhanced error handling and validation

### Version 1.2.0
- Added yearly targets management endpoints
- Enhanced dashboard statistics with real-time data
- Improved error handling and validation
- Added comprehensive API documentation

### Version 1.1.0
- Fixed dashboard statistics endpoint
- Added Puskesmas dashboard structure
- Improved response formatting

### Version 1.0.0
- Initial API implementation
- Basic CRUD operations
- Authentication system
- Statistics endpoints

---

## ⚡ Real-Time Features

### Real-Time Statistics System

Sistem ini mengimplementasikan perhitungan statistik real-time untuk data examination yang dijalankan saat ada input dari user (puskesmas), sehingga tidak membebani dashboard dan data akan lebih cepat dalam penyajiannya.

#### Pre-calculated Statistics
Data examination langsung dihitung dan disimpan sebagai angka di database dengan kolom tambahan:
- `is_controlled`: Boolean untuk menentukan apakah hasil examination terkontrol
- `is_first_visit_this_month`: Boolean untuk menentukan apakah ini kunjungan pertama pasien di bulan tersebut
- `is_standard_patient`: Boolean untuk menentukan apakah pasien termasuk "standard" (rutin kontrol)
- `patient_gender`: Gender pasien (disimpan untuk menghindari join)

#### Real-Time Processing Flow
```
User Input → Observer (creating) → Set year/month
           → Observer (created) → RealTimeStatisticsService.processExaminationData()
           → Calculate statistics → Update cache → Update patient standard status
```

#### Performance Benefits
1. **Faster Dashboard Loading**: Data sudah pre-calculated, tidak perlu query kompleks
2. **Real-time Updates**: Cache otomatis update saat ada input baru
3. **Reduced Database Load**: Mengurangi beban query kompleks pada dashboard
4. **Scalable**: Sistem dapat menangani volume data yang besar dengan performa konsisten

### Examination CRUD Improvements

#### Fixed Issues:
- **Method `store()`**: Sebelumnya melakukan DELETE + INSERT, sekarang INSERT murni
- **Method `update()`**: Sebelumnya DELETE + INSERT, sekarang UPDATE yang sebenarnya
- **Kehilangan History**: Data pemeriksaan lama tidak hilang lagi
- **ID Berubah**: ID pemeriksaan tetap konsisten

#### Implementation:

**Store Method - INSERT Murni:**
```php
// SEBELUM: DELETE + INSERT
DmExamination::where(...)->delete();
$examination = DmExamination::create($data);

// SESUDAH: INSERT Murni
$examination = DmExamination::create($data);
```

**Update Method - UPDATE Sebenarnya:**
```php
// SEBELUM: DELETE + INSERT
DmExamination::where(...)->delete();
DmExamination::create($newData);

// SESUDAH: UPDATE yang sebenarnya
$dmExamination->update($newData);
```

---

## 🏗️ System Architecture

### Database Schema Overview

#### Core Tables
- **users**: User management dengan role-based access
- **puskesmas**: Puskesmas data dan konfigurasi
- **patients**: Data pasien dengan NIK unik
- **ht_examinations**: Pemeriksaan hipertensi
- **dm_examinations**: Pemeriksaan diabetes mellitus
- **yearly_targets**: Target tahunan per puskesmas
- **monthly_statistics**: Cache statistik bulanan
- **archived_examinations**: Data pemeriksaan yang diarsipkan

#### Key Relationships
```
users → puskesmas (belongs_to)
puskesmas → patients (has_many)
puskesmas → examinations (has_many)
puskesmas → yearly_targets (has_many)
patients → examinations (has_many)
```

### API Architecture

#### Authentication Flow
```
Client → API → Sanctum Auth → Controller → Service → Repository → Database
```

#### Service Layer Pattern
- **RealTimeStatisticsService**: Real-time calculation dan cache management
- **FileUploadService**: Centralized file handling
- **ArchiveService**: Data archiving dan cleanup
- **StatisticsCacheService**: Cache management untuk performa

#### Repository Pattern
- **PuskesmasRepositoryInterface**: Abstraction untuk data access
- **Consistent Error Handling**: Custom exceptions dan response format
- **Built-in Caching**: Optimized query performance

### Security Features

#### Authentication & Authorization
- **Laravel Sanctum**: Token-based authentication
- **Role-based Access**: Admin vs Puskesmas user permissions
- **Rate Limiting**: API endpoint protection
- **CORS Configuration**: Cross-origin request handling

#### Data Validation
- **Form Request Validation**: Centralized validation rules
- **Custom Validation Rules**: Disease type, year range validation
- **File Upload Security**: Type, size, dan content validation
- **SQL Injection Protection**: Eloquent ORM protection

### Performance Optimizations

#### Caching Strategy
- **Monthly Statistics Cache**: Pre-calculated dashboard data
- **Real-time Updates**: Automatic cache invalidation
- **Query Optimization**: Efficient database queries
- **File Serving**: Optimized image serving

#### Database Optimizations
- **Indexed Columns**: Performance-critical columns
- **Relationship Eager Loading**: Reduced N+1 queries
- **Batch Operations**: Efficient bulk data processing
- **Archive Strategy**: Historical data management

---

*This comprehensive API reference provides complete documentation for all available endpoints, real-time features, and system architecture. For implementation examples and best practices, refer to the development guide.*