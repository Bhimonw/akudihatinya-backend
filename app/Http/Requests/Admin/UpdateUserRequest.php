<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Izinkan jika user adalah admin ATAU user sedang update dirinya sendiri
        $user = $this->user(); // yang login
        $updating = $this->route('user'); // user yang sedang diedit di route (bisa null di /me)

        return $user && (
            $user->isAdmin() ||
            !$updating || $user->id === optional($updating)->id
        );
    }

    public function rules(): array
    {
        // Ambil ID user yang sedang diedit (atau user login jika route /me)
        $editingUser = $this->route('user') ?? $this->user();

        return [
            'username' => [
                'sometimes',
                'string',
                Rule::unique('users')->ignore($editingUser?->id),
            ],
            'name' => 'sometimes|string|max:255', // Name will be used as puskesmas name
            'password' => 'sometimes|string|min:8|nullable|confirmed',
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
