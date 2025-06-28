# Use Case Diagram Per Modul - Sistem Akudihatinya

## Daftar Isi
1. [Modul Autentikasi](#modul-autentikasi)
2. [Modul Manajemen User (Admin)](#modul-manajemen-user-admin)
3. [Modul Manajemen Target Tahunan (Admin)](#modul-manajemen-target-tahunan-admin)
4. [Modul Manajemen Pasien (Puskesmas)](#modul-manajemen-pasien-puskesmas)
5. [Modul Pemeriksaan Hipertensi (Puskesmas)](#modul-pemeriksaan-hipertensi-puskesmas)
6. [Modul Pemeriksaan Diabetes (Puskesmas)](#modul-pemeriksaan-diabetes-puskesmas)
7. [Modul Dashboard dan Statistik](#modul-dashboard-dan-statistik)
8. [Modul Export dan Pelaporan](#modul-export-dan-pelaporan)

---

## Modul Autentikasi

### Aktor
- **Admin** (Dinas Kesehatan)
- **Petugas Puskesmas**

### Use Cases

#### UC-AUTH-001: Login
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Pengguna masuk ke sistem menggunakan kredensial
**Precondition**: Pengguna memiliki akun yang valid
**Postcondition**: Pengguna berhasil masuk dan mendapat access token

**Main Flow**:
1. Pengguna memasukkan username dan password
2. Sistem memvalidasi kredensial
3. Sistem menghasilkan access token dan refresh token
4. Sistem mengembalikan token dan informasi user

**Alternative Flow**:
- 2a. Kredensial tidak valid → Sistem menampilkan pesan error

#### UC-AUTH-002: Logout
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Pengguna keluar dari sistem
**Precondition**: Pengguna sudah login
**Postcondition**: Token pengguna dihapus

**Main Flow**:
1. Pengguna memilih logout
2. Sistem menghapus access token
3. Sistem menghapus refresh token dari database
4. Sistem mengonfirmasi logout berhasil

#### UC-AUTH-003: Refresh Token
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Memperbarui access token yang expired
**Precondition**: Refresh token masih valid
**Postcondition**: Access token baru dihasilkan

**Main Flow**:
1. Sistem mendeteksi access token expired
2. Sistem memvalidasi refresh token
3. Sistem menghasilkan access token baru
4. Sistem mengembalikan token baru

#### UC-AUTH-004: Change Password
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Pengguna mengubah password akun
**Precondition**: Pengguna sudah login
**Postcondition**: Password berhasil diubah

**Main Flow**:
1. Pengguna memasukkan password lama
2. Pengguna memasukkan password baru
3. Sistem memvalidasi password lama
4. Sistem mengupdate password
5. Sistem mengonfirmasi perubahan

---

## Modul Manajemen User (Admin)

### Aktor
- **Admin** (Dinas Kesehatan)

### Use Cases

#### UC-USER-001: Create User

**Deskripsi**: Admin membuat akun user baru untuk puskesmas
**Precondition**: Admin sudah login
**Postcondition**: User baru dan puskesmas terkait berhasil dibuat

**Main Flow**:
1. Admin mengisi form user baru (username, name, password, role)
2. Jika role = 'puskesmas': Admin mengisi nama puskesmas
3. Admin dapat upload foto profil (opsional)
4. Sistem validasi data (username harus unik)
5. **Database Transaction Process**:
   - Sistem membuat user terlebih dahulu
   - Sistem membuat entitas puskesmas dengan user_id
   - Sistem update user dengan puskesmas_id
6. Sistem mengembalikan data user lengkap dengan relasi puskesmas

**Business Rules**:
- Username harus unik dalam sistem
- Untuk role puskesmas, nama puskesmas wajib diisi
- Sistem otomatis mengelola relasi bidirectional antara user dan puskesmas
- Menggunakan database transaction untuk memastikan konsistensi data
6. Sistem menghasilkan kredensial default

#### UC-USER-002: View Users
**Aktor**: Admin
**Deskripsi**: Admin melihat daftar semua user
**Precondition**: Admin sudah login
**Postcondition**: Daftar user ditampilkan

**Main Flow**:
1. Admin mengakses halaman user management
2. Sistem menampilkan daftar user dengan pagination
3. Admin dapat melakukan filter berdasarkan role/puskesmas

#### UC-USER-003: Update User
**Aktor**: Admin
**Deskripsi**: Admin mengubah informasi user
**Precondition**: Admin sudah login, user target ada
**Postcondition**: Informasi user berhasil diupdate

**Main Flow**:
1. Admin memilih user yang akan diupdate
2. Admin mengubah informasi yang diperlukan
3. Sistem memvalidasi perubahan
4. Sistem menyimpan perubahan

#### UC-USER-004: Delete User
**Aktor**: Admin
**Deskripsi**: Admin menghapus user dari sistem
**Precondition**: Admin sudah login, user target ada
**Postcondition**: User berhasil dihapus

**Main Flow**:
1. Admin memilih user yang akan dihapus
2. Sistem menampilkan konfirmasi
3. Admin mengonfirmasi penghapusan
4. Sistem menghapus user dan data terkait

#### UC-USER-005: Reset Password
**Aktor**: Admin
**Deskripsi**: Admin mereset password user lain
**Precondition**: Admin sudah login, user target ada
**Postcondition**: Password user direset

**Main Flow**:
1. Admin memilih user untuk reset password
2. Sistem menghasilkan password baru
3. Sistem mengupdate password user
4. Sistem menampilkan password baru ke admin

---

## Modul Manajemen Target Tahunan (Admin)

### Aktor
- **Admin** (Dinas Kesehatan)

### Use Cases

#### UC-TARGET-001: Set Yearly Target
**Aktor**: Admin
**Deskripsi**: Admin menetapkan target tahunan untuk puskesmas
**Precondition**: Admin sudah login
**Postcondition**: Target tahunan berhasil ditetapkan

**Main Flow**:
1. Admin memilih puskesmas
2. Admin memilih jenis penyakit (HT/DM)
3. Admin memasukkan tahun target
4. Admin memasukkan jumlah target
5. Sistem menyimpan target

#### UC-TARGET-002: View Yearly Targets
**Aktor**: Admin
**Deskripsi**: Admin melihat semua target tahunan
**Precondition**: Admin sudah login
**Postcondition**: Daftar target ditampilkan

**Main Flow**:
1. Admin mengakses halaman target management
2. Sistem menampilkan daftar target dengan filter
3. Admin dapat filter berdasarkan tahun/puskesmas/jenis penyakit

#### UC-TARGET-003: Update Yearly Target
**Aktor**: Admin
**Deskripsi**: Admin mengubah target yang sudah ada
**Precondition**: Admin sudah login, target ada
**Postcondition**: Target berhasil diupdate

**Main Flow**:
1. Admin memilih target yang akan diubah
2. Admin mengubah jumlah target
3. Sistem memvalidasi perubahan
4. Sistem menyimpan perubahan

#### UC-TARGET-004: Delete Yearly Target
**Aktor**: Admin
**Deskripsi**: Admin menghapus target tahunan
**Precondition**: Admin sudah login, target ada
**Postcondition**: Target berhasil dihapus

**Main Flow**:
1. Admin memilih target yang akan dihapus
2. Sistem menampilkan konfirmasi
3. Admin mengonfirmasi penghapusan
4. Sistem menghapus target

---

## Modul Manajemen Pasien (Puskesmas)

### Aktor
- **Petugas Puskesmas**

### Use Cases

#### UC-PATIENT-001: Register Patient
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mendaftarkan pasien baru
**Precondition**: Petugas sudah login
**Postcondition**: Pasien baru berhasil terdaftar

**Main Flow**:
1. Petugas mengisi form registrasi pasien
2. Petugas memasukkan NIK (opsional)
3. Petugas memasukkan nomor BPJS (opsional)
4. Petugas memasukkan nomor rekam medis
5. Sistem memvalidasi data
6. Sistem menyimpan data pasien

**Business Rules**:
- NIK harus unik jika diisi
- Nomor rekam medis harus unik per puskesmas

#### UC-PATIENT-002: View Patients
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas melihat daftar pasien di puskesmas
**Precondition**: Petugas sudah login
**Postcondition**: Daftar pasien ditampilkan

**Main Flow**:
1. Petugas mengakses halaman patient management
2. Sistem menampilkan daftar pasien dengan pagination
3. Petugas dapat melakukan pencarian berdasarkan nama/NIK/nomor rekam medis

#### UC-PATIENT-003: Update Patient
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mengubah informasi pasien
**Precondition**: Petugas sudah login, pasien ada
**Postcondition**: Informasi pasien berhasil diupdate

**Main Flow**:
1. Petugas memilih pasien yang akan diupdate
2. Petugas mengubah informasi yang diperlukan
3. Sistem memvalidasi perubahan
4. Sistem menyimpan perubahan

#### UC-PATIENT-004: Delete Patient
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas menghapus data pasien
**Precondition**: Petugas sudah login, pasien ada
**Postcondition**: Pasien berhasil dihapus

**Main Flow**:
1. Petugas memilih pasien yang akan dihapus
2. Sistem menampilkan konfirmasi dan warning tentang data terkait
3. Petugas mengonfirmasi penghapusan
4. Sistem menghapus pasien dan semua data pemeriksaan terkait

#### UC-PATIENT-005: Add Examination Year
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas menambah tahun pemeriksaan untuk pasien
**Precondition**: Petugas sudah login, pasien ada
**Postcondition**: Tahun pemeriksaan berhasil ditambahkan

**Main Flow**:
1. Petugas memilih pasien
2. Petugas memilih jenis penyakit (HT/DM)
3. Petugas memasukkan tahun pemeriksaan
4. Sistem menambahkan tahun ke array ht_years/dm_years
5. Sistem mengupdate status has_ht/has_dm

#### UC-PATIENT-006: Remove Examination Year
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas menghapus tahun pemeriksaan pasien
**Precondition**: Petugas sudah login, pasien ada, tahun pemeriksaan ada
**Postcondition**: Tahun pemeriksaan berhasil dihapus

**Main Flow**:
1. Petugas memilih pasien
2. Petugas memilih jenis penyakit dan tahun yang akan dihapus
3. Sistem menghapus tahun dari array
4. Sistem mengupdate status has_ht/has_dm
5. Sistem menampilkan warning jika ada data pemeriksaan terkait

---

## Modul Pemeriksaan Hipertensi (Puskesmas)

### Aktor
- **Petugas Puskesmas**

### Use Cases

#### UC-HT-001: Record HT Examination
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mencatat hasil pemeriksaan hipertensi
**Precondition**: Petugas sudah login, pasien ada
**Postcondition**: Data pemeriksaan HT berhasil disimpan

**Main Flow**:
1. Petugas memilih pasien
2. Petugas memasukkan tanggal pemeriksaan
3. Petugas memasukkan tekanan darah sistolik
4. Petugas memasukkan tekanan darah diastolik
5. Sistem menghitung status terkontrol otomatis
6. Sistem menentukan apakah ini kunjungan pertama bulan ini
7. Sistem menyimpan data pemeriksaan

**Business Rules**:
- Tekanan darah terkontrol: sistolik 90-139 mmHg, diastolik 60-89 mmHg
- Hanya satu kunjungan pertama per pasien per bulan

#### UC-HT-002: View HT Examinations
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas melihat riwayat pemeriksaan HT
**Precondition**: Petugas sudah login
**Postcondition**: Daftar pemeriksaan HT ditampilkan

**Main Flow**:
1. Petugas mengakses halaman HT examinations
2. Sistem menampilkan daftar pemeriksaan dengan filter
3. Petugas dapat filter berdasarkan pasien/tanggal/status terkontrol

#### UC-HT-003: Update HT Examination
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mengubah data pemeriksaan HT
**Precondition**: Petugas sudah login, pemeriksaan ada
**Postcondition**: Data pemeriksaan berhasil diupdate

**Main Flow**:
1. Petugas memilih pemeriksaan yang akan diubah
2. Petugas mengubah data yang diperlukan
3. Sistem menghitung ulang status terkontrol
4. Sistem menyimpan perubahan

#### UC-HT-004: Delete HT Examination
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas menghapus data pemeriksaan HT
**Precondition**: Petugas sudah login, pemeriksaan ada
**Postcondition**: Data pemeriksaan berhasil dihapus

**Main Flow**:
1. Petugas memilih pemeriksaan yang akan dihapus
2. Sistem menampilkan konfirmasi
3. Petugas mengonfirmasi penghapusan
4. Sistem menghapus data pemeriksaan
5. Sistem mengupdate cache statistik jika diperlukan

---

## Modul Pemeriksaan Diabetes (Puskesmas)

### Aktor
- **Petugas Puskesmas**

### Use Cases

#### UC-DM-001: Record DM Examination
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mencatat hasil pemeriksaan diabetes
**Precondition**: Petugas sudah login, pasien ada
**Postcondition**: Data pemeriksaan DM berhasil disimpan

**Main Flow**:
1. Petugas memilih pasien
2. Petugas memasukkan tanggal pemeriksaan
3. Petugas memilih jenis pemeriksaan (HbA1c/GDP)
4. Petugas memasukkan hasil pemeriksaan
5. Sistem menghitung status terkontrol otomatis
6. Sistem menentukan apakah ini kunjungan pertama bulan ini
7. Sistem menyimpan data pemeriksaan

**Business Rules**:
- HbA1c terkontrol: < 7%
- GDP terkontrol: < 126 mg/dL
- Hanya satu kunjungan pertama per pasien per bulan

#### UC-DM-002: View DM Examinations
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas melihat riwayat pemeriksaan DM
**Precondition**: Petugas sudah login
**Postcondition**: Daftar pemeriksaan DM ditampilkan

**Main Flow**:
1. Petugas mengakses halaman DM examinations
2. Sistem menampilkan daftar pemeriksaan dengan filter
3. Petugas dapat filter berdasarkan pasien/tanggal/jenis tes/status terkontrol

#### UC-DM-003: Update DM Examination
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mengubah data pemeriksaan DM
**Precondition**: Petugas sudah login, pemeriksaan ada
**Postcondition**: Data pemeriksaan berhasil diupdate

**Main Flow**:
1. Petugas memilih pemeriksaan yang akan diubah
2. Petugas mengubah data yang diperlukan
3. Sistem menghitung ulang status terkontrol
4. Sistem menyimpan perubahan

#### UC-DM-004: Delete DM Examination
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas menghapus data pemeriksaan DM
**Precondition**: Petugas sudah login, pemeriksaan ada
**Postcondition**: Data pemeriksaan berhasil dihapus

**Main Flow**:
1. Petugas memilih pemeriksaan yang akan dihapus
2. Sistem menampilkan konfirmasi
3. Petugas mengonfirmasi penghapusan
4. Sistem menghapus data pemeriksaan
5. Sistem mengupdate cache statistik jika diperlukan

---

## Modul Dashboard dan Statistik

### Aktor
- **Admin** (Dinas Kesehatan)
- **Petugas Puskesmas**

### Use Cases

#### UC-DASH-001: View Admin Dashboard
**Aktor**: Admin
**Deskripsi**: Admin melihat statistik keseluruhan semua puskesmas
**Precondition**: Admin sudah login
**Postcondition**: Dashboard admin ditampilkan

**Main Flow**:
1. Admin mengakses dashboard
2. Sistem mengumpulkan statistik dari semua puskesmas
3. Sistem menampilkan:
   - Total pasien HT dan DM
   - Total pemeriksaan bulan ini
   - Persentase pencapaian target
   - Grafik tren bulanan
   - Ranking puskesmas berdasarkan pencapaian

#### UC-DASH-002: View Puskesmas Dashboard
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas melihat statistik puskesmas sendiri
**Precondition**: Petugas sudah login
**Postcondition**: Dashboard puskesmas ditampilkan

**Main Flow**:
1. Petugas mengakses dashboard
2. Sistem mengumpulkan statistik puskesmas
3. Sistem menampilkan:
   - Jumlah pasien HT dan DM
   - Pemeriksaan bulan ini
   - Progress terhadap target tahunan
   - Persentase pasien terkontrol
   - Grafik tren pemeriksaan

#### UC-DASH-003: View Real-time Statistics
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Melihat statistik real-time
**Precondition**: User sudah login
**Postcondition**: Statistik real-time ditampilkan

**Main Flow**:
1. User mengakses halaman statistik
2. Sistem menghitung statistik real-time
3. Sistem menampilkan data terkini tanpa cache

---

## Modul Export dan Pelaporan

### Aktor
- **Admin** (Dinas Kesehatan)
- **Petugas Puskesmas**

### Use Cases

#### UC-EXPORT-001: Export Patient Data
**Aktor**: Petugas Puskesmas
**Deskripsi**: Petugas mengekspor data pasien
**Precondition**: Petugas sudah login
**Postcondition**: File export berhasil dihasilkan

**Main Flow**:
1. Petugas memilih format export (Excel/PDF)
2. Petugas memilih filter data (semua/berdasarkan kriteria)
3. Sistem mengumpulkan data pasien
4. Sistem menghasilkan file export
5. Sistem menyediakan link download

#### UC-EXPORT-002: Generate Monthly Report
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Menghasilkan laporan bulanan
**Precondition**: User sudah login
**Postcondition**: Laporan bulanan berhasil dihasilkan

**Main Flow**:
1. User memilih bulan dan tahun
2. User memilih format laporan (PDF/Excel)
3. Sistem mengumpulkan data pemeriksaan bulan tersebut
4. Sistem menghasilkan laporan dengan template
5. Sistem menyediakan file untuk download

#### UC-EXPORT-003: Generate Quarterly Report
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Menghasilkan laporan kuartalan
**Precondition**: User sudah login
**Postcondition**: Laporan kuartalan berhasil dihasilkan

**Main Flow**:
1. User memilih kuartal dan tahun
2. User memilih format laporan
3. Sistem mengumpulkan data 3 bulan dalam kuartal
4. Sistem menghasilkan laporan komprehensif
5. Sistem menyediakan file untuk download

#### UC-EXPORT-004: Generate Annual Report
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Menghasilkan laporan tahunan
**Precondition**: User sudah login
**Postcondition**: Laporan tahunan berhasil dihasilkan

**Main Flow**:
1. User memilih tahun
2. User memilih format laporan
3. Sistem mengumpulkan data seluruh tahun
4. Sistem menghitung pencapaian target tahunan
5. Sistem menghasilkan laporan lengkap dengan analisis
6. Sistem menyediakan file untuk download

#### UC-EXPORT-005: Export Statistics Data
**Aktor**: Admin, Petugas Puskesmas
**Deskripsi**: Mengekspor data statistik mentah
**Precondition**: User sudah login
**Postcondition**: Data statistik berhasil diekspor

**Main Flow**:
1. User memilih periode data
2. User memilih jenis statistik (HT/DM/gabungan)
3. User memilih format export
4. Sistem mengumpulkan data statistik
5. Sistem menghasilkan file export
6. Sistem menyediakan link download

---

## Catatan Implementasi

### Security Considerations
1. Semua use case memerlukan autentikasi
2. Authorization berdasarkan role (admin vs puskesmas)
3. Data isolation per puskesmas untuk petugas puskesmas
4. Audit trail untuk operasi sensitif

### Performance Considerations
1. Caching untuk statistik yang sering diakses
2. Pagination untuk daftar data yang besar
3. Background job untuk export file besar
4. Database indexing untuk query yang sering digunakan

### Data Integrity
1. Validasi input di semua form
2. Constraint database untuk data critical
3. Soft delete untuk data penting
4. Backup otomatis data

### User Experience
1. Loading indicator untuk operasi yang memakan waktu
2. Konfirmasi untuk operasi destructive
3. Error handling yang user-friendly
4. Responsive design untuk berbagai device