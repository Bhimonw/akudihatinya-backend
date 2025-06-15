# Statistics Export API Documentation

API untuk export data statistik dalam format Excel. Endpoint telah dikonsolidasi menjadi 2 endpoint utama.

## Base URL
```
http://localhost:8000/api/statistics
```

## Authentication
Semua endpoint memerlukan autentikasi menggunakan Sanctum token:
```
Authorization: Bearer {token}
```

## Consolidated Endpoints

### 1. Admin Export
Export data statistik untuk admin (dapat mengakses semua puskesmas).

**Endpoint:** `GET /admin/export`

**Parameters:**
- `year` (required): Tahun data yang akan di-export (format: YYYY)
- `disease_type` (optional): Jenis penyakit (`all`, `ht`, `dm`). Default: `all`
- `table_type` (optional): Jenis tabel (`all`, `quarterly`, `monthly`, `puskesmas`). Default: `all`
- `puskesmas_id` (optional): ID puskesmas tertentu (hanya untuk admin)
- `format` (optional): Format export (`excel`, `pdf`). Default: `excel`

**Example Request:**
```bash
# Export semua data puskesmas untuk tahun 2024
curl -X GET "http://localhost:8000/api/statistics/admin/export?year=2024&table_type=puskesmas&disease_type=ht" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Accept: application/json"
```

### 2. Puskesmas Export
Export data statistik untuk puskesmas (hanya data puskesmas sendiri).

**Endpoint:** `GET /export`

**Parameters:**
- `year` (required): Tahun data yang akan di-export (format: YYYY)
- `disease_type` (optional): Jenis penyakit (`all`, `ht`, `dm`). Default: `all`
- `table_type` (optional): Jenis tabel (`all`, `quarterly`, `monthly`, `puskesmas`). Default: `all`
- `format` (optional): Format export (`excel`, `pdf`). Default: `excel`

**Example Request:**
```bash
# Export data puskesmas sendiri untuk tahun 2024
curl -X GET "http://localhost:8000/api/statistics/export?year=2024&table_type=puskesmas&disease_type=dm" \
  -H "Authorization: Bearer {puskesmas_token}" \
  -H "Accept: application/json"
```

### 3. Get Available Years

**Endpoint:** `GET /api/statistics/export/years`

**Parameters:**
- `puskesmas_id` (optional): ID puskesmas (hanya untuk admin)

**Response:**
```json
{
  "success": true,
  "data": [2024, 2023, 2022]
}
```

### 4. Get Puskesmas List (Admin Only)

**Endpoint:** `GET /api/statistics/export/puskesmas`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Puskesmas A"
    },
    {
      "id": 2,
      "name": "Puskesmas B"
    }
  ]
}
```

### 5. Get Export Options

**Endpoint:** `GET /api/statistics/export/options`

**Response:**
```json
{
  "success": true,
  "data": {
    "disease_types": [
      {
        "value": "ht",
        "label": "Hipertensi"
      },
      {
        "value": "dm",
        "label": "Diabetes Melitus"
      }
    ],
    "years": [2024, 2023, 2022],
    "puskesmas_list": [
      {
        "id": 1,
        "name": "Puskesmas A"
      }
    ]
  }
}
```

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "disease_type": ["The disease type field is required."]
  }
}
```

### Unauthorized (403)
```json
{
  "success": false,
  "message": "Unauthorized access"
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Export failed: Error message"
}
```

## User Permissions

### Admin Users
- Dapat mengakses data semua puskesmas
- Dapat menentukan `puskesmas_id` dalam parameter
- Dapat mengakses endpoint `/export/puskesmas`

### Puskesmas Users
- Hanya dapat mengakses data puskesmas mereka sendiri
- Parameter `puskesmas_id` akan diabaikan dan diganti dengan puskesmas user
- Tidak dapat mengakses endpoint `/export/puskesmas`

## Template Excel

Export menggunakan template Excel yang tersimpan di `resources/excel/puskesmas.xlsx`. Template ini berisi:

- Header dengan placeholder untuk tahun dan jenis penyakit
- Kolom untuk data bulanan (Januari - Desember)
- Kolom untuk data tahunan
- Format styling yang konsisten

## File Output

File yang dihasilkan akan memiliki nama format:
```
laporan_puskesmas_{disease_type}_{year}_{puskesmas_name}.xlsx
```

Contoh:
```
laporan_puskesmas_dm_2024_puskesmas_a.xlsx
```

## Implementation Details

### Services Used
- `PuskesmasExportService`: Service utama untuk export
- `PuskesmasFormatter`: Formatter untuk mengisi data ke template Excel
- `StatisticsService`: Service untuk mengambil data statistik

### Dependencies
- PhpSpreadsheet: Library untuk manipulasi Excel
- Laravel Sanctum: Authentication
- Carbon: Date manipulation

### Template Structure
Template Excel menggunakan struktur yang sama dengan formatter admin lainnya:
- Kolom A-C: No, Nama Puskesmas, Target
- Kolom D-BK: Data bulanan (5 kolom per bulan)
- Kolom BL-BQ: Data tahunan

### Data Calculation
Data yang ditampilkan mengikuti logik yang telah dimodifikasi:
- `male_count` dan `female_count`: Hanya pasien standar
- `total_count`: Semua pasien (standar + non-standar)
- `standard_count`: Pasien standar
- `non_standard_count`: Pasien non-standar
- `percentage`: Persentase pasien standar