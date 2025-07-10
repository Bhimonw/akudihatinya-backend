# 📋 Standar Penamaan Modular - Akudihatinya Backend

## 🎯 Overview

Dokumen ini mendefinisikan standar penamaan modular untuk memastikan konsistensi, maintainability, dan scalability dalam codebase Akudihatinya Backend.

## 📁 Struktur Direktori dan Penamaan

### 1. **Services** (`app/Services/`)

#### ✅ Penamaan yang Konsisten
```
✅ GOOD:
- StatisticsService.php
- StatisticsDataService.php
- StatisticsCacheService.php
- StatisticsAdminService.php
- StatisticsExportService.php
- RealTimeStatisticsService.php
- OptimizedStatisticsService.php
- DiseaseStatisticsService.php

- ProfileUpdateService.php
- ProfilePictureService.php

- ExportService.php
- PuskesmasExportService.php

- FileUploadService.php
- PdfService.php
- ArchiveService.php
- MonitoringReportService.php
- NewYearSetupService.php
```

#### 🔄 Rekomendasi Reorganisasi Modular

**Struktur Saat Ini:**
```
app/Services/
├── StatisticsService.php
├── StatisticsDataService.php
├── StatisticsCacheService.php
├── StatisticsAdminService.php
├── StatisticsExportService.php
├── RealTimeStatisticsService.php
├── OptimizedStatisticsService.php
├── DiseaseStatisticsService.php
├── ProfileUpdateService.php
├── ProfilePictureService.php
├── ExportService.php
├── PuskesmasExportService.php
├── FileUploadService.php
├── PdfService.php
├── ArchiveService.php
├── MonitoringReportService.php
└── NewYearSetupService.php
```

**Struktur Modular yang Direkomendasikan:**
```
app/Services/
├── Statistics/
│   ├── StatisticsService.php
│   ├── StatisticsDataService.php
│   ├── StatisticsCacheService.php
│   ├── StatisticsAdminService.php
│   ├── RealTimeStatisticsService.php
│   ├── OptimizedStatisticsService.php
│   └── DiseaseStatisticsService.php
├── Export/
│   ├── ExportService.php
│   ├── StatisticsExportService.php
│   ├── PuskesmasExportService.php
│   └── PdfService.php
├── Profile/
│   ├── ProfileUpdateService.php
│   └── ProfilePictureService.php
├── System/
│   ├── ArchiveService.php
│   ├── FileUploadService.php
│   ├── MonitoringReportService.php
│   └── NewYearSetupService.php
└── Core/
    └── BaseService.php (untuk shared functionality)
```

### 2. **Controllers** (`app/Http/Controllers/API/`)

#### ✅ Struktur Modular yang Baik
```
app/Http/Controllers/API/
├── Admin/
│   ├── UserController.php
│   └── YearlyTargetController.php
├── Auth/
│   └── AuthController.php
├── Puskesmas/
│   ├── DmExaminationController.php
│   ├── HtExaminationController.php
│   ├── PatientController.php
│   └── PatientControllerOptimized.php
├── Shared/
│   ├── ProfileController.php
│   ├── StatisticsAdminController.php
│   └── StatisticsController.php
├── System/
└── User/
```

#### 🔄 Rekomendasi Perbaikan

1. **Konsistensi Penamaan Controller:**
   ```
   ✅ GOOD: PatientController.php
   ❌ AVOID: PatientControllerOptimized.php (gunakan versioning atau namespace)
   ```

2. **Struktur yang Disarankan:**
   ```
   app/Http/Controllers/API/
   ├── Admin/
   │   ├── UserManagementController.php
   │   ├── YearlyTargetController.php
   │   └── SystemConfigController.php
   ├── Auth/
   │   ├── AuthController.php
   │   └── PasswordResetController.php
   ├── Puskesmas/
   │   ├── PatientController.php
   │   ├── ExaminationController.php
   │   ├── HtExaminationController.php
   │   └── DmExaminationController.php
   ├── Shared/
   │   ├── ProfileController.php
   │   ├── StatisticsController.php
   │   └── ReportController.php
   └── System/
       ├── HealthCheckController.php
       └── MaintenanceController.php
   ```

### 3. **Commands** (`app/Console/Commands/`)

#### ✅ Penamaan yang Konsisten
```
✅ GOOD:
- ArchiveExaminations.php
- CreateYearlyTargets.php
- PopulateExaminationStats.php
- RebuildStatisticsCache.php
- SetupNewYear.php
- OptimizePerformanceCommand.php
```

#### 🔄 Rekomendasi Modular

**Struktur yang Disarankan:**
```
app/Console/Commands/
├── Archive/
│   └── ArchiveExaminationsCommand.php
├── Statistics/
│   ├── PopulateExaminationStatsCommand.php
│   └── RebuildStatisticsCacheCommand.php
├── System/
│   ├── OptimizePerformanceCommand.php
│   └── SetupNewYearCommand.php
└── Setup/
    └── CreateYearlyTargetsCommand.php
```

### 4. **Middleware** (`app/Http/Middleware/`)

#### ✅ Penamaan yang Baik
```
✅ GOOD:
- AdminOrPuskesmas.php
- CheckUserRole.php
- HasRole.php
- IsAdmin.php
- IsPuskesmas.php
- OptimizedCacheMiddleware.php
- FileUploadRateLimit.php
```

#### 🔄 Rekomendasi Konsistensi

**Standar Penamaan:**
```
app/Http/Middleware/
├── Auth/
│   ├── CheckUserRoleMiddleware.php
│   ├── HasRoleMiddleware.php
│   ├── IsAdminMiddleware.php
│   ├── IsPuskesmasMiddleware.php
│   └── AdminOrPuskesmasMiddleware.php
├── Cache/
│   └── OptimizedCacheMiddleware.php
├── RateLimit/
│   └── FileUploadRateLimitMiddleware.php
└── Security/
    ├── ValidateSignatureMiddleware.php
    └── VerifyCsrfTokenMiddleware.php
```

### 5. **Models** (`app/Models/`)

#### ✅ Penamaan yang Konsisten
```
✅ GOOD:
- Patient.php
- HtExamination.php
- DmExamination.php
- Puskesmas.php
- User.php
- YearlyTarget.php
- MonthlyStatisticsCache.php
- UserRefreshToken.php
```

### 6. **Traits** (`app/Traits/`)

#### ✅ Penamaan yang Baik
```
✅ GOOD:
- HasCommonValidationRules.php
- PercentageCalculationTrait.php
- StatisticsValidationTrait.php
```

#### 🔄 Rekomendasi Modular

**Struktur yang Disarankan:**
```
app/Traits/
├── Validation/
│   ├── HasCommonValidationRules.php
│   └── StatisticsValidationTrait.php
├── Calculation/
│   └── PercentageCalculationTrait.php
└── Utility/
    └── CacheableTrait.php
```

## 📝 Konvensi Penamaan

### 1. **File dan Class Names**

```php
// ✅ GOOD - PascalCase untuk class names
class StatisticsService
class PatientController
class HasRoleMiddleware

// ✅ GOOD - Descriptive dan specific
class RealTimeStatisticsService
class OptimizedCacheMiddleware
class FileUploadRateLimitMiddleware

// ❌ AVOID - Generic atau ambiguous
class Helper
class Utility
class Manager
```

### 2. **Namespace Conventions**

```php
// ✅ GOOD - Modular namespaces
namespace App\Services\Statistics;
namespace App\Services\Export;
namespace App\Http\Controllers\API\Admin;
namespace App\Http\Middleware\Auth;

// ✅ GOOD - Clear hierarchy
namespace App\Traits\Validation;
namespace App\Traits\Calculation;
```

### 3. **Method Names**

```php
// ✅ GOOD - Verb-based, descriptive
public function calculateStatistics()
public function exportToExcel()
public function validateUserRole()
public function cacheStatisticsData()

// ❌ AVOID - Ambiguous
public function process()
public function handle()
public function execute()
```

### 4. **Variable Names**

```php
// ✅ GOOD - Descriptive camelCase
$patientExaminations
$monthlyStatistics
$yearlyTargets
$cacheKey

// ❌ AVOID - Abbreviated atau unclear
$data
$result
$temp
$x
```

## 🔧 Implementation Plan

### Phase 1: Immediate Improvements (Week 1)

1. **Standardize Command Naming:**
   ```bash
   # Rename commands to include "Command" suffix
   ArchiveExaminations.php → ArchiveExaminationsCommand.php
   CreateYearlyTargets.php → CreateYearlyTargetsCommand.php
   SetupNewYear.php → SetupNewYearCommand.php
   ```

2. **Standardize Middleware Naming:**
   ```bash
   # Add "Middleware" suffix for consistency
   AdminOrPuskesmas.php → AdminOrPuskesmasMiddleware.php
   CheckUserRole.php → CheckUserRoleMiddleware.php
   HasRole.php → HasRoleMiddleware.php
   ```

### Phase 2: Modular Reorganization (Week 2-3)

1. **Reorganize Services by Domain:**
   ```bash
   mkdir app/Services/Statistics
   mkdir app/Services/Export
   mkdir app/Services/Profile
   mkdir app/Services/System
   
   # Move files to appropriate directories
   mv app/Services/Statistics*.php app/Services/Statistics/
   mv app/Services/*Export*.php app/Services/Export/
   ```

2. **Update Namespaces:**
   ```php
   // Update all moved files with new namespaces
   namespace App\Services\Statistics;
   namespace App\Services\Export;
   ```

3. **Update Service Provider Registrations:**
   ```php
   // Update all service bindings in providers
   $this->app->bind(
       App\Services\Statistics\StatisticsService::class
   );
   ```

### Phase 3: Documentation and Standards (Week 4)

1. **Create Coding Standards Document**
2. **Update Developer Guide**
3. **Create Migration Guide for existing code**
4. **Setup automated linting rules**

## 🎯 Benefits of Modular Naming

### 1. **Improved Maintainability**
- Easier to locate specific functionality
- Clear separation of concerns
- Reduced cognitive load for developers

### 2. **Better Scalability**
- Easy to add new modules
- Clear extension points
- Reduced coupling between components

### 3. **Enhanced Team Collaboration**
- Consistent expectations
- Easier code reviews
- Faster onboarding for new developers

### 4. **Automated Tooling Support**
- Better IDE navigation
- Improved static analysis
- Enhanced refactoring capabilities

## 📊 Compliance Checklist

### Services
- [ ] All services follow domain-based organization
- [ ] Service names are descriptive and specific
- [ ] Related services are grouped in subdirectories
- [ ] Namespaces reflect directory structure

### Controllers
- [ ] Controllers are organized by user role/access level
- [ ] Controller names follow ResourceController pattern
- [ ] No duplicate or versioned controllers in same directory
- [ ] Clear separation between Admin, Puskesmas, and Shared

### Commands
- [ ] All commands have "Command" suffix
- [ ] Commands are grouped by functionality
- [ ] Command names are action-oriented
- [ ] Proper namespace organization

### Middleware
- [ ] All middleware have "Middleware" suffix
- [ ] Middleware grouped by purpose (Auth, Cache, RateLimit)
- [ ] Descriptive and specific names
- [ ] Consistent naming patterns

### General
- [ ] All classes follow PascalCase convention
- [ ] All methods follow camelCase convention
- [ ] All variables follow camelCase convention
- [ ] Namespaces reflect directory structure
- [ ] No generic or ambiguous names

## 🔄 Continuous Improvement

### Monthly Reviews
- Review new files for naming compliance
- Update standards based on team feedback
- Refactor inconsistent naming patterns

### Automated Checks
- Setup PHPStan rules for naming conventions
- Create custom linting rules
- Integrate with CI/CD pipeline

### Team Training
- Regular workshops on naming conventions
- Code review guidelines
- Pair programming sessions

---

**📞 Support**: Untuk pertanyaan tentang standar penamaan, silakan refer ke dokumentasi ini atau diskusikan dengan tim architecture.

**🔄 Updates**: Dokumen ini akan diupdate seiring dengan evolusi codebase dan feedback dari tim development.