<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Services\ProfilePictureService;
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
            
            // Handle method spoofing for multipart forms
            if ($request->has('_method') && $request->input('_method') === 'PUT') {
                $request->setMethod('PUT');
            }
            
            // Enhanced logging for debugging
            Log::info('UpdateMe request received', [
                'user_id' => $user->id,
                'method' => $request->method(),
                'has_file' => $request->hasFile('profile_picture'),
                'content_type' => $request->header('Content-Type'),
                'is_multipart' => str_contains($request->header('Content-Type', ''), 'multipart'),
                'content_length' => $request->header('Content-Length'),
                'file_keys' => array_keys($request->allFiles()),
                'input_keys' => array_keys($request->all()),
                'profile_picture_in_db' => $user->profile_picture,
                'has_method_spoofing' => $request->has('_method'),
                'spoofed_method' => $request->input('_method'),
                'all_files' => $request->allFiles(),
                'request_size' => strlen(serialize($request->all()))
            ]);
            
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
}
