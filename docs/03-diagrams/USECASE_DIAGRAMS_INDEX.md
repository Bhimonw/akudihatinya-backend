# Use Case Diagrams - Sistem Akudihatinya

## Daftar Diagram Use Case Per Modul

Dokumentasi ini berisi kumpulan diagram use case untuk setiap modul dalam sistem Akudihatinya. Setiap diagram menggambarkan interaksi antara aktor (Admin dan Petugas Puskesmas) dengan sistem untuk modul tertentu.

### 📋 Daftar Diagram

| No | Modul | File Diagram | Deskripsi |
|----|-------|--------------|----------|
| 1 | **Autentikasi** | [USECASE_DIAGRAM_AUTHENTICATION.svg](./USECASE_DIAGRAM_AUTHENTICATION.svg) | Proses login, logout, refresh token, dan change password |
| 2 | **Manajemen User** | [USECASE_DIAGRAM_USER_MANAGEMENT.svg](./USECASE_DIAGRAM_USER_MANAGEMENT.svg) | CRUD user, reset password (khusus Admin) |
| 3 | **Target Tahunan** | [USECASE_DIAGRAM_YEARLY_TARGET.svg](./USECASE_DIAGRAM_YEARLY_TARGET.svg) | Manajemen target HT/DM per puskesmas (khusus Admin) |
| 4 | **Manajemen Pasien** | [USECASE_DIAGRAM_PATIENT_MANAGEMENT.svg](./USECASE_DIAGRAM_PATIENT_MANAGEMENT.svg) | CRUD pasien, manajemen tahun pemeriksaan |
| 5 | **Pemeriksaan HT** | [USECASE_DIAGRAM_HT_EXAMINATION.svg](./USECASE_DIAGRAM_HT_EXAMINATION.svg) | Pencatatan dan manajemen pemeriksaan hipertensi |
| 6 | **Pemeriksaan DM** | [USECASE_DIAGRAM_DM_EXAMINATION.svg](./USECASE_DIAGRAM_DM_EXAMINATION.svg) | Pencatatan dan manajemen pemeriksaan diabetes |
| 7 | **Dashboard & Statistik** | [USECASE_DIAGRAM_DASHBOARD_STATISTICS.svg](./USECASE_DIAGRAM_DASHBOARD_STATISTICS.svg) | Dashboard admin dan puskesmas, statistik real-time |
| 8 | **Export & Pelaporan** | [USECASE_DIAGRAM_EXPORT_REPORTING.svg](./USECASE_DIAGRAM_EXPORT_REPORTING.svg) | Export data dan generate laporan berbagai format |

### 👥 Aktor Sistem

#### 🔵 Admin (Dinas Kesehatan)
- **Akses**: Seluruh sistem
- **Scope**: Data semua puskesmas
- **Fungsi Khusus**:
  - Manajemen user dan puskesmas
  - Penetapan target tahunan
  - Monitoring pencapaian semua puskesmas
  - Generate laporan komprehensif

#### 🟢 Petugas Puskesmas
- **Akses**: Modul operasional
- **Scope**: Data puskesmas sendiri saja
- **Fungsi Utama**:
  - Manajemen pasien
  - Pencatatan pemeriksaan HT/DM
  - Monitoring pencapaian target
  - Export data dan laporan puskesmas

### 🔗 Hubungan Antar Modul

```
Autentikasi
    ↓
┌─────────────────┬─────────────────┐
│   Admin Path    │ Puskesmas Path  │
├─────────────────┼─────────────────┤
│ User Management │ Patient Mgmt    │
│ Yearly Targets  │ HT Examination  │
│ Admin Dashboard │ DM Examination  │
│ All Statistics  │ Puskesmas Dash  │
│ Global Reports  │ Local Reports   │
└─────────────────┴─────────────────┘
            ↓
    Export & Reporting
```

### 📊 Fitur Utama Per Modul

#### 1. Modul Autentikasi
- ✅ Login dengan username/password
- ✅ JWT token dengan refresh mechanism
- ✅ Role-based access control
- ✅ Change password

#### 2. Modul Manajemen User (Admin Only)
- ✅ CRUD user untuk puskesmas
- ✅ Assignment role (admin/puskesmas)
- ✅ Reset password user
- ✅ Manajemen akses puskesmas

#### 3. Modul Target Tahunan (Admin Only)
- ✅ Set target HT/DM per puskesmas
- ✅ Monitor progress pencapaian
- ✅ Update dan delete target
- ✅ Filter berdasarkan tahun/puskesmas

#### 4. Modul Manajemen Pasien
- ✅ Registrasi pasien dengan NIK/BPJS
- ✅ Update informasi pasien
- ✅ Manajemen tahun pemeriksaan HT/DM
- ✅ Search dan filter pasien
- ✅ Export data pasien

#### 5. Modul Pemeriksaan HT
- ✅ Input tekanan darah (sistolik/diastolik)
- ✅ Auto-calculate status terkontrol
- ✅ Tracking kunjungan pertama per bulan
- ✅ Filter berdasarkan status kontrol

#### 6. Modul Pemeriksaan DM
- ✅ Support HbA1c dan GDP
- ✅ Auto-calculate status terkontrol
- ✅ Tracking kunjungan pertama per bulan
- ✅ Filter berdasarkan jenis tes

#### 7. Modul Dashboard & Statistik
- ✅ Dashboard terpisah admin vs puskesmas
- ✅ Real-time statistics
- ✅ Grafik dan chart interaktif
- ✅ Cache untuk optimasi performa
- ✅ Filter berdasarkan periode

#### 8. Modul Export & Pelaporan
- ✅ Multiple format (Excel, PDF, CSV)
- ✅ Laporan bulanan, kuartalan, tahunan
- ✅ Custom filter dan template
- ✅ Background processing untuk file besar
- ✅ Secure download links

### 🔒 Keamanan dan Otorisasi

| Modul | Admin | Puskesmas | Catatan |
|-------|-------|-----------|----------|
| Autentikasi | ✅ | ✅ | Semua user |
| User Management | ✅ | ❌ | Admin only |
| Yearly Targets | ✅ | ❌ | Admin only |
| Patient Management | ✅ | ✅ | Admin: semua, Puskesmas: sendiri |
| HT Examination | ✅ | ✅ | Admin: semua, Puskesmas: sendiri |
| DM Examination | ✅ | ✅ | Admin: semua, Puskesmas: sendiri |
| Dashboard | ✅ | ✅ | Scope berbeda |
| Export/Reports | ✅ | ✅ | Scope berbeda |

### 📈 Alur Kerja Sistem

#### Alur Admin:
1. **Login** → Dashboard Admin
2. **Setup**: Buat user puskesmas → Set target tahunan
3. **Monitoring**: Lihat progress semua puskesmas
4. **Reporting**: Generate laporan komprehensif
5. **Management**: Update target, reset password user

#### Alur Petugas Puskesmas:
1. **Login** → Dashboard Puskesmas
2. **Data Entry**: Registrasi pasien → Input pemeriksaan
3. **Monitoring**: Lihat progress target sendiri
4. **Reporting**: Export data dan laporan puskesmas
5. **Maintenance**: Update data pasien dan pemeriksaan

### 🎯 Business Rules Penting

1. **Data Isolation**: Petugas puskesmas hanya akses data sendiri
2. **Unique Constraints**: NIK unik global, rekam medis unik per puskesmas
3. **Control Status**: Auto-calculated berdasarkan kriteria medis
4. **First Visit**: Hanya 1 kunjungan pertama per pasien per bulan
5. **Target Tracking**: Progress dihitung real-time dari data pemeriksaan
6. **Audit Trail**: Semua perubahan data dicatat untuk audit

### 📝 Dokumentasi Terkait

- [ERD dan Use Case Documentation](./ERD_AND_USECASE_DOCUMENTATION.md)
- [Use Case Per Module Detail](./USECASE_PER_MODULE.md)
- [ERD Diagram](./ERD_DIAGRAM.svg)
- [Overall Use Case Diagram](./USECASE_DIAGRAM.svg)

---

**Catatan**: Semua diagram dibuat dalam format SVG untuk kemudahan viewing dan editing. Diagram dapat dibuka langsung di browser atau editor yang mendukung SVG.