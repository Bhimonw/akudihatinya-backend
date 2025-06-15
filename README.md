# Akudihatinya Backend

Sistem backend untuk aplikasi Akudihatinya - platform monitoring dan pelaporan kesehatan untuk Puskesmas dengan fokus pada penyakit Hipertensi (HT) dan Diabetes Melitus (DM).

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

Lihat [Deployment Guide](docs/DEPLOYMENT_GUIDE.md) untuk setup production yang lengkap.

## 📚 Documentation

- [API Documentation](docs/API_DOCUMENTATION.md) - Dokumentasi lengkap API endpoints
- [Development Guide](docs/DEVELOPMENT_GUIDE.md) - Panduan untuk developer
- [Deployment Guide](docs/DEPLOYMENT_GUIDE.md) - Panduan deployment production

## 🏗️ Architecture

### Project Structure

```
akudihatinya-backend/
├── app/
│   ├── Formatters/        # Data formatters untuk export
│   ├── Http/Controllers/  # API controllers
│   ├── Models/           # Eloquent models
│   ├── Repositories/     # Repository pattern implementation
│   ├── Services/         # Business logic services
│   └── ...
├── docs/                 # Documentation
├── resources/
│   ├── excel/           # Excel templates
│   └── pdf/             # PDF templates
├── routes/api/          # API routes
└── ...
```

### Key Components

#### Services
- `StatisticsService` - Logic untuk statistik dan pelaporan
- `ExportService` - Logic untuk export data umum
- `PuskesmasExportService` - Logic export khusus puskesmas
- `PdfService` - Logic untuk generate PDF

#### Formatters
- `PdfFormatter` - Base formatter untuk PDF
- `PuskesmasPdfFormatter` - Formatter khusus PDF puskesmas
- `AdminAllFormatter` - Formatter untuk data admin

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

Lihat [API Documentation](docs/API_DOCUMENTATION.md) untuk dokumentasi lengkap.

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

Lihat [Deployment Guide](docs/DEPLOYMENT_GUIDE.md) untuk panduan deployment manual yang lengkap.

## 🤝 Contributing

Kami menyambut kontribusi dari developer! Silakan baca [Development Guide](docs/DEVELOPMENT_GUIDE.md) untuk panduan lengkap.

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

*Dikembangkan dengan ❤️ untuk kemajuan sistem kesehatan Indonesia*
