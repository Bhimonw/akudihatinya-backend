# 📊 Dashboard Puskesmas - Summary & Metadata Structure

Dokumentasi ini menjelaskan struktur lengkap summary dan metadata untuk dashboard puskesmas dalam sistem Akudihatinya Backend.

## 🏗️ Struktur Response Dashboard

### Response untuk Admin (Semua Puskesmas)

```json
{
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
        },
        "2": {
          "target": "127",
          "male": "38",
          "female": "44",
          "total": "82",
          "standard": "65",
          "non_standard": "17",
          "percentage": 51.18
        }
        // ... data untuk bulan 3-12
      }
    },
    "dm": {
      "target": "892",
      "total_patients": "756",
      "standard_patients": "612",
      "non_standard_patients": "144",
      "male_patients": "298",
      "female_patients": "458",
      "achievement_percentage": 68.61,
      "monthly_data": {
        "1": {
          "target": "74",
          "male": "28",
          "female": "35",
          "total": "63",
          "standard": "51",
          "non_standard": "12",
          "percentage": 68.92
        }
        // ... data untuk bulan 2-12
      }
    }
  },
  "data": [
    {
      "puskesmas_id": 1,
      "puskesmas_name": "Puskesmas A",
      "ranking": 1,
      "ht": {
        "target": 150,
        "total_patients": "135",
        "achievement_percentage": 85.33,
        "standard_patients": "128",
        "non_standard_patients": "7",
        "monthly_data": {
          // struktur sama dengan summary monthly_data
        }
      },
      "dm": {
        "target": 90,
        "total_patients": "78",
        "achievement_percentage": 82.22,
        "standard_patients": "74",
        "non_standard_patients": "4",
        "monthly_data": {
          // struktur sama dengan summary monthly_data
        }
      }
    }
    // ... data puskesmas lainnya
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 15,
    "total": 15
  },
  "all_puskesmas": [
    {
      "id": 1,
      "name": "Puskesmas A"
    },
    {
      "id": 2,
      "name": "Puskesmas B"
    }
    // ... semua puskesmas
  ]
}
```

### Response untuk Puskesmas Spesifik

```json
{
  "year": "2025",
  "disease_type": "all",
  "month": null,
  "month_name": null,
  "data": [
    {
      "puskesmas_id": 1,
      "puskesmas_name": "Puskesmas A",
      "ranking": 1,
      "ht": {
        "target": 150,
        "total_patients": "135",
        "achievement_percentage": 85.33,
        "standard_patients": "128",
        "non_standard_patients": "7",
        "male_patients": "65",
        "female_patients": "70",
        "monthly_data": {
          "1": {
            "target": "13",
            "male": "5",
            "female": "6",
            "total": "11",
            "standard": "9",
            "non_standard": "2",
            "percentage": 69.23
          }
          // ... data bulan 2-12
        }
      },
      "dm": {
        "target": 90,
        "total_patients": "78",
        "achievement_percentage": 82.22,
        "standard_patients": "74",
        "non_standard_patients": "4",
        "male_patients": "35",
        "female_patients": "43",
        "monthly_data": {
          // struktur sama dengan ht monthly_data
        }
      }
    }
  ]
}
```

## 📋 Penjelasan Field

### Metadata Utama
- **year**: Tahun data (string)
- **disease_type**: Jenis penyakit (`"all"`, `"ht"`, `"dm"`)
- **month**: Bulan spesifik (null untuk data tahunan)
- **month_name**: Nama bulan (null untuk data tahunan)
- **total_puskesmas**: Total jumlah puskesmas (hanya untuk admin)

### Summary (Hanya untuk Admin)
- **target**: Target tahunan total semua puskesmas
- **total_patients**: Total pasien yang diperiksa
- **standard_patients**: Pasien dengan kondisi terkontrol/standar
- **non_standard_patients**: Pasien dengan kondisi tidak terkontrol
- **male_patients**: Total pasien laki-laki
- **female_patients**: Total pasien perempuan
- **achievement_percentage**: Persentase pencapaian (standard/target * 100)
- **monthly_data**: Data bulanan agregat dari semua puskesmas

### Data Puskesmas
- **puskesmas_id**: ID puskesmas
- **puskesmas_name**: Nama puskesmas
- **ranking**: Peringkat berdasarkan achievement_percentage
- **ht/dm**: Data spesifik per jenis penyakit
  - **target**: Target tahunan untuk jenis penyakit
  - **total_patients**: Total pasien yang diperiksa
  - **achievement_percentage**: Persentase pencapaian (standard/target * 100)
  - **standard_patients**: Pasien dengan kondisi terkontrol/standar
  - **non_standard_patients**: Pasien dengan kondisi tidak terkontrol
  - **male_patients**: Total pasien laki-laki
  - **female_patients**: Total pasien perempuan
  - **monthly_data**: Data bulanan dengan target bulanan (target tahunan / 12)

### Monthly Data Structure
Setiap bulan (1-12) memiliki struktur:
- **target**: Target bulanan
- **male**: Jumlah pasien laki-laki
- **female**: Jumlah pasien perempuan
- **total**: Total pasien (male + female)
- **standard**: Pasien dengan kondisi terkontrol
- **non_standard**: Pasien dengan kondisi tidak terkontrol
- **percentage**: Persentase pencapaian bulanan

### Meta (Pagination)
- **current_page**: Halaman saat ini
- **from**: Index data pertama
- **last_page**: Halaman terakhir
- **per_page**: Jumlah item per halaman
- **to**: Index data terakhir
- **total**: Total item

### All Puskesmas (Hanya untuk Admin)
Daftar semua puskesmas dengan id dan nama untuk keperluan filter/dropdown.

## 🔄 Endpoint yang Menggunakan Struktur Ini

### Admin Dashboard
```
GET /api/admin/dashboard
GET /api/statistics/admin
```

### Puskesmas Dashboard
```
GET /api/puskesmas/dashboard
GET /api/statistics/dashboard-statistics
```

## 📊 Parameter Request

- **year**: Tahun data (default: tahun saat ini)
- **disease_type**: Jenis penyakit (`all`, `ht`, `dm`) (default: `all`)
- **type**: Alias untuk disease_type (backward compatibility)

## 🔍 Implementasi

Struktur ini diimplementasikan dalam:
- **Controller**: `StatisticsController@dashboardStatistics`
- **Service**: `RealTimeStatisticsService@getFastDashboardStats`
- **Method**: `getAdminDashboardData()` dan `getPuskesmasSpecificData()`

## 📈 Contoh Penggunaan

### Request untuk Data Semua Penyakit Tahun 2025
```bash
GET /api/admin/dashboard?year=2025&disease_type=all
```

### Request untuk Data HT Saja
```bash
GET /api/puskesmas/dashboard?year=2025&disease_type=ht
```

### Request untuk Data DM Saja
```bash
GET /api/statistics/dashboard-statistics?year=2025&disease_type=dm
```

## ⚡ Real-Time Features

- Data dihitung secara real-time menggunakan `RealTimeStatisticsService`
- Cache bulanan untuk performa optimal
- Observer pattern untuk update otomatis saat ada data baru
- Pre-calculated statistics untuk response yang cepat

## 🎯 Ranking System

Puskesmas diurutkan berdasarkan:
1. **Achievement percentage** (tertinggi ke terendah)
2. Jika sama, berdasarkan **total_patients** (tertinggi ke terendah)
3. Jika masih sama, berdasarkan **puskesmas_id** (terkecil ke terbesar)

Ranking dihitung menggunakan method `calculateRanking()` dalam controller.