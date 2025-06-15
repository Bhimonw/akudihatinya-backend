# Entity Relationship Diagram (ERD)

Dokumen ini berisi Entity Relationship Diagram lengkap untuk sistem Akudihatinya Backend.

## ERD Diagram

```mermaid
erDiagram
    USERS {
        bigint id PK
        string username UK "Unique username"
        string password "Hashed password"
        string name "Full name"
        string profile_picture "Profile image path"
        enum role "admin, puskesmas"
        bigint puskesmas_id FK "NULL for admin"
        timestamp created_at
        timestamp updated_at
    }

    PUSKESMAS {
        bigint id PK
        string name "Nama Puskesmas"
        text address "Alamat lengkap"
        string phone "Nomor telepon"
        string email "Email puskesmas"
        boolean is_active "Status aktif"
        timestamp created_at
        timestamp updated_at
    }

    PATIENTS {
        bigint id PK
        bigint puskesmas_id FK
        string nik UK "Nomor Induk Kependudukan"
        string bpjs_number "Nomor BPJS"
        string medical_record_number "Nomor rekam medis"
        string name "Nama lengkap pasien"
        text address "Alamat pasien"
        string phone_number "Nomor telepon pasien"
        enum gender "male, female"
        date birth_date "Tanggal lahir"
        integer age "Usia pasien"
        json ht_years "Array tahun menderita HT"
        json dm_years "Array tahun menderita DM"
        timestamp created_at
        timestamp updated_at
    }

    HT_EXAMINATIONS {
        bigint id PK
        bigint patient_id FK
        bigint puskesmas_id FK
        date examination_date "Tanggal pemeriksaan"
        integer systolic "Tekanan sistolik"
        integer diastolic "Tekanan diastolik"
        integer year "Tahun pemeriksaan"
        integer month "Bulan pemeriksaan"
        boolean is_archived "Status arsip"
        timestamp created_at
        timestamp updated_at
    }

    DM_EXAMINATIONS {
        bigint id PK
        bigint patient_id FK
        bigint puskesmas_id FK
        date examination_date "Tanggal pemeriksaan"
        enum examination_type "hba1c, gdp, gd2jpp, gdsp"
        decimal result "Hasil pemeriksaan"
        integer year "Tahun pemeriksaan"
        integer month "Bulan pemeriksaan"
        boolean is_archived "Status arsip"
        timestamp created_at
        timestamp updated_at
    }

    YEARLY_TARGETS {
        bigint id PK
        bigint puskesmas_id FK
        enum disease_type "ht, dm"
        integer year "Tahun target"
        integer target_count "Jumlah target"
        timestamp created_at
        timestamp updated_at
    }

    USER_REFRESH_TOKENS {
        bigint id PK
        bigint user_id FK
        string token "Refresh token"
        timestamp expires_at "Waktu kadaluarsa"
        timestamp created_at
        timestamp updated_at
    }

    MONTHLY_STATISTICS_CACHE {
        bigint id PK
        bigint puskesmas_id FK
        enum disease_type "ht, dm"
        integer year "Tahun statistik"
        integer month "Bulan statistik"
        json data "Data statistik dalam JSON"
        timestamp created_at
        timestamp updated_at
    }

    PERSONAL_ACCESS_TOKENS {
        bigint id PK
        string tokenable_type "Model type"
        bigint tokenable_id "Model ID"
        string name "Token name"
        string token "Hashed token"
        json abilities "Token abilities"
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    %% Primary Relationships
    PUSKESMAS ||--o{ USERS : "has many users"
    PUSKESMAS ||--o{ PATIENTS : "has many patients"
    PUSKESMAS ||--o{ HT_EXAMINATIONS : "has many ht_examinations"
    PUSKESMAS ||--o{ DM_EXAMINATIONS : "has many dm_examinations"
    PUSKESMAS ||--o{ YEARLY_TARGETS : "has many yearly_targets"
    PUSKESMAS ||--o{ MONTHLY_STATISTICS_CACHE : "has many cache_entries"
    
    PATIENTS ||--o{ HT_EXAMINATIONS : "has many ht_examinations"
    PATIENTS ||--o{ DM_EXAMINATIONS : "has many dm_examinations"
    
    USERS ||--o{ USER_REFRESH_TOKENS : "has many refresh_tokens"
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : "has many access_tokens"
```

## Penjelasan Detail Entitas

### 1. USERS
**Deskripsi:** Tabel pengguna sistem yang mencakup admin dan pengguna puskesmas.

**Atribut Utama:**
- `role`: Menentukan level akses (admin dapat mengakses semua data, puskesmas hanya data sendiri)
- `puskesmas_id`: NULL untuk admin, berisi ID puskesmas untuk user puskesmas
- `username`: Unique identifier untuk login

**Relasi:**
- Belongs to PUSKESMAS (untuk user dengan role puskesmas)
- Has many USER_REFRESH_TOKENS
- Has many PERSONAL_ACCESS_TOKENS

### 2. PUSKESMAS
**Deskripsi:** Master data puskesmas yang menjadi pusat dari hampir semua entitas lain.

**Atribut Utama:**
- `is_active`: Flag untuk mengaktifkan/menonaktifkan puskesmas
- `name`: Nama resmi puskesmas

**Relasi:**
- Has many USERS
- Has many PATIENTS
- Has many HT_EXAMINATIONS
- Has many DM_EXAMINATIONS
- Has many YEARLY_TARGETS
- Has many MONTHLY_STATISTICS_CACHE

### 3. PATIENTS
**Deskripsi:** Data pasien yang terdaftar di puskesmas.

**Atribut Utama:**
- `nik`: Nomor Induk Kependudukan sebagai unique identifier
- `ht_years`: Array JSON berisi tahun-tahun pasien menderita hipertensi
- `dm_years`: Array JSON berisi tahun-tahun pasien menderita diabetes
- `medical_record_number`: Nomor rekam medis internal puskesmas

**Relasi:**
- Belongs to PUSKESMAS
- Has many HT_EXAMINATIONS
- Has many DM_EXAMINATIONS

### 4. HT_EXAMINATIONS
**Deskripsi:** Data pemeriksaan hipertensi pasien.

**Atribut Utama:**
- `systolic`: Tekanan darah sistolik
- `diastolic`: Tekanan darah diastolik
- `is_archived`: Flag untuk arsip data lama

**Business Logic:**
- Tekanan terkendali: sistolik 90-139 dan diastolik 60-89

**Relasi:**
- Belongs to PATIENT
- Belongs to PUSKESMAS

### 5. DM_EXAMINATIONS
**Deskripsi:** Data pemeriksaan diabetes mellitus pasien.

**Atribut Utama:**
- `examination_type`: Jenis pemeriksaan (HbA1c, GDP, GD2JPP, GDSP)
- `result`: Hasil pemeriksaan dalam bentuk decimal
- `is_archived`: Flag untuk arsip data lama

**Business Logic:**
- Kontrol berdasarkan jenis:
  - HbA1c: terkendali jika < 7
  - GDP: terkendali jika < 126
  - GD2JPP: terkendali jika < 200
  - GDSP: tidak dihitung untuk kontrol

**Relasi:**
- Belongs to PATIENT
- Belongs to PUSKESMAS

### 6. YEARLY_TARGETS
**Deskripsi:** Target tahunan untuk setiap puskesmas per jenis penyakit.

**Atribut Utama:**
- `disease_type`: Jenis penyakit (ht/dm)
- `target_count`: Jumlah target yang harus dicapai

**Relasi:**
- Belongs to PUSKESMAS

### 7. USER_REFRESH_TOKENS
**Deskripsi:** Token refresh untuk sistem autentikasi.

**Atribut Utama:**
- `token`: Refresh token string
- `expires_at`: Waktu kadaluarsa token

**Relasi:**
- Belongs to USER

### 8. MONTHLY_STATISTICS_CACHE
**Deskripsi:** Cache untuk statistik bulanan guna meningkatkan performa.

**Atribut Utama:**
- `data`: Data statistik dalam format JSON
- `disease_type`: Jenis penyakit untuk statistik

**Relasi:**
- Belongs to PUSKESMAS

### 9. PERSONAL_ACCESS_TOKENS
**Deskripsi:** Token akses personal untuk API authentication (Laravel Sanctum).

**Atribut Utama:**
- `tokenable_type`: Tipe model (biasanya User)
- `tokenable_id`: ID dari model
- `abilities`: Kemampuan yang dimiliki token

**Relasi:**
- Polymorphic relationship dengan USERS

## Indeks dan Constraint

### Primary Keys
- Semua tabel menggunakan `id` sebagai primary key dengan tipe `bigint`

### Foreign Keys
- `users.puskesmas_id` → `puskesmas.id`
- `patients.puskesmas_id` → `puskesmas.id`
- `ht_examinations.patient_id` → `patients.id`
- `ht_examinations.puskesmas_id` → `puskesmas.id`
- `dm_examinations.patient_id` → `patients.id`
- `dm_examinations.puskesmas_id` → `puskesmas.id`
- `yearly_targets.puskesmas_id` → `puskesmas.id`
- `user_refresh_tokens.user_id` → `users.id`
- `monthly_statistics_cache.puskesmas_id` → `puskesmas.id`

### Unique Constraints
- `users.username`
- `patients.nik`

### Performance Indexes
- Index pada `examination_date` untuk tabel pemeriksaan
- Index pada `year` dan `month` untuk filtering temporal
- Index pada `puskesmas_id` untuk filtering berdasarkan puskesmas
- Composite index pada `(puskesmas_id, year, month)` untuk statistik

## Catatan Implementasi

1. **Soft Deletes**: Tidak digunakan, sebagai gantinya menggunakan `is_archived` untuk data pemeriksaan
2. **JSON Fields**: Digunakan untuk `ht_years`, `dm_years`, dan `data` statistik
3. **Enum Values**: Didefinisikan di level aplikasi untuk konsistensi
4. **Timestamps**: Semua tabel menggunakan `created_at` dan `updated_at`
5. **Caching**: Implementasi cache pada level aplikasi untuk statistik bulanan