<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Exception;

class ProfilePictureService
{
    public function uploadProfilePicture($file, $oldPicturePath = null, $userId = null)
    {
        try {
            Log::info('Starting profile picture upload', [
                'user_id' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'old_picture' => $oldPicturePath
            ]);

            // Validate file
            if (!$file->isValid()) {
                throw new \Exception('File upload tidak valid');
            }

            // Optional file size check (increased limit to 10MB)
            if ($file->getSize() > 10240 * 1024) {
                Log::warning('Large file uploaded', ['size' => $file->getSize()]);
            }

            // Log mime type but don't restrict
            Log::info('File mime type', ['mime_type' => $file->getMimeType()]);
            
            // Accept any file type - let users upload what they want
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff', 'image/svg+xml'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                Log::info('Non-standard image format uploaded', ['mime_type' => $file->getMimeType()]);
                // Don't throw exception, just log it
            }

            // Delete old profile picture if exists
            if ($oldPicturePath) {
                $oldPath = public_path('img/' . $oldPicturePath);
                if (file_exists($oldPath)) {
                    if (!unlink($oldPath)) {
                        Log::warning('Failed to delete old profile picture', ['path' => $oldPath]);
                    } else {
                        Log::info('Old profile picture deleted', ['path' => $oldPath]);
                    }
                }
            }

            // Ensure directory exists and is writable
            $destinationPath = public_path('img');
            if (!is_dir($destinationPath)) {
                if (!mkdir($destinationPath, 0755, true)) {
                    throw new \Exception('Gagal membuat direktori upload');
                }
                Log::info('Created upload directory', ['path' => $destinationPath]);
            }
            
            // Check if directory is writable
            if (!is_writable($destinationPath)) {
                throw new \Exception('Direktori upload tidak dapat ditulis. Periksa permission folder.');
            }

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . ($userId ?? 'temp') . '_' . uniqid() . '.' . $extension;
            
            Log::info('Generated filename', ['filename' => $filename]);

            // Move file to public/img directory
            $fullPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;
            
            Log::info('Attempting to move file', [
                'from' => $file->getPathname(),
                'to' => $fullPath,
                'temp_file_exists' => file_exists($file->getPathname()),
                'destination_writable' => is_writable($destinationPath)
            ]);
            
            try {
                $moved = $file->move($destinationPath, $filename);
                if (!$moved) {
                    throw new \Exception('File move operation returned false');
                }
            } catch (\Exception $moveError) {
                Log::error('File move failed', [
                    'error' => $moveError->getMessage(),
                    'file_error' => $file->getError(),
                    'file_size' => $file->getSize(),
                    'destination' => $destinationPath,
                    'filename' => $filename
                ]);
                throw new \Exception('Gagal memindahkan file: ' . $moveError->getMessage());
            }
            
            // Verify file was actually moved
            if (!file_exists($fullPath)) {
                throw new \Exception('File tidak ditemukan setelah dipindahkan');
            }
            
            Log::info('File moved successfully', [
                'destination' => $fullPath,
                'file_size' => filesize($fullPath)
            ]);

            // Optimize image if GD extension is available
            try {
                if (extension_loaded('gd')) {
                    $this->optimizeImage($fullPath);
                    Log::info('Image optimized successfully');
                } else {
                    Log::warning('GD extension not available, skipping image optimization');
                }
            } catch (\Exception $optimizeError) {
                Log::warning('Image optimization failed', [
                    'error' => $optimizeError->getMessage()
                ]);
                // Continue execution even if optimization fails
            }

            return $filename;
            
        } catch (\Exception $e) {
            Log::error('Profile picture upload failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Gagal mengunggah gambar profil: ' . $e->getMessage());
        }
    }

    /**
     * Optimize image by resizing and compressing
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

            // Skip optimization if image is already small enough
            if ($originalWidth <= 400 && $originalHeight <= 400) {
                Log::info('Image already optimized, skipping resize', [
                    'width' => $originalWidth,
                    'height' => $originalHeight
                ]);
                return;
            }

            // Calculate new dimensions (max 400x400 while maintaining aspect ratio)
            $maxSize = 400;
            if ($originalWidth > $originalHeight) {
                $newWidth = $maxSize;
                $newHeight = intval(($originalHeight * $maxSize) / $originalWidth);
            } else {
                $newHeight = $maxSize;
                $newWidth = intval(($originalWidth * $maxSize) / $originalHeight);
            }

            // Create image resource based on type
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($imagePath);
                    break;
                case IMAGETYPE_BMP:
                    $sourceImage = imagecreatefrombmp($imagePath);
                    break;
                default:
                    Log::info('Image format not supported for optimization, skipping', ['type' => $imageType]);
                    return; // Skip optimization for unsupported formats
            }

            if (!$sourceImage) {
                throw new \Exception('Gagal membuat resource gambar');
            }

            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefill($newImage, 0, 0, $transparent);
            }

            // Resize image
            if (!imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight)) {
                throw new \Exception('Gagal mengubah ukuran gambar');
            }

            // Save optimized image
            $saved = false;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $saved = imagejpeg($newImage, $imagePath, 85); // Slightly lower quality for smaller file size
                    break;
                case IMAGETYPE_PNG:
                    $saved = imagepng($newImage, $imagePath, 8); // Good compression
                    break;
                case IMAGETYPE_GIF:
                    $saved = imagegif($newImage, $imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $saved = imagewebp($newImage, $imagePath, 85);
                    break;
                case IMAGETYPE_BMP:
                    $saved = imagebmp($newImage, $imagePath);
                    break;
            }

            if (!$saved) {
                throw new \Exception('Gagal menyimpan gambar yang dioptimasi');
            }

            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($newImage);

            Log::info('Image optimized successfully', [
                'original_size' => $originalWidth . 'x' . $originalHeight,
                'new_size' => $newWidth . 'x' . $newHeight,
                'file_path' => $imagePath
            ]);

        } catch (\Exception $e) {
            Log::error('Image optimization failed', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}