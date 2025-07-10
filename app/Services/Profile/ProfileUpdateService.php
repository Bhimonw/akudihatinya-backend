<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Service untuk menangani update profil user
 * Mengurangi duplikasi kode antara ProfileController dan UserController
 */
class ProfileUpdateService
{
    protected ProfilePictureService $profilePictureService;
    
    public function __construct(ProfilePictureService $profilePictureService)
    {
        $this->profilePictureService = $profilePictureService;
    }
    
    /**
     * Update profil user dengan data yang diberikan
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $profilePicture = null): User
    {
        return DB::transaction(function () use ($user, $data, $profilePicture) {
            Log::info('Profile update started', [
                'user_id' => $user->id,
                'fields_to_update' => array_keys($data),
                'has_profile_picture' => $profilePicture !== null
            ]);
            
            // Handle password hashing
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                Log::info('Password will be updated', ['user_id' => $user->id]);
            } else {
                unset($data['password']);
            }
            
            // Handle profile picture upload
            if ($profilePicture) {
                try {
                    $data['profile_picture'] = $this->profilePictureService->uploadProfilePicture(
                        $profilePicture,
                        $user->profile_picture,
                        $user->id
                    );
                    Log::info('Profile picture uploaded successfully', ['user_id' => $user->id]);
                } catch (Exception $e) {
                    Log::error('Profile picture upload failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    throw new Exception('Gagal mengupload foto profil: ' . $e->getMessage());
                }
            }
            
            // Handle puskesmas name update for puskesmas role
            if ($user->role === 'puskesmas' && isset($data['puskesmas_name']) && $user->puskesmas) {
                $this->updatePuskesmasName($user, $data['puskesmas_name']);
                unset($data['puskesmas_name']); // Remove from user data
            }
            
            // Update user data
            if (!empty($data)) {
                $user->update($data);
                Log::info('User data updated', [
                    'user_id' => $user->id,
                    'updated_fields' => array_keys($data)
                ]);
            }
            
            // Reload relationships
            $user->load('puskesmas');
            
            Log::info('Profile update completed successfully', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);
            
            return $user;
        });
    }
    
    /**
     * Update nama puskesmas untuk user dengan role puskesmas
     */
    protected function updatePuskesmasName(User $user, string $puskesmasName): void
    {
        if (!$user->puskesmas) {
            Log::warning('Attempted to update puskesmas name for user without puskesmas', [
                'user_id' => $user->id
            ]);
            return;
        }
        
        $oldName = $user->puskesmas->name;
        $user->puskesmas->update(['name' => $puskesmasName]);
        
        Log::info('Puskesmas name updated', [
            'user_id' => $user->id,
            'puskesmas_id' => $user->puskesmas->id,
            'old_name' => $oldName,
            'new_name' => $puskesmasName
        ]);
    }
    
    /**
     * Validasi apakah user dapat mengupdate field tertentu
     */
    public function canUpdateField(User $user, string $field, mixed $value): bool
    {
        switch ($field) {
            case 'puskesmas_name':
                return $user->role === 'puskesmas' && $user->puskesmas !== null;
            case 'role':
                // Role tidak boleh diupdate melalui profile update
                return false;
            default:
                return true;
        }
    }
    
    /**
     * Filter data yang valid untuk update berdasarkan user role
     */
    public function filterValidFields(User $user, array $data): array
    {
        $filteredData = [];
        
        foreach ($data as $field => $value) {
            if ($this->canUpdateField($user, $field, $value)) {
                $filteredData[$field] = $value;
            } else {
                Log::warning('Field update not allowed', [
                    'user_id' => $user->id,
                    'field' => $field,
                    'user_role' => $user->role
                ]);
            }
        }
        
        return $filteredData;
    }
}