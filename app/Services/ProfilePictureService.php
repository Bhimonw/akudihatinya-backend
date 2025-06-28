<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Exception;

class ProfilePictureService
{
    /**
     * Upload and process profile picture
     *
     * @param UploadedFile $file
     * @param string|null $oldPicturePath
     * @param int $userId
     * @return string|null
     * @throws Exception
     */
    public function uploadProfilePicture(UploadedFile $file, ?string $oldPicturePath, int $userId): ?string
    {
        try {
            // Delete old image if exists
            if ($oldPicturePath) {
                $this->deleteOldPicture($oldPicturePath);
            }

            // Generate unique filename
            $fileName = $this->generateFileName($file);
            $destinationPath = resource_path('img');

            // Create directory if it doesn't exist
            $this->ensureDirectoryExists($destinationPath);

            // Move file
            $moved = $file->move($destinationPath, $fileName);
            if (!$moved) {
                throw new Exception('Failed to move uploaded file');
            }

            // Resize image
            $fullImagePath = $destinationPath . DIRECTORY_SEPARATOR . $fileName;
            $this->resizeImage($fullImagePath, 200, 200);

            $relativePath = 'img/' . $fileName;

            Log::info('Profile picture uploaded successfully', [
                'user_id' => $userId,
                'path' => $relativePath,
                'file_exists' => file_exists($fullImagePath)
            ]);

            return $relativePath;
        } catch (Exception $e) {
            Log::error('Failed to upload profile picture', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Delete old profile picture
     *
     * @param string $picturePath
     * @return void
     */
    private function deleteOldPicture(string $picturePath): void
    {
        $oldImagePath = resource_path($picturePath);
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
            Log::info('Deleted old profile picture', ['path' => $picturePath]);
        }
    }

    /**
     * Generate unique filename
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateFileName(UploadedFile $file): string
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        
        return time() . '_' . $sanitizedName . '.' . $extension;
    }

    /**
     * Ensure directory exists
     *
     * @param string $path
     * @return void
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Resize image to specified dimensions
     *
     * @param string $imagePath
     * @param int $width
     * @param int $height
     * @return void
     */
    private function resizeImage(string $imagePath, int $width, int $height): void
    {
        // Get image info
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return;
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $imageType = $imageInfo[2];

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
            default:
                return; // Unsupported image type
        }

        if (!$sourceImage) {
            return;
        }

        // Create new image
        $newImage = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        }

        // Resize image
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);

        // Save resized image
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $imagePath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $imagePath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $imagePath);
                break;
        }

        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($newImage);
    }
}