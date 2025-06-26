# Dokumentasi ERD dan Use Case Diagram - Sistem Akudihatinya

## Deskripsi Sistem

Sistem Akudihatinya adalah aplikasi backend untuk manajemen data kesehatan yang fokus pada pemantauan penyakit Hipertensi (HT) dan Diabetes Mellitus (DM) di tingkat Puskesmas. Sistem ini memungkinkan admin (Dinas Kesehatan) dan petugas Puskesmas untuk mengelola data pasien, pemeriksaan, dan statistik kesehatan.

## Entity Relationship Diagram (ERD)

### Entitas dan Atribut

#### 1. **Users**
- `id` (Primary Key)
- `username` (Unique)
- `password`
- `name`
- `profile_picture`
- `role` (admin/puskesmas)
- `puskesmas_id` (Foreign Key)
- `created_at`
- `updated_at`

#### 2. **Puskesmas**
- `id` (Primary Key)
- `name`
- `created_at`
- `updated_at`

#### 3. **Patients**
- `id` (Primary Key)
- `puskesmas_id` (Foreign Key)
- `nik` (Unique, 16 karakter)
- `bpjs_number` (20 karakter)
- `medical_record_number`
- `name`
- `address`
- `phone_number`
- `gender` (male/female)
- `birth_date`
- `age`
- `has_ht` (Boolean, calculated)
- `has_dm` (Boolean, calculated)
- `ht_years` (JSON Array)
- `dm_years` (JSON Array)
- `created_at`
- `updated_at`

#### 4. **HT_Examinations**
- `id` (Primary Key)
- `patient_id` (Foreign Key)
- `puskesmas_id` (Foreign Key)
- `examination_date`
- `systolic` (Tekanan darah sistolik)
- `diastolic` (Tekanan darah diastolik)
- `is_controlled` (Boolean)
- `is_first_visit_this_month` (Boolean)
- `is_standard_patient` (Boolean)
- `patient_gender`
- `year`
- `month`
- `is_archived` (Boolean)
- `created_at`
- `updated_at`

#### 5. **DM_Examinations**
- `id` (Primary Key)
- `patient_id` (Foreign Key)
- `puskesmas_id` (Foreign Key)
- `examination_date`
- `examination_type` (hba1c/gdp)
- `result` (Decimal)
- `is_controlled` (Boolean)
- `is_first_visit_this_month` (Boolean)
- `is_standard_patient` (Boolean)
- `patient_gender`
- `year`
- `month`
- `is_archived` (Boolean)
- `created_at`
- `updated_at`

#### 6. **Yearly_Targets**
- `id` (Primary Key)
- `puskesmas_id` (Foreign Key)
- `disease_type` (ht/dm)
- `year`
- `target_count`
- `created_at`
- `updated_at`

#### 7. **User_Refresh_Tokens**
- `id` (Primary Key)
- `user_id` (Foreign Key)
- `token`
- `expires_at`
- `created_at`
- `updated_at`

#### 8. **Monthly_Statistics_Cache**
- `id` (Primary Key)
- `puskesmas_id` (Foreign Key)
- `disease_type`
- `year`
- `month`
- `statistics_data` (JSON)
- `created_at`
- `updated_at`

### Relasi Antar Entitas

1. **Users → Puskesmas** (Many-to-One)
   - Satu puskesmas dapat memiliki banyak user
   - Satu user hanya terkait dengan satu puskesmas

2. **Puskesmas → Patients** (One-to-Many)
   - Satu puskesmas dapat memiliki banyak pasien
   - Satu pasien hanya terdaftar di satu puskesmas

3. **Patients → HT_Examinations** (One-to-Many)
   - Satu pasien dapat memiliki banyak pemeriksaan HT
   - Satu pemeriksaan HT hanya untuk satu pasien

4. **Patients → DM_Examinations** (One-to-Many)
   - Satu pasien dapat memiliki banyak pemeriksaan DM
   - Satu pemeriksaan DM hanya untuk satu pasien

5. **Puskesmas → HT_Examinations** (One-to-Many)
   - Satu puskesmas dapat memiliki banyak pemeriksaan HT
   - Satu pemeriksaan HT dilakukan di satu puskesmas

6. **Puskesmas → DM_Examinations** (One-to-Many)
   - Satu puskesmas dapat memiliki banyak pemeriksaan DM
   - Satu pemeriksaan DM dilakukan di satu puskesmas

7. **Puskesmas → Yearly_Targets** (One-to-Many)
   - Satu puskesmas dapat memiliki banyak target tahunan
   - Satu target tahunan hanya untuk satu puskesmas

8. **Users → User_Refresh_Tokens** (One-to-Many)
   - Satu user dapat memiliki banyak refresh token
   - Satu refresh token hanya untuk satu user

9. **Puskesmas → Monthly_Statistics_Cache** (One-to-Many)
   - Satu puskesmas dapat memiliki banyak cache statistik bulanan
   - Satu cache statistik bulanan hanya untuk satu puskesmas

## Use Case Diagram

### Aktor

#### 1. **Admin (Dinas Kesehatan)**
- Role: admin
- Akses: Seluruh sistem
- Tanggung jawab: Mengelola user, target tahunan, dan melihat statistik keseluruhan

#### 2. **Petugas Puskesmas**
- Role: puskesmas
- Akses: Data puskesmas sendiri
- Tanggung jawab: Mengelola data pasien dan pemeriksaan

### Use Cases

#### **A. Manajemen Autentikasi**
1. **Login**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Masuk ke sistem menggunakan username dan password
   - Flow: Input credentials → Validasi → Generate access & refresh token

2. **Logout**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Keluar dari sistem dan menghapus token

3. **Refresh Token**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Memperbarui access token menggunakan refresh token

4. **Change Password**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Mengubah password akun

#### **B. Manajemen User (Admin Only)**
1. **Create User**
   - Aktor: Admin
   - Deskripsi: Membuat akun user baru untuk puskesmas
   - Flow: Input data user → Assign puskesmas → Generate credentials

2. **View Users**
   - Aktor: Admin
   - Deskripsi: Melihat daftar semua user dalam sistem

3. **Update User**
   - Aktor: Admin
   - Deskripsi: Mengubah informasi user

4. **Delete User**
   - Aktor: Admin
   - Deskripsi: Menghapus user dari sistem

5. **Reset Password**
   - Aktor: Admin
   - Deskripsi: Reset password user lain

#### **C. Manajemen Target Tahunan (Admin Only)**
1. **Set Yearly Target**
   - Aktor: Admin
   - Deskripsi: Menetapkan target tahunan untuk puskesmas
   - Flow: Pilih puskesmas → Pilih jenis penyakit → Set target count

2. **View Yearly Targets**
   - Aktor: Admin
   - Deskripsi: Melihat semua target tahunan

3. **Update Yearly Target**
   - Aktor: Admin
   - Deskripsi: Mengubah target tahunan yang sudah ada

4. **Delete Yearly Target**
   - Aktor: Admin
   - Deskripsi: Menghapus target tahunan

#### **D. Manajemen Pasien (Puskesmas)**
1. **Register Patient**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mendaftarkan pasien baru
   - Flow: Input data pasien → Validasi NIK → Simpan data

2. **View Patients**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Melihat daftar pasien di puskesmas

3. **Update Patient**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mengubah informasi pasien

4. **Delete Patient**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Menghapus data pasien

5. **Export Patient Data**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mengekspor data pasien ke Excel/PDF

6. **Add Examination Year**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Menambah tahun pemeriksaan untuk pasien

7. **Remove Examination Year**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Menghapus tahun pemeriksaan pasien

#### **E. Manajemen Pemeriksaan HT (Puskesmas)**
1. **Record HT Examination**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mencatat hasil pemeriksaan hipertensi
   - Flow: Pilih pasien → Input tekanan darah → Hitung status terkontrol

2. **View HT Examinations**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Melihat riwayat pemeriksaan HT

3. **Update HT Examination**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mengubah data pemeriksaan HT

4. **Delete HT Examination**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Menghapus data pemeriksaan HT

#### **F. Manajemen Pemeriksaan DM (Puskesmas)**
1. **Record DM Examination**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mencatat hasil pemeriksaan diabetes
   - Flow: Pilih pasien → Pilih jenis tes → Input hasil → Hitung status terkontrol

2. **View DM Examinations**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Melihat riwayat pemeriksaan DM

3. **Update DM Examination**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Mengubah data pemeriksaan DM

4. **Delete DM Examination**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Menghapus data pemeriksaan DM

#### **G. Dashboard dan Statistik**
1. **View Admin Dashboard**
   - Aktor: Admin
   - Deskripsi: Melihat statistik keseluruhan semua puskesmas
   - Data: Total pasien, pemeriksaan, pencapaian target

2. **View Puskesmas Dashboard**
   - Aktor: Petugas Puskesmas
   - Deskripsi: Melihat statistik puskesmas sendiri
   - Data: Statistik pasien, pemeriksaan, progress target

3. **Generate Reports**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Membuat laporan dalam format PDF/Excel
   - Jenis: Laporan bulanan, kuartalan, tahunan

4. **Export Statistics**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Mengekspor data statistik

#### **H. Manajemen Profil**
1. **Update Profile**
   - Aktor: Admin, Petugas Puskesmas
   - Deskripsi: Mengubah informasi profil pengguna
   - Data: Nama, foto profil

### Business Rules

1. **Kontrol Hipertensi**: Tekanan darah terkontrol jika sistolik 90-139 mmHg dan diastolik 60-89 mmHg
2. **Kontrol Diabetes**: 
   - HbA1c terkontrol jika < 7%
   - GDP terkontrol jika < 126 mg/dL
3. **Kunjungan Pertama**: Hanya satu kunjungan pertama per pasien per bulan
4. **Pasien Standar**: Pasien yang memenuhi kriteria tertentu untuk pelaporan
5. **Arsip Data**: Data dapat diarsipkan untuk periode tertentu
6. **Target Tahunan**: Setiap puskesmas memiliki target jumlah pasien per jenis penyakit per tahun

### Integrasi Sistem

1. **Authentication**: Menggunakan Laravel Sanctum untuk API authentication
2. **Authorization**: Role-based access control (Admin vs Puskesmas)
3. **Caching**: Monthly statistics cache untuk optimasi performa
4. **Export**: Integrasi dengan library PDF dan Excel untuk export data
5. **Validation**: Comprehensive input validation untuk data integrity

## Kesimpulan

Sistem Akudihatinya dirancang untuk mendukung manajemen data kesehatan yang efisien dengan fokus pada pemantauan penyakit kronis (HT dan DM). Dengan struktur database yang normalized dan use case yang comprehensive, sistem ini dapat mendukung kebutuhan pelaporan dan monitoring kesehatan di tingkat puskesmas dan dinas kesehatan.