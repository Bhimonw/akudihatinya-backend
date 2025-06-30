<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Scheduling\Schedule;
use App\Services\ProfilePictureService;
use App\Console\Commands\CleanupOldFiles;

class UploadServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register ProfilePictureService as singleton
        $this->app->singleton(ProfilePictureService::class, function ($app) {
            return new ProfilePictureService();
        });

        // Register cleanup command
        $this->commands([
            CleanupOldFiles::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Validate upload configuration on boot
        $this->validateUploadConfiguration();

        // Setup storage disks if needed
        $this->setupStorageDisks();

        // Schedule cleanup tasks
        $this->scheduleCleanupTasks();

        // Register upload macros
        $this->registerUploadMacros();
    }

    /**
     * Validate upload configuration
     */
    private function validateUploadConfiguration(): void
    {
        $requiredConfigs = [
            'upload.profile_pictures.disk',
            'upload.profile_pictures.path',
            'upload.profile_pictures.max_size',
            'upload.profile_pictures.allowed_mimes',
        ];

        foreach ($requiredConfigs as $config) {
            if (Config::get($config) === null) {
                throw new \Exception("Missing required upload configuration: {$config}");
            }
        }

        // Validate disk exists
        $disk = Config::get('upload.profile_pictures.disk');
        if (!array_key_exists($disk, Config::get('filesystems.disks', []))) {
            throw new \Exception("Upload disk '{$disk}' is not configured in filesystems.php");
        }

        // Validate max file size is reasonable
        $maxSize = Config::get('upload.profile_pictures.max_size');
        if ($maxSize > 10240) { // 10MB
            \Log::warning('Upload max file size is very large', ['max_size' => $maxSize]);
        }
    }

    /**
     * Setup storage disks with additional configuration
     */
    private function setupStorageDisks(): void
    {
        // Add CDN URL resolver if CDN is enabled
        if (Config::get('upload.cdn.enabled', false)) {
            $this->setupCdnUrlResolver();
        }

        // Ensure storage directories exist
        $this->ensureStorageDirectories();
    }

    /**
     * Setup CDN URL resolver
     */
    private function setupCdnUrlResolver(): void
    {
        $cdnBaseUrl = Config::get('upload.cdn.base_url');
        if (!$cdnBaseUrl) {
            return;
        }

        // Override storage URL for CDN
        Config::set('filesystems.disks.public.url', $cdnBaseUrl);
    }

    /**
     * Ensure storage directories exist
     */
    private function ensureStorageDirectories(): void
    {
        $disk = Config::get('upload.profile_pictures.disk', 'public');
        $path = Config::get('upload.profile_pictures.path', 'profile-pictures');

        try {
            if (!Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->makeDirectory($path);
                \Log::info('Created storage directory', ['disk' => $disk, 'path' => $path]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to create storage directory', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Schedule cleanup tasks
     */
    private function scheduleCleanupTasks(): void
    {
        if (!Config::get('upload.cleanup.enabled', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            
            $cleanupSchedule = Config::get('upload.cleanup.cleanup_schedule', 'daily');
            
            switch ($cleanupSchedule) {
                case 'hourly':
                    $schedule->command('app:cleanup-old-files --force')->hourly();
                    break;
                case 'daily':
                    $schedule->command('app:cleanup-old-files --force')->daily();
                    break;
                case 'weekly':
                    $schedule->command('app:cleanup-old-files --force')->weekly();
                    break;
                case 'monthly':
                    $schedule->command('app:cleanup-old-files --force')->monthly();
                    break;
            }
        });
    }

    /**
     * Register upload-related macros
     */
    private function registerUploadMacros(): void
    {
        // Add macro to get upload URL
        \Illuminate\Http\UploadedFile::macro('getUploadUrl', function () {
            $service = app(ProfilePictureService::class);
            return $service->getProfilePictureUrl($this->getClientOriginalName());
        });

        // Add macro to validate upload
        \Illuminate\Http\UploadedFile::macro('validateUpload', function () {
            $service = app(ProfilePictureService::class);
            
            // This would need to be implemented in ProfilePictureService
            // return $service->validateFile($this);
            return true;
        });
    }

    /**
     * Get upload statistics
     */
    public static function getUploadStats(): array
    {
        $disk = Config::get('upload.profile_pictures.disk', 'public');
        $path = Config::get('upload.profile_pictures.path', 'profile-pictures');

        try {
            $files = Storage::disk($disk)->files($path);
            $totalSize = 0;
            $fileCount = count($files);

            foreach ($files as $file) {
                $totalSize += Storage::disk($disk)->size($file);
            }

            return [
                'total_files' => $fileCount,
                'total_size' => $totalSize,
                'total_size_formatted' => static::formatBytes($totalSize),
                'disk' => $disk,
                'path' => $path,
                'last_updated' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'total_files' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B'
            ];
        }
    }

    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Health check for upload system
     */
    public static function healthCheck(): array
    {
        $checks = [];

        // Check storage disk accessibility
        $disk = Config::get('upload.profile_pictures.disk', 'public');
        try {
            Storage::disk($disk)->put('health-check.txt', 'test');
            Storage::disk($disk)->delete('health-check.txt');
            $checks['storage_writable'] = true;
        } catch (\Exception $e) {
            $checks['storage_writable'] = false;
            $checks['storage_error'] = $e->getMessage();
        }

        // Check GD extension
        $checks['gd_extension'] = extension_loaded('gd');

        // Check configuration
        $checks['config_valid'] = true;
        try {
            $provider = new static(app());
            $provider->validateUploadConfiguration();
        } catch (\Exception $e) {
            $checks['config_valid'] = false;
            $checks['config_error'] = $e->getMessage();
        }

        // Check disk space (if local storage)
        if ($disk === 'public' || $disk === 'local') {
            $storagePath = storage_path();
            $freeSpace = disk_free_space($storagePath);
            $checks['disk_space_free'] = $freeSpace;
            $checks['disk_space_free_formatted'] = static::formatBytes($freeSpace);
            $checks['disk_space_low'] = $freeSpace < (100 * 1024 * 1024); // Less than 100MB
        }

        $checks['overall_status'] = $checks['storage_writable'] && 
                                   $checks['gd_extension'] && 
                                   $checks['config_valid'] &&
                                   (!isset($checks['disk_space_low']) || !$checks['disk_space_low']);

        return $checks;
    }
}