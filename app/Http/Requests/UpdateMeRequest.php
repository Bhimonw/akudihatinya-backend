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
                'required_with_all:name,password', // Require username if other fields are being updated
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9._-]+$/', // Only allow alphanumeric, dots, underscores, and hyphens
                Rule::unique('users')->ignore($user->id),
            ],
            'name' => [
                'sometimes',
                'required_with_all:username,password', // Require name if other fields are being updated
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s]+$/' // Only allow letters and spaces
            ],
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/' // At least one lowercase, uppercase, and digit
            ],
            'password_confirmation' => 'required_with:password|same:password',
            'profile_picture' => [
                'sometimes',
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB
                'dimensions:min_width=50,min_height=50,max_width=2000,max_height=2000'
            ],
            '_method' => 'sometimes|string|in:PUT,PATCH', // Allow method spoofing
        ];
    }

    public function messages(): array
    {
        return [
            // Username messages
            'username.required_with_all' => 'Username wajib diisi jika mengubah data profil.',
            'username.unique' => 'Username sudah digunakan oleh pengguna lain.',
            'username.string' => 'Username harus berupa teks.',
            'username.min' => 'Username harus minimal 3 karakter.',
            'username.max' => 'Username tidak boleh lebih dari 255 karakter.',
            'username.regex' => 'Username hanya boleh mengandung huruf, angka, titik, garis bawah, dan tanda hubung.',
            
            // Name messages
            'name.required_with_all' => 'Nama wajib diisi jika mengubah data profil.',
            'name.string' => 'Nama harus berupa teks.',
            'name.min' => 'Nama harus minimal 2 karakter.',
            'name.max' => 'Nama tidak boleh lebih dari 255 karakter.',
            'name.regex' => 'Nama hanya boleh mengandung huruf dan spasi.',
            
            // Password messages
            'password.min' => 'Password harus minimal 8 karakter.',
            'password.max' => 'Password tidak boleh lebih dari 255 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.regex' => 'Password harus mengandung minimal satu huruf kecil, satu huruf besar, dan satu angka.',
            'password_confirmation.required_with' => 'Konfirmasi password wajib diisi jika mengubah password.',
            'password_confirmation.same' => 'Konfirmasi password harus sama dengan password.',
            
            // Profile picture messages
            'profile_picture.file' => 'File profil harus berupa file yang valid.',
            'profile_picture.image' => 'File harus berupa gambar.',
            'profile_picture.mimes' => 'Gambar harus berformat: jpeg, png, jpg, gif, atau webp.',
            'profile_picture.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
            'profile_picture.dimensions' => 'Dimensi gambar harus minimal 50x50 piksel dan maksimal 2000x2000 piksel.',
        ];
    }
}