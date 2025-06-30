<?php

namespace App\Http\Requests\Profile;

use App\Traits\HasCommonValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    use HasCommonValidationRules;
    
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => $this->getNameRules(),
            'password' => $this->getPasswordRules(),
            'puskesmas_name' => $this->getPuskesmasNameRules(),
            'profile_picture' => $this->getProfilePictureRules(),
        ];
    }
    
    public function messages(): array
    {
        return $this->getCommonErrorMessages();
    }
}