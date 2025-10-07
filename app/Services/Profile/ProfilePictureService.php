<?php

namespace App\Services\Profile;

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

            // Validate file integrity
            if (!$file->isValid()) {
                throw new \Exception('File upload tidak valid');
            }

            // Enforce strict allowed mime types (disallow SVG to avoid XSS payloads unless sanitized separately)
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
            $mime = $file->getMimeType();
            if (!in_array($mime, $allowedMimes, true)) {
                throw new \Exception('Tipe file tidak diizinkan');
            }

            // Enforce max file size 5MB
            if ($file->getSize() > 5 * 1024 * 1024) {
                throw new \Exception('Ukuran file melebihi batas 5MB');
            }

            Log::info('Validated image mime type', ['mime_type' => $mime]);

            // Delete old profile picture if exists
            if ($oldPicturePath) {
                // Prevent directory traversal
                $oldPictureBasename = basename($oldPicturePath);
                $oldPath = public_path('img/' . $oldPictureBasename);
                if (str_contains($oldPictureBasename, '..')) {
                    Log::warning('Attempted traversal in old picture path', ['path' => $oldPicturePath]);
                } elseif (file_exists($oldPath) && is_file($oldPath)) {
                    if (!@unlink($oldPath)) {
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
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            $allowedExtensions = ['jpg','jpeg','png','gif','webp','bmp','tiff'];
            if (!in_array($extension, $allowedExtensions, true)) {
                // Fallback to jpg if extension suspicious
                $extension = 'jpg';
            }
            $filename = time() . '_' . ($userId ?? 'u') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
            
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