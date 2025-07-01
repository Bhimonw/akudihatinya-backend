# Comprehensive System Design - Akudihatinya

## 📋 Daftar Isi

1. [Overview Sistem](#overview-sistem)
2. [Entity Relationship Diagram (ERD)](#entity-relationship-diagram-erd)
3. [Use Case Diagram Keseluruhan](#use-case-diagram-keseluruhan)
4. [Activity Diagrams](#activity-diagrams)
5. [Sequence Diagrams](#sequence-diagrams)
6. [Business Rules](#business-rules)
7. [Technical Architecture](#technical-architecture)

---

## Overview Sistem

**Akudihatinya** adalah sistem manajemen data kesehatan yang dirancang khusus untuk pemantauan dan pelaporan penyakit Hipertensi (HT) dan Diabetes Mellitus (DM) di tingkat Puskesmas. Sistem ini memfasilitasi dua level pengguna utama:

- **Admin (Dinas Kesehatan)**: Mengelola seluruh sistem, target tahunan, dan monitoring pencapaian
- **Petugas Puskesmas**: Mengelola data pasien dan pemeriksaan di puskesmas masing-masing

---

## Entity Relationship Diagram (ERD)

### 🗃️ Entitas Utama

#### 1. **Users**
```
id (PK)
username (UNIQUE)
password
name
profile_picture
role (admin/puskesmas)
puskesmas_id (FK)
created_at
updated_at
```

#### 2. **Puskesmas**
```
id (PK)
name
created_at
updated_at
```

#### 3. **Patients**
```
id (PK)
puskesmas_id (FK)
nik (UNIQUE, 16 chars)
bpjs_number (20 chars)
medical_record_number
name
address
phone_number
gender (male/female)
birth_date
age
has_ht (Boolean, calculated)
has_dm (Boolean, calculated)
ht_years (JSON Array)
dm_years (JSON Array)
created_at
updated_at
```

#### 4. **HT_Examinations**
```
id (PK)
patient_id (FK)
puskesmas_id (FK)
examination_date
systolic
diastolic
is_controlled (Boolean)
is_first_visit_this_month (Boolean)
is_standard_patient (Boolean)
patient_gender
year
month
is_archived (Boolean)
created_at
updated_at
```

#### 5. **DM_Examinations**
```
id (PK)
patient_id (FK)
puskesmas_id (FK)
examination_date
examination_type (hba1c/gdp)
result (Decimal)
is_controlled (Boolean)
is_first_visit_this_month (Boolean)
is_standard_patient (Boolean)
patient_gender
year
month
is_archived (Boolean)
created_at
updated_at
```

#### 6. **Yearly_Targets**
```
id (PK)
puskesmas_id (FK)
disease_type (ht/dm)
year
target_count
created_at
updated_at
```

#### 7. **User_Refresh_Tokens**
```
id (PK)
user_id (FK)
token
expires_at
created_at
updated_at
```

#### 8. **Monthly_Statistics_Cache**
```
id (PK)
puskesmas_id (FK)
disease_type
year
month
statistics_data (JSON)
created_at
updated_at
```

### 🔗 Relasi Antar Entitas

```
Users (1) ←→ (0..1) Puskesmas
Puskesmas (1) ←→ (*) Patients
Puskesmas (1) ←→ (*) HT_Examinations
Puskesmas (1) ←→ (*) DM_Examinations
Puskesmas (1) ←→ (*) Yearly_Targets
Puskesmas (1) ←→ (*) Monthly_Statistics_Cache
Patients (1) ←→ (*) HT_Examinations
Patients (1) ←→ (*) DM_Examinations
Users (1) ←→ (*) User_Refresh_Tokens
```

---

## Use Case Diagram Keseluruhan

### 👥 Aktor

#### 🔵 Admin (Dinas Kesehatan)
- **Role**: admin
- **Akses**: Seluruh sistem
- **Scope**: Data semua puskesmas

#### 🟢 Petugas Puskesmas
- **Role**: puskesmas
- **Akses**: Data puskesmas sendiri
- **Scope**: Operasional harian

### 📊 Use Cases Berdasarkan Modul

#### **A. 🔐 Autentikasi & Otorisasi**
```
┌─────────────────────────────────────┐
│            AUTHENTICATION          │
├─────────────────────────────────────┤
│ • Login                             │
│ • Logout                            │
│ • Refresh Token                     │
│ • Change Password                   │
│ • Update Profile                    │
└─────────────────────────────────────┘
        ↑                    ↑
    [Admin]              [Puskesmas]
```

#### **B. 👥 Manajemen User (Admin Only)**
```
┌─────────────────────────────────────┐
│           USER MANAGEMENT           │
├─────────────────────────────────────┤
│ • Create User                       │
│ • View Users                        │
│ • Update User                       │
│ • Delete User                       │
│ • Reset Password                    │
└─────────────────────────────────────┘
        ↑
    [Admin]
```

#### **C. 🎯 Target Tahunan (Admin Only)**
```
┌─────────────────────────────────────┐
│          YEARLY TARGETS             │
├─────────────────────────────────────┤
│ • Set Yearly Target                 │
│ • View Yearly Targets               │
│ • Update Yearly Target              │
│ • Delete Yearly Target              │
└─────────────────────────────────────┘
        ↑
    [Admin]
```

#### **D. 👤 Manajemen Pasien (Puskesmas)**
```
┌─────────────────────────────────────┐
│         PATIENT MANAGEMENT          │
├─────────────────────────────────────┤
│ • Register Patient                  │
│ • View Patients                     │
│ • Update Patient                    │
│ • Delete Patient                    │
│ • Export Patient Data               │
│ • Add Examination Year              │
│ • Remove Examination Year           │
└─────────────────────────────────────┘
        ↑
    [Puskesmas]
```

#### **E. 🩺 Pemeriksaan HT (Puskesmas)**
```
┌─────────────────────────────────────┐
│         HT EXAMINATIONS             │
├─────────────────────────────────────┤
│ • Record HT Examination             │
│ • View HT Examinations              │
│ • Update HT Examination             │
│ • Delete HT Examination             │
└─────────────────────────────────────┘
        ↑
    [Puskesmas]
```

#### **F. 💉 Pemeriksaan DM (Puskesmas)**
```
┌─────────────────────────────────────┐
│         DM EXAMINATIONS             │
├─────────────────────────────────────┤
│ • Record DM Examination             │
│ • View DM Examinations              │
│ • Update DM Examination             │
│ • Delete DM Examination             │
└─────────────────────────────────────┘
        ↑
    [Puskesmas]
```

#### **G. 📊 Dashboard & Statistik**
```
┌─────────────────────────────────────┐
│       DASHBOARD & STATISTICS        │
├─────────────────────────────────────┤
│ • View Admin Dashboard              │
│ • View Puskesmas Dashboard          │
│ • Generate Reports                  │
│ • Export Statistics                 │
│ • Real-time Data Updates            │
└─────────────────────────────────────┘
        ↑                    ↑
    [Admin]              [Puskesmas]
```

### 🔄 Hubungan Use Case

```
Authentication
    ↓
┌─────────────────┬─────────────────┐
│   Admin Path    │ Puskesmas Path  │
├─────────────────┼─────────────────┤
│ User Management │ Patient Mgmt    │
│ Yearly Targets  │ HT Examination  │
│ Admin Dashboard │ DM Examination  │
│ System Reports  │ Local Reports   │
└─────────────────┴─────────────────┘
```

---

## Activity Diagrams

### 🔐 Activity Diagram: User Authentication

```
[Start] → [Input Credentials] → [Validate Credentials]
    ↓
[Valid?] → No → [Show Error] → [End]
    ↓ Yes
[Generate Tokens] → [Store Refresh Token] → [Return Access Token] → [End]
```

### 👤 Activity Diagram: Patient Registration

```
[Start] → [Input Patient Data] → [Validate NIK]
    ↓
[NIK Exists?] → Yes → [Show Error] → [End]
    ↓ No
[Validate BPJS] → [Save Patient] → [Update Patient Flags] → [End]
```

### 🩺 Activity Diagram: HT Examination Recording

```
[Start] → [Select Patient] → [Input BP Values] → [Calculate Control Status]
    ↓
[Check First Visit] → [Calculate Standard Patient] → [Save Examination]
    ↓
[Update Cache] → [Update Patient Flags] → [End]
```

### 💉 Activity Diagram: DM Examination Recording

```
[Start] → [Select Patient] → [Choose Test Type] → [Input Result]
    ↓
[Calculate Control Status] → [Check First Visit] → [Calculate Standard Patient]
    ↓
[Save Examination] → [Update Cache] → [Update Patient Flags] → [End]
```

### 📊 Activity Diagram: Statistics Generation

```
[Start] → [Check Cache] → [Cache Valid?]
    ↓ No                    ↓ Yes
[Query Database] → [Calculate Stats] → [Update Cache] → [Return Data] → [End]
```

### 📋 Activity Diagram: Report Export

```
[Start] → [Select Report Type] → [Set Parameters] → [Generate Data]
    ↓
[Choose Format] → [PDF/Excel?]
    ↓ PDF                    ↓ Excel
[Generate PDF] → [Return File] ← [Generate Excel]
    ↓
[End]
```

---

## Sequence Diagrams

### 🔐 Sequence Diagram: User Login

```
User → API: POST /auth/login {username, password}
API → Database: Validate credentials
Database → API: User data
API → TokenService: Generate tokens
TokenService → API: {access_token, refresh_token}
API → Database: Store refresh token
API → User: {access_token, refresh_token, user_data}
```

### 👤 Sequence Diagram: Patient Registration

```
Puskesmas → API: POST /patients {patient_data}
API → Validator: Validate input
Validator → API: Validation result
API → Database: Check NIK uniqueness
Database → API: NIK status
API → Database: Save patient
Database → API: Patient created
API → CacheService: Update statistics
API → Puskesmas: Patient data
```

### 🩺 Sequence Diagram: HT Examination

```
Puskesmas → API: POST /examinations/ht {examination_data}
API → Validator: Validate input
API → Database: Get patient data
API → BusinessLogic: Calculate control status
API → BusinessLogic: Check first visit
API → BusinessLogic: Calculate standard patient
API → Database: Save examination
API → Observer: Trigger examination saved
Observer → CacheService: Update monthly cache
API → Puskesmas: Examination data
```

### 📊 Sequence Diagram: Dashboard Statistics

```
User → API: GET /statistics/dashboard
API → AuthService: Validate token
API → CacheService: Check monthly cache
CacheService → Database: Query if cache miss
CacheService → API: Statistics data
API → Formatter: Format response
API → User: Dashboard data
```

### 📋 Sequence Diagram: Report Export

```
User → API: GET /exports/report {type, format, params}
API → AuthService: Validate permissions
API → Database: Query report data
API → ExportService: Generate report
ExportService → FileSystem: Create file
ExportService → API: File path
API → User: Download link
```

---

## Business Rules

### 🩺 Kontrol Medis

#### Hipertensi (HT)
- **Terkontrol**: Sistolik 90-139 mmHg DAN Diastolik 60-89 mmHg
- **Tidak Terkontrol**: Di luar range tersebut

#### Diabetes Mellitus (DM)
- **HbA1c Terkontrol**: < 7%
- **GDP Terkontrol**: < 126 mg/dL

### 📅 Aturan Kunjungan

1. **Kunjungan Pertama Bulanan**: Hanya satu per pasien per bulan
2. **Pasien Standard**: Berdasarkan algoritma khusus untuk pelaporan
3. **Tahun Pemeriksaan**: Pasien dapat memiliki multiple tahun aktif

### 🎯 Target dan Pelaporan

1. **Target Tahunan**: Ditetapkan per puskesmas per jenis penyakit
2. **Pencapaian**: Dihitung berdasarkan pasien standard
3. **Periode Arsip**: Data dapat diarsipkan per periode

### 🔒 Keamanan dan Akses

1. **Role-based Access**: Admin vs Puskesmas
2. **Data Isolation**: Puskesmas hanya akses data sendiri
3. **Token Expiry**: Access token 1 jam, refresh token 7 hari

---

## Technical Architecture

### 🏗️ Arsitektur Sistem

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   API Gateway   │    │   Database      │
│   (Mobile/Web)  │◄──►│   (Laravel)     │◄──►│   (MySQL)       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │
                              ▼
                       ┌─────────────────┐
                       │   Services      │
                       │   - Auth        │
                       │   - Statistics  │
                       │   - Export      │
                       │   - Cache       │
                       └─────────────────┘
```

### 🔧 Komponen Teknis

#### Backend Framework
- **Laravel 11**: PHP framework
- **Laravel Sanctum**: API authentication
- **Eloquent ORM**: Database abstraction

#### Database
- **MySQL**: Primary database
- **Migrations**: Schema versioning
- **Seeders**: Test data generation

#### Caching & Performance
- **Monthly Statistics Cache**: Pre-calculated statistics
- **Real-time Updates**: Observer pattern
- **Database Indexing**: Optimized queries

#### Export & Reporting
- **PDF Generation**: Custom templates
- **Excel Export**: Structured data export
- **Multiple Formats**: Flexible output options

### 📊 Data Flow

```
Input → Validation → Business Logic → Database → Cache Update → Response
```

### 🔄 Real-time Updates

```
Examination Created → Observer Triggered → Cache Updated → Statistics Refreshed
```

---

## Kesimpulan

Sistem Akudihatinya dirancang dengan arsitektur yang scalable dan maintainable untuk mendukung:

✅ **Manajemen Data Kesehatan** yang komprehensif
✅ **Pelaporan Real-time** dengan performa optimal
✅ **Multi-level Access Control** untuk keamanan data
✅ **Export Flexibility** dalam berbagai format
✅ **Business Logic Compliance** sesuai standar medis

Dengan dokumentasi lengkap ini, sistem dapat dikembangkan dan dimaintain dengan efisien sambil memastikan kualitas dan konsistensi data kesehatan.