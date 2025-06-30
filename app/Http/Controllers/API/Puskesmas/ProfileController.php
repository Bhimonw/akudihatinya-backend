<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfilePictureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected ProfilePictureService $profilePictureService;

    public function __construct(ProfilePictureService $profilePictureService)
    {
        $this->profilePictureService = $profilePictureService;
    }
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
    

}
