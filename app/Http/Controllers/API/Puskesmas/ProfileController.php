<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            
            Log::info('Profile update request received', [
                'user_id' => $user->id,
                'has_file' => $request->hasFile('profile_picture')
            ]);
            
            // Hash password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            
            if ($request->hasFile('profile_picture')) {
                Log::info('Processing profile picture upload', [
                    'user_id' => $user->id,
                    'file_name' => $request->file('profile_picture')->getClientOriginalName(),
                    'file_size' => $request->file('profile_picture')->getSize()
                ]);
                
                try {
                    // Delete old image if exists
                    if ($user->profile_picture) {
                        $oldImagePath = resource_path($user->profile_picture);
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                            Log::info('Deleted old profile picture', ['path' => $user->profile_picture]);
                        }
                    }
                    
                    $file = $request->file('profile_picture');
                    
                    // Sanitize filename
                    $originalName = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                    $fileName = time() . '_' . $sanitizedName . '.' . $extension;
                    
                    $destinationPath = resource_path('img');
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0755, true);
                    }
                    
                    $moved = $file->move($destinationPath, $fileName);
                    
                    if (!$moved) {
                        throw new \Exception('Failed to move file');
                    }
                    
                    // Resize image to 200x200
                    $fullImagePath = $destinationPath . DIRECTORY_SEPARATOR . $fileName;
                    $this->resizeImage($fullImagePath, 200, 200);
                    
                    $data['profile_picture'] = 'img/' . $fileName;
                    
                    Log::info('Profile picture uploaded successfully', [
                        'user_id' => $user->id,
                        'path' => $data['profile_picture']
                    ]);
                } catch (\Exception $uploadError) {
                    Log::error('Failed to upload profile picture', [
                        'user_id' => $user->id,
                        'error' => $uploadError->getMessage(),
                        'trace' => $uploadError->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'message' => 'Gagal mengupload foto profil',
                        'error' => $uploadError->getMessage()
                    ], 422);
                }
            }
            
            // Handle puskesmas name update for puskesmas role
            if ($user->role === 'puskesmas' && isset($data['puskesmas_name']) && $user->puskesmas) {
                $oldPuskesmasName = $user->puskesmas->name;
                $user->puskesmas->update(['name' => $data['puskesmas_name']]);
                
                Log::info('Puskesmas name updated via ProfileController', [
                    'user_id' => $user->id,
                    'puskesmas_id' => $user->puskesmas->id,
                    'old_name' => $oldPuskesmasName,
                    'new_name' => $data['puskesmas_name']
                ]);
                
                // Remove puskesmas_name from user data as it's not a user field
                unset($data['puskesmas_name']);
            }
            
            $user->update($data);
            $user->load('puskesmas'); // Load relationship
            
            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);
            
            return response()->json([
                'message' => 'Profil berhasil diupdate',
                'user' => new UserResource($user),
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update profile', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Gagal memperbarui profil',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
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
    private function resizeImage($imagePath, $width, $height)
    {
        // Get image info
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new \Exception('Invalid image file');
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $imageType = $imageInfo[2];
        
        // Skip resize if already correct size
        if ($originalWidth == $width && $originalHeight == $height) {
            return;
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
            default:
                throw new \Exception('Unsupported image type');
        }
        
        if (!$sourceImage) {
            throw new \Exception('Failed to create image resource');
        }
        
        // Create new image with target dimensions
        $targetImage = imagecreatetruecolor($width, $height);
        
        if (!$targetImage) {
            imagedestroy($sourceImage);
            throw new \Exception('Failed to create target image');
        }
        
        // Preserve transparency for PNG and GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefill($targetImage, 0, 0, $transparent);
        }
        
        // Resize image
        $resized = imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            $width, $height,
            $originalWidth, $originalHeight
        );
        
        if (!$resized) {
            imagedestroy($sourceImage);
            imagedestroy($targetImage);
            throw new \Exception('Failed to resize image');
        }
        
        // Save resized image
        $saved = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $saved = imagejpeg($targetImage, $imagePath, 90);
                break;
            case IMAGETYPE_PNG:
                $saved = imagepng($targetImage, $imagePath, 6);
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($targetImage, $imagePath);
                break;
            case IMAGETYPE_WEBP:
                $saved = imagewebp($targetImage, $imagePath, 90);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        if (!$saved) {
            throw new \Exception('Failed to save resized image');
        }
    }
}
