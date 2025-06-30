# Upload System Deployment Script for Windows
# PowerShell script to deploy upload system with best practices

param(
    [string]$Environment = "development",
    [bool]$SkipTests = $false,
    [bool]$ForceCleanup = $false,
    [switch]$Help
)

# Colors for output
$Red = "Red"
$Green = "Green"
$Yellow = "Yellow"
$Blue = "Blue"
$White = "White"

function Write-Status {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor $Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor $Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor $Red
}

function Write-Header {
    param([string]$Message)
    Write-Host "=== $Message ===" -ForegroundColor $Blue
}

function Test-Command {
    param([string]$Command)
    try {
        Get-Command $Command -ErrorAction Stop | Out-Null
        return $true
    }
    catch {
        return $false
    }
}

function Test-PHPExtensions {
    Write-Status "Checking PHP extensions..."
    
    $requiredExtensions = @("gd", "fileinfo", "mbstring")
    $missingExtensions = @()
    
    try {
        $phpModules = php -m 2>$null
        
        foreach ($ext in $requiredExtensions) {
            if ($phpModules -notcontains $ext) {
                $missingExtensions += $ext
            }
        }
        
        if ($missingExtensions.Count -gt 0) {
            Write-Error "Missing PHP extensions: $($missingExtensions -join ', ')"
            Write-Host "Please install missing extensions through your PHP installation" -ForegroundColor $Yellow
            return $false
        }
        
        Write-Status "All required PHP extensions are installed"
        return $true
    }
    catch {
        Write-Error "Could not check PHP extensions. Is PHP installed and in PATH?"
        return $false
    }
}

function Set-Environment {
    Write-Status "Setting up environment configuration..."
    
    if (-not (Test-Path ".env")) {
        Write-Error ".env file not found. Please create it first."
        return $false
    }
    
    $envContent = Get-Content ".env" -Raw
    
    if ($envContent -notmatch "UPLOAD_DISK") {
        Write-Status "Adding upload configuration to .env..."
        
        $uploadConfig = switch ($Environment) {
            "production" {
                @"

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
"@
            }
            "staging" {
                @"

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
"@
            }
            default {
                @"

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
"@
            }
        }
        
        Add-Content -Path ".env" -Value $uploadConfig
        Write-Status "Upload configuration added to .env"
    }
    else {
        Write-Status "Upload configuration already exists in .env"
    }
    
    return $true
}

function Set-Storage {
    Write-Status "Setting up storage..."
    
    try {
        # Create storage link if using public disk
        $envContent = Get-Content ".env" -Raw
        if ($envContent -match "UPLOAD_DISK=public") {
            if (-not (Test-Path "public\storage")) {
                php artisan storage:link
                Write-Status "Storage link created"
            }
            else {
                Write-Status "Storage link already exists"
            }
        }
        
        # Create upload directories using Artisan tinker
        $tinkerScript = @"
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

`$disk = Config::get('upload.profile_pictures.disk', 'public');
`$path = Config::get('upload.profile_pictures.path', 'profile-pictures');

if (!Storage::disk(`$disk)->exists(`$path)) {
    Storage::disk(`$disk)->makeDirectory(`$path);
    echo 'Created directory: ' . `$path . PHP_EOL;
} else {
    echo 'Directory already exists: ' . `$path . PHP_EOL;
}
"@
        
        $tinkerScript | php artisan tinker
        return $true
    }
    catch {
        Write-Error "Failed to setup storage: $($_.Exception.Message)"
        return $false
    }
}

function Invoke-Tests {
    if ($SkipTests) {
        Write-Warning "Skipping tests as requested"
        return $true
    }
    
    Write-Status "Running upload system tests..."
    
    try {
        php artisan test --filter=ProfilePictureService --stop-on-failure
        if ($LASTEXITCODE -ne 0) {
            throw "ProfilePictureService tests failed"
        }
        
        php artisan test --filter=UploadSecurityMiddleware --stop-on-failure
        if ($LASTEXITCODE -ne 0) {
            throw "UploadSecurityMiddleware tests failed"
        }
        
        Write-Status "All tests passed"
        return $true
    }
    catch {
        Write-Error "Tests failed: $($_.Exception.Message)"
        return $false
    }
}

function Set-Permissions {
    Write-Status "Setting up file permissions..."
    
    try {
        # Windows doesn't have chmod, but we can ensure directories exist and are accessible
        if (Test-Path "storage") {
            # Ensure storage directory is writable
            $acl = Get-Acl "storage"
            Write-Status "Storage directory permissions verified"
        }
        
        if (Test-Path "bootstrap\cache") {
            $acl = Get-Acl "bootstrap\cache"
            Write-Status "Bootstrap cache permissions verified"
        }
        
        return $true
    }
    catch {
        Write-Warning "Could not verify permissions: $($_.Exception.Message)"
        return $true  # Don't fail deployment for permission checks on Windows
    }
}

function Set-ConfigCache {
    Write-Status "Caching configuration..."
    
    try {
        php artisan config:clear
        php artisan config:cache
        
        if ($Environment -eq "production") {
            php artisan route:cache
            php artisan view:cache
            Write-Status "Production optimizations applied"
        }
        
        return $true
    }
    catch {
        Write-Error "Failed to cache configuration: $($_.Exception.Message)"
        return $false
    }
}

function Set-Cleanup {
    Write-Status "Setting up file cleanup..."
    
    try {
        # Test cleanup command
        Write-Status "Testing cleanup command..."
        php artisan app:cleanup-old-files --dry-run
        
        if ($ForceCleanup) {
            Write-Warning "Running immediate cleanup (forced)..."
            php artisan app:cleanup-old-files --force
        }
        
        Write-Status "Cleanup system configured"
        return $true
    }
    catch {
        Write-Error "Failed to setup cleanup: $($_.Exception.Message)"
        return $false
    }
}

function Invoke-HealthCheck {
    Write-Status "Running health check..."
    
    try {
        $healthCheckScript = @"
use App\Providers\UploadServiceProvider;

`$health = UploadServiceProvider::healthCheck();

echo 'Upload System Health Check:' . PHP_EOL;
echo '=========================' . PHP_EOL;

foreach (`$health as `$key => `$value) {
    if (is_bool(`$value)) {
        `$status = `$value ? 'PASS' : 'FAIL';
        echo sprintf('%-25s: %s', `$key, `$status) . PHP_EOL;
    } else {
        echo sprintf('%-25s: %s', `$key, `$value) . PHP_EOL;
    }
}

if (!`$health['overall_status']) {
    echo PHP_EOL . 'WARNING: Some health checks failed!' . PHP_EOL;
    exit(1);
}
"@
        
        $healthCheckScript | php artisan tinker
        
        if ($LASTEXITCODE -eq 0) {
            Write-Status "Health check passed"
            return $true
        }
        else {
            Write-Error "Health check failed. Please review the issues above."
            return $false
        }
    }
    catch {
        Write-Error "Failed to run health check: $($_.Exception.Message)"
        return $false
    }
}

function Show-Summary {
    Write-Host ""
    Write-Header "Deployment Summary"
    
    $envContent = Get-Content ".env" -Raw
    $uploadDisk = if ($envContent -match "UPLOAD_DISK=(.+)") { $matches[1] } else { "unknown" }
    $maxFileSize = if ($envContent -match "UPLOAD_MAX_FILE_SIZE=(.+)") { $matches[1] } else { "unknown" }
    $rateLimiting = if ($envContent -match "UPLOAD_RATE_LIMIT_ENABLED=(.+)") { $matches[1] } else { "unknown" }
    $imageOptimization = if ($envContent -match "UPLOAD_OPTIMIZE_IMAGES=(.+)") { $matches[1] } else { "unknown" }
    $cleanupEnabled = if ($envContent -match "UPLOAD_CLEANUP_ENABLED=(.+)") { $matches[1] } else { "unknown" }
    
    Write-Host "Environment: " -NoNewline
    Write-Host $Environment -ForegroundColor $Yellow
    Write-Host "Upload Disk: " -NoNewline
    Write-Host $uploadDisk -ForegroundColor $Yellow
    Write-Host "Max File Size: " -NoNewline
    Write-Host "${maxFileSize}KB" -ForegroundColor $Yellow
    Write-Host "Rate Limiting: " -NoNewline
    Write-Host $rateLimiting -ForegroundColor $Yellow
    Write-Host "Image Optimization: " -NoNewline
    Write-Host $imageOptimization -ForegroundColor $Yellow
    Write-Host "Cleanup Enabled: " -NoNewline
    Write-Host $cleanupEnabled -ForegroundColor $Yellow
    
    Write-Host ""
    Write-Host "Next Steps:" -ForegroundColor $Green
    Write-Host "1. Test file upload: Upload a profile picture through your app"
    Write-Host "2. Monitor logs: Get-Content storage\logs\laravel.log -Tail 50 | Select-String 'upload'"
    Write-Host "3. Check statistics: php artisan tinker --execute='use App\\Providers\\UploadServiceProvider; var_dump(UploadServiceProvider::getUploadStats());'"
    Write-Host "4. Setup monitoring for production environment"
    
    Write-Host ""
    Write-Host "Useful Commands:" -ForegroundColor $Green
    Write-Host "- Health check: php artisan tinker --execute='use App\\Providers\\UploadServiceProvider; var_dump(UploadServiceProvider::healthCheck());'"
    Write-Host "- Manual cleanup: php artisan app:cleanup-old-files --dry-run"
    Write-Host "- View upload stats: php artisan tinker --execute='use App\\Providers\\UploadServiceProvider; var_dump(UploadServiceProvider::getUploadStats());'"
    Write-Host ""
}

function Show-Help {
    Write-Host "Upload System Deployment Script for Windows" -ForegroundColor $Blue
    Write-Host ""
    Write-Host "Usage: .\deploy-upload-system.ps1 [parameters]"
    Write-Host ""
    Write-Host "Parameters:"
    Write-Host "  -Environment     : development|staging|production (default: development)"
    Write-Host "  -SkipTests       : Skip running tests (default: false)"
    Write-Host "  -ForceCleanup    : Run immediate cleanup (default: false)"
    Write-Host "  -Help            : Show this help message"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\deploy-upload-system.ps1                                    # Development with tests"
    Write-Host "  .\deploy-upload-system.ps1 -Environment production            # Production deployment"
    Write-Host "  .\deploy-upload-system.ps1 -Environment staging -SkipTests    # Staging without tests"
    Write-Host "  .\deploy-upload-system.ps1 -Environment production -ForceCleanup  # Production with cleanup"
    Write-Host ""
}

function Main {
    if ($Help) {
        Show-Help
        return
    }
    
    Write-Header "Upload System Deployment Script"
    Write-Host "Environment: " -NoNewline
    Write-Host $Environment -ForegroundColor $Yellow
    Write-Host "Skip Tests: " -NoNewline
    Write-Host $SkipTests -ForegroundColor $Yellow
    Write-Host "Force Cleanup: " -NoNewline
    Write-Host $ForceCleanup -ForegroundColor $Yellow
    Write-Host ""
    
    Write-Status "Starting upload system deployment..."
    
    # Check prerequisites
    if (-not (Test-Command "php")) {
        Write-Error "PHP is not installed or not in PATH"
        exit 1
    }
    
    if (-not (Test-Path "artisan")) {
        Write-Error "Laravel artisan not found. Are you in the project root?"
        exit 1
    }
    
    # Run deployment steps
    $steps = @(
        { Test-PHPExtensions },
        { Set-Environment },
        { Set-Storage },
        { Set-Permissions },
        { Set-ConfigCache },
        { Invoke-Tests },
        { Set-Cleanup },
        { Invoke-HealthCheck }
    )
    
    foreach ($step in $steps) {
        if (-not (& $step)) {
            Write-Error "Deployment failed at step: $($step.ToString())"
            exit 1
        }
    }
    
    Show-Summary
    Write-Status "Upload system deployment completed successfully!"
}

# Run main function
Main