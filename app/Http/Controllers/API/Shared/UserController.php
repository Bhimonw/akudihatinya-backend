<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of users (Admin only)
     * GET /api/admin/users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search by name or username
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Load puskesmas relationship for puskesmas users
        $query->with('puskesmas');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user (Admin only)
     * POST /api/admin/users
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Debug: Log request data
            Log::info('Store user request received', [
                'has_file' => $request->hasFile('profile_picture'),
                'files' => $request->allFiles()
            ]);
            
            // Hash password
            $data['password'] = Hash::make($data['password']);
            
            // Use database transaction for data consistency
            $user = DB::transaction(function () use ($data, $request) {
                // Auto-create puskesmas and assign ID for puskesmas role
                if ($data['role'] === 'puskesmas') {
                    $puskesmasName = $request->input('puskesmas_name', $data['name']);
                    
                    // Create puskesmas entry first
                    $puskesmas = Puskesmas::create([
                        'name' => $puskesmasName
                    ]);
                    
                    $data['puskesmas_id'] = $puskesmas->id;
                    
                    Log::info('Auto-created puskesmas for new user', [
                        'puskesmas_id' => $puskesmas->id,
                        'puskesmas_name' => $puskesmas->name,
                        'user_name' => $data['name']
                    ]);
                }
            
                // Handle profile picture upload
                if ($request->hasFile('profile_picture')) {
                    Log::info('Processing profile picture upload for new user', [
                        'file_name' => $request->file('profile_picture')->getClientOriginalName(),
                        'file_size' => $request->file('profile_picture')->getSize()
                    ]);
                    
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
                    
                    Log::info('Profile picture uploaded successfully for new user', [
                        'path' => $data['profile_picture']
                    ]);
                }
                
                // Create user
                $user = User::create($data);
                $user->load('puskesmas'); // Load relationship
                
                return $user;
            });
            
            Log::info('User created successfully', [
                'user_id' => $user->id,
                'username' => $user->username,
                'created_by' => auth()->user()->name ?? 'system'
            ]);
            
            return response()->json([
                'message' => 'User berhasil dibuat',
                'user' => new UserResource($user)
            ], 201);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if it's a file upload error
            if (str_contains($e->getMessage(), 'Failed to move file') || 
                str_contains($e->getMessage(), 'Invalid image file')) {
                return response()->json([
                    'message' => 'Gagal mengupload foto profil',
                    'error' => config('app.debug') ? $e->getMessage() : 'File upload error'
                ], 422);
            }
            
            return response()->json([
                'message' => 'Gagal membuat user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified user (Admin only)
     * GET /api/admin/users/{id}
     */
    public function show(User $user)
    {
        $user->load('puskesmas');
        
        return response()->json([
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Update the specified user (Admin only)
     * PUT /api/admin/users/{id}
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            $data = $request->validated();
            
            // Debug: Log request data
            Log::info('Update user request received', [
                'user_id' => $user->id,
                'has_file' => $request->hasFile('profile_picture'),
                'files' => $request->allFiles()
            ]);
            
            // Hash password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            
            // Handle profile picture upload
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
                
                Log::info('Puskesmas name updated', [
                    'user_id' => $user->id,
                    'puskesmas_id' => $user->puskesmas->id,
                    'old_name' => $oldPuskesmasName,
                    'new_name' => $data['puskesmas_name']
                ]);
                
                // Remove puskesmas_name from user data as it's not a user field
                unset($data['puskesmas_name']);
            }
            
            // Handle puskesmas name update for puskesmas role
            if ($user->role === 'puskesmas' && $request->filled('puskesmas_name') && $user->puskesmas) {
                $oldPuskesmasName = $user->puskesmas->name;
                $user->puskesmas->update(['name' => $request->puskesmas_name]);
                
                Log::info('Puskesmas name updated via updateMe', [
                    'user_id' => $user->id,
                    'puskesmas_id' => $user->puskesmas->id,
                    'old_name' => $oldPuskesmasName,
                    'new_name' => $request->puskesmas_name
                ]);
            }
            
            $user->update($data);
            $user->load('puskesmas'); // Load relationship
            
            Log::info('User updated successfully', [
                'user_id' => $user->id,
                'username' => $user->username,
                'updated_by' => auth()->user()->name ?? 'system'
            ]);
            
            return response()->json([
                'message' => 'User berhasil diperbarui',
                'user' => new UserResource($user)
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Gagal memperbarui user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified user (Admin only)
     * DELETE /api/admin/users/{id}
     */
    public function destroy(User $user)
    {
        try {
            // Prevent admin from deleting themselves
            if ($user->id === auth()->id()) {
                return response()->json([
                    'message' => 'Tidak dapat menghapus akun sendiri'
                ], 422);
            }
            
            // Delete profile picture if exists
            if ($user->profile_picture) {
                $imagePath = resource_path($user->profile_picture);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $userName = $user->name;
            $user->delete();
            
            Log::info('User deleted successfully', [
                'user_name' => $userName,
                'deleted_by' => auth()->user()->name
            ]);
            
            return response()->json([
                'message' => 'User berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Gagal menghapus user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset user password (Admin only)
     * POST /api/admin/users/{id}/reset-password
     */
    public function resetPassword(Request $request, User $user)
    {
        try {
            $request->validate([
                'password' => 'required|string|min:8|confirmed'
            ]);
            
            $user->update([
                'password' => Hash::make($request->password)
            ]);
            
            // Revoke all tokens for this user
            $user->refreshTokens()->delete();
            
            Log::info('User password reset successfully', [
                'user_id' => $user->id,
                'username' => $user->username,
                'reset_by' => auth()->user()->name
            ]);
            
            return response()->json([
                'message' => 'Password berhasil direset'
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to reset user password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Gagal mereset password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get current authenticated user profile
     * GET /api/me
     */
    public function me()
    {
        $user = auth()->user();
        $user->load('puskesmas');
        
        return response()->json([
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Update current authenticated user profile
     * PUT /api/me
     */
    public function updateMe(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Debug: Log all request data
            Log::info('UpdateMe request received', [
                'user_id' => $user->id,
                'has_file' => $request->hasFile('profile_picture'),
                'files' => $request->allFiles(),
                'all_data' => $request->all(),
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method()
            ]);
            
            // Log before validation
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                Log::info('File details before validation', [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError()
                ]);
            }
            
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'password' => 'sometimes|string|min:8|confirmed',
                'puskesmas_name' => 'sometimes|nullable|string|max:255', // Nama puskesmas untuk update
                'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
            
            Log::info('Validation passed successfully');
            
            $data = $request->only(['name']);
            
            // Hash password if provided
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            
            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                Log::info('Processing profile picture upload for user update', [
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
                    
                    Log::info('Profile picture uploaded successfully for user update', [
                        'user_id' => $user->id,
                        'path' => $data['profile_picture'],
                        'full_path' => $destinationPath . DIRECTORY_SEPARATOR . $fileName,
                        'file_exists' => file_exists($destinationPath . DIRECTORY_SEPARATOR . $fileName)
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
            
            $user->update($data);
            $user->load('puskesmas'); // Load relationship
            
            Log::info('User profile updated', [
                'user_id' => $user->id,
                'username' => $user->username,
                'profile_picture_in_db' => $user->profile_picture,
                'data_sent_to_update' => $data
            ]);
            
            return response()->json([
                'message' => 'Profil berhasil diperbarui',
                'user' => new UserResource($user)
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update user profile', [
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
            default:
                throw new \Exception('Unsupported image type');
        }
        
        if (!$sourceImage) {
            throw new \Exception('Failed to create image resource');
        }
        
        // Create new image with target dimensions
        $resizedImage = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $width, $height,
            $originalWidth, $originalHeight
        );
        
        // Save resized image
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($resizedImage, $imagePath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($resizedImage, $imagePath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($resizedImage, $imagePath);
                break;
        }
        
        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        Log::info('Image resized successfully', [
            'path' => $imagePath,
            'original_size' => $originalWidth . 'x' . $originalHeight,
            'new_size' => $width . 'x' . $height
        ]);
    }
}