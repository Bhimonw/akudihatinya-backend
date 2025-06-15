# Development Guide - Akudihatinya Backend

Panduan pengembangan untuk kontributor dan developer yang bekerja pada proyek Akudihatinya Backend.

## Table of Contents
1. [Setup Development Environment](#setup-development-environment)
2. [Project Structure](#project-structure)
3. [Coding Standards](#coding-standards)
4. [Database Guidelines](#database-guidelines)
5. [API Development](#api-development)
6. [Testing](#testing)
7. [Git Workflow](#git-workflow)
8. [Performance Guidelines](#performance-guidelines)

## Setup Development Environment

### Prerequisites
> **Note:** Untuk detail lengkap system requirements dan PHP extensions, lihat [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md#prerequisites)

- PHP 8.1+
- Composer 2.x
- MySQL 8.0+ atau MariaDB 10.4+
- Node.js 16+
- Git
- IDE (VS Code, PhpStorm, dll.)

### Quick Development Setup
```bash
# Clone repository
git clone https://github.com/your-repo/akudihatinya-backend.git
cd akudihatinya-backend

# Install dependencies
composer install
npm install

# Setup environment (lihat DEPLOYMENT_GUIDE.md untuk konfigurasi lengkap)
cp .env.example .env
php artisan key:generate

# Setup database
php artisan migrate
php artisan db:seed

# Generate IDE helpers (development only)
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta

# Start development server
php artisan serve
```

### Recommended VS Code Extensions
```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "bradlc.vscode-tailwindcss",
    "ms-vscode.vscode-json",
    "esbenp.prettier-vscode",
    "ryannaddy.laravel-artisan",
    "onecentlin.laravel-blade",
    "codingyu.laravel-goto-view",
    "amiralizadeh9480.laravel-extra-intellisense"
  ]
}
```

## Project Structure

```
akudihatinya-backend/
├── app/
│   ├── Console/           # Artisan commands
│   ├── Exceptions/        # Exception handlers
│   ├── Formatters/        # Data formatters
│   │   ├── AdminAllFormatter.php
│   │   ├── PdfFormatter.php
│   │   ├── PuskesmasPdfFormatter.php
│   │   └── ...
│   ├── Helpers/           # Helper classes
│   ├── Http/
│   │   ├── Controllers/   # API controllers
│   │   ├── Middleware/    # Custom middleware
│   │   ├── Requests/      # Form requests
│   │   └── Resources/     # API resources
│   ├── Models/            # Eloquent models
│   ├── Observers/         # Model observers
│   ├── Providers/         # Service providers
│   ├── Repositories/      # Repository pattern
│   └── Services/          # Business logic services
├── config/                # Configuration files
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/          # Database seeders
├── docs/                  # Documentation
├── resources/
│   ├── excel/            # Excel templates
│   └── pdf/              # PDF templates
├── routes/
│   └── api/              # API routes
├── storage/              # Storage files
└── tests/                # Test files
```

### Key Directories Explained

#### `app/Formatters/`
Berisi class untuk memformat data sebelum dikirim ke view atau export:
- `PdfFormatter.php` - Base formatter untuk PDF
- `PuskesmasPdfFormatter.php` - Formatter khusus untuk PDF puskesmas
- `AdminAllFormatter.php` - Formatter untuk data admin

#### `app/Services/`
Berisi business logic yang dapat digunakan kembali:
- `StatisticsService.php` - Logic untuk statistik
- `ExportService.php` - Logic untuk export data
- `PdfService.php` - Logic untuk generate PDF
- `PuskesmasExportService.php` - Logic export khusus puskesmas

#### `app/Repositories/`
Implementasi Repository Pattern untuk akses data:
- `PuskesmasRepository.php` - Repository untuk data puskesmas
- `YearlyTargetRepository.php` - Repository untuk target tahunan

## Coding Standards

### PHP Standards
Mengikuti PSR-12 coding standard:

```php
<?php

namespace App\Services;

use App\Models\Puskesmas;
use Illuminate\Support\Collection;

class ExampleService
{
    private PuskesmasRepository $puskesmasRepository;

    public function __construct(PuskesmasRepository $puskesmasRepository)
    {
        $this->puskesmasRepository = $puskesmasRepository;
    }

    /**
     * Get statistics data for given parameters.
     */
    public function getStatistics(int $year, ?int $month = null): Collection
    {
        // Implementation
    }
}
```

### Naming Conventions

#### Classes
- Controllers: `PascalCase` + `Controller` suffix
- Models: `PascalCase` (singular)
- Services: `PascalCase` + `Service` suffix
- Repositories: `PascalCase` + `Repository` suffix

#### Methods
- `camelCase`
- Descriptive names: `getUserStatistics()`, `exportToPdf()`

#### Variables
- `camelCase`
- Descriptive names: `$puskesmasData`, `$yearlyTarget`

#### Database
- Tables: `snake_case` (plural): `ht_examinations`, `yearly_targets`
- Columns: `snake_case`: `created_at`, `puskesmas_id`

### Documentation
Semua public methods harus memiliki PHPDoc:

```php
/**
 * Export puskesmas statistics to PDF.
 *
 * @param string $diseaseType Type of disease (ht|dm)
 * @param int $year Year for the report
 * @param int $puskesmasId Puskesmas ID
 * @return \Illuminate\Http\Response
 * @throws \Exception When PDF generation fails
 */
public function exportPuskesmasPdf(string $diseaseType, int $year, int $puskesmasId): Response
{
    // Implementation
}
```

## Database Guidelines

### Migration Best Practices

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ht_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->date('examination_date');
            $table->decimal('systolic_pressure', 5, 2);
            $table->decimal('diastolic_pressure', 5, 2);
            $table->boolean('is_standard')->default(false);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['patient_id', 'examination_date']);
            $table->index('examination_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ht_examinations');
    }
};
```

### Model Best Practices

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'medical_record_number',
        'birth_date',
        'gender',
        'address',
        'phone',
        'puskesmas_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    // Relationships
    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function htExaminations(): HasMany
    {
        return $this->hasMany(HtExamination::class);
    }

    public function dmExaminations(): HasMany
    {
        return $this->hasMany(DmExamination::class);
    }

    // Scopes
    public function scopeByPuskesmas($query, int $puskesmasId)
    {
        return $query->where('puskesmas_id', $puskesmasId);
    }

    // Accessors
    public function getAgeAttribute(): int
    {
        return $this->birth_date->age;
    }
}
```

## API Development

### Controller Structure

```php
<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StatisticsController extends Controller
{
    public function __construct(
        private StatisticsService $statisticsService
    ) {}

    /**
     * Export statistics data.
     */
    public function exportStatistics(ExportRequest $request): Response
    {
        try {
            $data = $this->statisticsService->getExportData(
                $request->validated()
            );

            return $this->statisticsService->export($data, $request->format);
        } catch (\Exception $e) {
            \Log::error('Export failed', [
                'error' => $e->getMessage(),
                'params' => $request->validated()
            ]);

            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

### Request Validation

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'required|in:ht,dm,all',
            'format' => 'required|in:pdf,excel',
            'puskesmas_id' => 'nullable|exists:puskesmas,id',
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'Tahun harus diisi',
            'disease_type.in' => 'Jenis penyakit tidak valid',
            'format.in' => 'Format export tidak valid',
        ];
    }
}
```

### API Response Format

```php
// Success Response
{
    "data": {
        "statistics": [...],
        "meta": {
            "total": 100,
            "page": 1,
            "per_page": 15
        }
    }
}

// Error Response
{
    "error": "Error message",
    "message": "Detailed error description",
    "code": "ERROR_CODE"
}
```

## Testing

### Unit Tests

```php
<?php

namespace Tests\Unit\Services;

use App\Services\StatisticsService;
use App\Models\Puskesmas;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StatisticsService::class);
    }

    public function test_can_get_statistics_for_year(): void
    {
        // Arrange
        $puskesmas = Puskesmas::factory()->create();
        $year = 2024;

        // Act
        $result = $this->service->getYearlyStatistics($puskesmas->id, $year);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_patients', $result);
    }
}
```

### Feature Tests

```php
<?php

namespace Tests\Feature\API;

use App\Models\User;
use App\Models\Puskesmas;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StatisticsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_statistics(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $puskesmas = Puskesmas::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->getJson('/api/statistics/export?year=2024&format=excel');

        // Assert
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_puskesmas_user_cannot_access_admin_endpoints(): void
    {
        // Arrange
        $user = User::factory()->puskesmas()->create();

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/admin/users');

        // Assert
        $response->assertStatus(403);
    }
}
```

### Running Tests

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

## Git Workflow

### Branch Naming
- `feature/feature-name` - New features
- `bugfix/bug-description` - Bug fixes
- `hotfix/critical-fix` - Critical fixes
- `refactor/component-name` - Code refactoring

### Commit Messages
Menggunakan Conventional Commits:

```
feat: add puskesmas PDF export functionality
fix: resolve database connection timeout
refactor: optimize statistics query performance
docs: update API documentation
test: add unit tests for export service
```

### Pull Request Process
1. Create feature branch from `develop`
2. Implement changes with tests
3. Update documentation if needed
4. Create PR to `develop`
5. Code review and approval
6. Merge to `develop`
7. Deploy to staging for testing
8. Merge to `main` for production

## Performance Guidelines

### Database Optimization

```php
// Good: Use eager loading
$patients = Patient::with(['puskesmas', 'htExaminations'])
    ->where('puskesmas_id', $puskesmasId)
    ->get();

// Bad: N+1 query problem
$patients = Patient::where('puskesmas_id', $puskesmasId)->get();
foreach ($patients as $patient) {
    echo $patient->puskesmas->name; // N+1 queries
}

// Good: Use chunking for large datasets
Patient::chunk(1000, function ($patients) {
    foreach ($patients as $patient) {
        // Process patient
    }
});

// Good: Use database indexes
Schema::table('ht_examinations', function (Blueprint $table) {
    $table->index(['patient_id', 'examination_date']);
});
```

### Caching

```php
// Cache expensive queries
public function getStatistics(int $year): array
{
    return Cache::remember(
        "statistics.{$year}",
        now()->addHours(6),
        fn() => $this->calculateStatistics($year)
    );
}

// Cache with tags for easy invalidation
Cache::tags(['statistics', 'puskesmas'])
    ->remember('puskesmas.statistics', 3600, function () {
        return $this->getPuskesmasStatistics();
    });

// Invalidate cache when data changes
Cache::tags(['statistics'])->flush();
```

### Query Optimization

```php
// Good: Select only needed columns
$users = User::select(['id', 'name', 'email'])
    ->where('active', true)
    ->get();

// Good: Use database aggregations
$totalPatients = Patient::where('puskesmas_id', $id)->count();

// Good: Use exists() for checking existence
if (Patient::where('medical_record_number', $number)->exists()) {
    // Handle duplicate
}
```

## Code Quality Tools

### PHP CS Fixer
```bash
# Install
composer require --dev friendsofphp/php-cs-fixer

# Run
vendor/bin/php-cs-fixer fix
```

### PHPStan
```bash
# Install
composer require --dev phpstan/phpstan

# Run
vendor/bin/phpstan analyse
```

### Larastan
```bash
# Install
composer require --dev nunomaduro/larastan

# Run
vendor/bin/phpstan analyse --memory-limit=2G
```

## Debugging

### Laravel Telescope
```bash
# Install (development only)
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Debug Bar
```bash
# Install (development only)
composer require barryvdh/laravel-debugbar --dev
```

### Logging
```php
// Use structured logging
\Log::info('Export completed', [
    'user_id' => auth()->id(),
    'export_type' => 'pdf',
    'parameters' => $request->validated(),
    'execution_time' => $executionTime
]);

// Use different log levels
\Log::debug('Debug information');
\Log::info('General information');
\Log::warning('Warning message');
\Log::error('Error occurred', ['exception' => $e]);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow coding standards
4. Write tests for new functionality
5. Update documentation
6. Submit a pull request

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [PHP The Right Way](https://phptherightway.com/)
- [PSR Standards](https://www.php-fig.org/psr/)
- [Laravel Best Practices](https://github.com/alexeymezenin/laravel-best-practices)