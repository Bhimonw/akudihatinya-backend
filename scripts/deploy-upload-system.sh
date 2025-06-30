#!/bin/bash

# Upload System Deployment Script
# This script helps deploy the upload system with best practices

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-"development"}
SKIP_TESTS=${2:-"false"}
FORCE_CLEANUP=${3:-"false"}

echo -e "${BLUE}=== Upload System Deployment Script ===${NC}"
echo -e "Environment: ${YELLOW}$ENVIRONMENT${NC}"
echo -e "Skip Tests: ${YELLOW}$SKIP_TESTS${NC}"
echo -e "Force Cleanup: ${YELLOW}$FORCE_CLEANUP${NC}"
echo ""

# Function to print status
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check PHP extensions
check_php_extensions() {
    print_status "Checking PHP extensions..."
    
    required_extensions=("gd" "fileinfo" "mbstring")
    missing_extensions=()
    
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            missing_extensions+=("$ext")
        fi
    done
    
    if [ ${#missing_extensions[@]} -ne 0 ]; then
        print_error "Missing PHP extensions: ${missing_extensions[*]}"
        echo "Please install missing extensions:"
        echo "  Ubuntu/Debian: sudo apt-get install php-gd php-fileinfo php-mbstring"
        echo "  CentOS/RHEL: sudo yum install php-gd php-fileinfo php-mbstring"
        exit 1
    fi
    
    print_status "All required PHP extensions are installed"
}

# Function to setup environment file
setup_environment() {
    print_status "Setting up environment configuration..."
    
    if [ ! -f ".env" ]; then
        print_error ".env file not found. Please create it first."
        exit 1
    fi
    
    # Check if upload configuration exists
    if ! grep -q "UPLOAD_DISK" .env; then
        print_status "Adding upload configuration to .env..."
        
        case $ENVIRONMENT in
            "production")
                cat >> .env << EOF

# Upload System Configuration - Production
UPLOAD_DISK=s3
UPLOAD_MAX_FILE_SIZE=2048
UPLOAD_PROFILE_PICTURES_PATH=profile-pictures

# Image Optimization
UPLOAD_OPTIMIZE_IMAGES=true
UPLOAD_MAX_IMAGE_WIDTH=1024
UPLOAD_MAX_IMAGE_HEIGHT=1024
UPLOAD_JPEG_QUALITY=85
UPLOAD_PNG_COMPRESSION=6
UPLOAD_PRESERVE_TRANSPARENCY=true

# CDN (Configure your CDN URL)
UPLOAD_CDN_ENABLED=false
UPLOAD_CDN_BASE_URL=

# Security - Production
UPLOAD_RATE_LIMIT_ENABLED=true
UPLOAD_RATE_LIMIT_MAX_ATTEMPTS=5
UPLOAD_RATE_LIMIT_DECAY_MINUTES=60
UPLOAD_GENERATE_UNIQUE_NAMES=true
UPLOAD_SCAN_CONTENT=true
UPLOAD_CHECK_DIMENSIONS=true

# Performance
UPLOAD_ENABLE_CACHING=true
UPLOAD_CACHE_TTL=3600
UPLOAD_LAZY_LOADING=true

# Cleanup
UPLOAD_CLEANUP_ENABLED=true
UPLOAD_CLEANUP_RETENTION_DAYS=30
UPLOAD_CLEANUP_SCHEDULE=daily

# Logging
UPLOAD_LOGGING_ENABLED=true
UPLOAD_LOG_UPLOADS=true
UPLOAD_LOG_SECURITY_EVENTS=true
UPLOAD_LOG_CLEANUP=true
EOF
                ;;
            "staging")
                cat >> .env << EOF

# Upload System Configuration - Staging
UPLOAD_DISK=public
UPLOAD_MAX_FILE_SIZE=2048
UPLOAD_PROFILE_PICTURES_PATH=profile-pictures

# Image Optimization
UPLOAD_OPTIMIZE_IMAGES=true
UPLOAD_MAX_IMAGE_WIDTH=1024
UPLOAD_MAX_IMAGE_HEIGHT=1024
UPLOAD_JPEG_QUALITY=80
UPLOAD_PNG_COMPRESSION=6
UPLOAD_PRESERVE_TRANSPARENCY=true

# Security - Staging
UPLOAD_RATE_LIMIT_ENABLED=true
UPLOAD_RATE_LIMIT_MAX_ATTEMPTS=10
UPLOAD_RATE_LIMIT_DECAY_MINUTES=60
UPLOAD_GENERATE_UNIQUE_NAMES=true
UPLOAD_SCAN_CONTENT=true
UPLOAD_CHECK_DIMENSIONS=true

# Cleanup
UPLOAD_CLEANUP_ENABLED=true
UPLOAD_CLEANUP_RETENTION_DAYS=7
UPLOAD_CLEANUP_SCHEDULE=daily

# Logging
UPLOAD_LOGGING_ENABLED=true
UPLOAD_LOG_UPLOADS=true
UPLOAD_LOG_SECURITY_EVENTS=true
UPLOAD_LOG_CLEANUP=true
EOF
                ;;
            *)
                cat >> .env << EOF

# Upload System Configuration - Development
UPLOAD_DISK=public
UPLOAD_MAX_FILE_SIZE=5120
UPLOAD_PROFILE_PICTURES_PATH=profile-pictures

# Image Optimization
UPLOAD_OPTIMIZE_IMAGES=true
UPLOAD_MAX_IMAGE_WIDTH=1024
UPLOAD_MAX_IMAGE_HEIGHT=1024
UPLOAD_JPEG_QUALITY=75
UPLOAD_PNG_COMPRESSION=6
UPLOAD_PRESERVE_TRANSPARENCY=true

# Security - Development (Relaxed)
UPLOAD_RATE_LIMIT_ENABLED=false
UPLOAD_RATE_LIMIT_MAX_ATTEMPTS=50
UPLOAD_RATE_LIMIT_DECAY_MINUTES=60
UPLOAD_GENERATE_UNIQUE_NAMES=false
UPLOAD_SCAN_CONTENT=false
UPLOAD_CHECK_DIMENSIONS=true

# Cleanup
UPLOAD_CLEANUP_ENABLED=true
UPLOAD_CLEANUP_RETENTION_DAYS=3
UPLOAD_CLEANUP_SCHEDULE=daily

# Logging
UPLOAD_LOGGING_ENABLED=true
UPLOAD_LOG_UPLOADS=true
UPLOAD_LOG_SECURITY_EVENTS=false
UPLOAD_LOG_CLEANUP=true
EOF
                ;;
        esac
        
        print_status "Upload configuration added to .env"
    else
        print_status "Upload configuration already exists in .env"
    fi
}

# Function to setup storage
setup_storage() {
    print_status "Setting up storage..."
    
    # Create storage link if using public disk
    if grep -q "UPLOAD_DISK=public" .env; then
        if [ ! -L "public/storage" ]; then
            php artisan storage:link
            print_status "Storage link created"
        else
            print_status "Storage link already exists"
        fi
    fi
    
    # Create upload directories
    php artisan tinker --execute="
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Support\Facades\Config;
        
        \$disk = Config::get('upload.profile_pictures.disk', 'public');
        \$path = Config::get('upload.profile_pictures.path', 'profile-pictures');
        
        if (!Storage::disk(\$disk)->exists(\$path)) {
            Storage::disk(\$disk)->makeDirectory(\$path);
            echo 'Created directory: ' . \$path . PHP_EOL;
        } else {
            echo 'Directory already exists: ' . \$path . PHP_EOL;
        }
    "
}

# Function to run tests
run_tests() {
    if [ "$SKIP_TESTS" = "true" ]; then
        print_warning "Skipping tests as requested"
        return
    fi
    
    print_status "Running upload system tests..."
    
    # Run specific upload tests
    if command_exists "php"; then
        php artisan test --filter=ProfilePictureService --stop-on-failure
        php artisan test --filter=UploadSecurityMiddleware --stop-on-failure
        print_status "All tests passed"
    else
        print_error "PHP not found. Cannot run tests."
        exit 1
    fi
}

# Function to setup permissions
setup_permissions() {
    print_status "Setting up file permissions..."
    
    # Set storage permissions
    if [ -d "storage" ]; then
        chmod -R 755 storage/
        print_status "Storage permissions set to 755"
    fi
    
    # Set bootstrap/cache permissions
    if [ -d "bootstrap/cache" ]; then
        chmod -R 755 bootstrap/cache/
        print_status "Bootstrap cache permissions set to 755"
    fi
}

# Function to clear and cache configuration
cache_config() {
    print_status "Caching configuration..."
    
    php artisan config:clear
    php artisan config:cache
    
    if [ "$ENVIRONMENT" = "production" ]; then
        php artisan route:cache
        php artisan view:cache
        print_status "Production optimizations applied"
    fi
}

# Function to setup cleanup schedule
setup_cleanup() {
    print_status "Setting up file cleanup..."
    
    # Test cleanup command
    print_status "Testing cleanup command..."
    php artisan app:cleanup-old-files --dry-run
    
    if [ "$FORCE_CLEANUP" = "true" ]; then
        print_warning "Running immediate cleanup (forced)..."
        php artisan app:cleanup-old-files --force
    fi
    
    print_status "Cleanup system configured"
}

# Function to run health check
run_health_check() {
    print_status "Running health check..."
    
    php artisan tinker --execute="
        use App\Providers\UploadServiceProvider;
        
        \$health = UploadServiceProvider::healthCheck();
        
        echo 'Upload System Health Check:' . PHP_EOL;
        echo '=========================' . PHP_EOL;
        
        foreach (\$health as \$key => \$value) {
            if (is_bool(\$value)) {
                \$status = \$value ? 'PASS' : 'FAIL';
                echo sprintf('%-25s: %s', \$key, \$status) . PHP_EOL;
            } else {
                echo sprintf('%-25s: %s', \$key, \$value) . PHP_EOL;
            }
        }
        
        if (!\$health['overall_status']) {
            echo PHP_EOL . 'WARNING: Some health checks failed!' . PHP_EOL;
            exit(1);
        }
    "
    
    if [ $? -eq 0 ]; then
        print_status "Health check passed"
    else
        print_error "Health check failed. Please review the issues above."
        exit 1
    fi
}

# Function to display deployment summary
show_summary() {
    echo ""
    echo -e "${GREEN}=== Deployment Summary ===${NC}"
    echo -e "Environment: ${YELLOW}$ENVIRONMENT${NC}"
    echo -e "Upload Disk: ${YELLOW}$(grep UPLOAD_DISK .env | cut -d'=' -f2)${NC}"
    echo -e "Max File Size: ${YELLOW}$(grep UPLOAD_MAX_FILE_SIZE .env | cut -d'=' -f2)KB${NC}"
    echo -e "Rate Limiting: ${YELLOW}$(grep UPLOAD_RATE_LIMIT_ENABLED .env | cut -d'=' -f2)${NC}"
    echo -e "Image Optimization: ${YELLOW}$(grep UPLOAD_OPTIMIZE_IMAGES .env | cut -d'=' -f2)${NC}"
    echo -e "Cleanup Enabled: ${YELLOW}$(grep UPLOAD_CLEANUP_ENABLED .env | cut -d'=' -f2)${NC}"
    echo ""
    echo -e "${GREEN}Next Steps:${NC}"
    echo "1. Test file upload: Upload a profile picture through your app"
    echo "2. Monitor logs: tail -f storage/logs/laravel.log | grep -i upload"
    echo "3. Check statistics: php artisan tinker --execute='use App\\Providers\\UploadServiceProvider; var_dump(UploadServiceProvider::getUploadStats());'"
    echo "4. Setup monitoring for production environment"
    echo ""
    echo -e "${GREEN}Useful Commands:${NC}"
    echo "- Health check: php artisan tinker --execute='use App\\Providers\\UploadServiceProvider; var_dump(UploadServiceProvider::healthCheck());'"
    echo "- Manual cleanup: php artisan app:cleanup-old-files --dry-run"
    echo "- View upload stats: php artisan tinker --execute='use App\\Providers\\UploadServiceProvider; var_dump(UploadServiceProvider::getUploadStats());'"
    echo ""
}

# Main deployment process
main() {
    print_status "Starting upload system deployment..."
    
    # Check prerequisites
    if ! command_exists "php"; then
        print_error "PHP is not installed or not in PATH"
        exit 1
    fi
    
    if [ ! -f "artisan" ]; then
        print_error "Laravel artisan not found. Are you in the project root?"
        exit 1
    fi
    
    # Run deployment steps
    check_php_extensions
    setup_environment
    setup_storage
    setup_permissions
    cache_config
    run_tests
    setup_cleanup
    run_health_check
    show_summary
    
    print_status "Upload system deployment completed successfully!"
}

# Handle script arguments
case "$1" in
    "--help"|"help")
        echo "Upload System Deployment Script"
        echo ""
        echo "Usage: $0 [environment] [skip_tests] [force_cleanup]"
        echo ""
        echo "Arguments:"
        echo "  environment    : development|staging|production (default: development)"
        echo "  skip_tests     : true|false (default: false)"
        echo "  force_cleanup  : true|false (default: false)"
        echo ""
        echo "Examples:"
        echo "  $0                                    # Development with tests"
        echo "  $0 production                         # Production deployment"
        echo "  $0 staging true                       # Staging without tests"
        echo "  $0 production false true              # Production with cleanup"
        echo ""
        exit 0
        ;;
    *)
        main
        ;;
esac