# 🛠️ Developer Guide

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
</p>

<h3>👨‍💻 Complete Development & Deployment Guide</h3>
<p><em>Everything you need to develop, deploy, and contribute to Akudihatinya Backend</em></p>

---

## 📋 Table of Contents

1. [Getting Started](#getting-started)
2. [Development Environment Setup](#development-environment-setup)
3. [Project Structure](#project-structure)
4. [Coding Standards](#coding-standards)
5. [Development Workflow](#development-workflow)
6. [Testing Guidelines](#testing-guidelines)
7. [Deployment Guide](#deployment-guide)
8. [Contributing Guidelines](#contributing-guidelines)
9. [Performance Guidelines](#performance-guidelines)
10. [Troubleshooting](#troubleshooting)

---

## 🚀 Getting Started

### Prerequisites

#### System Requirements
- **PHP**: 8.1 atau lebih tinggi
- **Composer**: 2.x
- **Database**: MySQL 8.0+ atau MariaDB 10.4+
- **Node.js**: 16+ (untuk asset compilation)
- **Web Server**: Apache/Nginx (untuk production)
- **Git**: Latest version
- **IDE**: VS Code, PhpStorm, atau IDE lainnya

#### PHP Extensions
Pastikan extension berikut terinstall:
```
- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- PDO_MySQL
- Tokenizer
- XML
- GD (untuk PDF generation)
- Zip
- Redis (optional, untuk caching)
```

### Quick Start

```bash
# 1. Clone repository
git clone https://github.com/Bhimonw/akudihatinya-backend.git
cd akudihatinya-backend

# 2. Install dependencies
composer install
npm install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Configure database (edit .env file)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=akudihatinya
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Setup database
php artisan migrate
php artisan db:seed

# 6. Start development server
php artisan serve
```

---

## 🔧 Development Environment Setup

### Environment Configuration

#### .env File Setup
```env
# Application
APP_NAME="Akudihatinya Backend"
APP_ENV=local
APP_KEY=base64:YOUR_APP_KEY
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akudihatinya
DB_USERNAME=root
DB_PASSWORD=

# Cache & Session
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Redis (optional)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1
```

### Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE akudihatinya;
EXIT;

# Run migrations
php artisan migrate

# Seed database with sample data
php artisan db:seed

# Reset database (if needed)
php artisan migrate:fresh --seed
```

### Development Tools

#### IDE Helpers (Optional)
```bash
# Generate IDE helpers for better autocomplete
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta
```

#### Debugging Tools
```bash
# Install Laravel Telescope (for debugging)
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

---

## 📁 Project Structure

```
akudihatinya-backend/
├── app/
│   ├── Console/
│   │   ├── Commands/           # Custom Artisan commands
│   │   └── Kernel.php         # Command scheduling
│   ├── Http/
│   │   ├── Controllers/       # API controllers
│   │   ├── Middleware/        # Custom middleware
│   │   ├── Requests/          # Form request validation
│   │   └── Resources/         # API resources
│   ├── Models/                # Eloquent models
│   ├── Repositories/          # Repository pattern
│   ├── Services/              # Business logic
│   └── Formatters/            # Data formatters
├── config/                    # Configuration files
├── database/
│   ├── migrations/            # Database migrations
│   ├── seeders/               # Database seeders
│   └── factories/             # Model factories
├── docs/                      # Documentation
├── routes/
│   ├── api.php               # API routes
│   ├── console.php           # Console routes
│   └── web.php               # Web routes
├── storage/                   # File storage
├── tests/                     # Test files
└── vendor/                    # Composer dependencies
```

### Key Directories

#### Controllers (`app/Http/Controllers/`)
- **Admin/**: Admin-specific controllers
- **Puskesmas/**: Puskesmas user controllers
- **Statistics/**: Statistics and reporting controllers
- **Auth/**: Authentication controllers

#### Models (`app/Models/`)
- **User.php**: User authentication model
- **Puskesmas.php**: Puskesmas management
- **Patient.php**: Patient data
- **HTExamination.php**: Hypertension examinations
- **DMExamination.php**: Diabetes examinations
- **YearlyTarget.php**: Yearly targets
- **MonthlyStatistic.php**: Monthly statistics

#### Services (`app/Services/`)
- **StatisticsService.php**: Statistics calculations
- **ArchiveService.php**: Data archiving
- **NewYearSetupService.php**: New year setup
- **ExaminationService.php**: Examination processing

---

## 📝 Coding Standards

### PHP Standards

#### PSR Standards
- Follow **PSR-1** (Basic Coding Standard)
- Follow **PSR-4** (Autoloading Standard)
- Follow **PSR-12** (Extended Coding Style)

#### Laravel Conventions
```php
// Model naming (singular, PascalCase)
class Patient extends Model

// Controller naming (PascalCase + Controller suffix)
class PatientController extends Controller

// Method naming (camelCase)
public function createPatient()

// Variable naming (camelCase)
$patientData = [];

// Constant naming (UPPER_SNAKE_CASE)
const MAX_PATIENTS = 1000;

// Database table naming (plural, snake_case)
'patients', 'ht_examinations', 'yearly_targets'

// Migration naming (descriptive)
2024_01_01_000000_create_patients_table.php
```

#### Code Documentation
```php
/**
 * Create a new patient record
 *
 * @param array $data Patient data
 * @return Patient Created patient instance
 * @throws ValidationException When validation fails
 */
public function createPatient(array $data): Patient
{
    // Implementation
}
```

### Database Guidelines

#### Migration Best Practices
```php
// Use descriptive column names
$table->string('patient_name');
$table->date('examination_date');
$table->enum('control_status', ['controlled', 'uncontrolled']);

// Add indexes for performance
$table->index(['puskesmas_id', 'examination_date']);
$table->unique(['patient_id', 'examination_date']);

// Use foreign key constraints
$table->foreignId('puskesmas_id')->constrained();
```

#### Model Relationships
```php
// Use proper relationship methods
public function puskesmas(): BelongsTo
{
    return $this->belongsTo(Puskesmas::class);
}

public function patients(): HasMany
{
    return $this->hasMany(Patient::class);
}
```

### API Development

#### Controller Structure
```php
class PatientController extends Controller
{
    public function __construct(
        private PatientService $patientService
    ) {}

    public function index(Request $request)
    {
        $patients = $this->patientService->getPaginated(
            $request->get('search'),
            $request->get('per_page', 10)
        );

        return PatientResource::collection($patients);
    }

    public function store(PatientRequest $request)
    {
        $patient = $this->patientService->create($request->validated());

        return new PatientResource($patient);
    }
}
```

#### Request Validation
```php
class PatientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'nik' => 'required|string|size:16|unique:patients',
            'birth_date' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
        ];
    }

    public function messages(): array
    {
        return [
            'nik.unique' => 'NIK sudah terdaftar dalam sistem.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
        ];
    }
}
```

#### API Resources
```php
class PatientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nik' => $this->nik,
            'age' => $this->getAge(),
            'gender' => $this->gender,
            'puskesmas' => new PuskesmasResource($this->whenLoaded('puskesmas')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

---

## 🔄 Development Workflow

### Git Workflow

#### Branch Naming
```bash
# Feature branches
feature/patient-management
feature/statistics-dashboard

# Bug fix branches
bugfix/login-validation
bugfix/statistics-calculation

# Hotfix branches
hotfix/security-patch
hotfix/critical-bug
```

#### Commit Messages
```bash
# Format: type(scope): description
feat(patient): add patient registration form
fix(auth): resolve login validation issue
docs(api): update endpoint documentation
refactor(service): improve statistics calculation
test(patient): add patient creation tests
```

#### Development Process
```bash
# 1. Create feature branch
git checkout -b feature/new-feature

# 2. Make changes and commit
git add .
git commit -m "feat(scope): add new feature"

# 3. Push branch
git push origin feature/new-feature

# 4. Create pull request
# 5. Code review
# 6. Merge to main
```

### Code Review Checklist

- [ ] Code follows PSR standards
- [ ] Proper error handling
- [ ] Input validation implemented
- [ ] Database queries optimized
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] No sensitive data exposed
- [ ] Performance considerations addressed

---

## 🧪 Testing Guidelines

### Test Structure

```php
// Feature Test Example
class PatientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_patient()
    {
        $user = User::factory()->create(['role' => 'puskesmas']);
        $patientData = [
            'name' => 'John Doe',
            'nik' => '1234567890123456',
            'birth_date' => '1980-01-01',
            'gender' => 'male',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/puskesmas/patients', $patientData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'nik']
            ]);

        $this->assertDatabaseHas('patients', [
            'nik' => '1234567890123456'
        ]);
    }
}
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/PatientManagementTest.php

# Run with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

---

## 🚀 Deployment Guide

### Production Environment Setup

#### Server Requirements
- **OS**: Ubuntu 20.04+ atau CentOS 8+
- **Web Server**: Nginx atau Apache
- **PHP**: 8.1+ dengan required extensions
- **Database**: MySQL 8.0+ atau MariaDB 10.4+
- **Memory**: Minimum 2GB RAM
- **Storage**: Minimum 10GB SSD

#### Installation Steps

```bash
# 1. Clone repository
git clone https://github.com/Bhimonw/akudihatinya-backend.git
cd akudihatinya-backend

# 2. Install dependencies (production)
composer install --optimize-autoloader --no-dev

# 3. Setup environment
cp .env.example .env
# Edit .env with production values

# 4. Generate application key
php artisan key:generate

# 5. Setup database
php artisan migrate --force
php artisan db:seed --force

# 6. Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### Production .env Configuration

```env
# Application
APP_NAME="Akudihatinya Backend"
APP_ENV=production
APP_KEY=base64:YOUR_PRODUCTION_KEY
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=akudihatinya_prod
DB_USERNAME=akudihatinya_user
DB_PASSWORD=secure_password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/akudihatinya-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### SSL Configuration (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Generate SSL certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Deployment Automation

#### Deployment Script

```bash
#!/bin/bash
# deploy.sh

set -e

echo "Starting deployment..."

# Pull latest changes
git pull origin main

# Install/update dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl reload nginx
sudo systemctl restart php8.1-fpm

echo "Deployment completed successfully!"
```

#### GitHub Actions CI/CD

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        
    - name: Install dependencies
      run: composer install --optimize-autoloader --no-dev
      
    - name: Run tests
      run: php artisan test
      
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.KEY }}
        script: |
          cd /var/www/akudihatinya-backend
          ./deploy.sh
```

---

## 🤝 Contributing Guidelines

### Code of Conduct

#### Our Standards

**Perilaku yang Diharapkan:**
- Menggunakan bahasa yang ramah dan inklusif
- Menghormati sudut pandang dan pengalaman yang berbeda
- Menerima kritik konstruktif dengan baik
- Fokus pada apa yang terbaik untuk komunitas
- Menunjukkan empati terhadap anggota komunitas lainnya

**Perilaku yang Tidak Dapat Diterima:**
- Penggunaan bahasa atau gambar yang bersifat seksual
- Trolling, komentar yang menghina, atau serangan personal/politik
- Harassment publik atau privat
- Mempublikasikan informasi pribadi orang lain tanpa izin
- Perilaku lain yang tidak pantas dalam lingkungan profesional

### Contribution Process

#### 1. Fork Repository
```bash
# Fork di GitHub, kemudian clone
git clone https://github.com/YOUR_USERNAME/akudihatinya-backend.git
cd akudihatinya-backend

# Add upstream remote
git remote add upstream https://github.com/Bhimonw/akudihatinya-backend.git
```

#### 2. Setup Development Environment
```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database
php artisan migrate
php artisan db:seed
```

#### 3. Create Feature Branch
```bash
# Create and switch to feature branch
git checkout -b feature/your-feature-name

# Keep your fork updated
git fetch upstream
git rebase upstream/main
```

#### 4. Make Changes
- Write clean, documented code
- Follow coding standards
- Add tests for new features
- Update documentation if needed

#### 5. Test Your Changes
```bash
# Run tests
php artisan test

# Check code style
./vendor/bin/phpcs

# Fix code style
./vendor/bin/phpcbf
```

#### 6. Commit Changes
```bash
# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat(scope): add new feature description"
```

#### 7. Push and Create Pull Request
```bash
# Push to your fork
git push origin feature/your-feature-name

# Create pull request on GitHub
```

### Pull Request Guidelines

#### PR Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass
- [ ] New tests added
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes
```

#### Review Process
1. **Automated Checks**: CI/CD pipeline runs tests
2. **Code Review**: Maintainers review code quality
3. **Testing**: Manual testing if needed
4. **Approval**: At least one maintainer approval required
5. **Merge**: Squash and merge to main branch

### Issue Reporting

#### Bug Report Template
```markdown
## Bug Description
Clear description of the bug

## Steps to Reproduce
1. Go to '...'
2. Click on '...'
3. See error

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Environment
- OS: [e.g. Ubuntu 20.04]
- PHP Version: [e.g. 8.1.0]
- Laravel Version: [e.g. 10.x]
```

#### Feature Request Template
```markdown
## Feature Description
Clear description of the feature

## Use Case
Why is this feature needed?

## Proposed Solution
How should this be implemented?

## Alternatives
Other solutions considered
```

---

## ⚡ Performance Guidelines

### Database Optimization

#### Query Optimization
```php
// Use eager loading to prevent N+1 queries
$patients = Patient::with(['puskesmas', 'htExaminations'])->get();

// Use select to limit columns
$patients = Patient::select(['id', 'name', 'nik'])->get();

// Use pagination for large datasets
$patients = Patient::paginate(10);

// Use database indexes
Schema::table('patients', function (Blueprint $table) {
    $table->index(['puskesmas_id', 'created_at']);
});
```

#### Caching Strategies
```php
// Cache expensive queries
$statistics = Cache::remember('monthly_statistics', 3600, function () {
    return MonthlyStatistic::calculateForCurrentMonth();
});

// Use Redis for session and cache
// In .env:
// CACHE_DRIVER=redis
// SESSION_DRIVER=redis
```

### API Performance

#### Response Optimization
```php
// Use API resources for consistent responses
return PatientResource::collection($patients);

// Implement pagination
return $this->paginate($request->get('per_page', 10));

// Use HTTP caching headers
return response()->json($data)
    ->header('Cache-Control', 'public, max-age=3600');
```

#### Rate Limiting
```php
// In RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

### Monitoring

#### Application Monitoring
```php
// Use Laravel Telescope for debugging
composer require laravel/telescope --dev

// Use Laravel Horizon for queue monitoring
composer require laravel/horizon

// Log performance metrics
Log::info('Statistics calculation completed', [
    'execution_time' => $executionTime,
    'memory_usage' => memory_get_peak_usage(true)
]);
```

---

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Issues
```bash
# Check database connection
php artisan tinker
> DB::connection()->getPdo();

# Clear config cache
php artisan config:clear

# Check database credentials in .env
```

#### Permission Issues
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### Memory Issues
```bash
# Increase memory limit in php.ini
memory_limit = 512M

# Or set in .env
PHP_MEMORY_LIMIT=512M

# Optimize Composer autoloader
composer dump-autoload --optimize
```

#### Queue Issues
```bash
# Check queue status
php artisan queue:work --verbose

# Clear failed jobs
php artisan queue:flush

# Restart queue workers
php artisan queue:restart
```

### Debug Tools

#### Laravel Telescope
```bash
# Install Telescope
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

# Access at /telescope
```

#### Debug Bar
```bash
# Install Debug Bar
composer require barryvdh/laravel-debugbar --dev

# Publish config
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"
```

#### Logging
```php
// Use different log levels
Log::emergency('System is down');
Log::alert('Action must be taken immediately');
Log::critical('Critical conditions');
Log::error('Error conditions');
Log::warning('Warning conditions');
Log::notice('Normal but significant condition');
Log::info('Informational messages');
Log::debug('Debug-level messages');

// Log with context
Log::info('User login', ['user_id' => $user->id, 'ip' => $request->ip()]);
```

### Performance Debugging

#### Query Debugging
```php
// Enable query logging
DB::enableQueryLog();

// Your code here

// Get executed queries
$queries = DB::getQueryLog();
dd($queries);
```

#### Memory Profiling
```php
// Check memory usage
echo 'Memory usage: ' . memory_get_usage(true) / 1024 / 1024 . ' MB';
echo 'Peak memory: ' . memory_get_peak_usage(true) / 1024 / 1024 . ' MB';
```

---

## 📚 Additional Resources

### Documentation Links
- [Laravel Documentation](https://laravel.com/docs)
- [PHP Documentation](https://www.php.net/docs.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Nginx Documentation](https://nginx.org/en/docs/)

### Learning Resources
- [Laravel Bootcamp](https://bootcamp.laravel.com/)
- [Laracasts](https://laracasts.com/)
- [Laravel News](https://laravel-news.com/)
- [PHP The Right Way](https://phptherightway.com/)

### Community
- [Laravel Community](https://laravel.com/community)
- [Laravel Discord](https://discord.gg/laravel)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/laravel)

---

*This developer guide provides comprehensive information for developing, deploying, and contributing to the Akudihatinya Backend project. For specific feature documentation, refer to the API reference and system architecture guides.*