# API Documentation - Akudihatinya Backend

Dokumentasi lengkap untuk semua endpoint API yang tersedia dalam sistem Akudihatinya.

## Base URL
```
http://localhost:8000/api
```

## Authentication
Semua endpoint yang memerlukan autentikasi menggunakan Laravel Sanctum dengan header:
```
Authorization: Bearer {token}
```

---

## 1. Authentication Endpoints

### Login
**POST** `/auth/login`

Login user dan mendapatkan access token.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "access_token": "token_string",
  "refresh_token": "refresh_token_string",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com",
    "role": "admin|puskesmas"
  }
}
```

### Refresh Token
**POST** `/auth/refresh`

Memperbarui access token menggunakan refresh token.

**Request Body:**
```json
{
  "refresh_token": "refresh_token_string"
}
```

### Logout
**POST** `/auth/logout`

Logout user dan menghapus token.

**Headers:** `Authorization: Bearer {token}`

### Get Current User
**GET** `/auth/user`

Mendapatkan informasi user yang sedang login.

**Headers:** `Authorization: Bearer {token}`

### Change Password
**POST** `/auth/change-password`

Mengubah password user.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "current_password": "current_password",
  "new_password": "new_password",
  "new_password_confirmation": "new_password"
}
```

---

## 2. User Management Endpoints

### Get Current User Profile
**GET** `/users/me`

Mendapatkan profil user yang sedang login.

**Headers:** `Authorization: Bearer {token}`

### Update Current User Profile
**PUT** `/users/me`

Memperbarui profil user yang sedang login.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "name": "New Name",
  "email": "new@example.com"
}
```

---

## 3. Statistics Endpoints

### Dashboard Statistics
**GET** `/statistics/dashboard-statistics`

Mendapatkan statistik untuk dashboard.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `year` (optional): Tahun statistik (default: tahun sekarang)
- `month` (optional): Bulan statistik (1-12, default: semua bulan)
- `disease_type` (optional): Jenis penyakit (`all`, `ht`, `dm`, default: `all`)

### Admin Statistics
**GET** `/statistics/admin`

Mendapatkan statistik khusus admin (hanya untuk admin).

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Admin

### General Statistics
**GET** `/statistics/`

Mendapatkan statistik umum.

**Headers:** `Authorization: Bearer {token}`

### HT Statistics
**GET** `/statistics/ht`

Mendapatkan statistik hipertensi.

**Headers:** `Authorization: Bearer {token}`

### DM Statistics
**GET** `/statistics/dm`

Mendapatkan statistik diabetes melitus.

**Headers:** `Authorization: Bearer {token}`

---

## 4. Export Endpoints

### Export Statistics
**GET** `/statistics/export`

Mengexport data statistik dalam format Excel atau PDF.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `year` (optional): Tahun (default: tahun sekarang)
- `month` (optional): Bulan (1-12, default: laporan tahunan)
- `disease_type` (optional): Jenis penyakit (`all`, `ht`, `dm`, default: `all`)
- `table_type` (optional): Jenis tabel (`all`, `quarterly`, `monthly`, `puskesmas`, default: `all`)
- `format` (optional): Format export (`excel`, `pdf`, default: `excel`)
- `puskesmas_id` (optional): ID puskesmas (untuk admin)
- `name` (optional): Filter nama puskesmas (untuk admin)

**Examples:**
```bash
# Export Excel tahunan semua penyakit
GET /api/statistics/export?year=2024&format=excel

# Export PDF bulanan hipertensi
GET /api/statistics/export?year=2024&month=6&disease_type=ht&format=pdf

# Export PDF puskesmas
GET /api/statistics/export?table_type=puskesmas&year=2024&disease_type=ht&format=pdf
```

### Export Monthly Statistics
**GET** `/statistics/{year}/{month}/export`

Mengexport statistik bulanan.

**Headers:** `Authorization: Bearer {token}`

**Path Parameters:**
- `year`: Tahun (format: YYYY)
- `month`: Bulan (1-12)

**Query Parameters:**
- `disease_type` (optional): Jenis penyakit (`all`, `ht`, `dm`)
- `format` (optional): Format export (`excel`, `pdf`)

### Export Puskesmas PDF (Annual)
**POST** `/statistics/export/puskesmas-pdf`

Mengexport laporan tahunan puskesmas dalam format PDF khusus.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "disease_type": "ht",
  "year": 2024,
  "puskesmas_id": 1
}
```

### Export Puskesmas PDF (Quarterly)
**POST** `/statistics/export/puskesmas-quarterly-pdf`

Mengexport laporan triwulanan puskesmas dalam format PDF khusus.

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "disease_type": "ht",
  "year": 2024,
  "quarter": 1,
  "puskesmas_id": 1
}
```

### Export Monitoring Report
**GET** `/statistics/monitoring`

Mengexport laporan pemantauan pasien.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `year` (optional): Tahun
- `month` (optional): Bulan
- `disease_type` (optional): Jenis penyakit
- `format` (optional): Format export

### Export Monthly Monitoring Report
**GET** `/statistics/monitoring/{year}/{month}`

Mengexport laporan pemantauan bulanan.

**Headers:** `Authorization: Bearer {token}`

**Path Parameters:**
- `year`: Tahun (format: YYYY)
- `month`: Bulan (1-12)

---

## 5. Export Utility Endpoints

### Get Available Years
**GET** `/statistics/export/years`

Mendapatkan daftar tahun yang tersedia untuk export.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "years": [2023, 2024, 2025]
}
```

### Get Puskesmas List
**GET** `/statistics/export/puskesmas`

Mendapatkan daftar puskesmas (untuk admin).

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Admin

**Response:**
```json
{
  "puskesmas": [
    {
      "id": 1,
      "name": "Puskesmas A",
      "address": "Alamat Puskesmas A"
    }
  ]
}
```

### Get Export Options
**GET** `/statistics/export/options`

Mendapatkan opsi export yang tersedia.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "disease_types": [
    {"value": "ht", "label": "Hipertensi"},
    {"value": "dm", "label": "Diabetes Melitus"}
  ],
  "can_select_puskesmas": true,
  "user_role": "admin"
}
```

---

## 6. Admin Endpoints

### Admin Dashboard
**GET** `/admin/dashboard`

Mendapatkan data dashboard admin.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Admin

### User Management
**GET** `/admin/users`
**POST** `/admin/users`
**GET** `/admin/users/{id}`
**PUT** `/admin/users/{id}`
**DELETE** `/admin/users/{id}`

CRUD operations untuk manajemen user.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Admin

### Reset User Password
**POST** `/admin/users/{id}/reset-password`

Reset password user.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Admin

### Yearly Target Management
**GET** `/admin/yearly-targets`
**POST** `/admin/yearly-targets`
**GET** `/admin/yearly-targets/{id}`
**PUT** `/admin/yearly-targets/{id}`
**DELETE** `/admin/yearly-targets/{id}`

CRUD operations untuk manajemen target tahunan.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Admin

---

## 7. Puskesmas Endpoints

### Puskesmas Dashboard
**GET** `/puskesmas/dashboard`

Mendapatkan data dashboard puskesmas.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Puskesmas

### Patient Management
**GET** `/puskesmas/patients`
**POST** `/puskesmas/patients`
**GET** `/puskesmas/patients/{id}`
**PUT** `/puskesmas/patients/{id}`
**DELETE** `/puskesmas/patients/{id}`

CRUD operations untuk manajemen pasien.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Puskesmas

### Patient Examination Year
**POST** `/puskesmas/patients/{id}/examination-year`
**PUT** `/puskesmas/patients/{id}/examination-year`

Menambah/menghapus tahun pemeriksaan pasien.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Puskesmas

### HT Examination Management
**GET** `/puskesmas/ht-examinations`
**POST** `/puskesmas/ht-examinations`
**GET** `/puskesmas/ht-examinations/{id}`
**PUT** `/puskesmas/ht-examinations/{id}`
**DELETE** `/puskesmas/ht-examinations/{id}`

CRUD operations untuk pemeriksaan hipertensi.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Puskesmas

### DM Examination Management
**GET** `/puskesmas/dm-examinations`
**POST** `/puskesmas/dm-examinations`
**GET** `/puskesmas/dm-examinations/{id}`
**PUT** `/puskesmas/dm-examinations/{id}`
**DELETE** `/puskesmas/dm-examinations/{id}`

CRUD operations untuk pemeriksaan diabetes melitus.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Puskesmas

### Update Profile
**POST** `/profile`

Memperbarui profil puskesmas.

**Headers:** `Authorization: Bearer {token}`
**Role Required:** Puskesmas

---

## Error Responses

### 400 Bad Request
```json
{
  "message": "Parameter tidak valid"
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "message": "Unauthorized"
}
```

### 404 Not Found
```json
{
  "message": "Data tidak ditemukan"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### 500 Internal Server Error
```json
{
  "message": "Terjadi kesalahan server",
  "error": "Error details"
}
```

---

## Notes

1. **Role-based Access**: Beberapa endpoint memerlukan role tertentu (Admin atau Puskesmas)
2. **Pagination**: Endpoint yang mengembalikan list data mendukung pagination dengan parameter `page` dan `per_page`
3. **Filtering**: Banyak endpoint mendukung filtering berdasarkan tahun, bulan, jenis penyakit, dll.
4. **File Downloads**: Endpoint export akan mengembalikan file untuk didownload
5. **Validation**: Semua input akan divalidasi sesuai dengan aturan bisnis

## Rate Limiting

API menggunakan rate limiting untuk mencegah abuse:
- 60 requests per menit untuk endpoint umum
- 30 requests per menit untuk endpoint export

## Versioning

Saat ini menggunakan v1. Versi API akan ditambahkan di URL jika diperlukan di masa depan.