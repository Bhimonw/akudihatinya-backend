<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Exception;

class ProfilePictureService
{
    private array $config;
    
    public function __construct()
    {
        $this->config = Config::get('upload.profile_pictures');
    }
    
    /**
     * Get configuration value with fallback
     */
    private function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }
    
    /**
     * Get storage disk
     */
    private function getDisk(): string
    {
        return $this->getConfig('disk', Config::get('upload.default_disk', 'public'));
    }
    
    /**
     * Get storage path
     */
    private function getStoragePath(): string
    {
        return $this->getConfig('path', 'profile-pictures');
    }
    
    /**
     * Get max file size in bytes
     */
    private function getMaxFileSize(): int
    {
        return $this->getConfig('max_size', 2048) * 1024; // Convert KB to bytes
    }
    
    /**
     * Get allowed MIME types
     */
    private function getAllowedMimes(): array
    {
        return $this->getConfig('allowed_mimes', [
            'image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'
        ]);
    }
    
    /**
     * Get allowed extensions
     */
    private function getAllowedExtensions(): array
    {
        return $this->getConfig('allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }
    
    /**
     * Get max image dimensions
     */
    private function getMaxImageSize(): int
    {
        return $this->getConfig('dimensions.max_width', 800);
    }
    
    /**
     * Check if optimization is enabled
     */
    private function isOptimizationEnabled(): bool
    {
        return $this->getConfig('optimization.enabled', true);
    }
    
    /**
     * Get image quality for optimization
     */
    private function getImageQuality(): int
    {
        return $this->getConfig('optimization.quality', 85);
    }
    
    /**
     * Get PNG compression level
     */
    private function getPngCompression(): int
    {
        return $this->getConfig('optimization.png_compression', 6);
    }
    
    /**
     * Check if transparency should be preserved
     */
    private function shouldPreserveTransparency(): bool
    {
        return $this->getConfig('optimization.preserve_transparency', true);
    }
    
    public function uploadProfilePicture($file, $oldPicturePath = null, $userId = null)
    {
        try {
            $disk = $this->getDisk();
            $storagePath = $this->getStoragePath();
            
            // Enhanced logging for debugging
            Log::info('=== UPLOAD DEBUG START ===', [
                'user_id' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'old_picture' => $oldPicturePath,
                'disk' => $disk,
                'storage_path' => $storagePath,
                'temp_path' => $file->getPathname(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError()
            ]);

            // Check storage disk configuration
            $diskConfig = Config::get("filesystems.disks.{$disk}");
            Log::info('Storage disk configuration', [
                'disk' => $disk,
                'config' => $diskConfig
            ]);

            // Check if storage directory exists and is writable
            $fullStoragePath = storage_path('app/public/' . $storagePath);
            Log::info('Storage directory check', [
                'full_path' => $fullStoragePath,
                'exists' => is_dir($fullStoragePath),
                'writable' => is_writable($fullStoragePath),
                'permissions' => is_dir($fullStoragePath) ? substr(sprintf('%o', fileperms($fullStoragePath)), -4) : 'N/A'
            ]);

            // Create directory if it doesn't exist
            if (!is_dir($fullStoragePath)) {
                Log::info('Creating storage directory', ['path' => $fullStoragePath]);
                if (!mkdir($fullStoragePath, 0755, true)) {
                    throw new \Exception('Failed to create storage directory: ' . $fullStoragePath);
                }
                Log::info('Storage directory created successfully');
            }

            // Validate file
            $this->validateFile($file);
            Log::info('File validation passed');

            // Delete old profile picture if exists and cleanup is enabled
            if ($oldPicturePath && Config::get('upload.cleanup.delete_old_on_update', true)) {
                $this->deleteOldPicture($oldPicturePath);
            }

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = $this->generateUniqueFilename($extension, $userId);
            
            Log::info('Generated filename', [
                'filename' => $filename,
                'extension' => $extension
            ]);

            // Store file using configured disk with enhanced debugging
            Log::info('Attempting to store file', [
                'storage_path' => $storagePath,
                'filename' => $filename,
                'disk' => $disk,
                'full_target_path' => $storagePath . '/' . $filename
            ]);

            $storedPath = $file->storeAs(
                $storagePath,
                $filename,
                $disk
            );
            
            Log::info('Store operation result', [
                'stored_path' => $storedPath,
                'success' => !empty($storedPath)
            ]);
            
            if (!$storedPath) {
                throw new \Exception('Gagal menyimpan file ke storage - storeAs returned false/null');
            }

            // Verify file was actually saved
            $fileExists = Storage::disk($disk)->exists($storedPath);
            $fileSize = $fileExists ? Storage::disk($disk)->size($storedPath) : 0;
            
            Log::info('File verification after storage', [
                'stored_path' => $storedPath,
                'file_exists' => $fileExists,
                'file_size' => $fileSize,
                'original_size' => $file->getSize()
            ]);

            if (!$fileExists) {
                throw new \Exception('File was stored but cannot be found afterwards: ' . $storedPath);
            }

            if ($fileSize === 0) {
                throw new \Exception('File was stored but has zero size: ' . $storedPath);
            }

            // Get absolute path for verification
            try {
                $absolutePath = Storage::disk($disk)->path($storedPath);
                Log::info('Absolute path verification', [
                    'absolute_path' => $absolutePath,
                    'file_exists_absolute' => file_exists($absolutePath),
                    'file_size_absolute' => file_exists($absolutePath) ? filesize($absolutePath) : 0
                ]);
            } catch (\Exception $pathError) {
                Log::warning('Could not get absolute path', ['error' => $pathError->getMessage()]);
            }

            // Optimize image if enabled and GD extension is available
            if ($this->isOptimizationEnabled()) {
                try {
                    if (extension_loaded('gd')) {
                        $fullPath = Storage::disk($disk)->path($storedPath);
                        Log::info('Starting image optimization', ['path' => $fullPath]);
                        $this->optimizeImage($fullPath);
                        
                        // Verify file still exists after optimization
                        $stillExists = Storage::disk($disk)->exists($storedPath);
                        $newSize = $stillExists ? Storage::disk($disk)->size($storedPath) : 0;
                        
                        Log::info('Image optimization completed', [
                            'still_exists' => $stillExists,
                            'new_size' => $newSize
                        ]);
                    } else {
                        Log::warning('GD extension not available, skipping image optimization');
                    }
                } catch (\Exception $optimizeError) {
                    Log::warning('Image optimization failed', [
                        'error' => $optimizeError->getMessage()
                    ]);
                    // Continue execution even if optimization fails
                }
            }

            Log::info('=== UPLOAD DEBUG SUCCESS ===', [
                'filename' => $filename,
                'stored_path' => $storedPath
            ]);

            return $filename;
            
        } catch (\Exception $e) {
            Log::error('=== UPLOAD DEBUG FAILED ===', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'temp_path' => $file->getPathname(),
                    'is_valid' => $file->isValid(),
                    'error_code' => $file->getError()
                ]
            ]);
            throw new \Exception('Gagal mengunggah gambar profil: ' . $e->getMessage());
        }
    }

    /**
     * Optimize image by resizing and compressing with automatic resize
     *
     * @param string $imagePath
     * @return void
     * @throws \Exception
     */
    private function optimizeImage(string $imagePath): void
    {
        try {
            // Get image info
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                throw new \Exception('Tidak dapat membaca informasi gambar');
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];
            $originalFileSize = filesize($imagePath);

            Log::info('Starting automatic image resize and optimization', [
                'original_dimensions' => $originalWidth . 'x' . $originalHeight,
                'original_file_size' => $originalFileSize,
                'image_type' => $imageType
            ]);

            // Get resize configuration
            $maxSize = $this->getMaxImageSize();
            $minSize = config('upload.profile_pictures.min_dimensions.width', 100);
            $autoResize = config('upload.profile_pictures.auto_resize.enabled', true);
            $resizeQuality = config('upload.profile_pictures.auto_resize.quality', 85);
            $maxFileSize = config('upload.profile_pictures.auto_resize.max_file_size', 2048000); // 2MB

            // Always resize if auto_resize is enabled or if image is too large
            $shouldResize = $autoResize || 
                           $originalWidth > $maxSize || 
                           $originalHeight > $maxSize || 
                           $originalFileSize > $maxFileSize;

            if (!$shouldResize) {
                Log::info('Image does not need resizing', [
                    'width' => $originalWidth,
                    'height' => $originalHeight,
                    'file_size' => $originalFileSize,
                    'max_size' => $maxSize,
                    'max_file_size' => $maxFileSize
                ]);
                return;
            }

            // Calculate optimal dimensions
            $newDimensions = $this->calculateOptimalDimensions(
                $originalWidth, 
                $originalHeight, 
                $maxSize, 
                $minSize
            );
            
            $newWidth = $newDimensions['width'];
            $newHeight = $newDimensions['height'];

            // Create image resource based on type
            $sourceImage = $this->createImageResource($imagePath, $imageType);
            if (!$sourceImage) {
                throw new \Exception('Gagal membuat resource gambar');
            }

            // Create new image with better quality settings
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency and improve quality
            $this->preserveImageQuality($newImage, $imageType);

            // Resize image with high quality resampling
            if (!imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight)) {
                throw new \Exception('Gagal mengubah ukuran gambar');
            }

            // Save optimized image with configured quality settings
            $this->saveOptimizedImage($newImage, $imagePath, $imageType, $resizeQuality);

            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($newImage);

            $newFileSize = filesize($imagePath);
            $compressionRatio = round((($originalFileSize - $newFileSize) / $originalFileSize) * 100, 2);

            Log::info('Image automatically resized and optimized successfully', [
                'original_size' => $originalWidth . 'x' . $originalHeight,
                'new_size' => $newWidth . 'x' . $newHeight,
                'original_file_size' => $originalFileSize,
                'new_file_size' => $newFileSize,
                'compression_ratio' => $compressionRatio . '%',
                'file_path' => $imagePath
            ]);

        } catch (\Exception $e) {
            Log::error('Automatic image resize and optimization failed', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate optimal dimensions for resizing
     */
    private function calculateOptimalDimensions(int $originalWidth, int $originalHeight, int $maxSize, int $minSize): array
    {
        // Ensure minimum size requirements
        if ($originalWidth < $minSize || $originalHeight < $minSize) {
            // If image is too small, scale up to minimum size
            if ($originalWidth < $originalHeight) {
                $newWidth = $minSize;
                $newHeight = intval(($originalHeight * $minSize) / $originalWidth);
            } else {
                $newHeight = $minSize;
                $newWidth = intval(($originalWidth * $minSize) / $originalHeight);
            }
        } else {
            // Standard resize logic - maintain aspect ratio
            if ($originalWidth > $originalHeight) {
                $newWidth = min($maxSize, $originalWidth);
                $newHeight = intval(($originalHeight * $newWidth) / $originalWidth);
            } else {
                $newHeight = min($maxSize, $originalHeight);
                $newWidth = intval(($originalWidth * $newHeight) / $originalHeight);
            }
        }

        // Ensure dimensions are at least minimum size
        $newWidth = max($newWidth, $minSize);
        $newHeight = max($newHeight, $minSize);

        return [
            'width' => $newWidth,
            'height' => $newHeight
        ];
    }

    /**
     * Create image resource from file
     */
    private function createImageResource(string $imagePath, int $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($imagePath);
            default:
                throw new \Exception('Format gambar tidak didukung untuk optimasi: ' . $imageType);
        }
    }

    /**
     * Preserve image quality and transparency
     */
    private function preserveImageQuality($newImage, int $imageType): void
    {
        // Enable anti-aliasing for better quality
        if (function_exists('imageantialias')) {
            imageantialias($newImage, true);
        }

        // Preserve transparency for PNG and GIF
        if (($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) && $this->shouldPreserveTransparency()) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        } else {
            // Set white background for JPEG
            $white = imagecolorallocate($newImage, 255, 255, 255);
            imagefill($newImage, 0, 0, $white);
        }
    }

    /**
     * Save optimized image with quality settings
     */
    private function saveOptimizedImage($newImage, string $imagePath, int $imageType, int $quality): void
    {
        $saved = false;
        $pngCompression = $this->getPngCompression();
        
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $saved = imagejpeg($newImage, $imagePath, $quality);
                break;
            case IMAGETYPE_PNG:
                // Convert quality (0-100) to PNG compression (0-9)
                $pngQuality = 9 - intval(($quality / 100) * 9);
                $saved = imagepng($newImage, $imagePath, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($newImage, $imagePath);
                break;
            case IMAGETYPE_WEBP:
                $saved = imagewebp($newImage, $imagePath, $quality);
                break;
        }

        if (!$saved) {
            throw new \Exception('Gagal menyimpan gambar yang dioptimasi');
        }
    }
    
    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @return void
     * @throws \Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \Exception('File upload tidak valid');
        }

        // Check file size
        $maxSize = $this->getMaxFileSize();
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = round($maxSize / (1024 * 1024), 1);
            throw new \Exception("Ukuran file terlalu besar. Maksimal {$maxSizeMB}MB.");
        }

        // Check mime type
        $allowedMimes = $this->getAllowedMimes();
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Format file tidak didukung. Gunakan: jpeg, png, jpg, gif, atau webp.');
        }
        
        // Additional security check - validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->getAllowedExtensions();
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('Ekstensi file tidak diizinkan.');
        }
        
        // Validate file content if security validation is enabled
        if (Config::get('upload.security.validate_file_content', true)) {
            $this->validateFileContent($file);
        }
    }
    
    /**
     * Validate file content for security
     */
    private function validateFileContent(UploadedFile $file): void
    {
        try {
            // Check if file is actually an image
            $imageInfo = getimagesize($file->getPathname());
            if (!$imageInfo) {
                throw new \Exception('File bukan gambar yang valid.');
            }
            
            // Check minimum dimensions if configured
            $minWidth = $this->getConfig('dimensions.min_width', 0);
            $minHeight = $this->getConfig('dimensions.min_height', 0);
            
            if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
                throw new \Exception("Dimensi gambar terlalu kecil. Minimal {$minWidth}x{$minHeight} pixels.");
            }
            
        } catch (\Exception $e) {
            throw new \Exception('Validasi konten file gagal: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete old profile picture
     *
     * @param string $oldPicturePath
     * @return void
     */
    private function deleteOldPicture(string $oldPicturePath): void
    {
        try {
            $disk = $this->getDisk();
            $storagePath = $this->getStoragePath();
            $fallbackDisk = Config::get('upload.fallback_disk', 'local');
            
            // Try to delete from current storage location first
            $currentPath = $storagePath . '/' . $oldPicturePath;
            if (Storage::disk($disk)->exists($currentPath)) {
                Storage::disk($disk)->delete($currentPath);
                
                if (Config::get('upload.logging.enabled', true)) {
                    Log::info('Old profile picture deleted from current storage', [
                        'path' => $currentPath,
                        'disk' => $disk
                    ]);
                }
                return;
            }
            
            // Try fallback disk if different from current disk
            if ($fallbackDisk !== $disk) {
                if (Storage::disk($fallbackDisk)->exists($currentPath)) {
                    Storage::disk($fallbackDisk)->delete($currentPath);
                    
                    if (Config::get('upload.logging.enabled', true)) {
                        Log::info('Old profile picture deleted from fallback storage', [
                            'path' => $currentPath,
                            'disk' => $fallbackDisk
                        ]);
                    }
                    return;
                }
            }
            
            // Fallback: delete from old public/img location for backward compatibility
            $legacyPath = 'img/' . $oldPicturePath;
            if (Storage::disk('public')->exists($legacyPath)) {
                Storage::disk('public')->delete($legacyPath);
                
                if (Config::get('upload.logging.enabled', true)) {
                    Log::info('Old profile picture deleted from legacy location', [
                        'path' => $legacyPath
                    ]);
                }
                return;
            }
            
            // Last resort: try direct file system access for very old files
            $publicPath = 'img/' . $oldPicturePath;
            if (file_exists(public_path($publicPath))) {
                unlink(public_path($publicPath));
                
                if (Config::get('upload.logging.enabled', true)) {
                    Log::info('Old profile picture deleted from public directory', [
                        'path' => $publicPath
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to delete old profile picture', [
                'path' => $oldPicturePath,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Generate unique filename
     *
     * @param string $extension
     * @param int|null $userId
     * @return string
     */
    private function generateUniqueFilename(string $extension, ?int $userId = null): string
    {
        // Generate unique names for security if enabled
        if (Config::get('upload.security.generate_unique_names', true)) {
            $timestamp = time();
            $userPart = $userId ? "user_{$userId}" : 'temp';
            $randomPart = Str::random(12); // Longer random string for better security
            $hashPart = substr(md5(uniqid()), 0, 8); // Additional uniqueness
            
            return "{$timestamp}_{$userPart}_{$randomPart}_{$hashPart}.{$extension}";
        } else {
            // Simple naming for development/testing
            $userPart = $userId ? "user_{$userId}" : 'temp';
            $timestamp = time();
            
            return "{$userPart}_{$timestamp}.{$extension}";
        }
    }
    
    /**
     * Get the URL for a profile picture
     */
    public function getProfilePictureUrl(?string $filename): string
    {
        if (!$filename) {
            return '';
        }
        
        // Check if CDN is enabled
        if (Config::get('upload.cdn.enabled', false)) {
            $cdnBaseUrl = Config::get('upload.cdn.base_url');
            $cdnPath = Config::get('upload.cdn.profile_pictures_path', '/profile-pictures');
            return rtrim($cdnBaseUrl, '/') . $cdnPath . '/' . $filename;
        }
        
        // Use storage URL
        $storageUrl = Config::get('upload.storage_url');
        $storagePath = $this->getStoragePath();
        
        return rtrim($storageUrl, '/') . '/' . $storagePath . '/' . $filename;
    }
    
    /**
     * Check if file exists in storage
     */
    public function fileExists(string $filename): bool
    {
        $disk = $this->getDisk();
        $storagePath = $this->getStoragePath();
        $fullPath = $storagePath . '/' . $filename;
        
        return Storage::disk($disk)->exists($fullPath);
    }

    /**
     * Get detailed upload status for debugging
     */
    public function getUploadStatus(): array
    {
        try {
            $disk = $this->getDisk();
            $storagePath = $this->getStoragePath();
            $diskConfig = Config::get("filesystems.disks.{$disk}");
            
            // Check storage directory
            $fullStoragePath = storage_path('app/public/' . $storagePath);
            
            // Count files in storage
            $fileCount = 0;
            $totalSize = 0;
            
            if (Storage::disk($disk)->exists($storagePath)) {
                $files = Storage::disk($disk)->files($storagePath);
                $fileCount = count($files);
                
                foreach ($files as $file) {
                    $totalSize += Storage::disk($disk)->size($file);
                }
            }
            
            // Check disk space
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            
            return [
                'status' => 'operational',
                'disk' => [
                    'name' => $disk,
                    'config' => $diskConfig,
                    'storage_path' => $storagePath,
                    'full_storage_path' => $fullStoragePath,
                    'directory_exists' => is_dir($fullStoragePath),
                    'directory_writable' => is_writable($fullStoragePath),
                    'permissions' => is_dir($fullStoragePath) ? substr(sprintf('%o', fileperms($fullStoragePath)), -4) : 'N/A'
                ],
                'files' => [
                    'count' => $fileCount,
                    'total_size' => $totalSize,
                    'total_size_mb' => round($totalSize / 1024 / 1024, 2)
                ],
                'system' => [
                    'free_space' => $freeSpace,
                    'total_space' => $totalSpace,
                    'free_space_mb' => round($freeSpace / 1024 / 1024, 2),
                    'usage_percent' => round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2)
                ],
                'php' => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'memory_limit' => ini_get('memory_limit'),
                    'gd_extension' => extension_loaded('gd'),
                    'fileinfo_extension' => extension_loaded('fileinfo')
                ],
                'config' => [
                    'max_size_kb' => $this->getConfig('max_size', 2048),
                    'allowed_extensions' => $this->getAllowedExtensions(),
                    'optimization_enabled' => $this->isOptimizationEnabled(),
                    'logging_enabled' => Config::get('upload.logging.enabled', true)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test file upload functionality
     */
    public function testUpload(): array
    {
        try {
            $disk = $this->getDisk();
            $storagePath = $this->getStoragePath();
            
            // Create test content
            $testContent = 'Test upload - ' . date('Y-m-d H:i:s');
            $testFilename = 'test_upload_' . time() . '.txt';
            $testPath = $storagePath . '/' . $testFilename;
            
            Log::info('Testing upload functionality', [
                'test_filename' => $testFilename,
                'test_path' => $testPath,
                'disk' => $disk
            ]);
            
            // Test file creation
            $stored = Storage::disk($disk)->put($testPath, $testContent);
            
            if (!$stored) {
                return [
                    'success' => false,
                    'error' => 'Failed to store test file'
                ];
            }
            
            // Test file existence
            $exists = Storage::disk($disk)->exists($testPath);
            $size = $exists ? Storage::disk($disk)->size($testPath) : 0;
            
            // Test file content
            $retrievedContent = $exists ? Storage::disk($disk)->get($testPath) : '';
            $contentMatch = $retrievedContent === $testContent;
            
            // Clean up test file
            if ($exists) {
                Storage::disk($disk)->delete($testPath);
            }
            
            return [
                'success' => $exists && $contentMatch,
                'details' => [
                    'stored' => $stored,
                    'exists' => $exists,
                    'size' => $size,
                    'content_match' => $contentMatch,
                    'expected_size' => strlen($testContent),
                    'cleaned_up' => !Storage::disk($disk)->exists($testPath)
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Upload test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}