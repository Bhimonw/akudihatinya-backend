<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\ProfilePictureService;
use App\Http\Resources\UserResource;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Exception;

class UserController extends Controller
{
    protected ProfilePictureService $profilePictureService;

    public function __construct(ProfilePictureService $profilePictureService)
    {
        $this->profilePictureService = $profilePictureService;
    }
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
            $data = $request->only(['username', 'name', 'password']);
            
            // Otomatis set role sebagai 'puskesmas' untuk semua user yang dibuat
            $data['role'] = 'puskesmas';

            Log::info('Store user request received', [
                'has_file' => $request->hasFile('profile_picture'),
                'files' => $request->allFiles(),
                'auto_role' => $data['role']
            ]);

            // Hash password
            $data['password'] = Hash::make($data['password']);

            // Handle profile picture upload first
            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->profilePictureService->uploadProfilePicture(
                    $request->file('profile_picture'),
                    null, // No old picture for new user
                    0 // Temporary user ID for logging
                );
            }

            // Use database transaction for data consistency
            $user = DB::transaction(function () use ($data, $request) {
                // Karena semua user yang dibuat otomatis memiliki role 'puskesmas',
                // selalu auto-create puskesmas dan assign ID
                $puskesmasName = $data['name']; // Use name as puskesmas name

                // 1. Create user first (without puskesmas_id)
                $tempData = $data;
                unset($tempData['puskesmas_id']); // Remove puskesmas_id temporarily
                $user = User::create($tempData);

                // 2. Create puskesmas with user_id
                $puskesmas = Puskesmas::create([
                    'name' => $puskesmasName,
                    'user_id' => $user->id  // Provide required user_id
                ]);

                // 3. Update user with puskesmas_id
                $user->update(['puskesmas_id' => $puskesmas->id]);

                Log::info('Auto-created puskesmas for new user', [
                    'puskesmas_id' => $puskesmas->id,
                    'puskesmas_name' => $puskesmas->name,
                    'user_name' => $user->name,
                    'auto_role' => 'puskesmas'
                ]);

                return $user;
            });

            // Load relationship
            $user->load('puskesmas');

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
            if (
                str_contains($e->getMessage(), 'Failed to move file') ||
                str_contains($e->getMessage(), 'Invalid image file')
            ) {
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
            // Log admin update request
            Log::info('Admin update user request received', [
                'target_user_id' => $user->id,
                'admin_user_id' => auth()->id(),
                'has_file' => $request->hasFile('profile_picture')
            ]);

            DB::beginTransaction();

            $data = $request->only(['username', 'name', 'password']);

            // Log validated data
            Log::info('Admin update data validated', [
                'fields' => array_keys($data),
                'target_user_id' => $user->id
            ]);

            // Hash password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                try {
                    $data['profile_picture'] = $this->profilePictureService->uploadProfilePicture(
                        $request->file('profile_picture'),
                        $user->profile_picture,
                        $user->id
                    );
                } catch (\Exception $uploadError) {
                    return response()->json([
                        'message' => 'Gagal mengupload foto profil',
                        'error' => $uploadError->getMessage()
                    ], 422);
                }
            }

            $user->update($data);

            // Handle puskesmas name update for puskesmas role - use name as puskesmas name
            if ($user->role === 'puskesmas' && isset($data['name']) && $user->puskesmas) {
                $oldPuskesmasName = $user->puskesmas->name;
                $user->puskesmas->update(['name' => $data['name']]);

                Log::info('Puskesmas name updated using user name', [
                    'user_id' => $user->id,
                    'puskesmas_id' => $user->puskesmas->id,
                    'old_name' => $oldPuskesmasName,
                    'new_name' => $data['name']
                ]);
            }

            $user->load('puskesmas'); // Load relationship

            DB::commit(); // Commit the transaction

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
            DB::rollback(); // Rollback on validation error
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollback(); // Rollback on any other error
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
            'user' => new UserResource($user),
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_puskesmas' => $user->isPuskesmas(),
        ]);
    }

    /**
     * Update current authenticated user profile
     * PUT /api/me
     */
    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 404);
            }
            
            // Handle method spoofing for multipart forms
            if ($request->has('_method') && $request->input('_method') === 'PUT') {
                $request->setMethod('PUT');
            }
            

            
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                Log::info('File details', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                ]);
            }
            
$validatedData = $request->validated();
            
            // Remove _method from validated data if present
            unset($validatedData['_method']);
            
            // Handle profile picture upload with better error handling
            if ($request->hasFile('profile_picture')) {
                try {
                    $validatedData['profile_picture'] = $this->profilePictureService->uploadProfilePicture(
                        $request->file('profile_picture'),
                        $user->profile_picture, // old picture path
                        $user->id // user ID
                    );
                    Log::info('Profile picture uploaded successfully', [
                        'user_id' => $user->id,
                        'new_path' => $validatedData['profile_picture']
                    ]);
                } catch (\Exception $uploadError) {
                    Log::error('Profile picture upload failed', [
                        'user_id' => $user->id,
                        'error' => $uploadError->getMessage()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal mengunggah foto profil: ' . $uploadError->getMessage(),
                        'errors' => ['profile_picture' => ['Gagal mengunggah foto profil']]
                    ], 422);
                }
            }
            
            // Handle password hashing
            if (isset($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }
            
            // Update user data
            $user->update($validatedData);
            
            // Handle puskesmas name update for puskesmas role - use name as puskesmas name
            if ($user->role === 'puskesmas' && isset($validatedData['name']) && $user->puskesmas) {
                try {
                    $oldPuskesmasName = $user->puskesmas->name;
                    $user->puskesmas->update(['name' => $validatedData['name']]);

                    Log::info('Puskesmas name updated using user name via updateMe', [
                        'user_id' => $user->id,
                        'puskesmas_id' => $user->puskesmas->id,
                        'old_name' => $oldPuskesmasName,
                        'new_name' => $validatedData['name']
                    ]);
                } catch (\Exception $puskesmasError) {
                    Log::warning('Failed to update puskesmas name', [
                        'user_id' => $user->id,
                        'error' => $puskesmasError->getMessage()
                    ]);
                    // Continue execution even if puskesmas update fails
                }
            }
            
            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($validatedData)
            ]);
            
            // Reload user with fresh data and relationships
            $user->refresh();
            $user->load('puskesmas');
            
            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui',
                'user' => new UserResource($user),
                'data' => new UserResource($user) // For backward compatibility
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for profile update', [
                'user_id' => $request->user()->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Failed to update user profile', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui profil. Silakan coba lagi.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get current user profile (alternative endpoint)
     * GET /api/profile/me
     */
    public function showProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture ? $this->profilePictureService->getProfilePictureUrl($user->profile_picture) : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Update user profile including profile picture (supports both UpdateMeRequest and UpdateProfileRequest)
     * PUT/PATCH/POST /api/profile/me
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Determine which request class to use based on user role or request type
            if ($user->role === 'puskesmas' && $request->has('puskesmas_name')) {
                // Use UpdateProfileRequest for puskesmas users
                $validatedRequest = app(UpdateProfileRequest::class);
                $data = $validatedRequest->validated();
            } else {
                // Use UpdateMeRequest for regular users
                $validatedRequest = app(UpdateMeRequest::class);
                $data = $validatedRequest->validated();
            }
            
            Log::info('Profile update started', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'has_file' => $request->hasFile('profile_picture'),
                'data_keys' => array_keys($data)
            ]);
            
            // Hash password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                Log::info('Password will be updated for user', ['user_id' => $user->id]);
            } else {
                unset($data['password']);
            }
            
            // Handle profile picture upload with automatic resize
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                
                Log::info('Processing profile picture upload with auto-resize', [
                    'user_id' => $user->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'old_picture' => $user->profile_picture
                ]);
                
                try {
                    $filename = $this->profilePictureService->uploadProfilePicture(
                        $file,
                        $user->profile_picture,
                        $user->id
                    );
                    
                    $data['profile_picture'] = $filename;
                    
                    Log::info('Profile picture uploaded and resized successfully', [
                        'user_id' => $user->id,
                        'new_filename' => $filename
                    ]);
                    
                } catch (\Exception $uploadError) {
                    Log::error('Profile picture upload failed', [
                        'user_id' => $user->id,
                        'error' => $uploadError->getMessage(),
                        'trace' => $uploadError->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal mengupload foto profil',
                        'error' => $uploadError->getMessage(),
                        'debug_info' => config('app.debug') ? [
                            'upload_config' => $this->profilePictureService->getUploadStatus(),
                            'file_info' => [
                                'name' => $request->file('profile_picture')->getClientOriginalName(),
                                'size' => $request->file('profile_picture')->getSize(),
                                'mime' => $request->file('profile_picture')->getMimeType(),
                                'is_valid' => $request->file('profile_picture')->isValid(),
                                'error_code' => $request->file('profile_picture')->getError()
                            ]
                        ] : null
                    ], 422);
                }
            }
            
            // Handle puskesmas name update for puskesmas role
            if ($user->role === 'puskesmas' && isset($data['puskesmas_name']) && $user->puskesmas) {
                $oldPuskesmasName = $user->puskesmas->name;
                $user->puskesmas->update(['name' => $data['puskesmas_name']]);
                
                Log::info('Puskesmas name updated via UserController', [
                    'user_id' => $user->id,
                    'puskesmas_id' => $user->puskesmas->id,
                    'old_name' => $oldPuskesmasName,
                    'new_name' => $data['puskesmas_name']
                ]);
                
                // Remove puskesmas_name from user data as it's not a user field
                unset($data['puskesmas_name']);
            }
            
            // Update user data
            $user->update($data);
            $user->load('puskesmas'); // Load relationship for puskesmas users
            
            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($data)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user->fresh(),
                    'profile_picture_url' => $user->profile_picture 
                        ? $this->profilePictureService->getProfilePictureUrl($user->profile_picture)
                        : null
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete profile picture.
     * DELETE /api/profile/me/profile-picture
     */
    public function deleteProfilePicture(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->profile_picture) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada foto profil untuk dihapus'
                ], 404);
            }

            // Delete the file
            $this->profilePictureService->deleteOldPicture($user->profile_picture);
            
            // Update user record
            $user->update(['profile_picture' => null]);
            
            Log::info('Profile picture deleted', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete profile picture', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus foto profil'
            ], 500);
        }
    }

    /**
     * Get upload status information
     * GET /api/profile/me/upload-status
     */
    public function uploadStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get detailed status from service
            $serviceStatus = $this->profilePictureService->getUploadStatus();
            
            $status = [
                'upload_enabled' => true,
                'max_file_size' => config('upload.profile_pictures.max_size', 2048),
                'allowed_extensions' => config('upload.profile_pictures.allowed_extensions', ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                'current_picture' => $user->profile_picture,
                'current_picture_url' => $user->profile_picture 
                    ? $this->profilePictureService->getProfilePictureUrl($user->profile_picture)
                    : null,
                'current_picture_exists' => $user->profile_picture 
                    ? $this->profilePictureService->fileExists($user->profile_picture)
                    : false,
                'storage_info' => [
                    'disk' => config('upload.profile_pictures.disk', 'public'),
                    'path' => config('upload.profile_pictures.path', 'profile-pictures')
                ],
                'system_status' => $serviceStatus
            ];
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upload status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test upload functionality (for debugging)
     * GET /api/profile/me/test-upload
     */
    public function testUpload(Request $request): JsonResponse
    {
        try {
            // Run upload test
            $testResult = $this->profilePictureService->testUpload();
            
            return response()->json([
                'success' => true,
                'message' => 'Upload test completed',
                'data' => $testResult
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed system diagnostics
     * GET /api/profile/me/diagnostics
     */
    public function diagnostics(Request $request): JsonResponse
    {
        try {
            $diagnostics = [
                'timestamp' => now()->toISOString(),
                'upload_status' => $this->profilePictureService->getUploadStatus(),
                'test_result' => $this->profilePictureService->testUpload(),
                'environment' => [
                    'app_env' => config('app.env'),
                    'app_debug' => config('app.debug'),
                    'storage_driver' => config('filesystems.default'),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version()
                ],
                'recent_logs' => $this->getRecentUploadLogs()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $diagnostics
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get diagnostics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent upload-related logs
     */
    private function getRecentUploadLogs(): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            
            if (!file_exists($logFile)) {
                return ['error' => 'Log file not found'];
            }
            
            $logs = [];
            $handle = fopen($logFile, 'r');
            
            if ($handle) {
                // Read last 100 lines
                $lines = [];
                while (($line = fgets($handle)) !== false) {
                    $lines[] = $line;
                    if (count($lines) > 100) {
                        array_shift($lines);
                    }
                }
                fclose($handle);
                
                // Filter upload-related logs
                foreach ($lines as $line) {
                    if (strpos($line, 'UPLOAD DEBUG') !== false || 
                        strpos($line, 'ProfilePictureService') !== false ||
                        strpos($line, 'profile picture') !== false) {
                        $logs[] = trim($line);
                    }
                }
            }
            
            return array_slice($logs, -20); // Last 20 upload-related logs
            
        } catch (Exception $e) {
            return ['error' => 'Failed to read logs: ' . $e->getMessage()];
        }
    }
}
