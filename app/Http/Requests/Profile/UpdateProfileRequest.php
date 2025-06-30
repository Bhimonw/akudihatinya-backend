<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8|nullable|confirmed',
            'puskesmas_name' => 'sometimes|nullable|string|max:255', // Nama puskesmas untuk update
            'profile_picture' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB
                'dimensions:min_width=50,min_height=50,max_width=2000,max_height=2000'
            ],
        ];
    }
}