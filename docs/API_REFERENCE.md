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
7. [Response Structures](#response-structures)
8. [Error Handling](#error-handling)

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

## 📝 API Changelog

### Version 2.0 (Latest)
- ✅ Fixed dashboard statistics JSON response consistency
- ✅ Added missing fields: `non_standard_patients`, `male_patients`, `female_patients`
- ✅ Improved yearly targets API with flexible filtering
- ✅ Enhanced error handling and validation
- ✅ Added comprehensive dashboard response structure
- ✅ Implemented proper pagination for all list endpoints

### Version 1.0
- ✅ Initial API implementation
- ✅ Basic CRUD operations
- ✅ Authentication system
- ✅ Statistics endpoints

---

*This API reference provides complete documentation for all available endpoints. For implementation examples and best practices, refer to the development guide.*