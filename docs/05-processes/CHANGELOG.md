# 📝 Changelog

Semua perubahan penting pada proyek Akudihatinya Backend akan didokumentasikan dalam file ini.

Format berdasarkan [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan proyek ini mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - 2025-06-XX

### Added
- User creation fix documentation (`docs/USER_CREATION_FIX.md`)
- Enhanced user management documentation with corrected flow
- Database transaction implementation for user-puskesmas creation

### Fixed
- **Critical**: Fixed user creation error `SQLSTATE[HY000]: General error: 1364 Field 'user_id' doesn't have a default value` when creating puskesmas users
- **UserController**: Corrected order of operations in `UserController::store()` method for puskesmas role users
- **Puskesmas Model**: Added `user_id` to fillable array to allow mass assignment during puskesmas creation
- **Database**: Implemented proper database transaction handling for user-puskesmas relationship creation

### Changed
- Updated API documentation for user creation endpoint
- Enhanced user creation flow documentation in ERD and use case docs
- Improved error handling in user creation process

## [Unreleased] - 2025-03-XX

### Added
- Comprehensive API documentation (`docs/API_COMPLETE.md`)
- Development guide for contributors (`docs/DEVELOPER_GUIDE.md`)
- System architecture and deployment guide (`docs/SYSTEM_ARCHITECTURE.md`)
- Code quality insights and guidelines (`docs/CODE_QUALITY_INSIGHTS.md`)
- Enhanced README.md with project overview and quick start guide
- Changelog documentation
- Code quality improvements and project cleanup

### Changed
- Consolidated export endpoints in `StatisticsController`
- Updated README.md from Laravel default to project-specific documentation
- Improved project structure documentation
- Enhanced development workflow and contribution process

### Removed
- Duplicate export endpoints (`exportHtStatistics`, `exportDmStatistics`)
- Redundant routes in `routes/api/statistics.php`
- Unused test files (`test.html`, `test.http`, `test_endpoint.ps1`)
- Duplicate documentation files and consolidated into single API documentation

### Fixed
- Route consolidation for better maintainability
- Endpoint consistency across the API
- Project structure and documentation organization

### Contributors
- [@Bhimonw](https://github.com/Bhimonw) - Documentation improvements, code cleanup, and project organization

## [1.2.0] - 2024-01-XX

### Added
- Puskesmas-specific PDF export functionality
- New `PuskesmasPdfFormatter` for specialized PDF formatting
- Enhanced `PdfService` with puskesmas template support
- Quarterly reporting capabilities
- Comprehensive error handling for export operations

### Changed
- Improved statistics query performance
- Enhanced PDF template formatting
- Updated export service architecture

### Fixed
- PDF generation memory optimization
- Export file naming consistency
- Database query optimization for large datasets

## [1.1.0] - 2023-12-XX

### Added
- Monitoring report export functionality
- Enhanced user management system
- Role-based access control improvements
- Excel export templates

### Changed
- Updated authentication system
- Improved API response formatting
- Enhanced error handling

### Fixed
- Database connection timeout issues
- Session management improvements
- Export performance optimizations

## [1.0.0] - 2023-11-XX

### Added
- Initial release of Akudihatinya Backend
- Multi-role authentication (Admin, Puskesmas)
- Statistics and reporting system
- Patient management
- Basic export functionality (Excel, PDF)
- RESTful API endpoints
- Database migrations and seeders
- Basic documentation

### Features
- User authentication with Laravel Sanctum
- Role-based access control
- Statistics calculation and reporting
- Data export in multiple formats
- Puskesmas management
- Patient data management
- HT (Hypertension) and DM (Diabetes Mellitus) tracking

---

## Types of Changes

- **Added** for new features
- **Changed** for changes in existing functionality
- **Deprecated** for soon-to-be removed features
- **Removed** for now removed features
- **Fixed** for any bug fixes
- **Security** for vulnerability fixes

## Release Process

1. Update version number in relevant files
2. Update CHANGELOG.md with new version
3. Create git tag with version number
4. Deploy to staging for testing
5. Deploy to production
6. Create GitHub release with changelog notes

## Migration Notes

### From 1.1.x to 1.2.x
- No breaking changes
- New PDF export endpoints available
- Enhanced error handling may change some error response formats

### From 1.0.x to 1.1.x
- Database migrations required for new monitoring features
- API response format improvements (backward compatible)
- New authentication middleware (automatic)

## Support

For questions about specific versions or upgrade paths, please:
1. Check the documentation in `docs/` folder
2. Review the API documentation
3. Create an issue on GitHub
4. Contact the development team