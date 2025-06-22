# 🚀 Deployment Guide

Panduan lengkap untuk deployment aplikasi Akudihatinya Backend.

## Prerequisites

### System Requirements
- PHP 8.1 atau lebih tinggi
- Composer 2.x
- MySQL 8.0 atau MariaDB 10.4+
- Node.js 16+ (untuk asset compilation)
- Web server (Apache/Nginx)

### PHP Extensions
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
```

## Installation

### 1. Clone Repository
```bash
git clone https://github.com/Bhimonw/akudihatinya-backend.git
cd akudihatinya-backend
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node.js dependencies (jika ada)
npm install
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Environment Variables
Edit file `.env` dengan konfigurasi yang sesuai:

```env
# Application
APP_NAME="Akudihatinya"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akudihatinya
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com
```

### 5. Database Setup
```bash
# Run migrations
php artisan migrate --force

# Seed database (optional)
php artisan db:seed
```

### 6. Storage Setup
```bash
# Create storage link
php artisan storage:link

# Set permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### 7. Cache Optimization
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Generate IDE helper files (development only)
php artisan ide-helper:generate
php artisan ide-helper:models
```

## Web Server Configuration

### Apache Configuration
Buat file `.htaccess` di root directory:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Atau konfigurasi virtual host:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/akudihatinya-backend/public
    
    <Directory /path/to/akudihatinya-backend/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/akudihatinya_error.log
    CustomLog ${APACHE_LOG_DIR}/akudihatinya_access.log combined
</VirtualHost>
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/akudihatinya-backend/public;
    
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

## SSL Configuration

### Using Let's Encrypt (Certbot)
```bash
# Install certbot
sudo apt install certbot python3-certbot-apache

# Generate SSL certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## Database Backup

### Automated Backup Script
Buat file `backup.sh`:
```bash
#!/bin/bash

# Configuration
DB_NAME="akudihatinya"
DB_USER="your_username"
DB_PASS="your_password"
BACKUP_DIR="/path/to/backups"
DATE=$(date +"%Y%m%d_%H%M%S")

# Create backup directory if not exists
mkdir -p $BACKUP_DIR

# Create backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/backup_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/backup_$DATE.sql

# Remove backups older than 30 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: backup_$DATE.sql.gz"
```

Jadwalkan dengan crontab:
```bash
sudo crontab -e
# Add: 0 2 * * * /path/to/backup.sh
```

## Monitoring

### Log Monitoring
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor web server logs
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

### Performance Monitoring
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm

# Monitor MySQL
mysql -u root -p -e "SHOW PROCESSLIST;"

# Check disk usage
df -h

# Check memory usage
free -h
```

## Security

### File Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /path/to/akudihatinya-backend

# Set directory permissions
find /path/to/akudihatinya-backend -type d -exec chmod 755 {} \;

# Set file permissions
find /path/to/akudihatinya-backend -type f -exec chmod 644 {} \;

# Set executable permissions for artisan
chmod +x artisan
```

### Firewall Configuration
```bash
# Allow HTTP and HTTPS
sudo ufw allow 80
sudo ufw allow 443

# Allow SSH (if needed)
sudo ufw allow 22

# Enable firewall
sudo ufw enable
```

## Maintenance

### Regular Tasks
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize application
php artisan optimize

# Update dependencies
composer update --no-dev

# Run migrations (if any)
php artisan migrate --force
```

### Maintenance Mode
```bash
# Enable maintenance mode
php artisan down --message="System maintenance in progress"

# Disable maintenance mode
php artisan up
```

## Troubleshooting

### Common Issues

#### 1. Permission Denied Errors
```bash
# Fix storage permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

#### 2. Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### 3. 500 Internal Server Error
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check web server logs
tail -f /var/log/apache2/error.log
```

#### 4. Memory Limit Issues
Edit `php.ini`:
```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M
```

### Performance Optimization

#### 1. Enable OPcache
Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

#### 2. Configure Redis
```bash
# Install Redis
sudo apt install redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
# Set: maxmemory 256mb
# Set: maxmemory-policy allkeys-lru
```

#### 3. Database Optimization
```sql
-- Add indexes for better performance
ALTER TABLE ht_examinations ADD INDEX idx_patient_date (patient_id, examination_date);
ALTER TABLE dm_examinations ADD INDEX idx_patient_date (patient_id, examination_date);
ALTER TABLE patients ADD INDEX idx_puskesmas (puskesmas_id);
```

## Scaling

### Load Balancer Configuration
Untuk deployment dengan multiple servers, gunakan load balancer seperti HAProxy atau Nginx.

### Database Replication
Setup MySQL master-slave replication untuk read scaling.

### CDN Integration
Gunakan CDN untuk static assets dan file uploads.

## Backup and Recovery

### Full System Backup
```bash
#!/bin/bash
# Full backup script
tar -czf /backup/akudihatinya_$(date +%Y%m%d).tar.gz \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    /path/to/akudihatinya-backend
```

### Recovery Process
1. Restore files from backup
2. Restore database from SQL dump
3. Update `.env` configuration
4. Run `composer install`
5. Run `php artisan migrate`
6. Clear caches and optimize

## Support

Untuk bantuan lebih lanjut:
- Check dokumentasi Laravel: https://laravel.com/docs
- Review application logs: `storage/logs/laravel.log`
- Contact development team