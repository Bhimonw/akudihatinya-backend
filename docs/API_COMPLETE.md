# 📚 Complete API Documentation

<p align="center">
  <img src="https://img.shields.io/badge/API-REST-blue?style=for-the-badge" alt="REST API">
  <img src="https://img.shields.io/badge/Auth-Sanctum-green?style=for-the-badge" alt="Laravel Sanctum">
  <img src="https://img.shields.io/badge/Format-JSON-orange?style=for-the-badge" alt="JSON">
  <img src="https://img.shields.io/badge/Version-1.3.0-purple?style=for-the-badge" alt="Version">
</p>

<h3>🔌 Comprehensive API Reference</h3>
<p><em>Complete documentation for all API endpoints, authentication, real-time features, and system architecture</em></p>

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Authentication](#authentication)
3. [Base Configuration](#base-configuration)
4. [Admin APIs](#admin-apis)
5. [Puskesmas APIs](#puskesmas-apis)
6. [Statistics APIs](#statistics-apis)
7. [Dashboard APIs](#dashboard-apis)
8. [User Profile APIs](#user-profile-apis)
9. [Export APIs](#export-apis)
10. [File Upload APIs](#file-upload-apis)
11. [Real-Time Features](#real-time-features)
12. [Response Structures](#response-structures)
13. [Error Handling](#error-handling)
14. [System Architecture](#system-architecture)
15. [Development Guide](#development-guide)
16. [Testing](#testing)
17. [Changelog](#changelog)

---

## 🚀 Quick Start

### Base URLs
- **Development**: `http://localhost:8000/api`
- **Production**: `https://your-domain.com/api`

### Authentication Headers
```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {your_access_token}
```

### Quick Test
```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Get Dashboard Statistics
curl -X GET http://localhost:8000/api/statistics/dashboard-statistics \
  -H "Authorization: Bearer {token}"
```

---

## 🔐 Authentication

### Authentication Method
All API endpoints use **Laravel Sanctum** for authentication with Bearer tokens.

### Login Endpoint

**POST** `/api/auth/login`

**Request:**
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
      "puskesmas_id": null,
      "profile_picture_url": "http://localhost:8000/storage/profile_pictures/user_1.jpg"
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

### User Roles
- **admin**: Full system access
- **puskesmas**: Limited to own puskesmas data

---

## ⚙️ Base Configuration

### Rate Limiting
- **General APIs**: 60 requests per minute
- **Authentication**: 5 requests per minute
- **Statistics**: 30 requests per minute
- **File Upload**: 10 requests per minute

### Common Response Format
```json
{
  "success": true|false,
  "message": "Response message",
  "data": {},
  "errors": {},
  "meta": {}
}
```

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
  "phone": "081234567890",
  "medical_record_number": "MR001"
}
```

#### Update Patient
**PUT** `/api/puskesmas/patients/{id}`

#### Delete Patient
**DELETE** `/api/puskesmas/patients/{id}`

### Examination Management

#### Hypertension Examinations
**GET** `/api/puskesmas/ht-examinations`
**POST** `/api/puskesmas/ht-examinations`

**Request Body:**
```json
{
  "patient_id": 1,
  "examination_date": "2024-01-15",
  "systolic_pressure": 140,
  "diastolic_pressure": 90,
  "medication_given": true,
  "notes": "Pasien kontrol rutin"
}
```

**PUT** `/api/puskesmas/ht-examinations/{id}`
**DELETE** `/api/puskesmas/ht-examinations/{id}`

#### Diabetes Examinations
**GET** `/api/puskesmas/dm-examinations`
**POST** `/api/puskesmas/dm-examinations`

**Request Body:**
```json
{
  "patient_id": 1,
  "examination_date": "2024-01-15",
  "blood_sugar_level": 180,
  "medication_given": true,
  "notes": "Gula darah tinggi"
}
```

**PUT** `/api/puskesmas/dm-examinations/{id}`
**DELETE** `/api/puskesmas/dm-examinations/{id}`

---

## 📊 Statistics APIs

### Dashboard Statistics
**GET** `/api/statistics/dashboard-statistics`

**Query Parameters:**
- `year` (optional): Filter by year (default: current year)
- `month` (optional): Filter by month (1-12)
- `disease_type` (optional): Filter by disease type (`ht`, `dm`, `all`)

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_patients": 1250,
      "total_examinations": 3450,
      "controlled_patients": 890,
      "control_percentage": 71.2
    },
    "monthly_data": [
      {
        "month": 1,
        "month_name": "Januari",
        "ht_examinations": 120,
        "dm_examinations": 85,
        "controlled_ht": 85,
        "controlled_dm": 60
      }
    ],
    "disease_breakdown": {
      "hypertension": {
        "total_patients": 750,
        "controlled": 520,
        "percentage": 69.3
      },
      "diabetes": {
        "total_patients": 500,
        "controlled": 370,
        "percentage": 74.0
      }
    }
  }
}
```

### Monthly Statistics
**GET** `/api/statistics/monthly`

### Yearly Statistics
**GET** `/api/statistics/yearly`

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
    "profile_picture_url": "http://localhost:8000/storage/profile_pictures/user_1.jpg",
    "puskesmas": {
      "id": 1,
      "name": "Puskesmas A",
      "address": "Jl. Kesehatan No. 1"
    }
  }
}
```

### Update User Profile
**PUT** `/api/users/me`

**Request Body (Form Data):**
```
name: John Doe Updated
email: john.updated@example.com
profile_picture: [file]
```

**Response:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "John Doe Updated",
    "email": "john.updated@example.com",
    "profile_picture_url": "http://localhost:8000/storage/profile_pictures/user_1_optimized.jpg"
  }
}
```

---

## 📤 Export APIs

### Puskesmas PDF Export
**POST** `/api/statistics/export/puskesmas-pdf`

**Request Body:**
```json
{
  "puskesmas_id": 1,
  "year": 2024,
  "disease_type": "ht"
}
```

### Quarterly PDF Export
**POST** `/api/statistics/export/puskesmas-quarterly-pdf`

**Request Body:**
```json
{
  "puskesmas_id": 1,
  "year": 2024,
  "quarter": 1,
  "disease_type": "all"
}
```

### Export Options
**GET** `/api/statistics/export/options`

**Response:**
```json
{
  "success": true,
  "data": {
    "years": [2024, 2023, 2022],
    "quarters": [1, 2, 3, 4],
    "disease_types": [
      {"value": "ht", "label": "Hipertensi"},
      {"value": "dm", "label": "Diabetes Mellitus"},
      {"value": "all", "label": "Semua Penyakit"}
    ],
    "formats": ["pdf", "excel"]
  }
}
```

---

## 📁 File Upload APIs

### Profile Picture Upload
**PUT** `/api/users/me` (with multipart/form-data)

**Features:**
- **Unlimited file size**: Chunked upload for large files
- **Auto optimization**: Automatic resizing and compression
- **Format conversion**: Support for JPG, PNG, WebP
- **Real-time processing**: On-the-fly image conversion

**Request:**
```http
PUT /api/users/me
Content-Type: multipart/form-data

profile_picture: [large_image_file.jpg]
name: Updated Name
```

**Response:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "profile_picture_url": "http://localhost:8000/storage/profile_pictures/user_1_optimized.jpg",
    "file_size": "2.5MB",
    "optimized_size": "450KB",
    "compression_ratio": "82%"
  }
}
```

### Image Format Conversion
**GET** `/storage/profile_pictures/{filename}?format=webp&width=300&height=300`

**Parameters:**
- `format`: Target format (webp, jpg, png)
- `width`: Target width in pixels
- `height`: Target height in pixels
- `quality`: Image quality (1-100)

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

---

## 📋 Response Structures

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.3.0"
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [],
    "first_page_url": "http://localhost:8000/api/endpoint?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://localhost:8000/api/endpoint?page=5",
    "links": [],
    "next_page_url": "http://localhost:8000/api/endpoint?page=2",
    "path": "http://localhost:8000/api/endpoint",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 50
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  },
  "meta": {
    "error_code": "VALIDATION_ERROR",
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

---

## 🚨 Error Handling

### HTTP Status Codes
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `429`: Too Many Requests
- `500`: Internal Server Error

### Custom Error Codes
- `VALIDATION_ERROR`: Input validation failed
- `AUTHENTICATION_ERROR`: Invalid credentials
- `AUTHORIZATION_ERROR`: Insufficient permissions
- `PUSKESMAS_NOT_FOUND`: Puskesmas not found
- `PATIENT_NOT_FOUND`: Patient not found
- `FILE_UPLOAD_ERROR`: File upload failed
- `RATE_LIMIT_EXCEEDED`: Too many requests

### Error Response Examples

#### Validation Error
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "nik": ["NIK sudah terdaftar dalam sistem."],
    "birth_date": ["Tanggal lahir harus sebelum hari ini."]
  }
}
```

#### Authentication Error
```json
{
  "success": false,
  "message": "Invalid credentials",
  "error_code": "AUTHENTICATION_ERROR"
}
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

---

## 💻 Development Guide

### API Development Best Practices

#### Controller Structure
```php
class PatientController extends Controller
{
    public function __construct(
        private PatientService $patientService
    ) {}

    public function index(Request $request)
    {
        $patients = $this->patientService->getPaginated(
            $request->get('search'),
            $request->get('per_page', 10)
        );

        return PatientResource::collection($patients);
    }

    public function store(PatientRequest $request)
    {
        $patient = $this->patientService->create($request->validated());

        return new PatientResource($patient);
    }
}
```

#### Request Validation
```php
class PatientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'nik' => 'required|string|size:16|unique:patients',
            'birth_date' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
        ];
    }

    public function messages(): array
    {
        return [
            'nik.unique' => 'NIK sudah terdaftar dalam sistem.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
        ];
    }
}
```

#### API Resources
```php
class PatientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nik' => $this->nik,
            'age' => $this->getAge(),
            'gender' => $this->gender,
            'puskesmas' => new PuskesmasResource($this->whenLoaded('puskesmas')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

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

## 🧪 Testing

### API Testing Examples

#### Authentication Test
```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

#### Statistics Test
```bash
# Get Dashboard Statistics
curl -X GET http://localhost:8000/api/statistics/dashboard-statistics \
  -H "Authorization: Bearer {token}"
```

#### Create Target Test
```bash
# Create Yearly Target
curl -X POST http://localhost:8000/api/admin/yearly-targets \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"puskesmas_id":1,"disease_type":"ht","year":2024,"target_count":150}'
```

### Postman Collection

1. **Set Base URL**: `http://localhost:8000/api`
2. **Set Authorization**: Bearer Token
3. **Import Collection**: Available in `/docs/postman/`

### Unit Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test tests/Feature/API/StatisticsExportTest.php

# Run with coverage
php artisan test --coverage
```

---

## 📝 Changelog

### Version 1.3.0 (Latest) - 2024-01-15
- ✅ Added real-time statistics system with pre-calculated data
- ✅ Implemented profile picture upload with automatic resizing and optimization
- ✅ Fixed examination CRUD operations (proper INSERT/UPDATE)
- ✅ Migrated to StatisticsController with RealTimeStatisticsService
- ✅ Added PDF generation best practices and custom exception handling
- ✅ Enhanced error handling and validation
- ✅ Added unlimited file upload support with chunked processing
- ✅ Implemented image format conversion and optimization
- ✅ Added comprehensive API documentation consolidation

### Version 1.2.0 - 2024-01-10
- ✅ Added yearly targets management endpoints
- ✅ Enhanced dashboard statistics with real-time data
- ✅ Improved error handling and validation
- ✅ Added comprehensive API documentation

### Version 1.1.0 - 2024-01-05
- ✅ Fixed dashboard statistics endpoint
- ✅ Added Puskesmas dashboard structure
- ✅ Improved response formatting

### Version 1.0.0 - 2024-01-01
- ✅ Initial API implementation
- ✅ Basic CRUD operations
- ✅ Authentication system
- ✅ Statistics endpoints

---

## 📞 Support

### Documentation Links
- **System Architecture**: [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md)
- **Developer Guide**: [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md)
- **File Upload Guide**: [FILE_UPLOAD_GUIDE.md](./FILE_UPLOAD_GUIDE.md)
- **Code Quality**: [CODE_QUALITY_INSIGHTS.md](./CODE_QUALITY_INSIGHTS.md)

### Contact
- **Project Repository**: [GitHub Repository](https://github.com/your-repo)
- **Issue Tracker**: [GitHub Issues](https://github.com/your-repo/issues)
- **Documentation**: [Project Documentation](./README.md)

---

*This comprehensive API documentation provides complete reference for all available endpoints, real-time features, file upload capabilities, and system architecture. For implementation examples and best practices, refer to the linked documentation files.*