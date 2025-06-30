<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupOldFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-old-files 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days= : Number of days to retain files (overrides config)}
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old uploaded files and orphaned files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!Config::get('upload.cleanup.enabled', true)) {
            $this->info('File cleanup is disabled in configuration.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $retentionDays = $this->option('days') ?? Config::get('upload.cleanup.retention_days', 30);
        $force = $this->option('force');

        $this->info("Starting file cleanup process...");
        $this->info("Retention period: {$retentionDays} days");
        $this->info("Dry run mode: " . ($dryRun ? 'Yes' : 'No'));
        $this->line('');

        if (!$force && !$dryRun) {
            if (!$this->confirm('This will permanently delete old files. Continue?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $totalDeleted = 0;
        $totalSize = 0;

        // Cleanup profile pictures
        $result = $this->cleanupProfilePictures($retentionDays, $dryRun);
        $totalDeleted += $result['count'];
        $totalSize += $result['size'];

        // Cleanup orphaned files if enabled
        if (Config::get('upload.cleanup.orphaned_files_cleanup', false)) {
            $result = $this->cleanupOrphanedFiles($dryRun);
            $totalDeleted += $result['count'];
            $totalSize += $result['size'];
        }

        $this->line('');
        $this->info("Cleanup completed!");
        $this->info("Files processed: {$totalDeleted}");
        $this->info("Space freed: " . $this->formatBytes($totalSize));

        // Log the cleanup operation
        if (Config::get('upload.logging.enabled', true)) {
            Log::info('File cleanup completed', [
                'retention_days' => $retentionDays,
                'files_deleted' => $totalDeleted,
                'space_freed' => $totalSize,
                'dry_run' => $dryRun
            ]);
        }

        return 0;
    }

    /**
     * Cleanup old profile pictures
     */
    private function cleanupProfilePictures(int $retentionDays, bool $dryRun): array
    {
        $this->info('🖼️  Cleaning up old profile pictures...');
        
        $disk = Config::get('upload.profile_pictures.disk', 'public');
        $path = Config::get('upload.profile_pictures.path', 'profile-pictures');
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        $files = Storage::disk($disk)->files($path);
        $deletedCount = 0;
        $deletedSize = 0;
        
        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();
        
        foreach ($files as $file) {
            $progressBar->advance();
            
            try {
                $lastModified = Carbon::createFromTimestamp(
                    Storage::disk($disk)->lastModified($file)
                );
                
                if ($lastModified->lt($cutoffDate)) {
                    $filename = basename($file);
                    
                    // Check if file is still referenced in database
                    $isReferenced = DB::table('users')
                        ->where('profile_picture', $filename)
                        ->exists();
                    
                    if (!$isReferenced) {
                        $fileSize = Storage::disk($disk)->size($file);
                        
                        if (!$dryRun) {
                            Storage::disk($disk)->delete($file);
                        }
                        
                        $deletedCount++;
                        $deletedSize += $fileSize;
                        
                        if ($dryRun) {
                            $this->line("\n[DRY RUN] Would delete: {$file} (" . $this->formatBytes($fileSize) . ")");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("\nError processing file {$file}: " . $e->getMessage());
            }
        }
        
        $progressBar->finish();
        $this->line('');
        
        if ($deletedCount > 0) {
            $action = $dryRun ? 'Would delete' : 'Deleted';
            $this->info("{$action} {$deletedCount} old profile pictures (" . $this->formatBytes($deletedSize) . ")");
        } else {
            $this->info('No old profile pictures found for cleanup.');
        }
        
        return ['count' => $deletedCount, 'size' => $deletedSize];
    }

    /**
     * Cleanup orphaned files (files not referenced in database)
     */
    private function cleanupOrphanedFiles(bool $dryRun): array
    {
        $this->info('🗑️  Cleaning up orphaned files...');
        
        $disk = Config::get('upload.profile_pictures.disk', 'public');
        $path = Config::get('upload.profile_pictures.path', 'profile-pictures');
        
        $files = Storage::disk($disk)->files($path);
        $referencedFiles = DB::table('users')
            ->whereNotNull('profile_picture')
            ->pluck('profile_picture')
            ->toArray();
        
        $deletedCount = 0;
        $deletedSize = 0;
        
        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();
        
        foreach ($files as $file) {
            $progressBar->advance();
            
            $filename = basename($file);
            
            if (!in_array($filename, $referencedFiles)) {
                try {
                    $fileSize = Storage::disk($disk)->size($file);
                    
                    if (!$dryRun) {
                        Storage::disk($disk)->delete($file);
                    }
                    
                    $deletedCount++;
                    $deletedSize += $fileSize;
                    
                    if ($dryRun) {
                        $this->line("\n[DRY RUN] Would delete orphaned: {$file} (" . $this->formatBytes($fileSize) . ")");
                    }
                } catch (\Exception $e) {
                    $this->error("\nError processing orphaned file {$file}: " . $e->getMessage());
                }
            }
        }
        
        $progressBar->finish();
        $this->line('');
        
        if ($deletedCount > 0) {
            $action = $dryRun ? 'Would delete' : 'Deleted';
            $this->info("{$action} {$deletedCount} orphaned files (" . $this->formatBytes($deletedSize) . ")");
        } else {
            $this->info('No orphaned files found for cleanup.');
        }
        
        return ['count' => $deletedCount, 'size' => $deletedSize];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}