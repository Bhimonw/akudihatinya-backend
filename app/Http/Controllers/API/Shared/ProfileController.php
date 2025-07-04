<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\UpdateMeAlternativeRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileUpdateService;
use App\Services\ProfilePictureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected ProfileUpdateService $profileUpdateService;
    protected ProfilePictureService $profilePictureService;

    public function __construct(
        ProfileUpdateService $profileUpdateService,
        ProfilePictureService $profilePictureService
    ) {
        $this->profileUpdateService = $profileUpdateService;
        $this->profilePictureService = $profilePictureService;
    }
    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            
            // Filter data yang valid untuk user role
            $filteredData = $this->profileUpdateService->filterValidFields($user, $data);
            
            // Update profil menggunakan service
            $updatedUser = $this->profileUpdateService->updateProfile(
                $user,
                $filteredData,
                $request->file('profile_picture')
            );
            
            return response()->json([
                'message' => 'Profil berhasil diupdate',
                'user' => new UserResource($updatedUser),
            ]);
            
        } catch (ValidationException $e) {
            Log::warning('Profile update validation failed', [
                'user_id' => auth()->id(),
                'errors' => $e->errors()
            ]);
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
     * Update current authenticated user profile
     * PUT /api/users/me
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
                'input_keys' => array_keys($request->except(['profile_picture'])),
                'profile_picture_in_db' => $user->profile_picture,
                'has_method_spoofing' => $request->has('_method'),
                'spoofed_method' => $request->input('_method'),
                'file_count' => count($request->allFiles())
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

    /**
     * Update current authenticated user profile with alternative validation (800x800 max)
     * PUT /api/users/me/alt
     */
    public function updateMeAlternative(UpdateMeAlternativeRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Handle method spoofing for multipart forms
            if ($request->has('_method') && $request->input('_method') === 'PUT') {
                $request->setMethod('PUT');
            }
            
            // Enhanced logging for debugging
            Log::info('UpdateMe Alternative request received', [
                'user_id' => $user->id,
                'method' => $request->method(),
                'has_file' => $request->hasFile('profile_picture'),
                'content_type' => $request->header('Content-Type'),
                'is_multipart' => str_contains($request->header('Content-Type', ''), 'multipart'),
                'content_length' => $request->header('Content-Length'),
                'file_keys' => array_keys($request->allFiles()),
                'input_keys' => array_keys($request->except(['profile_picture'])),
                'profile_picture_in_db' => $user->profile_picture,
                'has_method_spoofing' => $request->has('_method'),
                'spoofed_method' => $request->input('_method'),
                'file_count' => count($request->allFiles())
            ]);
            
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                Log::info('File details (Alternative)', [
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
                    Log::info('Profile picture uploaded successfully (Alternative)', [
                        'user_id' => $user->id,
                        'new_path' => $validatedData['profile_picture']
                    ]);
                } catch (\Exception $uploadError) {
                    Log::error('Profile picture upload failed (Alternative)', [
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
            
            Log::info('Profile updated successfully (Alternative)', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($validatedData)
            ]);
            
            // Reload user with fresh data and relationships
            $user->refresh();
            $user->load('puskesmas');
            
            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui dengan validasi alternatif (max 800x800)',
                'user' => new UserResource($user),
                'data' => new UserResource($user) // For backward compatibility
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for profile update (Alternative)', [
                'user_id' => $request->user()->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Failed to update user profile (Alternative)', [
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
