# 🏥 Akudihatinya Backend

> **Platform monitoring dan pelaporan kesehatan untuk Puskesmas**  
> Sistem backend untuk monitoring penyakit Hipertensi (HT) dan Diabetes Melitus (DM)

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## 🚀 Features

- **Multi-Role Authentication**: Admin dan Puskesmas dengan akses berbeda
- **Statistics & Reporting**: Laporan statistik komprehensif untuk HT dan DM
- **Export Functionality**: Export data ke PDF dan Excel dengan template khusus
- **Puskesmas Management**: Manajemen data puskesmas dan target tahunan
- **Patient Management**: Manajemen data pasien dan pemeriksaan
- **Monitoring Reports**: Laporan monitoring bulanan dan triwulanan
- **RESTful API**: API yang well-documented untuk integrasi frontend

## 📋 Requirements

- PHP 8.1+
- Composer 2.x
- MySQL 8.0+ atau MariaDB 10.4+
- Node.js 16+ (untuk asset compilation)
- Git

## 🛠️ Installation

### Quick Start

```bash
# Clone repository
git clone https://github.com/Bhimonw/akudihatinya-backend.git
cd akudihatinya-backend

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env file
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=akudihatinya
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations and seeders
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve
```

### Production Setup

Lihat [System Architecture](docs/SYSTEM_ARCHITECTURE.md) untuk setup production yang lengkap.

## 📚 Documentation

> **📖 Dokumentasi lengkap tersedia di folder [`docs/`](./docs/)**

### 🚀 Quick Links
| Kategori | Dokumen | Deskripsi |
|----------|---------|----------|
| **Getting Started** | [Developer Guide](./docs/DEVELOPER_GUIDE.md) | Setup environment & coding standards |
| | [System Architecture](./docs/SYSTEM_ARCHITECTURE.md) | Production deployment & architecture |
| **Complete API Documentation** | [API Complete](./docs/API_COMPLETE.md) | Comprehensive API endpoints reference |
| **System Design** | [System Architecture](./docs/SYSTEM_ARCHITECTURE.md) | Complete system design & diagrams |
| **Features** | [API Complete](./docs/API_COMPLETE.md) | Complete API documentation with all features |
| | [New Year Setup](./docs/NEW_YEAR_SETUP.md) | Annual data reset and target creation |
| | [Code Quality Insights](./docs/CODE_QUALITY_INSIGHTS.md) | Code quality recommendations |
| **Project Info** | [Changelog](./docs/CHANGELOG.md) | Version history and changes |
| | [Developer Guide](./docs/DEVELOPER_GUIDE.md) | Complete development guide |
| | [Code Quality Insights](./docs/CODE_QUALITY_INSIGHTS.md) | Code quality recommendations |

### 📋 Documentation Index
Lihat [**docs/README.md**](./docs/README.md) untuk daftar lengkap semua dokumentasi yang tersedia.

> ℹ️ Contoh file export Excel (monthly/quarterly/puskesmas) telah dipindahkan ke `docs/examples/exports/` untuk menjaga root repository tetap bersih.

## 🔧 Recent Fixes

### User Creation Fix (June 2025)
**Problem**: Error saat membuat user dengan role puskesmas - `SQLSTATE[HY000]: Field 'user_id' doesn't have a default value`

**Solution**: 
- Implementasi database transaction untuk konsistensi data
- Perbaikan urutan pembuatan: User → Puskesmas → Update relasi
- Enhanced error handling dan validation

📖 **Detail lengkap**: [User Creation Fix Documentation](./docs/USER_CREATION_FIX.md)

## 🏗️ Architecture

### Project Structure

This Laravel application follows a standard structure with additional custom directories for better organization:

```
├── app/                    # Laravel application code
├── bootstrap/              # Laravel bootstrap files
├── config/                 # Configuration files
├── database/               # Migrations, seeders, factories
├── development/            # Development tools and IDE helpers
├── docs/                   # Project documentation
├── public/                 # Public web files
├── resources/              # PDF templates and Excel files
├── routes/                 # Route definitions
├── scripts/                # Utility and testing scripts
├── storage/                # File storage
└── tests/                  # Test files
```

### Custom Directories

- **`development/`** - Contains IDE helper files and development tools
- **`scripts/`** - Utility scripts for testing and development
- **`docs/`** - Comprehensive project documentation
- **`resources/`** - PDF templates for reports and Excel export templates

### Key Components

#### Services
- `StatisticsService` - Logic untuk statistik dan pelaporan
- `ExportService` - Logic untuk export data umum
- `PuskesmasExportService` - Logic export khusus puskesmas
- `PdfService` - Logic untuk generate PDF

#### Formatters
- `AdminAllFormatter` - Formatter untuk laporan tahunan Excel
- `AdminMonthlyFormatter` - Formatter untuk laporan bulanan Excel
- `AdminQuarterlyFormatter` - Formatter untuk laporan triwulan Excel
- `PuskesmasFormatter` - Formatter untuk template puskesmas Excel
- `ExcelExportFormatter` - Base formatter untuk ekspor Excel

#### Repositories
- `PuskesmasRepository` - Repository untuk data puskesmas
- `YearlyTargetRepository` - Repository untuk target tahunan

## 🔐 Authentication

Sistem menggunakan Laravel Sanctum untuk API authentication dengan 2 role:

- **Admin**: Akses penuh ke semua data dan fitur
- **Puskesmas**: Akses terbatas hanya ke data puskesmas sendiri

### Login

```bash
POST /api/auth/login
{
    "email": "admin@example.com",
    "password": "password"
}
```

## 📊 API Endpoints

### Statistics & Export

```bash
# Get statistics
GET /api/statistics?year=2024&disease_type=ht

# Export to Excel
GET /api/statistics/export?year=2024&format=excel&disease_type=all

# Export to PDF (Puskesmas specific)
GET /api/statistics/export?year=2024&format=pdf&table_type=puskesmas&disease_type=ht&puskesmas_id=1

# Export monitoring report
GET /api/statistics/export-monitoring?year=2024&month=6&format=pdf
```

### User Management (Admin only)

```bash
# Get all users
GET /api/admin/users

# Create user
POST /api/admin/users

# Update user
PUT /api/admin/users/{id}

# Delete user
DELETE /api/admin/users/{id}
```

### Puskesmas Management

```bash
# Get puskesmas list
GET /api/puskesmas

# Get puskesmas details
GET /api/puskesmas/{id}

# Update yearly targets
PUT /api/puskesmas/{id}/yearly-targets
```

Lihat [Complete API Documentation](docs/API_COMPLETE.md) untuk dokumentasi lengkap.

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/API/StatisticsExportTest.php

# Run with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

## 🚀 Deployment

### Using Docker

```bash
# Build image
docker build -t akudihatinya-backend .

# Run container
docker run -d \
  --name akudihatinya-backend \
  -p 8000:8000 \
  -e DB_HOST=your-db-host \
  -e DB_DATABASE=akudihatinya \
  -e DB_USERNAME=your-username \
  -e DB_PASSWORD=your-password \
  akudihatinya-backend
```

### Manual Deployment

Lihat [System Architecture](docs/SYSTEM_ARCHITECTURE.md) untuk panduan deployment manual yang lengkap.

### 🔄 CI/CD (GitHub Actions)

Pipeline otomatis terdiri dari dua workflow:

1. `CI` (`.github/workflows/ci.yml`)
  - Trigger: setiap push & PR
  - Backend job: install composer deps, migrate (MySQL service), jalankan tests
  - Frontend job: `npm ci`, `npm run lint`, `npm run build` (output langsung ke `akudihatinya-backend/public/frontend` – tidak ada artifact terpisah)
  - Integration packaging: arsipkan folder backend (yang sudah berisi hasil build frontend) menjadi `release-bundle.tar.gz`

2. `Deploy` (`.github/workflows/deploy.yml`)
  - Trigger: push ke `main` atau manual (workflow_dispatch)
  - Mengunduh artifact `release-bundle` (jika ada) atau fallback checkout
  - Rsync ke server target + optimisasi artisan (config/route/view cache) + migrate
  - Zero downtime sederhana (down/up) – dapat ditingkatkan ke strategi symlink.

Keuntungan alur langsung:
- Lebih sederhana (tidak perlu upload/download artifact frontend khusus)
- Mengurangi waktu pipeline & risiko mismatch versi frontend-backend.

#### Secrets yang Dibutuhkan
| Secret | Deskripsi |
|--------|-----------|
| `DEPLOY_SSH_KEY` | Private key untuk SSH ke server (format OpenSSH) |
| `DEPLOY_HOST` | Hostname/IP server | 
| `DEPLOY_USER` | Username SSH | 
| `DEPLOY_PATH` | Path direktori deploy di server (misal `/var/www/akudihatinya`) |

Tambahan environment di server (file `.env` produksi) harus sudah ada dan tidak dioverwrite oleh pipeline.

#### Menjalankan Deploy Manual
Masuk ke tab Actions → pilih workflow Deploy → Run workflow → pilih environment (misal `production`).

#### Rollback (Manual)
1. Simpan rilis sebelumnya di server (opsional: tar backup sebelum overwrite)
2. Jika ada error kritikal: `php artisan down`, restore backup, `php artisan up`

#### Optimalisasi Lanjutan (Belum Diimplementasi)
| Fitur | Catatan |
|-------|--------|
| Zero-downtime deploy | Bisa pakai symlink pattern (`releases/` + `current/`) |
| Frontend integrity hashing | Tambah Subresource Integrity (SRI) opsional |
| Security headers | Tambah middleware custom |
| Static analysis | Tambah step PHPStan/Pint lint gating |

#### Local Test Pipeline
Simulasi langkah utama secara manual:
```
composer install
php artisan migrate --force
php artisan test
cd ../frontend-akudihatinya && npm ci && npm run build
```

## 🚀 Frontend Production Build Integration

Frontend (Vite + Vue) berada di folder `frontend-akudihatinya` dan sekarang build langsung keluar ke `akudihatinya-backend/public/frontend` (lihat `vite.config.js`).

### Langkah Harian (Dev → Prod)
```
cd frontend-akudihatinya
npm ci  # atau npm install
npm run dev   # pengembangan
npm run build # hasil langsung ke ../akudihatinya-backend/public/frontend
```

Tidak perlu script sync terpisah lagi. Direktori `public/frontend` di-ignore (kecuali README & .gitkeep) sehingga artifact tidak ikut commit.

### Serving
- API: `/api/*`
- Frontend: `/frontend/index.html` (atau atur rewrite root → file ini jika menjadi root SPA)

Contoh Nginx:
```
location /api/ {
  proxy_pass http://127.0.0.1:8000/api/;
}
location /frontend/ {
  root /var/www/akudihatinya-backend/public;
  try_files $uri /frontend/index.html;
}
```

### Runtime API Base URL (Hybrid)
Resolusi prioritas:
1. `window.__RUNTIME_CONFIG__.API_BASE_URL` (file runtime-config.js di server)
2. `import.meta.env.VITE_API_BASE_URL`
3. Fallback `window.location.origin + '/api'`

Update tanpa rebuild:
```
cp frontend-akudihatinya/public/runtime-config.example.js akudihatinya-backend/public/frontend/runtime-config.js
# edit runtime-config.js → window.__RUNTIME_CONFIG__ = { API_BASE_URL: 'https://your-domain/api' };
```

### Troubleshooting Ringkas
| Masalah | Solusi |
|---------|--------|
| 404 assets | Pastikan web root menunjuk ke `public/` dan path relatif benar |
| API 401 | Cek header CORS & Sanctum config; base URL benar |
| Memanggil localhost | Rebuild dengan `.env.production` benar atau set runtime-config.js |
| Blank page | Cek console browser & permission file |

### Optional Root SPA
Jika ingin root `/` langsung ke frontend:
```
Route::get('/{any}', fn() => file_get_contents(public_path('frontend/index.html')))
  ->where('any', '.*');
```
(Gunakan hanya jika tidak memakai Blade di root.)

## 📑 Export Semantics (Statistik) – Option B Harmonization

Kolom ekspor (Admin All / Monthly / Quarterly) telah distandardisasi agar selaras dengan dashboard:

| Kolom | Periode (bulan/tri) | Arti | Catatan |
|-------|---------------------|------|---------|
| L | Bulanan/Quarter | Pemeriksaan standard (laki-laki) | Hanya pasien berstatus standard pada bulan tsb |
| P | Bulanan/Quarter | Pemeriksaan standard (perempuan) | Sama seperti L (standard only) |
| TOTAL | Bulanan/Quarter | Total Pelayanan = S + TS | Re-defined (sebelumnya hanya S) |
| TS | Bulanan/Quarter | Pemeriksaan non-standard | Tidak memenuhi kesinambungan bulanan |
| %S | Bulanan/Quarter | (S / Target) | S = TOTAL - TS |
| S (Annual Summary) | Tahunan | Jumlah standard aggregate | Ditampilkan eksplisit di blok ringkasan akhir |

Definisi:
- Standard Patient: Pasien hadir setiap bulan secara kontinu sejak bulan pertama kunjungan tanpa jeda.
- Monthly counts = jumlah pemeriksaan (visits), bukan pasien unik.
- Yearly totals (distinct) ditangani di service untuk ringkasan; di file export disediakan S aggregate & total pelayanan.

Alasan perubahan TOTAL:
- Mengurangi kebingungan antara “TOTAL” di dashboard (pelayanan) vs Excel (sebelumnya S)
- Memastikan pengguna tidak salah menafsirkan coverage capaian.

Rumus penting:
```
S = TOTAL - TS
%S = S / Target (dibulatkan sesuai formatter)
Total Pelayanan (jika diperlukan eksplisit) = TOTAL
```

Jika membutuhkan S per periode sebagai kolom terpisah di masa depan, tambahkan kolom baru agar backward compatible.

### Validasi Konsistensi
Uji regresi direkomendasikan:
1. Buat data 1 pasien hadir 3 bulan tanpa gap → selalu standard.
2. Tambah pasien kedua skip 1 bulan → hitung TS sesuai bulan skip.
3. Verifikasi: TOTAL = S + TS; %S memakai S.

### Rencana Testing (Belum Implementasi)
- Feature test export membandingkan output JSON statistik vs nilai ter-parse dari sheet.
- Edge case: bulan tanpa data (semua 0) tetap output kolom lengkap.

---

---

## 🤝 Contributing

Kami menyambut kontribusi dari developer! Silakan baca [Developer Guide](docs/DEVELOPER_GUIDE.md) untuk panduan lengkap.

### Quick Contributing Steps

1. Fork repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'feat: add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open Pull Request

### Coding Standards

- Follow PSR-12 coding standard
- Write tests for new features
- Update documentation
- Use conventional commits

## 🐛 Troubleshooting

### Common Issues

#### Database Connection Error
```bash
# Check database configuration
php artisan config:cache
php artisan config:clear

# Test database connection
php artisan tinker
> DB::connection()->getPdo();
```

#### PDF Generation Fails
```bash
# Check storage permissions
chmod -R 775 storage/
chown -R www-data:www-data storage/

# Clear cache
php artisan cache:clear
php artisan view:clear
```

#### Memory Limit Exceeded
```bash
# Increase PHP memory limit in php.ini
memory_limit = 512M

# Or set in .env
PHP_MEMORY_LIMIT=512M
```

## 📞 Support

Jika Anda mengalami masalah atau memiliki pertanyaan:

1. Periksa [dokumentasi](docs/)
2. Cari di [Issues](https://github.com/Bhimonw/akudihatinya-backend/issues)
3. Buat issue baru jika diperlukan
4. Hubungi [@Bhimonw](https://github.com/Bhimonw)

## 👥 Contributors

### Maintainer
- **Bhimo Noorasty Whibhisono** ([@Bhimonw](https://github.com/Bhimonw))
  - Lead Developer & Project Maintainer
  - Responsible for architecture, code review, and releases

### Recent Contributions (March 2025 - Present)
- [@Bhimonw](https://github.com/Bhimonw) - Project setup, comprehensive documentation, code cleanup, and development workflow improvements

Terima kasih kepada semua kontributor yang telah membantu mengembangkan sistem ini! 🙏

## 📄 License

Project ini dilisensikan di bawah [MIT License](LICENSE).

---

**Akudihatinya Backend** - Membantu Puskesmas dalam monitoring dan pelaporan kesehatan yang lebih baik.
