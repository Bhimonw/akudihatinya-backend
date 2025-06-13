# Puskesmas Export API Documentation

API untuk export statistik puskesmas dalam format Excel menggunakan template yang telah disediakan.

## Base URL
```
GET /api/puskesmas/export
```

## Authentication
Semua endpoint memerlukan authentication menggunakan Sanctum token.

## Endpoints

### 1. Export Statistik Puskesmas

**Endpoint:** `GET /api/puskesmas/export`

**Parameters:**
- `disease_type` (required): `ht` atau `dm`
- `year` (required): Tahun (integer, min: 2020, max: tahun depan)
- `puskesmas_id` (optional): ID puskesmas (hanya untuk admin)

**Response:** File Excel (.xlsx)

**Example:**
```bash
curl -X GET "http://localhost:8000/api/puskesmas/export?disease_type=dm&year=2024&puskesmas_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Export Statistik HT

**Endpoint:** `GET /api/puskesmas/export/ht`

**Parameters:**
- `year` (required): Tahun
- `puskesmas_id` (optional): ID puskesmas (hanya untuk admin)

**Example:**
```bash
curl -X GET "http://localhost:8000/api/puskesmas/export/ht?year=2024" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Export Statistik DM

**Endpoint:** `GET /api/puskesmas/export/dm`

**Parameters:**
- `year` (required): Tahun
- `puskesmas_id` (optional): ID puskesmas (hanya untuk admin)

**Example:**
```bash
curl -X GET "http://localhost:8000/api/puskesmas/export/dm?year=2024" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 4. Get Available Years

**Endpoint:** `GET /api/puskesmas/export/years`

**Parameters:**
- `puskesmas_id` (optional): ID puskesmas (hanya untuk admin)

**Response:**
```json
{
  "success": true,
  "data": [2024, 2023, 2022]
}
```

### 5. Get Puskesmas List (Admin Only)

**Endpoint:** `GET /api/puskesmas/export/puskesmas`

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

### 6. Get Export Options

**Endpoint:** `GET /api/puskesmas/export/options`

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