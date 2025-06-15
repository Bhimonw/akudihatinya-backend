# Contributing to Akudihatinya Backend

Terima kasih atas minat Anda untuk berkontribusi pada proyek Akudihatinya Backend! Panduan ini akan membantu Anda memahami cara berkontribusi dengan efektif.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Process](#development-process)
4. [Coding Standards](#coding-standards)
5. [Testing Guidelines](#testing-guidelines)
6. [Documentation](#documentation)
7. [Pull Request Process](#pull-request-process)
8. [Issue Reporting](#issue-reporting)
9. [Community](#community)

## Code of Conduct

Proyek ini mengikuti kode etik yang ramah dan inklusif. Dengan berpartisipasi, Anda diharapkan untuk menjaga standar ini. Laporkan perilaku yang tidak pantas ke tim development.

### Our Standards

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

## Getting Started

### Prerequisites

Pastikan Anda memiliki:
- PHP 8.1+
- Composer 2.x
- MySQL 8.0+ atau MariaDB 10.4+
- Git
- IDE yang mendukung PHP (VS Code, PhpStorm, dll.)

### Setup Development Environment

1. **Fork repository**
   ```bash
   # Fork di GitHub, kemudian clone
   git clone https://github.com/YOUR_USERNAME/akudihatinya-backend.git
   cd akudihatinya-backend
   ```

2. **Setup upstream remote**
   ```bash
   git remote add upstream https://github.com/ORIGINAL_OWNER/akudihatinya-backend.git
   ```

3. **Install dependencies**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

4. **Setup database**
   ```bash
   # Configure .env file with your database settings
   php artisan migrate
   php artisan db:seed
   ```

5. **Run tests**
   ```bash
   php artisan test
   ```

## Development Process

### Workflow

1. **Sync with upstream**
   ```bash
   git checkout develop
   git pull upstream develop
   ```

2. **Create feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make changes**
   - Write code
   - Add tests
   - Update documentation

4. **Commit changes**
   ```bash
   git add .
   git commit -m "feat: add your feature description"
   ```

5. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   ```

### Branch Naming Convention

- `feature/feature-name` - New features
- `bugfix/bug-description` - Bug fixes
- `hotfix/critical-fix` - Critical fixes
- `refactor/component-name` - Code refactoring
- `docs/documentation-update` - Documentation updates

### Commit Message Convention

Gunakan [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(export): add puskesmas PDF export functionality
fix(auth): resolve token expiration issue
docs(api): update endpoint documentation
refactor(statistics): optimize query performance
test(export): add unit tests for PDF service
```

## Coding Standards

### PHP Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
- Use type hints for all parameters and return types
- Write descriptive variable and method names
- Add PHPDoc comments for all public methods

### Code Quality Tools

```bash
# PHP CS Fixer
vendor/bin/php-cs-fixer fix

# PHPStan
vendor/bin/phpstan analyse

# Larastan
vendor/bin/phpstan analyse --memory-limit=2G
```

### Example Code Style

```php
<?php

namespace App\Services;

use App\Models\Puskesmas;
use Illuminate\Support\Collection;

class StatisticsService
{
    /**
     * Calculate statistics for given parameters.
     *
     * @param int $year
     * @param string $diseaseType
     * @param int|null $puskesmasId
     * @return Collection
     */
    public function calculateStatistics(
        int $year,
        string $diseaseType,
        ?int $puskesmasId = null
    ): Collection {
        // Implementation
    }
}
```

## Testing Guidelines

### Test Types

1. **Unit Tests** - Test individual methods/classes
2. **Feature Tests** - Test API endpoints and workflows
3. **Integration Tests** - Test component interactions

### Writing Tests

```php
<?php

namespace Tests\Feature\API;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StatisticsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_statistics(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        
        // Act
        $response = $this->actingAs($admin)
            ->getJson('/api/statistics/export?year=2024&format=excel');
        
        // Assert
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
```

### Test Requirements

- All new features must have tests
- Bug fixes should include regression tests
- Maintain test coverage above 80%
- Tests should be fast and reliable

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test tests/Feature/API/StatisticsExportTest.php

# Run with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

## Documentation

### Documentation Requirements

- Update API documentation for new endpoints
- Add PHPDoc comments for all public methods
- Update README.md if needed
- Include examples in documentation

### Documentation Style

```php
/**
 * Export puskesmas statistics to PDF.
 *
 * This method generates a PDF report containing statistics data
 * for a specific puskesmas and disease type.
 *
 * @param string $diseaseType Type of disease (ht|dm)
 * @param int $year Year for the report
 * @param int $puskesmasId Puskesmas ID
 * @return \Illuminate\Http\Response PDF response
 * @throws \Exception When PDF generation fails
 * 
 * @example
 * $service->exportPuskesmasPdf('ht', 2024, 1);
 */
public function exportPuskesmasPdf(string $diseaseType, int $year, int $puskesmasId): Response
{
    // Implementation
}
```

## Pull Request Process

### Before Submitting

1. **Ensure tests pass**
   ```bash
   php artisan test
   ```

2. **Run code quality checks**
   ```bash
   vendor/bin/php-cs-fixer fix
   vendor/bin/phpstan analyse
   ```

3. **Update documentation**
   - API documentation
   - PHPDoc comments
   - README if needed

4. **Sync with upstream**
   ```bash
   git checkout develop
   git pull upstream develop
   git checkout your-feature-branch
   git rebase develop
   ```

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] New tests added for new functionality
- [ ] Manual testing completed

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or documented)
```

### Review Process

1. **Automated checks** - CI/CD pipeline runs tests
2. **Code review** - Team members review code
3. **Testing** - Manual testing if needed
4. **Approval** - At least one approval required
5. **Merge** - Squash and merge to develop

## Issue Reporting

### Bug Reports

Gunakan template berikut untuk bug reports:

```markdown
**Bug Description**
A clear description of the bug

**Steps to Reproduce**
1. Go to '...'
2. Click on '....'
3. See error

**Expected Behavior**
What you expected to happen

**Actual Behavior**
What actually happened

**Environment**
- OS: [e.g. Windows 10]
- PHP Version: [e.g. 8.1.0]
- Laravel Version: [e.g. 10.x]

**Additional Context**
Any other context about the problem
```

### Feature Requests

```markdown
**Feature Description**
A clear description of the feature

**Problem Statement**
What problem does this solve?

**Proposed Solution**
How should this be implemented?

**Alternatives Considered**
Other solutions you've considered

**Additional Context**
Any other context or screenshots
```

### Security Issues

**JANGAN** melaporkan security issues di GitHub Issues. Kirim email ke tim security:
- Email: security@akudihatinya.com
- Sertakan detail lengkap tentang vulnerability
- Berikan waktu untuk fix sebelum disclosure

## Community

### Communication Channels

- **GitHub Issues** - Bug reports, feature requests
- **GitHub Discussions** - General discussions, questions
- **Email** - security@akudihatinya.com (security issues)

### Getting Help

1. **Check documentation** - `docs/` folder
2. **Search existing issues** - GitHub Issues
3. **Ask in discussions** - GitHub Discussions
4. **Contact maintainers** - Email or GitHub

### Recognition

Kontributor akan diakui dalam:
- CHANGELOG.md
- GitHub contributors list
- Release notes
- Project documentation

## Development Resources

### Useful Links

- [Laravel Documentation](https://laravel.com/docs)
- [PHP The Right Way](https://phptherightway.com/)
- [PSR Standards](https://www.php-fig.org/psr/)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Keep a Changelog](https://keepachangelog.com/)

### IDE Setup

#### VS Code Extensions
```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "bradlc.vscode-tailwindcss",
    "ms-vscode.vscode-json",
    "esbenp.prettier-vscode",
    "ryannaddy.laravel-artisan",
    "onecentlin.laravel-blade"
  ]
}
```

#### PhpStorm Setup
- Enable Laravel plugin
- Configure PHP CS Fixer
- Setup PHPStan integration
- Configure database connection

## Maintainers

### Project Lead
- **Bhimo Noorasty Whibhisono** ([@Bhimonw](https://github.com/Bhimonw))
  - GitHub: https://github.com/Bhimonw
  - Role: Lead Developer & Project Maintainer
  - Responsibilities: Code review, architecture decisions, release management

### Contact Information
- **General Questions**: GitHub Discussions
- **Bug Reports**: GitHub Issues
- **Security Issues**: security@akudihatinya.com
- **Direct Contact**: GitHub @Bhimonw

## Questions?

Jika Anda memiliki pertanyaan tentang contributing, jangan ragu untuk:
1. Membuka GitHub Discussion
2. Menghubungi maintainers melalui GitHub
3. Membaca dokumentasi di `docs/DEVELOPMENT_GUIDE.md`
4. Melihat contoh di existing pull requests

## Acknowledgments

Terima kasih kepada semua kontributor yang telah membantu mengembangkan proyek Akudihatinya Backend. Kontribusi Anda sangat berarti untuk kemajuan sistem kesehatan di Indonesia.

### Recent Contributors (2025)
- [@Bhimonw](https://github.com/Bhimonw) - Project setup, documentation, and code organization

Terima kasih atas kontribusi Anda! 🚀