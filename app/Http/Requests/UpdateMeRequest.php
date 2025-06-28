<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // User can always update their own profile
    }

    public function rules(): array
    {
        $user = $this->user();
        
        return [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
            'profile_picture' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048',
                'dimensions:min_width=50,min_height=50,max_width=2000,max_height=2000'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan oleh user lain.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'profile_picture.image' => 'File harus berupa gambar.',
            'profile_picture.max' => 'Ukuran file maksimal 2MB.',
            'profile_picture.dimensions' => 'Dimensi gambar minimal 50x50 dan maksimal 2000x2000 pixel.',
        ];
    }
}