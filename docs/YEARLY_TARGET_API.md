# 🎯 Yearly Target API Documentation

Dokumentasi lengkap untuk API manajemen target tahunan dalam sistem Akudihatinya.

## Base URL
```
http://localhost:8000/api/admin
```

## Authentication
Semua endpoint memerlukan autentikasi menggunakan Laravel Sanctum dengan header:
```
Authorization: Bearer {token}
```

**Role Required:** Admin

---

## Endpoints

### 1. Get Yearly Targets
**GET** `/yearly-targets`

Mendapatkan daftar target tahunan dengan filtering dan pagination.

#### Query Parameters
- `puskesmas_id` (optional): Filter berdasarkan ID puskesmas
- `disease_type` (optional): Filter berdasarkan jenis penyakit (`ht`, `dm`)
- `year` (optional): Filter berdasarkan tahun
- `page` (optional): Nomor halaman untuk pagination
- `per_page` (optional): Jumlah data per halaman (default: 10)

#### Behavior
- **List Mode**: Jika tidak semua parameter identifikasi (`puskesmas_id`, `disease_type`, `year`) disediakan, akan mengembalikan daftar dengan pagination
- **Show Mode**: Jika semua parameter identifikasi disediakan, akan mengembalikan satu target spesifik

#### Examples

**List Mode:**
```
GET /api/admin/yearly-targets
GET /api/admin/yearly-targets?year=2024
GET /api/admin/yearly-targets?disease_type=ht
GET /api/admin/yearly-targets?puskesmas_id=1&per_page=20
```

**Show Mode:**
```
GET /api/admin/yearly-targets?puskesmas_id=1&disease_type=ht&year=2024
```

#### Response (List Mode)
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "puskesmas_id": 1,
      "disease_type": "ht",
      "year": 2024,
      "target_count": 100,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z",
      "puskesmas": {
        "id": 1,
        "name": "Puskesmas Contoh",
        "address": "Jl. Contoh No. 123"
      }
    }
  ],
  "first_page_url": "http://localhost:8000/api/admin/yearly-targets?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://localhost:8000/api/admin/yearly-targets?page=1",
  "links": [
    {
      "url": null,
      "label": "&laquo; Previous",
      "active": false
    },
    {
      "url": "http://localhost:8000/api/admin/yearly-targets?page=1",
      "label": "1",
      "active": true
    },
    {
      "url": null,
      "label": "Next &raquo;",
      "active": false
    }
  ],
  "next_page_url": null,
  "path": "http://localhost:8000/api/admin/yearly-targets",
  "per_page": 10,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```

#### Response (Show Mode)
```json
{
  "target": {
    "id": 1,
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 100,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "puskesmas": {
      "id": 1,
      "name": "Puskesmas Contoh",
      "address": "Jl. Contoh No. 123"
    }
  }
}
```

#### Error Response (404 - Show Mode)
```json
{
  "error": "yearly_target_not_found",
  "message": "ID sasaran tahunan tidak ditemukan"
}
```

---

### 2. Create Yearly Target
**POST** `/yearly-targets`

Membuat target tahunan baru atau memperbarui yang sudah ada.

#### Request Body
```json
{
  "puskesmas_id": 1,
  "disease_type": "ht",
  "year": 2024,
  "target_count": 100
}
```

#### Validation Rules
- `puskesmas_id`: required, integer, exists in puskesmas table
- `disease_type`: required, string, in: ht, dm
- `year`: required, integer
- `target_count`: required, integer, min: 1

#### Response
```json
{
  "message": "Sasaran tahunan berhasil disimpan",
  "target": {
    "id": 1,
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 100,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

#### Error Response (422 - Validation Error)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "puskesmas_id": ["The puskesmas id field is required."],
    "disease_type": ["The disease type field is required."],
    "year": ["The year field is required."],
    "target_count": ["The target count field is required."]
  }
}
```

---

### 3. Update Yearly Target
**PUT** `/yearly-targets`

Memperbarui target tahunan berdasarkan kombinasi puskesmas_id, disease_type, dan year.

#### Query Parameters (Optional)
Parameter query dapat digunakan untuk konsistensi, tetapi data utama diambil dari request body.

#### Request Body
```json
{
  "puskesmas_id": 1,
  "disease_type": "ht",
  "year": 2024,
  "target_count": 150
}
```

#### Validation Rules
- `puskesmas_id`: required, integer, exists in puskesmas table
- `disease_type`: required, string, in: ht, dm
- `year`: required, integer
- `target_count`: required, integer, min: 1

#### Response
```json
{
  "message": "Target tahunan berhasil diperbarui",
  "target": {
    "id": 1,
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 150,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T12:00:00.000000Z"
  }
}
```

#### Error Response (404)
```json
{
  "error": "yearly_target_not_found",
  "message": "ID sasaran tahunan tidak ditemukan"
}
```

---

### 4. Delete Yearly Target
**DELETE** `/yearly-targets`

Menghapus target tahunan berdasarkan kombinasi puskesmas_id, disease_type, dan year.

#### Query Parameters (Optional)
Parameter query dapat digunakan untuk konsistensi, tetapi data utama diambil dari request body.

#### Request Body
```json
{
  "puskesmas_id": 1,
  "disease_type": "ht",
  "year": 2024
}
```

#### Validation Rules
- `puskesmas_id`: required, integer
- `disease_type`: required, string, in: ht, dm
- `year`: required, integer

#### Response
```json
{
  "message": "Target tahunan berhasil dihapus"
}
```

#### Error Response (404)
```json
{
  "error": "yearly_target_not_found",
  "message": "ID sasaran tahunan tidak ditemukan"
}
```

---

## Error Codes

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
  "error": "yearly_target_not_found",
  "message": "ID sasaran tahunan tidak ditemukan"
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

## Data Types

### Disease Types
- `ht`: Hipertensi (Hypertension)
- `dm`: Diabetes Melitus (Diabetes Mellitus)

### Target Object
```json
{
  "id": "integer - Primary key",
  "puskesmas_id": "integer - Foreign key ke tabel puskesmas",
  "disease_type": "string - Jenis penyakit (ht/dm)",
  "year": "integer - Tahun target",
  "target_count": "integer - Jumlah target",
  "created_at": "timestamp - Waktu dibuat",
  "updated_at": "timestamp - Waktu diperbarui",
  "puskesmas": {
    "id": "integer - ID puskesmas",
    "name": "string - Nama puskesmas",
    "address": "string - Alamat puskesmas"
  }
}
```

---

## Business Rules

1. **Unique Constraint**: Kombinasi `puskesmas_id`, `disease_type`, dan `year` harus unik
2. **Target Count**: Harus berupa integer positif (minimal 1)
3. **Disease Type**: Hanya menerima nilai `ht` atau `dm`
4. **Year**: Harus berupa integer yang valid
5. **Puskesmas**: Harus ada dalam database

---

## Usage Examples

### Scenario 1: Membuat Target Baru
```bash
curl -X POST \
  http://localhost:8000/api/admin/yearly-targets \
  -H 'Authorization: Bearer your_token_here' \
  -H 'Content-Type: application/json' \
  -d '{
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 100
  }'
```

### Scenario 2: Melihat Target Spesifik
```bash
curl -X GET \
  'http://localhost:8000/api/admin/yearly-targets?puskesmas_id=1&disease_type=ht&year=2024' \
  -H 'Authorization: Bearer your_token_here'
```

### Scenario 3: Memperbarui Target
```bash
curl -X PUT \
  http://localhost:8000/api/admin/yearly-targets \
  -H 'Authorization: Bearer your_token_here' \
  -H 'Content-Type: application/json' \
  -d '{
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024,
    "target_count": 150
  }'
```

### Scenario 4: Menghapus Target
```bash
curl -X DELETE \
  http://localhost:8000/api/admin/yearly-targets \
  -H 'Authorization: Bearer your_token_here' \
  -H 'Content-Type: application/json' \
  -d '{
    "puskesmas_id": 1,
    "disease_type": "ht",
    "year": 2024
  }'
```

### Scenario 5: Melihat Semua Target dengan Filter
```bash
curl -X GET \
  'http://localhost:8000/api/admin/yearly-targets?year=2024&per_page=20' \
  -H 'Authorization: Bearer your_token_here'
```

---

## Notes

1. **Pagination**: Endpoint GET mendukung pagination Laravel standar
2. **Filtering**: Mendukung filtering berdasarkan puskesmas, jenis penyakit, dan tahun
3. **Dual Mode**: Endpoint GET dapat berfungsi sebagai list atau show berdasarkan parameter
4. **Validation**: Semua input divalidasi sesuai aturan bisnis
5. **Error Handling**: Pesan error dalam bahasa Indonesia untuk user experience yang lebih baik
6. **Authentication**: Semua endpoint memerlukan token autentikasi dan role admin

---

## Changelog

### Version 2.0 (Latest)
- Mengubah semua endpoint untuk menggunakan query parameters alih-alih route parameters
- Menggabungkan fungsi show ke dalam endpoint index
- Menambahkan validasi yang lebih ketat
- Memperbaiki pesan error untuk konsistensi

### Version 1.0
- Implementasi awal dengan route model binding
- Endpoint terpisah untuk setiap operasi CRUD