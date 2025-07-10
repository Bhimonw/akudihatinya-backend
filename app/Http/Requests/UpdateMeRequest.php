<?php

namespace App\Http\Requests;

use App\Traits\Validation\HasCommonValidationRulesTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    use HasCommonValidationRulesTrait;
    
    public function authorize(): bool
    {
        return true; // User can always update their own profile
    }

    public function rules(): array
    {
        $user = $this->user();
        
        return [
            'username' => $this->getUsernameRules(false, true, $user->id),
            'name' => $this->getNameRules(),
            'password' => $this->getPasswordRules(),
            'password_confirmation' => 'required_with:password|same:password',
            'profile_picture' => $this->getProfilePictureRules(),
            '_method' => 'sometimes|string|in:PUT,PATCH', // Allow method spoofing
        ];
    }

    public function messages(): array
    {
        return array_merge($this->getCommonErrorMessages(), [
            'password_confirmation.required_with' => 'Konfirmasi password wajib diisi jika mengubah password.',
            'password_confirmation.same' => 'Konfirmasi password harus sama dengan password.',
        ]);
    }
}