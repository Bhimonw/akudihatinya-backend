# 🎯 Use Case Diagram

Dokumen ini berisi semua Use Case Diagram untuk sistem Akudihatinya Backend, termasuk diagram utama, diagram sederhana untuk modul akun/user, dan diagram untuk fitur-fitur spesifik.
## 1. Use Case Diagram Utama

```mermaid
graph TB
    %% Actors
    Admin[👤 Admin]
    PuskesmasUser[👤 User Puskesmas]
    System[🖥️ System]
    
    %% Admin Use Cases
    subgraph "Admin Use Cases"
        UC1[Login]
        UC2[Manage Puskesmas]
        UC3[View All Statistics]
        UC4[Export Global Reports]
        UC5[Manage Users]
        UC6[Set Yearly Targets]
        UC7[View System Analytics]
        UC8[Manage System Settings]
    end
    
    %% Puskesmas User Use Cases
    subgraph "Puskesmas User Use Cases"
        UC9[Login]
        UC10[Manage Patients]
        UC11[Record HT Examinations]
        UC12[Record DM Examinations]
        UC13[View Puskesmas Statistics]
        UC14[Export Puskesmas Reports]
        UC15[Update Profile]
        UC16[View Patient History]
        UC17[Archive Old Data]
    end
    
    %% System Use Cases
    subgraph "System Use Cases"
        UC18[Generate Monthly Statistics]
        UC19[Cache Statistics Data]
        UC20[Send Notifications]
        UC21[Backup Data]
        UC22[Clean Archived Data]
    end
    
    %% Relationships
    Admin --> UC1
    Admin --> UC2
    Admin --> UC3
    Admin --> UC4
    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    
    PuskesmasUser --> UC9
    PuskesmasUser --> UC10
    PuskesmasUser --> UC11
    PuskesmasUser --> UC12
    PuskesmasUser --> UC13
    PuskesmasUser --> UC14
    PuskesmasUser --> UC15
    PuskesmasUser --> UC16
    PuskesmasUser --> UC17
    
    System --> UC18
    System --> UC19
    System --> UC20
    System --> UC21
    System --> UC22
```

## Detail Use Cases

### 1. Authentication Use Cases

#### UC1 & UC9: Login
**Actor:** Admin, User Puskesmas  
**Deskripsi:** Pengguna melakukan autentikasi untuk mengakses sistem  
**Precondition:** Pengguna memiliki akun yang valid  
**Flow:**
1. Pengguna memasukkan username dan password
2. Sistem memvalidasi kredensial
3. Sistem menghasilkan access token dan refresh token
4. Pengguna diarahkan ke dashboard sesuai role

**Postcondition:** Pengguna berhasil login dan dapat mengakses fitur sesuai role

### 2. Admin Use Cases

#### UC2: Manage Puskesmas
**Actor:** Admin  
**Deskripsi:** Admin mengelola data puskesmas  
**Flow:**
1. Admin mengakses halaman manajemen puskesmas
2. Admin dapat menambah, mengubah, atau menonaktifkan puskesmas
3. Sistem menyimpan perubahan data puskesmas

#### UC3: View All Statistics
**Actor:** Admin  
**Deskripsi:** Admin melihat statistik dari semua puskesmas  
**Flow:**
1. Admin mengakses dashboard statistik global
2. Sistem menampilkan agregasi data dari semua puskesmas
3. Admin dapat memfilter berdasarkan periode, jenis penyakit, atau puskesmas

#### UC4: Export Global Reports
**Actor:** Admin  
**Deskripsi:** Admin mengekspor laporan global dalam format PDF/Excel  
**Flow:**
1. Admin memilih parameter laporan (periode, format, filter)
2. Sistem menghasilkan laporan sesuai parameter
3. Sistem menyediakan file untuk diunduh

#### UC5: Manage Users
**Actor:** Admin  
**Deskripsi:** Admin mengelola akun pengguna puskesmas  
**Flow:**
1. Admin mengakses halaman manajemen pengguna
2. Admin dapat menambah, mengubah, atau menonaktifkan pengguna
3. Admin mengatur role dan akses pengguna

#### UC6: Set Yearly Targets
**Actor:** Admin  
**Deskripsi:** Admin menetapkan target tahunan untuk setiap puskesmas  
**Flow:**
1. Admin mengakses halaman target tahunan
2. Admin menetapkan target untuk HT dan DM per puskesmas
3. Sistem menyimpan target dan menggunakannya untuk evaluasi

#### UC7: View System Analytics
**Actor:** Admin  
**Deskripsi:** Admin melihat analitik penggunaan sistem  
**Flow:**
1. Admin mengakses dashboard analitik sistem
2. Sistem menampilkan metrik penggunaan, performa, dan tren

#### UC8: Manage System Settings
**Actor:** Admin  
**Deskripsi:** Admin mengelola pengaturan sistem global  
**Flow:**
1. Admin mengakses halaman pengaturan sistem
2. Admin mengubah konfigurasi sistem
3. Sistem menerapkan perubahan konfigurasi

### 3. Puskesmas User Use Cases

#### UC10: Manage Patients
**Actor:** User Puskesmas  
**Deskripsi:** User mengelola data pasien di puskesmasnya  
**Flow:**
1. User mengakses halaman manajemen pasien
2. User dapat menambah, mengubah, atau melihat data pasien
3. Sistem menyimpan data pasien dengan validasi NIK

#### UC11: Record HT Examinations
**Actor:** User Puskesmas  
**Deskripsi:** User mencatat hasil pemeriksaan hipertensi  
**Flow:**
1. User memilih pasien untuk pemeriksaan
2. User memasukkan data tekanan darah (sistolik/diastolik)
3. Sistem menghitung status kontrol berdasarkan nilai normal
4. Sistem menyimpan data pemeriksaan

#### UC12: Record DM Examinations
**Actor:** User Puskesmas  
**Deskripsi:** User mencatat hasil pemeriksaan diabetes mellitus  
**Flow:**
1. User memilih pasien untuk pemeriksaan
2. User memilih jenis pemeriksaan (HbA1c, GDP, GD2JPP, GDSP)
3. User memasukkan hasil pemeriksaan
4. Sistem menghitung status kontrol berdasarkan jenis pemeriksaan
5. Sistem menyimpan data pemeriksaan

#### UC13: View Puskesmas Statistics
**Actor:** User Puskesmas  
**Deskripsi:** User melihat statistik puskesmasnya  
**Flow:**
1. User mengakses dashboard statistik puskesmas
2. Sistem menampilkan statistik HT dan DM untuk puskesmas tersebut
3. User dapat memfilter berdasarkan periode

#### UC14: Export Puskesmas Reports
**Actor:** User Puskesmas  
**Deskripsi:** User mengekspor laporan puskesmasnya  
**Flow:**
1. User memilih parameter laporan (periode, format, jenis penyakit)
2. Sistem menghasilkan laporan khusus puskesmas
3. Sistem menyediakan file untuk diunduh

#### UC15: Update Profile
**Actor:** User Puskesmas  
**Deskripsi:** User mengubah profil dan informasi akunnya  
**Flow:**
1. User mengakses halaman profil
2. User mengubah informasi profil (nama, foto, dll)
3. Sistem menyimpan perubahan profil

#### UC16: View Patient History
**Actor:** User Puskesmas  
**Deskripsi:** User melihat riwayat pemeriksaan pasien  
**Flow:**
1. User memilih pasien
2. Sistem menampilkan riwayat pemeriksaan HT dan DM
3. User dapat melihat tren dan perkembangan kondisi pasien

#### UC17: Archive Old Data
**Actor:** User Puskesmas  
**Deskripsi:** User mengarsipkan data pemeriksaan lama  
**Flow:**
1. User mengakses halaman manajemen data
2. User memilih data yang akan diarsipkan
3. Sistem memindahkan data ke status arsip

### 4. System Use Cases

#### UC18: Generate Monthly Statistics
**Actor:** System  
**Deskripsi:** Sistem otomatis menghasilkan statistik bulanan  
**Flow:**
1. Sistem berjalan secara terjadwal setiap akhir bulan
2. Sistem menghitung statistik untuk semua puskesmas
3. Sistem menyimpan hasil ke cache untuk performa

#### UC19: Cache Statistics Data
**Actor:** System  
**Deskripsi:** Sistem menyimpan data statistik ke cache  
**Flow:**
1. Sistem mendeteksi permintaan statistik yang sering diakses
2. Sistem menyimpan hasil perhitungan ke cache
3. Sistem menggunakan cache untuk permintaan selanjutnya

#### UC20: Send Notifications
**Actor:** System  
**Deskripsi:** Sistem mengirim notifikasi otomatis  
**Flow:**
1. Sistem mendeteksi event yang memerlukan notifikasi
2. Sistem menghasilkan pesan notifikasi
3. Sistem mengirim notifikasi ke pengguna terkait

#### UC21: Backup Data
**Actor:** System  
**Deskripsi:** Sistem melakukan backup data secara otomatis  
**Flow:**
1. Sistem berjalan secara terjadwal
2. Sistem membuat backup database
3. Sistem menyimpan backup ke lokasi aman

#### UC22: Clean Archived Data
**Actor:** System  
**Deskripsi:** Sistem membersihkan data arsip lama  
**Flow:**
1. Sistem berjalan secara terjadwal
2. Sistem mengidentifikasi data arsip yang sudah sangat lama
3. Sistem menghapus data sesuai kebijakan retensi

## Use Case Relationships

### Include Relationships
- UC11, UC12 include "Validate Patient Data"
- UC4, UC14 include "Generate Report"
- UC3, UC13 include "Calculate Statistics"

### Extend Relationships
- UC10 extends "Send Patient Notification"
- UC11, UC12 extend "Update Patient Status"
- UC6 extends "Send Target Notification"

### Generalization
- UC1 dan UC9 adalah generalisasi dari "Authentication"
- UC4 dan UC14 adalah generalisasi dari "Export Report"

## Business Rules

1. **Role-based Access Control:**
   - Admin dapat mengakses semua data
   - User Puskesmas hanya dapat mengakses data puskesmasnya

2. **Data Validation:**
   - NIK pasien harus unik
   - Tanggal pemeriksaan tidak boleh di masa depan
   - Nilai pemeriksaan harus dalam rentang yang valid

3. **Audit Trail:**
   - Semua perubahan data dicatat dengan timestamp
   - Data pemeriksaan tidak dapat dihapus, hanya diarsipkan

4. **Performance:**
   - Statistik bulanan di-cache untuk performa
   - Data arsip dipisahkan untuk optimasi query

5. **Security:**
   - Semua endpoint memerlukan autentikasi
   - Token memiliki masa berlaku terbatas
   - Password di-hash dengan algoritma yang aman

## Use Case Diagram dengan Relasi yang Jelas

```mermaid
graph TB
    %% Actors dengan styling
    Admin["👤 Admin<br/>Pengelola Sistem"]
    PuskesmasUser["👤 User Puskesmas<br/>Petugas Kesehatan"]
    System["🖥️ System<br/>Proses Otomatis"]
    
    %% Admin Use Cases dengan styling
    subgraph AdminUC["🔧 Admin Use Cases"]
        direction TB
        UC1["🔐 UC1: Login"]
        UC2["🏥 UC2: Manage Puskesmas"]
        UC3["📊 UC3: View All Statistics"]
        UC4["📄 UC4: Export Global Reports"]
        UC5["👥 UC5: Manage Users"]
        UC6["🎯 UC6: Set Yearly Targets"]
        UC7["📈 UC7: View System Analytics"]
        UC8["⚙️ UC8: Manage System Settings"]
    end
    
    %% Puskesmas User Use Cases dengan styling
    subgraph PuskesmasUC["🏥 Puskesmas User Use Cases"]
        direction TB
        UC9["🔐 UC9: Login"]
        UC10["👤 UC10: Manage Patients"]
        UC11["🩺 UC11: Record HT Examinations"]
        UC12["💉 UC12: Record DM Examinations"]
        UC13["📊 UC13: View Puskesmas Statistics"]
        UC14["📄 UC14: Export Puskesmas Reports"]
        UC15["👤 UC15: Update Profile"]
        UC16["📋 UC16: View Patient History"]
        UC17["📦 UC17: Archive Old Data"]
    end
    
    %% System Use Cases dengan styling
    subgraph SystemUC["⚡ System Use Cases"]
        direction TB
        UC18["📊 UC18: Generate Monthly Statistics"]
        UC19["💾 UC19: Cache Statistics Data"]
        UC20["🔔 UC20: Send Notifications"]
        UC21["💾 UC21: Backup Data"]
        UC22["🗑️ UC22: Clean Archived Data"]
    end
    
    %% Include Use Cases
    subgraph IncludeUC["📎 Include Use Cases"]
        direction TB
        ValidatePatient["✅ Validate Patient Data"]
        GenerateReport["📄 Generate Report"]
        CalculateStats["🧮 Calculate Statistics"]
        Authentication["🔐 Authentication"]
    end
    
    %% Extend Use Cases
    subgraph ExtendUC["🔗 Extend Use Cases"]
        direction TB
        SendPatientNotif["🔔 Send Patient Notification"]
        UpdatePatientStatus["🔄 Update Patient Status"]
        SendTargetNotif["🎯 Send Target Notification"]
    end
    
    %% Actor Relationships
    Admin -.-> UC1
    Admin -.-> UC2
    Admin -.-> UC3
    Admin -.-> UC4
    Admin -.-> UC5
    Admin -.-> UC6
    Admin -.-> UC7
    Admin -.-> UC8
    
    PuskesmasUser -.-> UC9
    PuskesmasUser -.-> UC10
    PuskesmasUser -.-> UC11
    PuskesmasUser -.-> UC12
    PuskesmasUser -.-> UC13
    PuskesmasUser -.-> UC14
    PuskesmasUser -.-> UC15
    PuskesmasUser -.-> UC16
    PuskesmasUser -.-> UC17
    
    System -.-> UC18
    System -.-> UC19
    System -.-> UC20
    System -.-> UC21
    System -.-> UC22
    
    %% Include Relationships (dashed arrows)
    UC11 -.->|include| ValidatePatient
    UC12 -.->|include| ValidatePatient
    UC4 -.->|include| GenerateReport
    UC14 -.->|include| GenerateReport
    UC3 -.->|include| CalculateStats
    UC13 -.->|include| CalculateStats
    UC1 -.->|generalize| Authentication
    UC9 -.->|generalize| Authentication
    
    %% Extend Relationships (dotted arrows)
    UC10 -.->|extend| SendPatientNotif
    UC11 -.->|extend| UpdatePatientStatus
    UC12 -.->|extend| UpdatePatientStatus
    UC6 -.->|extend| SendTargetNotif
    
    %% Styling
    classDef actorStyle fill:#e1f5fe,stroke:#01579b,stroke-width:2px,color:#000
    classDef adminUCStyle fill:#f3e5f5,stroke:#4a148c,stroke-width:2px,color:#000
    classDef puskesmasUCStyle fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px,color:#000
    classDef systemUCStyle fill:#fff3e0,stroke:#e65100,stroke-width:2px,color:#000
    classDef includeStyle fill:#fce4ec,stroke:#880e4f,stroke-width:2px,color:#000
    classDef extendStyle fill:#f1f8e9,stroke:#33691e,stroke-width:2px,color:#000
    
    class Admin,PuskesmasUser,System actorStyle
    class UC1,UC2,UC3,UC4,UC5,UC6,UC7,UC8 adminUCStyle
    class UC9,UC10,UC11,UC12,UC13,UC14,UC15,UC16,UC17 puskesmasUCStyle
    class UC18,UC19,UC20,UC21,UC22 systemUCStyle
    class ValidatePatient,GenerateReport,CalculateStats,Authentication includeStyle
    class SendPatientNotif,UpdatePatientStatus,SendTargetNotif extendStyle
```

## 2. Use Case Diagram Modul Akun/User

```mermaid
graph TB
    %% Actors
    User["👤 User"]
    Admin["👤 Admin"]
    
    %% System Boundary
    subgraph SystemBoundary["🏥 Akudihatinya Backend - User Management"]
        %% Main Use Cases
        Login["🔐 Login"]
        Register["📝 Register/Create"]
        EditAkun["✏️ Edit Akun"]
        HapusAkun["🗑️ Hapus Akun"]
        
        %% Extend relationship
        Login -.->|extend| Register
    end
    
    %% Actor relationships
    User --> Login
    User --> Register
    User --> EditAkun
    User --> HapusAkun
    
    Admin --> Login
    Admin --> Register
    Admin --> EditAkun
    Admin --> HapusAkun
    
    %% Styling
    classDef actorStyle fill:#f9f9f9,stroke:#333,stroke-width:2px,color:#000
    classDef usecaseStyle fill:#87CEEB,stroke:#4682B4,stroke-width:2px,color:#000
    classDef systemStyle fill:#FFB6C1,stroke:#DC143C,stroke-width:3px,color:#000
    
    class User,Admin actorStyle
    class Login,Register,EditAkun,HapusAkun usecaseStyle
    class SystemBoundary systemStyle
```

### Detail Use Cases - User Management

#### 1. Login
**Actor:** User, Admin  
**Deskripsi:** Pengguna melakukan autentikasi untuk mengakses sistem  
**Precondition:** Pengguna memiliki akun yang valid  
**Flow:**
1. Pengguna memasukkan username dan password
2. Sistem memvalidasi kredensial
3. Sistem menghasilkan access token
4. Pengguna diarahkan ke dashboard sesuai role

**Postcondition:** Pengguna berhasil login dan dapat mengakses sistem

#### 2. Register/Create
**Actor:** User, Admin  
**Deskripsi:** Membuat akun baru dalam sistem  
**Precondition:** Pengguna belum memiliki akun  
**Flow:**
1. Pengguna mengisi form registrasi
2. Sistem memvalidasi data yang dimasukkan
3. Sistem memeriksa keunikan username/email
4. Sistem menyimpan data pengguna baru
5. Sistem mengirim konfirmasi registrasi

**Postcondition:** Akun baru berhasil dibuat dan dapat digunakan untuk login

#### 3. Edit Akun
**Actor:** User, Admin  
**Deskripsi:** Mengubah informasi profil pengguna  
**Precondition:** Pengguna sudah login ke sistem  
**Flow:**
1. Pengguna mengakses halaman profil
2. Pengguna mengubah informasi yang diinginkan
3. Sistem memvalidasi perubahan data
4. Sistem menyimpan perubahan
5. Sistem menampilkan konfirmasi perubahan

**Postcondition:** Informasi profil pengguna berhasil diperbarui

#### 4. Hapus Akun
**Actor:** User, Admin  
**Deskripsi:** Menghapus akun pengguna dari sistem  
**Precondition:** Pengguna sudah login dan memiliki hak akses  
**Flow:**
1. Pengguna memilih opsi hapus akun
2. Sistem menampilkan konfirmasi penghapusan
3. Pengguna mengkonfirmasi penghapusan
4. Sistem menghapus atau menonaktifkan akun
5. Sistem mencatat aktivitas penghapusan

**Postcondition:** Akun pengguna berhasil dihapus atau dinonaktifkan

## 3. Patient Management Use Case Diagram

```mermaid
graph TB
    %% Actors
    PU[👤 Puskesmas User]
    A[👨‍💼 Admin]
    S[🤖 System]
    
    %% Patient Management System
    subgraph PMSystem["📋 Patient Management System"]
        %% Core Use Cases
        UC1["📝 Create Patient"]
        UC2["👁️ View Patient List"]
        UC3["✏️ Edit Patient"]
        UC4["🗑️ Delete Patient"]
        UC5["🔍 Search Patient"]
        UC6["📊 Export Patient Data"]
        UC7["📋 Add Examination Year"]
        UC8["❌ Remove Examination Year"]
        
        %% Extended Use Cases
        UC9["📄 Export to Excel"]
        UC10["📑 Export to PDF"]
        UC11["🔍 Filter by Disease Type"]
        UC12["📱 Validate Phone Number"]
    end
    
    %% Relationships
    PU --> UC1
    PU --> UC2
    PU --> UC3
    PU --> UC4
    PU --> UC5
    PU --> UC6
    PU --> UC7
    PU --> UC8
    
    A --> UC2
    A --> UC5
    A --> UC6
    
    %% Include relationships
    UC6 -.->|include| UC9
    UC6 -.->|include| UC10
    UC2 -.->|include| UC11
    UC5 -.->|include| UC11
    UC1 -.->|include| UC12
    UC3 -.->|include| UC12
    
    %% System relationships
    S --> UC12
    
    %% Styling
    classDef actor fill:#e1f5fe,stroke:#01579b,stroke-width:2px
    classDef usecase fill:#f3e5f5,stroke:#4a148c,stroke-width:2px
    classDef system fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px
    classDef include fill:#fff3e0,stroke:#e65100,stroke-width:2px
    
    class PU,A,S actor
    class UC1,UC2,UC3,UC4,UC5,UC6,UC7,UC8 usecase
    class UC9,UC10,UC11,UC12 include
```

## 4. Examination Management Use Case Diagram

```mermaid
graph TB
    %% Actors
    PU[👤 Puskesmas User]
    A[👨‍💼 Admin]
    S[🤖 System]
    
    %% Examination Management System
    subgraph EMSystem["🩺 Examination Management System"]
        %% HT Examination Use Cases
        UC13["🩺 Create HT Examination"]
        UC14["📋 View HT Examinations"]
        UC15["✏️ Edit HT Examination"]
        UC16["🗑️ Delete HT Examination"]
        
        %% DM Examination Use Cases
        UC17["💉 Create DM Examination"]
        UC18["📋 View DM Examinations"]
        UC19["✏️ Edit DM Examination"]
        UC20["🗑️ Delete DM Examination"]
        UC21["📦 Batch Update DM"]
        
        %% Common Use Cases
        UC22["🔍 Filter by Date"]
        UC23["🔍 Filter by Patient"]
        UC24["📊 Filter by Archive Status"]
        UC25["📅 Validate Examination Date"]
        UC26["🏥 Validate Puskesmas Access"]
        UC27["📋 Auto-populate Patient Data"]
    end
    
    %% Relationships
    PU --> UC13
    PU --> UC14
    PU --> UC15
    PU --> UC16
    PU --> UC17
    PU --> UC18
    PU --> UC19
    PU --> UC20
    PU --> UC21
    
    A --> UC14
    A --> UC18
    A --> UC21
    
    %% Include relationships
    UC13 -.->|include| UC25
    UC13 -.->|include| UC26
    UC13 -.->|include| UC27
    UC17 -.->|include| UC25
    UC17 -.->|include| UC26
    UC17 -.->|include| UC27
    UC14 -.->|include| UC22
    UC14 -.->|include| UC23
    UC14 -.->|include| UC24
    UC18 -.->|include| UC22
    UC18 -.->|include| UC23
    UC18 -.->|include| UC24
    
    %% System relationships
    S --> UC25
    S --> UC26
    S --> UC27
    
    %% Styling
    classDef actor fill:#e1f5fe,stroke:#01579b,stroke-width:2px
    classDef usecase fill:#f3e5f5,stroke:#4a148c,stroke-width:2px
    classDef system fill:#e8f5e8,stroke:#1b5e20,stroke-width:2px
    classDef include fill:#fff3e0,stroke:#e65100,stroke-width:2px
    
    class PU,A,S actor
    class UC13,UC14,UC15,UC16,UC17,UC18,UC19,UC20,UC21 usecase
    class UC22,UC23,UC24,UC25,UC26,UC27 include
```

## Business Rules dan Guidelines

### Authentication & Authorization
1. Username/email harus unik dalam sistem
2. Password harus memenuhi kriteria keamanan minimum
3. Session timeout setelah periode tidak aktif
4. User hanya dapat mengedit dan menghapus akun sendiri
5. Admin dapat mengelola semua akun pengguna

### Data Validation
1. Email harus dalam format yang valid
2. Username tidak boleh mengandung karakter khusus tertentu
3. Semua field wajib harus diisi
4. Validasi CSRF untuk semua form

### Security
1. Password di-hash sebelum disimpan
2. Implementasi rate limiting untuk mencegah brute force
3. Audit trail untuk semua aktivitas penting
4. Penghapusan akun dicatat untuk keperluan audit

---

**Catatan:** Semua diagram dibuat mengikuti standar UML Use Case Diagram dan dapat disesuaikan lebih lanjut sesuai kebutuhan spesifik sistem.
