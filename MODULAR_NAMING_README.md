# 🏗️ Standar Penamaan Modular - Akudihatinya Backend

[![Naming Standards](https://img.shields.io/badge/Naming%20Standards-Compliant-green.svg)](./MODULAR_NAMING_STANDARDS.md)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-Automated-blue.svg)](./.github/workflows/naming-standards-validation.yml)
[![Documentation](https://img.shields.io/badge/Documentation-Complete-brightgreen.svg)](./MODULAR_NAMING_IMPLEMENTATION.md)

## 📋 Overview

Sistem standar penamaan modular yang komprehensif untuk backend Akudihatinya, dirancang untuk meningkatkan konsistensi, maintainability, dan developer experience.

## 🎯 Tujuan

- ✅ **Konsistensi**: Standar penamaan yang seragam di seluruh codebase
- ✅ **Organisasi**: Struktur direktori yang logis dan mudah dipahami
- ✅ **Maintainability**: Kode yang lebih mudah dipelihara dan dikembangkan
- ✅ **Automation**: Validasi otomatis untuk mencegah pelanggaran standar
- ✅ **Developer Experience**: Onboarding yang lebih cepat untuk developer baru

## 🛠️ Komponen Utama

### 📚 Dokumentasi

| File | Deskripsi | Status |
|------|-----------|--------|
| [`MODULAR_NAMING_STANDARDS.md`](./MODULAR_NAMING_STANDARDS.md) | Standar penamaan lengkap dan panduan implementasi | ✅ Complete |
| [`MODULAR_NAMING_IMPLEMENTATION.md`](./MODULAR_NAMING_IMPLEMENTATION.md) | Panduan implementasi detail dan troubleshooting | ✅ Complete |
| [`MODULAR_NAMING_README.md`](./MODULAR_NAMING_README.md) | Overview dan quick start guide | ✅ Complete |

### 🔧 Tools dan Scripts

| Tool | Lokasi | Fungsi | Status |
|------|--------|--------|--------|
| **Validation Command** | `app/Console/Commands/ValidateNamingStandardsCommand.php` | Validasi standar penamaan via Artisan | ✅ Ready |
| **Migration Script** | `migrate-modular-naming.php` | Migrasi otomatis struktur file | ✅ Ready |
| **Directory Analyzer** | `scripts/analyze-directory-structure.php` | Analisis struktur direktori | ✅ Ready |
| **PHPStan Rules** | `phpstan-naming-rules.neon` | Validasi dengan PHPStan | ✅ Ready |

### 🚀 CI/CD Integration

| Component | Lokasi | Fungsi | Status |
|-----------|--------|--------|--------|
| **GitHub Workflow** | `.github/workflows/naming-standards-validation.yml` | Validasi otomatis di CI/CD | ✅ Ready |
| **Auto-fix PR** | Included in workflow | Otomatis buat PR dengan perbaikan | ✅ Ready |
| **Report Generation** | Included in workflow | Generate laporan untuk PR | ✅ Ready |

## 🚀 Quick Start

### 1. Validasi Kondisi Saat Ini

```bash
# Analisis struktur direktori
php scripts/analyze-directory-structure.php

# Validasi standar penamaan
php artisan naming:validate --report
```

### 2. Preview Perubahan

```bash
# Dry run migrasi (preview saja)
php migrate-modular-naming.php --dry-run
```

### 3. Backup dan Migrasi

```bash
# Backup kode
git checkout -b backup-before-naming-migration
git add . && git commit -m "Backup before naming migration"

# Eksekusi migrasi
php migrate-modular-naming.php --execute
```

### 4. Validasi dan Perbaikan

```bash
# Auto-fix pelanggaran yang bisa diperbaiki
php artisan naming:validate --fix

# Validasi final
php artisan naming:validate --strict
```

## 📊 Struktur Direktori Target

### Services (Domain-based Organization)
```
app/Services/
├── Statistics/          # Layanan statistik dan perhitungan
├── Export/             # Layanan export dan reporting
├── Profile/            # Layanan profil pengguna
├── System/             # Layanan sistem dan konfigurasi
└── Core/               # Layanan inti dan base classes
```

### Controllers (Feature-based Organization)
```
app/Http/Controllers/API/
├── Admin/              # Controller untuk admin
├── Auth/               # Controller autentikasi
├── Puskesmas/          # Controller data puskesmas
├── Shared/             # Controller bersama
├── System/             # Controller sistem
└── User/               # Controller pengguna
```

### Middleware (Purpose-based Organization)
```
app/Http/Middleware/
├── Auth/               # Middleware autentikasi
├── Cache/              # Middleware caching
├── RateLimit/          # Middleware rate limiting
└── Security/           # Middleware keamanan
```

### Commands (Function-based Organization)
```
app/Console/Commands/
├── Archive/            # Command untuk archiving
├── Statistics/         # Command untuk statistik
├── System/             # Command sistem dan maintenance
└── Setup/              # Command setup dan konfigurasi
```

## 🔍 Validation Commands

### Basic Validation
```bash
# Validasi dasar
php artisan naming:validate

# Validasi dengan mode strict
php artisan naming:validate --strict

# Validasi path tertentu
php artisan naming:validate --path=app/Services
```

### Advanced Options
```bash
# Auto-fix pelanggaran
php artisan naming:validate --fix

# Generate laporan detail
php artisan naming:validate --report

# Kombinasi options
php artisan naming:validate --strict --report --fix
```

### Migration Commands
```bash
# Preview perubahan
php migrate-modular-naming.php --dry-run

# Eksekusi migrasi
php migrate-modular-naming.php --execute

# Generate rollback script
php migrate-modular-naming.php --rollback
```

### Analysis Commands
```bash
# Analisis struktur
php scripts/analyze-directory-structure.php

# Export hasil ke JSON
php scripts/analyze-directory-structure.php --export analysis.json
```

## 📈 Monitoring dan Metrics

### Key Performance Indicators

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| **Organization Score** | >90% | TBD | 🔄 Measuring |
| **Naming Violations** | 0 | TBD | 🔄 Measuring |
| **Directory Compliance** | 100% | TBD | 🔄 Measuring |
| **CI/CD Success Rate** | >95% | TBD | 🔄 Measuring |

### Automated Monitoring

- **GitHub Workflow**: Runs on every push and PR
- **Weekly Reports**: Automated analysis setiap minggu
- **PR Comments**: Otomatis comment hasil validasi di PR
- **Auto-fix PRs**: Otomatis buat PR dengan perbaikan

## 🔧 Configuration

### Environment Setup

```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Install PHPStan (optional)
composer require --dev phpstan/phpstan
```

### PHPStan Integration

```bash
# Run PHPStan with naming rules
vendor/bin/phpstan analyse --configuration=phpstan-naming-rules.neon

# Generate baseline
vendor/bin/phpstan analyse --generate-baseline
```

## 🚨 Troubleshooting

### Common Issues

#### 1. Import Statements Broken
```bash
composer dump-autoload
php artisan config:clear
php artisan route:clear
```

#### 2. Service Provider Issues
- Check `config/app.php` untuk registrations
- Update namespace di service providers
- Update binding di containers

#### 3. Route Definitions
- Update controller references
- Update middleware references
- Test semua endpoints

### Rollback Procedure

```bash
# Git rollback
git checkout backup-before-naming-migration

# Script rollback
php rollback-naming-migration.php

# Clear caches
composer dump-autoload
php artisan config:clear
```

## 📚 Documentation Links

### Core Documentation
- 📖 [**Naming Standards**](./MODULAR_NAMING_STANDARDS.md) - Standar penamaan lengkap
- 🛠️ [**Implementation Guide**](./MODULAR_NAMING_IMPLEMENTATION.md) - Panduan implementasi detail
- 🚀 [**Performance Optimization**](./OPTIMIZATION_README.md) - Overview optimisasi performa

### Technical Documentation
- 🔧 [**Validation Command**](./app/Console/Commands/ValidateNamingStandardsCommand.php) - Source code command validasi
- 📦 [**Migration Script**](./migrate-modular-naming.php) - Source code script migrasi
- 📊 [**Directory Analyzer**](./scripts/analyze-directory-structure.php) - Source code analyzer
- ⚙️ [**PHPStan Rules**](./phpstan-naming-rules.neon) - Konfigurasi PHPStan

### CI/CD Documentation
- 🔄 [**GitHub Workflow**](./.github/workflows/naming-standards-validation.yml) - CI/CD configuration

## 🎯 Implementation Roadmap

### ✅ Phase 1: Foundation (Completed)
- [x] Create naming standards documentation
- [x] Develop validation tools
- [x] Setup CI/CD integration
- [x] Create migration scripts

### 🔄 Phase 2: Migration (In Progress)
- [ ] Analyze current codebase
- [ ] Execute gradual migration
- [ ] Fix validation violations
- [ ] Update documentation

### 📋 Phase 3: Optimization (Planned)
- [ ] Fine-tune validation rules
- [ ] Optimize CI/CD performance
- [ ] Add IDE integrations
- [ ] Create training materials

### 📋 Phase 4: Maintenance (Ongoing)
- [ ] Regular monitoring
- [ ] Rule updates
- [ ] Team training
- [ ] Continuous improvement

## 🤝 Contributing

### Reporting Issues
1. Check existing issues di GitHub
2. Create new issue dengan template yang sesuai
3. Provide detailed reproduction steps

### Suggesting Improvements
1. Fork repository
2. Create feature branch
3. Implement changes
4. Submit pull request

### Code Review Guidelines
- Ensure all naming standards are followed
- Run validation tools before submitting
- Update documentation if needed
- Add tests for new features

## 📞 Support

### Getting Help
- 📖 **Documentation**: Check documentation files first
- 🐛 **Issues**: Create GitHub issue for bugs
- 💡 **Features**: Submit feature requests via GitHub
- 💬 **Discussion**: Use team communication channels

### Team Contacts
- **Tech Lead**: For architectural decisions
- **DevOps**: For CI/CD issues
- **QA**: For testing and validation

---

## 📊 Status Dashboard

| Component | Status | Last Updated | Next Review |
|-----------|--------|--------------|-------------|
| **Documentation** | ✅ Complete | 2025-01-20 | 2025-02-20 |
| **Validation Tools** | ✅ Ready | 2025-01-20 | 2025-02-20 |
| **CI/CD Integration** | ✅ Active | 2025-01-20 | 2025-02-20 |
| **Migration Scripts** | ✅ Ready | 2025-01-20 | 2025-02-20 |
| **Team Training** | 📋 Planned | - | 2025-01-25 |

---

*Untuk informasi lebih detail, silakan merujuk ke dokumentasi lengkap di [MODULAR_NAMING_IMPLEMENTATION.md](./MODULAR_NAMING_IMPLEMENTATION.md)*

**Last Updated**: January 20, 2025  
**Version**: 1.0.0  
**Maintainer**: Development Team