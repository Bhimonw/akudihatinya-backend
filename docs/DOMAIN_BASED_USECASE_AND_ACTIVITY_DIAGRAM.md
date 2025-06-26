# Use Case Berdasarkan Domain dan Activity Diagram - Sistem Akudihatinya

## Deskripsi Domain

Sistem Akudihatinya adalah aplikasi manajemen kesehatan yang berfokus pada domain **Healthcare Management** dengan subdomain:

1. **Patient Management Domain** - Pengelolaan data pasien
2. **Disease Monitoring Domain** - Pemantauan penyakit HT dan DM
3. **Statistics & Reporting Domain** - Statistik dan pelaporan
4. **User & Access Management Domain** - Manajemen pengguna dan akses
5. **Target Management Domain** - Pengelolaan target tahunan
6. **Archive Management Domain** - Pengelolaan arsip data

## Domain-Based Use Cases

### 1. Patient Management Domain

#### Use Case: Kelola Data Pasien
**Aktor:** Petugas Puskesmas
**Deskripsi:** Mengelola seluruh siklus hidup data pasien dari registrasi hingga pemeliharaan data

**Preconditions:**
- User telah login sebagai petugas puskesmas
- User memiliki akses ke puskesmas tertentu

**Main Flow:**
1. Petugas memilih menu manajemen pasien
2. Sistem menampilkan daftar pasien di puskesmas
3. Petugas dapat melakukan:
   - Registrasi pasien baru
   - Update data pasien existing
   - Menambah/menghapus tahun pemeriksaan
   - Export data pasien
   - Hapus data pasien

**Business Rules:**
- NIK harus unik dan 16 karakter
- BPJS number maksimal 20 karakter
- Pasien hanya bisa terdaftar di satu puskesmas
- Data pasien yang sudah memiliki pemeriksaan tidak bisa dihapus

#### Activity Diagram: Registrasi Pasien Baru
```
[Start] → [Input Data Pasien] → [Validasi NIK] → {NIK Valid?}
    ↓ No                                              ↓ Yes
[Tampilkan Error] ← ← ← ← ← ← ← ← ← ← ← ← ← ← ← ← [Simpan Data Pasien]
    ↓                                                  ↓
[Kembali ke Form] → → → → → → → → → → → → → → → → → [Generate Medical Record]
                                                       ↓
                                                   [Tampilkan Konfirmasi]
                                                       ↓
                                                     [End]
```

### 2. Disease Monitoring Domain

#### Use Case: Pemantauan Hipertensi (HT)
**Aktor:** Petugas Puskesmas
**Deskripsi:** Mencatat dan memantau pemeriksaan hipertensi pasien

**Main Flow:**
1. Petugas memilih pasien untuk pemeriksaan HT
2. Input data pemeriksaan (tekanan darah, tanggal)
3. Sistem menghitung status terkontrol otomatis
4. Sistem menentukan apakah kunjungan pertama bulan ini
5. Sistem menyimpan data dan update statistik

**Business Rules:**
- HT terkontrol: Sistolik 90-139 mmHg, Diastolik 60-89 mmHg
- Hanya satu kunjungan pertama per pasien per bulan
- Data otomatis dikategorikan berdasarkan tahun dan bulan

#### Activity Diagram: Pencatatan Pemeriksaan HT
```
[Start] → [Pilih Pasien] → [Input Tekanan Darah] → [Validasi Input]
                                                        ↓
                                                   {Input Valid?}
                                                   ↓ No    ↓ Yes
                                            [Show Error] [Hitung Status Terkontrol]
                                                   ↑           ↓
                                            [Kembali] ← [Cek Kunjungan Pertama]
                                                               ↓
                                                        [Update Statistik]
                                                               ↓
                                                         [Simpan Data]
                                                               ↓
                                                        [Tampilkan Hasil]
                                                               ↓
                                                             [End]
```

#### Use Case: Pemantauan Diabetes Mellitus (DM)
**Aktor:** Petugas Puskesmas
**Deskripsi:** Mencatat dan memantau pemeriksaan diabetes mellitus pasien

**Main Flow:**
1. Petugas memilih pasien untuk pemeriksaan DM
2. Pilih jenis pemeriksaan (HbA1c atau GDP)
3. Input hasil pemeriksaan
4. Sistem menghitung status terkontrol berdasarkan jenis tes
5. Sistem menyimpan data dan update statistik

**Business Rules:**
- DM terkontrol HbA1c: < 7%
- DM terkontrol GDP: < 126 mg/dL
- Jenis pemeriksaan mempengaruhi kriteria kontrol

#### Activity Diagram: Pencatatan Pemeriksaan DM
```
[Start] → [Pilih Pasien] → [Pilih Jenis Tes] → {HbA1c atau GDP?}
                                              ↓ HbA1c    ↓ GDP
                                         [Input HbA1c] [Input GDP]
                                              ↓             ↓
                                         [Validasi < 7%] [Validasi < 126]
                                              ↓             ↓
                                              → [Hitung Status] ←
                                                      ↓
                                               [Update Statistik]
                                                      ↓
                                                [Simpan Data]
                                                      ↓
                                               [Tampilkan Hasil]
                                                      ↓
                                                    [End]
```

### 3. Statistics & Reporting Domain

#### Use Case: Generate Statistik Real-time
**Aktor:** Admin, Petugas Puskesmas
**Deskripsi:** Menghasilkan statistik kesehatan secara real-time

**Main Flow:**
1. User memilih jenis statistik (HT/DM)
2. Pilih periode (bulan/kuartal/tahun)
3. Sistem mengambil data dari cache atau hitung ulang
4. Tampilkan statistik dengan breakdown bulanan
5. Opsi export ke PDF/Excel

**Business Rules:**
- Data di-cache per bulan untuk performa
- Admin melihat semua puskesmas, petugas hanya puskesmasnya
- Statistik mencakup: total pasien, terkontrol, tidak terkontrol, pencapaian target

#### Activity Diagram: Generate Statistik
```
[Start] → [Pilih Jenis Statistik] → [Pilih Periode] → [Cek Cache]
                                                          ↓
                                                    {Cache Tersedia?}
                                                    ↓ Yes    ↓ No
                                              [Ambil dari Cache] [Hitung Real-time]
                                                    ↓             ↓
                                                    → [Format Data] ←
                                                          ↓
                                                   [Tampilkan Statistik]
                                                          ↓
                                                    {Export Request?}
                                                    ↓ Yes    ↓ No
                                               [Generate Export] [End]
                                                          ↓
                                                   [Download File]
                                                          ↓
                                                        [End]
```

### 4. User & Access Management Domain

#### Use Case: Manajemen User (Admin)
**Aktor:** Admin
**Deskripsi:** Mengelola user dan akses sistem

**Main Flow:**
1. Admin login ke sistem
2. Akses menu manajemen user
3. Dapat melakukan: create, read, update, delete user
4. Assign user ke puskesmas
5. Reset password user

**Business Rules:**
- Hanya admin yang dapat mengelola user
- User puskesmas hanya bisa akses data puskesmasnya
- Username harus unik

#### Activity Diagram: Create User Baru
```
[Start] → [Input Data User] → [Pilih Puskesmas] → [Generate Username]
                                                        ↓
                                                 [Validasi Unique]
                                                        ↓
                                                  {Username Unique?}
                                                  ↓ No      ↓ Yes
                                           [Generate Ulang] [Hash Password]
                                                  ↑             ↓
                                           [Kembali] ← ← [Simpan User]
                                                               ↓
                                                        [Send Credentials]
                                                               ↓
                                                        [Tampilkan Sukses]
                                                               ↓
                                                             [End]
```

### 5. Target Management Domain

#### Use Case: Kelola Target Tahunan
**Aktor:** Admin
**Deskripsi:** Menetapkan dan mengelola target tahunan puskesmas

**Main Flow:**
1. Admin pilih puskesmas
2. Pilih jenis penyakit (HT/DM)
3. Set target count untuk tahun tertentu
4. Sistem menyimpan dan mulai tracking progress

**Business Rules:**
- Target ditetapkan per puskesmas per jenis penyakit per tahun
- Progress dihitung berdasarkan jumlah pasien unik yang diperiksa
- Target dapat diupdate selama tahun berjalan

#### Activity Diagram: Set Target Tahunan
```
[Start] → [Pilih Puskesmas] → [Pilih Disease Type] → [Input Target Count]
                                                            ↓
                                                     [Validasi Input]
                                                            ↓
                                                      {Valid Input?}
                                                      ↓ No    ↓ Yes
                                               [Show Error] [Cek Existing Target]
                                                      ↑           ↓
                                               [Kembali] ← {Target Exists?}
                                                           ↓ Yes    ↓ No
                                                    [Update Target] [Create Target]
                                                           ↓             ↓
                                                           → [Simpan] ←
                                                                ↓
                                                         [Update Progress]
                                                                ↓
                                                         [Tampilkan Sukses]
                                                                ↓
                                                              [End]
```

### 6. Archive Management Domain

#### Use Case: Arsip Data Otomatis
**Aktor:** System (Automated)
**Deskripsi:** Mengarsipkan data pemeriksaan secara otomatis di awal tahun

**Main Flow:**
1. Sistem trigger di awal tahun (1 Januari)
2. Identifikasi data tahun sebelumnya
3. Update flag is_archived = true
4. Generate laporan arsip
5. Notifikasi admin

**Business Rules:**
- Data diarsipkan otomatis setiap awal tahun
- Data yang diarsipkan tetap bisa diakses untuk laporan
- Proses arsip tidak menghapus data fisik

#### Activity Diagram: Proses Arsip Otomatis
```
[Start: 1 Jan] → [Identifikasi Data Tahun Lalu] → [Update Flag Archive HT]
                                                          ↓
                                                   [Update Flag Archive DM]
                                                          ↓
                                                   [Hitung Total Archived]
                                                          ↓
                                                   [Generate Report]
                                                          ↓
                                                   [Send Notification]
                                                          ↓
                                                   [Log Archive Process]
                                                          ↓
                                                        [End]
```

## Domain Integration Flow

### Cross-Domain Activity: Workflow Pemeriksaan Lengkap
```
[Login User] → [Patient Management: Pilih/Daftar Pasien]
                              ↓
                [Disease Monitoring: Pilih Jenis Pemeriksaan]
                              ↓
                        {HT atau DM?}
                        ↓ HT    ↓ DM
                [Input Tekanan Darah] [Pilih Jenis Tes DM]
                        ↓             ↓
                [Hitung Status HT] [Input Hasil DM]
                        ↓             ↓
                        → [Simpan Data] ←
                              ↓
                [Statistics: Update Cache]
                              ↓
                [Target: Update Progress]
                              ↓
                [Tampilkan Hasil Pemeriksaan]
                              ↓
                            [End]
```

## Domain Services dan Responsibilities

### 1. Patient Management Services
- **PatientService**: CRUD operations pasien
- **PatientValidationService**: Validasi data pasien
- **MedicalRecordService**: Generate nomor rekam medis

### 2. Disease Monitoring Services
- **HtExaminationService**: Logika pemeriksaan hipertensi
- **DmExaminationService**: Logika pemeriksaan diabetes
- **DiseaseStatisticsService**: Statistik per penyakit

### 3. Statistics & Reporting Services
- **StatisticsService**: Agregasi statistik
- **StatisticsCacheService**: Manajemen cache
- **ExportService**: Export data ke berbagai format
- **ReportService**: Generate laporan

### 4. User & Access Management Services
- **AuthService**: Autentikasi dan autorisasi
- **UserService**: Manajemen user
- **RoleService**: Manajemen role dan permission

### 5. Target Management Services
- **YearlyTargetService**: Manajemen target tahunan
- **TargetProgressService**: Tracking progress target

### 6. Archive Management Services
- **ArchiveService**: Proses arsip data
- **DataRetentionService**: Kebijakan retensi data

## Business Rules Summary

1. **Data Integrity**: NIK unik, validasi input medis
2. **Access Control**: Role-based access (admin vs puskesmas)
3. **Medical Standards**: Kriteria kontrol HT dan DM sesuai standar medis
4. **Performance**: Caching untuk statistik, indexing database
5. **Audit Trail**: Log semua perubahan data penting
6. **Data Retention**: Arsip otomatis, tidak hapus data fisik
7. **Target Tracking**: Real-time progress monitoring
8. **Export Capability**: Multiple format support (PDF, Excel)

## Kesimpulan

Dokumentasi ini menunjukkan bagaimana sistem Akudihatinya diorganisir berdasarkan domain-driven design dengan use case dan activity diagram yang jelas untuk setiap domain. Setiap domain memiliki tanggung jawab yang spesifik namun terintegrasi untuk mendukung workflow manajemen kesehatan yang komprehensif.