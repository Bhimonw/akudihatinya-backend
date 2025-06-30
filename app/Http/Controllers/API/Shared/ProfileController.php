<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected ProfileUpdateService $profileUpdateService;

    public function __construct(ProfileUpdateService $profileUpdateService)
    {
        $this->profileUpdateService = $profileUpdateService;
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
    

}
