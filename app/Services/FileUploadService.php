<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload profile picture to resources/img directory
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

            // Move file to destination
            $file->move($uploadPath, $fileName);
            
            Log::info('Profile picture uploaded successfully', [
                'filename' => $fileName,
                'path' => $uploadPath . '/' . $fileName
            ]);

            return config('upload.profile_pictures.path') . '/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Failed to upload profile picture', [
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
     * Validate if file is a valid image
     *
     * @param UploadedFile $file
     * @return bool
     */
    public function isValidImage(UploadedFile $file): bool
    {
        $config = config('upload.profile_pictures');
        $allowedMimes = array_map(fn($mime) => 'image/' . $mime, $config['allowed_mimes']);
        $maxSize = $config['max_size'] * 1024; // Convert KB to bytes
        
        // Check MIME type and file size
        if (!in_array($file->getMimeType(), $allowedMimes) || $file->getSize() > $maxSize) {
            return false;
        }
        
        // Check image dimensions
        $imageInfo = getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return false;
        }
        
        [$width, $height] = $imageInfo;
        $dimensions = $config['dimensions'];
        
        return $width >= $dimensions['min_width'] && 
               $height >= $dimensions['min_height'] && 
               $width <= $dimensions['max_width'] && 
               $height <= $dimensions['max_height'];
    }

    /**
     * Get file URL for display
     *
     * @param string|null $filePath
     * @return string|null
     */
    public function getFileUrl(?string $filePath): ?string
    {
        if (!$filePath) {
            return null;
        }
        
        return asset($filePath);
    }
}