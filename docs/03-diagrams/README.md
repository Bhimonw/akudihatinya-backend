# Dokumentasi Diagram Sistem Akudihatinya

Dokumentasi ini berisi diagram-diagram sistem yang diorganisir berdasarkan domain fungsional. Semua diagram menggunakan bahasa Indonesia dan memiliki judul yang jelas.

## Struktur Organisasi Diagram

Diagram diorganisir dalam folder berdasarkan domain fungsional:

### 1. Domain Autentikasi (`01-autentikasi/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_AUTENTIKASI.svg](./01-autentikasi/USECASE_DIAGRAM_AUTENTIKASI.svg)
- **Class Diagram**: [CLASS_DIAGRAM_AUTENTIKASI.svg](./01-autentikasi/CLASS_DIAGRAM_AUTENTIKASI.svg)
- **Activity Diagram**: [ACTIVITY_DIAGRAM_AUTENTIKASI.svg](./01-autentikasi/ACTIVITY_DIAGRAM_AUTENTIKASI.svg)
- **Sequence Diagram**: [SEQUENCE_DIAGRAM_AUTENTIKASI.svg](./01-autentikasi/SEQUENCE_DIAGRAM_AUTENTIKASI.svg)

### 2. Domain Manajemen Pengguna (`02-manajemen-pengguna/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_MANAJEMEN_PENGGUNA.svg](./02-manajemen-pengguna/USECASE_DIAGRAM_MANAJEMEN_PENGGUNA.svg)

### 3. Domain Manajemen Pasien (`03-manajemen-pasien/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_MANAJEMEN_PASIEN.svg](./03-manajemen-pasien/USECASE_DIAGRAM_MANAJEMEN_PASIEN.svg)
- **Class Diagram**: [CLASS_DIAGRAM_MANAJEMEN_PASIEN.svg](./03-manajemen-pasien/CLASS_DIAGRAM_MANAJEMEN_PASIEN.svg)
- **Activity Diagram**: [ACTIVITY_DIAGRAM_MANAJEMEN_PASIEN.svg](./03-manajemen-pasien/ACTIVITY_DIAGRAM_MANAJEMEN_PASIEN.svg)
- **Sequence Diagram**: [SEQUENCE_DIAGRAM_MANAJEMEN_PASIEN.svg](./03-manajemen-pasien/SEQUENCE_DIAGRAM_MANAJEMEN_PASIEN.svg)

### 4. Domain Pemeriksaan Hipertensi (`04-pemeriksaan-hipertensi/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_PEMERIKSAAN_HIPERTENSI.svg](./04-pemeriksaan-hipertensi/USECASE_DIAGRAM_PEMERIKSAAN_HIPERTENSI.svg)
- **Activity Diagram**: [ACTIVITY_DIAGRAM_PEMERIKSAAN_HIPERTENSI.svg](./04-pemeriksaan-hipertensi/ACTIVITY_DIAGRAM_PEMERIKSAAN_HIPERTENSI.svg)
- **Sequence Diagram**: [SEQUENCE_DIAGRAM_PEMERIKSAAN_HIPERTENSI.svg](./04-pemeriksaan-hipertensi/SEQUENCE_DIAGRAM_PEMERIKSAAN_HIPERTENSI.svg)

### 5. Domain Pemeriksaan Diabetes (`05-pemeriksaan-diabetes/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_PEMERIKSAAN_DIABETES.svg](./05-pemeriksaan-diabetes/USECASE_DIAGRAM_PEMERIKSAAN_DIABETES.svg)
- **Activity Diagram**: [ACTIVITY_DIAGRAM_PEMERIKSAAN_DIABETES.svg](./05-pemeriksaan-diabetes/ACTIVITY_DIAGRAM_PEMERIKSAAN_DIABETES.svg)
- **Sequence Diagram**: [SEQUENCE_DIAGRAM_PEMERIKSAAN_DIABETES.svg](./05-pemeriksaan-diabetes/SEQUENCE_DIAGRAM_PEMERIKSAAN_DIABETES.svg)

### 6. Domain Dashboard dan Statistik (`06-dashboard-statistik/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_DASHBOARD_STATISTIK.svg](./06-dashboard-statistik/USECASE_DIAGRAM_DASHBOARD_STATISTIK.svg)
- **Sequence Diagram**: [SEQUENCE_DIAGRAM_DASHBOARD_STATISTIK.svg](./06-dashboard-statistik/SEQUENCE_DIAGRAM_DASHBOARD_STATISTIK.svg)

### 7. Domain Target Tahunan (`07-target-tahunan/`)
- **Use Case Diagram**: [USECASE_DIAGRAM_TARGET_TAHUNAN.svg](./07-target-tahunan/USECASE_DIAGRAM_TARGET_TAHUNAN.svg)
- **Sequence Diagram**: [SEQUENCE_DIAGRAM_TARGET_TAHUNAN.svg](./07-target-tahunan/SEQUENCE_DIAGRAM_TARGET_TAHUNAN.svg)

## Karakteristik Diagram

### Bahasa
- Semua diagram menggunakan **Bahasa Indonesia**
- Terminologi konsisten di seluruh diagram
- Penamaan yang mudah dipahami oleh stakeholder lokal

### Desain
- **Judul besar** dan jelas pada setiap diagram
- Subtitle yang menunjukkan konteks sistem
- Warna yang konsisten untuk elemen yang sama
- Layout yang terorganisir dan mudah dibaca

### Organisasi
- **Pemisahan berdasarkan domain** untuk kemudahan navigasi
- Struktur folder yang logis dan konsisten
- Penamaan file yang deskriptif

## Jenis Diagram

### Use Case Diagram
Menggambarkan interaksi antara aktor (pengguna) dengan sistem, menunjukkan fungsionalitas yang tersedia untuk setiap jenis pengguna.

### Class Diagram
Menampilkan struktur kelas, atribut, metode, dan hubungan antar kelas dalam sistem.

### Activity Diagram
Menggambarkan alur kerja atau proses bisnis dalam sistem, menunjukkan langkah-langkah yang dilakukan.

### Sequence Diagram
Menampilkan interaksi antar objek dalam urutan waktu, menunjukkan bagaimana pesan dikirim antar komponen.

## Penggunaan

Diagram-diagram ini dapat digunakan untuk:
- **Dokumentasi sistem** untuk developer dan stakeholder
- **Analisis dan desain** sistem
- **Komunikasi** antar tim pengembang
- **Training** pengguna baru
- **Maintenance** dan pengembangan lanjutan

## Catatan

- Diagram dibuat dalam format SVG untuk skalabilitas dan kualitas yang baik
- Setiap domain memiliki diagram yang relevan dengan fungsionalitasnya
- Diagram dapat dibuka langsung di browser atau editor SVG
- Untuk modifikasi, gunakan editor yang mendukung format SVG