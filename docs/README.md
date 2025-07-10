# 📚 Dokumentasi Akudihatinya Backend

<p align="center">
  <img src="https://img.shields.io/badge/Documentation-Organized-green?style=for-the-badge" alt="Documentation">
  <img src="https://img.shields.io/badge/Structure-Modular-blue?style=for-the-badge" alt="Modular">
  <img src="https://img.shields.io/badge/Maintenance-Easy-orange?style=for-the-badge" alt="Easy Maintenance">
</p>

Selamat datang di dokumentasi lengkap untuk Akudihatinya Backend. Dokumentasi ini telah diorganisir secara modular untuk memudahkan navigasi dan pemeliharaan.

> 📋 **Quick Navigation**: Lihat [INDEX.md](./INDEX.md) untuk navigasi lengkap semua dokumentasi

## 📂 Struktur Dokumentasi

### 01. 🔌 API Documentation
- **[API_COMPLETE.md](./01-api/API_COMPLETE.md)** - Dokumentasi lengkap semua endpoint API, authentication, dan panduan penggunaan
- **[API_PROFILE_UPDATE.md](./01-api/API_PROFILE_UPDATE.md)** - Dokumentasi endpoint update profil user
- **[API_PROFILE_UPDATE_ALTERNATIVE.md](./01-api/API_PROFILE_UPDATE_ALTERNATIVE.md)** - Dokumentasi alternatif endpoint update profil
- **[EXCEL_FORMATTER_GUIDE.md](./01-api/EXCEL_FORMATTER_GUIDE.md)** - Panduan formatter untuk export Excel

### 02. 🏗️ Architecture
- **[SYSTEM_ARCHITECTURE.md](./02-architecture/SYSTEM_ARCHITECTURE.md)** - Arsitektur sistem dan komponen utama
- **[ERD_AND_USECASE_DOCUMENTATION.md](./02-architecture/ERD_AND_USECASE_DOCUMENTATION.md)** - Entity Relationship Diagram dan dokumentasi use case
- **[DOMAIN_BASED_USECASE_AND_ACTIVITY_DIAGRAM.md](./02-architecture/DOMAIN_BASED_USECASE_AND_ACTIVITY_DIAGRAM.md)** - Use case dan activity diagram berbasis domain
- **[USECASE_PER_MODULE.md](./02-architecture/USECASE_PER_MODULE.md)** - Use case per modul sistem

### 03. 📊 Diagrams
#### Entity Relationship Diagram
- **[ERD_DIAGRAM.svg](./03-diagrams/ERD_DIAGRAM.svg)** - Diagram hubungan antar entitas database

#### Use Case Diagrams
- **[USECASE_DIAGRAM.svg](./03-diagrams/USECASE_DIAGRAM.svg)** - Use case diagram utama
- **[USECASE_DIAGRAMS_INDEX.md](./03-diagrams/USECASE_DIAGRAMS_INDEX.md)** - Index semua use case diagram
- **[USECASE_DIAGRAM_AUTHENTICATION.svg](./03-diagrams/USECASE_DIAGRAM_AUTHENTICATION.svg)** - Authentication use cases
- **[USECASE_DIAGRAM_DASHBOARD_STATISTICS.svg](./03-diagrams/USECASE_DIAGRAM_DASHBOARD_STATISTICS.svg)** - Dashboard statistics use cases
- **[USECASE_DIAGRAM_DM_EXAMINATION.svg](./03-diagrams/USECASE_DIAGRAM_DM_EXAMINATION.svg)** - DM examination use cases
- **[USECASE_DIAGRAM_EXPORT_REPORTING.svg](./03-diagrams/USECASE_DIAGRAM_EXPORT_REPORTING.svg)** - Export & reporting use cases
- **[USECASE_DIAGRAM_HT_EXAMINATION.svg](./03-diagrams/USECASE_DIAGRAM_HT_EXAMINATION.svg)** - HT examination use cases
- **[USECASE_DIAGRAM_PATIENT_MANAGEMENT.svg](./03-diagrams/USECASE_DIAGRAM_PATIENT_MANAGEMENT.svg)** - Patient management use cases
- **[USECASE_DIAGRAM_USER_MANAGEMENT.svg](./03-diagrams/USECASE_DIAGRAM_USER_MANAGEMENT.svg)** - User management use cases
- **[USECASE_DIAGRAM_YEARLY_TARGET.svg](./03-diagrams/USECASE_DIAGRAM_YEARLY_TARGET.svg)** - Yearly target use cases

#### Activity Diagrams
- **[ACTIVITY_DIAGRAM_ARCHIVE_PROCESS.svg](./03-diagrams/ACTIVITY_DIAGRAM_ARCHIVE_PROCESS.svg)** - Archive process flow
- **[ACTIVITY_DIAGRAM_DM_EXAMINATION.svg](./03-diagrams/ACTIVITY_DIAGRAM_DM_EXAMINATION.svg)** - DM examination process
- **[ACTIVITY_DIAGRAM_HT_EXAMINATION.svg](./03-diagrams/ACTIVITY_DIAGRAM_HT_EXAMINATION.svg)** - HT examination process
- **[ACTIVITY_DIAGRAM_PATIENT_REGISTRATION.svg](./03-diagrams/ACTIVITY_DIAGRAM_PATIENT_REGISTRATION.svg)** - Patient registration process
- **[ACTIVITY_DIAGRAM_STATISTICS_GENERATION.svg](./03-diagrams/ACTIVITY_DIAGRAM_STATISTICS_GENERATION.svg)** - Statistics generation process

### 04. 📖 Guides
- **[DEVELOPER_GUIDE.md](./04-guides/DEVELOPER_GUIDE.md)** - Panduan lengkap untuk developer
- **[USER_CREATION_FIX.md](./04-guides/USER_CREATION_FIX.md)** - Panduan perbaikan pembuatan user

### 05. ⚙️ Processes
- **[NEW_YEAR_SETUP.md](./05-processes/NEW_YEAR_SETUP.md)** - Proses setup tahun baru
- **[CHANGELOG.md](./05-processes/CHANGELOG.md)** - Log perubahan sistem

### 06. 🔧 Code Quality
- **[CODE_QUALITY_IMPROVEMENTS.md](./06-code-quality/CODE_QUALITY_IMPROVEMENTS.md)** - Peningkatan kualitas kode pada sistem manajemen profil
- **[CODE_QUALITY_IMPROVEMENTS_COMPREHENSIVE.md](./06-code-quality/CODE_QUALITY_IMPROVEMENTS_COMPREHENSIVE.md)** - Peningkatan kualitas kode secara komprehensif

### 07. ⚡ Optimizations
- **[OPTIMALISASI_PERHITUNGAN_PERSENTASE.md](./07-optimizations/OPTIMALISASI_PERHITUNGAN_PERSENTASE.md)** - Optimalisasi sistem perhitungan persentase
- **[PERCENTAGE_CALCULATION_FIX.md](./07-optimizations/PERCENTAGE_CALCULATION_FIX.md)** - Perbaikan perhitungan persentase
- **[MONTHLY_STANDARD_CALCULATION_UPDATE.md](./07-optimizations/MONTHLY_STANDARD_CALCULATION_UPDATE.md)** - Update perhitungan standar bulanan

## 🚀 Quick Start

1. **Untuk Developer Baru**: Mulai dengan [DEVELOPER_GUIDE.md](./04-guides/DEVELOPER_GUIDE.md)
2. **Untuk API Integration**: Lihat [API_COMPLETE.md](./01-api/API_COMPLETE.md)
3. **Untuk Memahami Arsitektur**: Baca [SYSTEM_ARCHITECTURE.md](./02-architecture/SYSTEM_ARCHITECTURE.md)
4. **Untuk Melihat Database Design**: Lihat [ERD_DIAGRAM.svg](./03-diagrams/ERD_DIAGRAM.svg)

## 📋 Konvensi Penamaan

### Direktori
- `01-api/` - Dokumentasi API dan endpoint
- `02-architecture/` - Dokumentasi arsitektur sistem
- `03-diagrams/` - Semua diagram (SVG files)
- `04-guides/` - Panduan dan tutorial
- `05-processes/` - Proses bisnis dan operasional

### File
- **UPPERCASE_WITH_UNDERSCORES.md** - Untuk dokumentasi utama
- **DIAGRAM_TYPE_SUBJECT.svg** - Untuk diagram SVG
- Prefix angka pada direktori untuk menunjukkan urutan prioritas

## 🔄 Maintenance

### Menambah Dokumentasi Baru
1. Tentukan kategori yang sesuai (API, Architecture, Diagrams, Guides, Processes)
2. Letakkan file di direktori yang tepat
3. Update README.md ini jika diperlukan
4. Gunakan konvensi penamaan yang konsisten

### Update Dokumentasi
1. Selalu update tanggal modifikasi
2. Tambahkan entry di CHANGELOG.md jika perubahan signifikan
3. Pastikan link antar dokumen tetap valid

## 📞 Support

Jika ada pertanyaan atau butuh bantuan:
1. Cek dokumentasi yang relevan terlebih dahulu
2. Lihat CHANGELOG.md untuk perubahan terbaru
3. Hubungi tim development

---

*Dokumentasi ini diorganisir untuk memudahkan navigasi dan pemeliharaan. Setiap kategori memiliki fokus yang jelas dan terpisah.*