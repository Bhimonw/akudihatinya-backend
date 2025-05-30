# Aku Dihatinya Backend API

This is the backend API for the Aku Dihatinya application, a healthcare management system built with Laravel.

## API Documentation

### Authentication

All protected routes require authentication using Laravel Sanctum. Include the token in the Authorization header:
```
Authorization: Bearer <your-token>
```

#### Public Routes

- `POST /api/login` - User login
- `POST /api/refresh` - Refresh authentication token

#### Protected Routes

##### Auth Routes
- `POST /api/logout` - User logout
- `GET /api/user` - Get authenticated user details
- `POST /api/change-password` - Change user password
- `POST /api/profile` - Update user profile
- `GET /api/users/me` - Get current user details
- `PUT /api/users/me` - Update current user details

### Admin Routes

All admin routes are prefixed with `/api/admin` and require admin privileges.

#### User Management
- `GET /api/admin/users` - List all users
- `POST /api/admin/users` - Create new user
- `GET /api/admin/users/{user}` - Get user details
- `PUT /api/admin/users/{user}` - Update user
- `DELETE /api/admin/users/{user}` - Delete user
- `POST /api/admin/users/{user}/reset-password` - Reset user password

#### Yearly Targets
- `GET /api/admin/yearly-targets` - List yearly targets
- `POST /api/admin/yearly-targets` - Create yearly target
- `GET /api/admin/yearly-targets/{target}` - Get target details
- `PUT /api/admin/yearly-targets/{target}` - Update target
- `DELETE /api/admin/yearly-targets/{target}` - Delete target

### Puskesmas Routes

All puskesmas routes are prefixed with `/api/puskesmas` and require puskesmas privileges.

#### Patient Management
- `GET /api/puskesmas/patients` - List patients
- `POST /api/puskesmas/patients` - Create new patient
- `GET /api/puskesmas/patients/{patient}` - Get patient details
- `PUT /api/puskesmas/patients/{patient}` - Update patient
- `DELETE /api/puskesmas/patients/{patient}` - Delete patient
- `POST /api/puskesmas/patients/{patient}/examination-year` - Add examination year
- `PUT /api/puskesmas/patients/{patient}/examination-year` - Remove examination year

#### Examinations
- `GET /api/puskesmas/ht-examinations` - List HT examinations
- `POST /api/puskesmas/ht-examinations` - Create HT examination
- `GET /api/puskesmas/ht-examinations/{examination}` - Get HT examination details
- `PUT /api/puskesmas/ht-examinations/{examination}` - Update HT examination
- `DELETE /api/puskesmas/ht-examinations/{examination}` - Delete HT examination

- `GET /api/puskesmas/dm-examinations` - List DM examinations
- `POST /api/puskesmas/dm-examinations` - Create DM examination
- `GET /api/puskesmas/dm-examinations/{examination}` - Get DM examination details
- `PUT /api/puskesmas/dm-examinations/{examination}` - Update DM examination
- `DELETE /api/puskesmas/dm-examinations/{examination}` - Delete DM examination

### Statistics Routes

All statistics routes are prefixed with `/api/statistics` and require either admin or puskesmas privileges.

#### Dashboard Statistics
- `GET /api/statistics/dashboard` - Get dashboard statistics

#### Admin Statistics
- `GET /api/statistics/admin` - Get admin statistics

#### Export Statistics
- `GET /api/statistics/export` - Export all statistics
- `GET /api/statistics/export/ht` - Export HT statistics
- `GET /api/statistics/export/dm` - Export DM statistics
- `GET /api/statistics/export/{year}/{month}` - Export monthly statistics

#### Monitoring
- `GET /api/statistics/monitoring` - Get monitoring statistics

## Error Handling

The API uses standard HTTP response codes:
- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error

## Response Format

All responses are in JSON format. Error responses include a message and optional validation errors:

```json
{
    "message": "Error message",
    "errors": {
        "field": ["Error details"]
    }
}
```

## Development

### Requirements
- PHP 8.1 or higher
- Composer
- MySQL 5.7 or higher

### Installation

1. Clone the repository
2. Install dependencies:
```bash
composer install
```
3. Copy `.env.example` to `.env` and configure your environment
4. Generate application key:
```bash
php artisan key:generate
```
5. Run migrations:
```bash
php artisan migrate
```
6. Start the development server:
```bash
php artisan serve
```

### Testing

Run the test suite:
```bash
php artisan test
```
