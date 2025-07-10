<?php

namespace App\Services\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload profile picture to resources/img directory     * Handles files of any size and optimizes them for display
     *
     * @param UploadedFile $file
     * @param string|null $oldFilePath
     * @return string|null
     */
    public function uploadProfilePicture(UploadedFile $file, ?string $oldFilePath = null): ?string
    {
        try {
            // Delete old file if exists
            if ($oldFilePath) {
                $this->deleteFile($oldFilePath);
            }

            // Generate unique filename
            $fileName = $this->generateFileName($file);
            
            // Ensure directory exists
            $uploadPath = resource_path(config('upload.profile_pictures.path'));
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $fullPath = $uploadPath . '/' . $fileName;
            
            // For large files, process in chunks instead of using move()
            if ($file->getSize() > 5 * 1024 * 1024) { // 5MB threshold
                $this->handleLargeFileUpload($file, $fullPath);
            } else {
                // Move file to destination for smaller files
                $file->move($uploadPath, $fileName);
            }
            
            // Optimize image for display if it's an image
            if (strpos($file->getMimeType(), 'image/') === 0) {
                $this->optimizeImage($fullPath);
            }
            
            Log::info('File uploaded successfully', [
                'filename' => $fileName,
                'path' => $fullPath,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType()
            ]);

            return config('upload.profile_pictures.path') . '/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Failed to upload file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Delete file from filesystem
     *
     * @param string $filePath
     * @return bool
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            $fullPath = resource_path($filePath);
            
            if (file_exists($fullPath)) {
                unlink($fullPath);
                
                Log::info('File deleted successfully', [
                    'path' => $fullPath
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete file', [
                'path' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Generate unique filename for uploaded file
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Sanitize filename
        $sanitizedName = Str::slug($originalName);
        
        return time() . '_' . $sanitizedName . '.' . $extension;
    }

    /**
     * Handle large file upload using chunked processing
     *
     * @param UploadedFile $file
     * @param string $destinationPath
     * @return void
     * @throws \Exception
     */
    private function handleLargeFileUpload(UploadedFile $file, string $destinationPath): void
    {
        $source = fopen($file->getPathname(), 'rb');
        $destination = fopen($destinationPath, 'wb');
        
        if (!$source || !$destination) {
            throw new \Exception('Failed to open file streams for large file upload');
        }
        
        try {
            // Process file in 1MB chunks
            $chunkSize = 1024 * 1024; // 1MB
            while (!feof($source)) {
                $chunk = fread($source, $chunkSize);
                fwrite($destination, $chunk);
            }
        } finally {
            fclose($source);
            fclose($destination);
        }
    }
    
    /**
     * Optimize image for display while maintaining quality
     *
     * @param string $imagePath
     * @return void
     */
    private function optimizeImage(string $imagePath): void
    {
        try {
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                return;
            }
            
            [$width, $height, $type] = $imageInfo;
            $config = config('upload.profile_pictures.dimensions');
            
            // Only resize if image is larger than max dimensions
            if ($width > $config['max_width'] || $height > $config['max_height']) {
                $this->resizeImage($imagePath, $config['max_width'], $config['max_height'], $type);
            }
            
            // Compress image to reduce file size
            $this->compressImage($imagePath, $type);
            
        } catch (\Exception $e) {
            Log::warning('Failed to optimize image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Resize image to fit within specified dimensions
     *
     * @param string $imagePath
     * @param int $maxWidth
     * @param int $maxHeight
     * @param int $imageType
     * @return void
     */
    private function resizeImage(string $imagePath, int $maxWidth, int $maxHeight, int $imageType): void
    {
        $source = $this->createImageFromType($imagePath, $imageType);
        if (!$source) {
            return;
        }
        
        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);
        
        // Create new image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Save resized image
        $this->saveImageByType($resized, $imagePath, $imageType);
        
        imagedestroy($source);
        imagedestroy($resized);
    }
    
    /**
     * Compress image to reduce file size
     *
     * @param string $imagePath
     * @param int $imageType
     * @return void
     */
    private function compressImage(string $imagePath, int $imageType): void
    {
        $source = $this->createImageFromType($imagePath, $imageType);
        if (!$source) {
            return;
        }
        
        // Save with compression
        $this->saveImageByType($source, $imagePath, $imageType, 85); // 85% quality
        
        imagedestroy($source);
    }
    
    /**
     * Create image resource from file based on type
     *
     * @param string $imagePath
     * @param int $imageType
     * @return resource|false
     */
    private function createImageFromType(string $imagePath, int $imageType)
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
                return false;
        }
    }
    
    /**
     * Save image resource to file based on type
     *
     * @param resource $image
     * @param string $imagePath
     * @param int $imageType
     * @param int $quality
     * @return void
     */
    private function saveImageByType($image, string $imagePath, int $imageType, int $quality = 100): void
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($image, $imagePath, $quality);
                break;
            case IMAGETYPE_PNG:
                // PNG compression level (0-9, where 9 is max compression)
                $pngQuality = (int)(9 - ($quality / 100) * 9);
                imagepng($image, $imagePath, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                imagegif($image, $imagePath);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($image, $imagePath, $quality);
                break;
        }
    }

    /**
     * Validate if file is a valid image (now supports any file size)
     *
     * @param UploadedFile $file
     * @return bool
     */
    public function isValidImage(UploadedFile $file): bool
    {
        $config = config('upload.profile_pictures');
        $allowedMimes = array_map(fn($mime) => 'image/' . $mime, $config['allowed_mimes']);
        
        // Check MIME type only (removed file size restriction)
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return false;
        }
        
        // Check if it's a valid image file
        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return false;
        }
        
        [$width, $height] = $imageInfo;
        $dimensions = $config['dimensions'];
        
        // Only check minimum dimensions (max will be handled by optimization)
        return $width >= $dimensions['min_width'] && 
               $height >= $dimensions['min_height'];
    }

    /**
     * Get file URL for display with optional format conversion
     *
     * @param string|null $filePath
     * @param string|null $format Convert to this format if specified (webp, jpg, png)
     * @param int|null $width Resize to this width if specified
     * @param int|null $height Resize to this height if specified
     * @return string|null
     */
    public function getFileUrl(?string $filePath, ?string $format = null, ?int $width = null, ?int $height = null): ?string
    {
        if (!$filePath) {
            return null;
        }
        
        $fullPath = resource_path($filePath);
        
        // If no conversion or resizing needed, return the original URL
        if (!$format && !$width && !$height) {
            return asset($filePath);
        }
        
        // Check if file exists
        if (!file_exists($fullPath)) {
            Log::warning('File not found for conversion', ['path' => $fullPath]);
            return asset($filePath); // Return original URL as fallback
        }
        
        try {
            // Get file info
            $pathInfo = pathinfo($fullPath);
            $imageInfo = getimagesize($fullPath);
            
            if (!$imageInfo) {
                return asset($filePath); // Not an image, return original URL
            }
            
            // Determine target format
            $targetFormat = $format ?: $pathInfo['extension'];
            
            // Create cache directory if it doesn't exist
            $cacheDir = resource_path('img/cache');
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            
            // Generate cache filename
            $cacheFilename = md5($filePath . $format . $width . $height) . '.' . $targetFormat;
            $cachePath = $cacheDir . '/' . $cacheFilename;
            
            // Check if cached version exists
            if (file_exists($cachePath)) {
                return asset('img/cache/' . $cacheFilename);
            }
            
            // Convert and resize image
            $this->convertAndResizeImage($fullPath, $cachePath, $imageInfo[2], $this->getImageTypeFromFormat($targetFormat), $width, $height);
            
            return asset('img/cache/' . $cacheFilename);
        } catch (\Exception $e) {
            Log::error('Failed to convert image for display', [
                'path' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return asset($filePath); // Return original URL as fallback
        }
    }
    
    /**
     * Convert and resize image to target format and dimensions
     *
     * @param string $sourcePath
     * @param string $targetPath
     * @param int $sourceType
     * @param int $targetType
     * @param int|null $width
     * @param int|null $height
     * @return void
     */
    private function convertAndResizeImage(string $sourcePath, string $targetPath, int $sourceType, int $targetType, ?int $width = null, ?int $height = null): void
    {
        $source = $this->createImageFromType($sourcePath, $sourceType);
        if (!$source) {
            throw new \Exception('Failed to create image resource from source');
        }
        
        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        
        // Calculate new dimensions
        if ($width && $height) {
            // Both dimensions specified
            $newWidth = $width;
            $newHeight = $height;
        } elseif ($width) {
            // Only width specified, maintain aspect ratio
            $ratio = $width / $originalWidth;
            $newWidth = $width;
            $newHeight = (int)($originalHeight * $ratio);
        } elseif ($height) {
            // Only height specified, maintain aspect ratio
            $ratio = $height / $originalHeight;
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = $height;
        } else {
            // No resizing needed
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }
        
        // Create new image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($targetType === IMAGETYPE_PNG || $targetType === IMAGETYPE_GIF || $targetType === IMAGETYPE_WEBP) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Save to target format
        $this->saveImageByType($resized, $targetPath, $targetType, 90); // 90% quality for converted images
        
        imagedestroy($source);
        imagedestroy($resized);
    }
    
    /**
     * Get image type constant from format string
     *
     * @param string $format
     * @return int
     */
    private function getImageTypeFromFormat(string $format): int
    {
        switch (strtolower($format)) {
            case 'jpg':
            case 'jpeg':
                return IMAGETYPE_JPEG;
            case 'png':
                return IMAGETYPE_PNG;
            case 'gif':
                return IMAGETYPE_GIF;
            case 'webp':
                return IMAGETYPE_WEBP;
            default:
                return IMAGETYPE_JPEG; // Default to JPEG
        }
    }
}