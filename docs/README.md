# Dokumentasi Akudihatinya Backend

Selamat datang di dokumentasi lengkap untuk sistem Akudihatinya Backend. Dokumentasi ini menyediakan panduan komprehensif untuk pengembangan, deployment, dan penggunaan sistem.

## 📋 Daftar Isi

### 🚀 Getting Started
- [**DEVELOPMENT_GUIDE.md**](./DEVELOPMENT_GUIDE.md) - Panduan pengembangan untuk developer
- [**DEPLOYMENT_GUIDE.md**](./DEPLOYMENT_GUIDE.md) - Panduan deployment dan instalasi
- [**API_DOCUMENTATION.md**](./API_DOCUMENTATION.md) - Dokumentasi lengkap API endpoints

### 🏗️ Arsitektur & Desain Sistem
- [**ERD.md**](./ERD.md) - Entity Relationship Diagram
- [**USE_CASE_DIAGRAM.md**](./USE_CASE_DIAGRAM.md) - Use Case Diagrams (lengkap)
- [**DATA_FLOW_DIAGRAM.md**](./DATA_FLOW_DIAGRAM.md) - Data Flow Diagram
- [**STATE_DIAGRAM.md**](./STATE_DIAGRAM.md) - State Diagrams
- [**SYSTEM_DIAGRAMS.md**](./SYSTEM_DIAGRAMS.md) - Activity, Sequence, Class & Architecture Diagrams

### 🔧 Fitur & Implementasi
- [**PROFILE_PICTURE_UPLOAD.md**](./PROFILE_PICTURE_UPLOAD.md) - Sistem upload foto profil (lengkap)
- [**YEARLY_TARGET_API.md**](./YEARLY_TARGET_API.md) - API target tahunan
- [**EXAMINATION_CRUD_IMPROVEMENTS.md**](./EXAMINATION_CRUD_IMPROVEMENTS.md) - Peningkatan CRUD pemeriksaan

### 📊 Best Practices & Guidelines
- [**PDF_GENERATION_BEST_PRACTICES.md**](./PDF_GENERATION_BEST_PRACTICES.md) - Best practices PDF generation
- [**README_PDF_FORMATTER.md**](./README_PDF_FORMATTER.md) - Panduan PDF formatter

## 📁 Documentation Structure

### Core Documentation
- **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** - Complete API reference with endpoints, authentication, and examples
- **[DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md)** - Development environment setup and coding standards
- **[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)** - Production deployment instructions

### System Architecture & Design
- **[ERD.md](ERD.md)** - Entity Relationship Diagram
- **[USE_CASE_DIAGRAM.md](USE_CASE_DIAGRAM.md)** - Complete use case diagrams for all modules (User Management, Patient Management, Examination Management)
- **[SYSTEM_DIAGRAMS.md](SYSTEM_DIAGRAMS.md)** - Activity, Sequence, Class, and Architecture diagrams
- **[DATA_FLOW_DIAGRAM.md](DATA_FLOW_DIAGRAM.md)** - Context diagram showing data flow
- **[STATE_DIAGRAM.md](STATE_DIAGRAM.md)** - User authentication and patient management states

### Features & Implementation
- **[PROFILE_PICTURE_UPLOAD.md](PROFILE_PICTURE_UPLOAD.md)** - Complete profile picture management system with automatic resizing and storage
- **[PDF_GENERATION_BEST_PRACTICES.md](PDF_GENERATION_BEST_PRACTICES.md)** - Comprehensive PDF generation guide including formatter implementation, error handling, and best practices

### File Structure Overview
```
docs/
├── README.md                           # File ini - indeks dokumentasi
├── DEVELOPMENT_GUIDE.md                # Setup development environment
├── DEPLOYMENT_GUIDE.md                 # Panduan deployment
├── API_DOCUMENTATION.md                # Dokumentasi API lengkap
├── ERD.md                              # Database design
├── USE_CASE_DIAGRAM.md                 # Use cases (gabungan semua)
├── DATA_FLOW_DIAGRAM.md                # Aliran data sistem
├── STATE_DIAGRAM.md                    # State transitions
├── SYSTEM_DIAGRAMS.md                  # Activity, Sequence, Class diagrams
├── PROFILE_PICTURE_UPLOAD.md           # Upload foto profil (lengkap)
├── YEARLY_TARGET_API.md                # API target tahunan
├── EXAMINATION_CRUD_IMPROVEMENTS.md    # Peningkatan CRUD
├── PDF_GENERATION_BEST_PRACTICES.md    # Best practices PDF
└── README_PDF_FORMATTER.md             # Panduan PDF formatter
```

## 🔄 Perubahan Terbaru

### Reorganisasi Dokumentasi (Latest)
- ✅ **Menggabungkan dokumentasi profile picture** - `PROFILE_PICTURE_RESOURCES_IMG.md` digabung ke `PROFILE_PICTURE_UPLOAD.md` untuk coverage komprehensif termasuk automatic resizing dan storage management
- ✅ **Konsolidasi Use Case Diagrams** - `SIMPLE_USE_CASE_DIAGRAM.md` dan `ADDITIONAL_USE_CASE_DIAGRAMS.md` digabung ke `USE_CASE_DIAGRAM.md` mencakup User Management, Patient Management, dan Examination Management
- ✅ **PDF Generation** - `README_PDF_FORMATTER.md` digabung ke `PDF_GENERATION_BEST_PRACTICES.md` untuk panduan lengkap PDF generation termasuk formatter implementation
- ✅ **Menghapus duplikasi** - File duplikat telah dihapus untuk menghindari konfusi
- ✅ **Membuat indeks dokumentasi** - File README.md ini sebagai panduan navigasi dengan struktur yang diperbarui

### Fitur Profile Picture
- ✅ **Automatic image resizing** - Semua gambar otomatis di-resize ke 200x200 pixels
- ✅ **Storage di resources/img** - Migrasi dari storage/app/public ke resources/img
- ✅ **Automatic URL generation** - URL gambar otomatis dibuat via asset() helper
- ✅ **Route serving images** - Route otomatis untuk mengakses file gambar

## 📖 Cara Menggunakan Dokumentasi

### Untuk Developer Baru
1. Mulai dengan [**DEVELOPMENT_GUIDE.md**](./DEVELOPMENT_GUIDE.md) untuk setup environment
2. Baca [**API_DOCUMENTATION.md**](./API_DOCUMENTATION.md) untuk memahami API
3. Lihat [**ERD.md**](./ERD.md) untuk memahami struktur database
4. Pelajari [**USE_CASE_DIAGRAM.md**](./USE_CASE_DIAGRAM.md) untuk memahami flow sistem

### Untuk DevOps/Deployment
1. Ikuti [**DEPLOYMENT_GUIDE.md**](./DEPLOYMENT_GUIDE.md) untuk deployment
2. Baca [**DEVELOPMENT_GUIDE.md**](./DEVELOPMENT_GUIDE.md) bagian environment setup

### Untuk System Analyst/Architect
1. Mulai dengan [**USE_CASE_DIAGRAM.md**](./USE_CASE_DIAGRAM.md)
2. Lanjut ke [**DATA_FLOW_DIAGRAM.md**](./DATA_FLOW_DIAGRAM.md)
3. Pelajari [**SYSTEM_DIAGRAMS.md**](./SYSTEM_DIAGRAMS.md)
4. Review [**ERD.md**](./ERD.md) untuk database design

### Untuk Frontend Developer
1. Fokus pada [**API_DOCUMENTATION.md**](./API_DOCUMENTATION.md)
2. Baca [**PROFILE_PICTURE_UPLOAD.md**](./PROFILE_PICTURE_UPLOAD.md) untuk implementasi upload gambar
3. Lihat [**USE_CASE_DIAGRAM.md**](./USE_CASE_DIAGRAM.md) untuk memahami user flow

## 🔍 Tips Navigasi

- **Gunakan Ctrl+F** untuk mencari topik spesifik dalam file
- **Klik link** untuk navigasi antar dokumen
- **Lihat Table of Contents** di setiap file untuk navigasi cepat
- **Perhatikan emoji** untuk identifikasi cepat jenis konten

## 📝 Kontribusi Dokumentasi

Jika Anda ingin menambah atau memperbaiki dokumentasi:

1. **Ikuti struktur yang ada** - Gunakan format markdown yang konsisten
2. **Tambahkan ke indeks** - Update file README.md ini jika menambah file baru
3. **Gunakan emoji** - Untuk konsistensi visual
4. **Link antar dokumen** - Buat cross-reference yang berguna
5. **Update changelog** - Catat perubahan di bagian "Perubahan Terbaru"

## 🏷️ Konvensi Penamaan File

- `UPPERCASE_WITH_UNDERSCORES.md` - Untuk dokumentasi utama
- Nama deskriptif yang jelas
- Hindari singkatan yang tidak jelas
- Gunakan prefix untuk kategori (misal: `API_`, `SYSTEM_`)

---

**Terakhir diupdate:** Desember 2024  
**Versi dokumentasi:** 2.0  
**Status:** ✅ Aktif dan terpelihara

> 💡 **Tip:** Bookmark file ini sebagai starting point untuk navigasi dokumentasi!