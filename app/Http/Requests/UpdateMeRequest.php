<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateMeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // User can always update their own profile
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->user();
        $uploadConfig = config('upload.profile_pictures', []);
        
        return [
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:50', // Reduced for better performance and UX
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users')->ignore($user->id),
            ],
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:100', // Reasonable limit for names
                'regex:/^[\p{L}\s\-\']+$/u', // Support Unicode letters, spaces, hyphens, apostrophes
            ],
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'max:128', // Reasonable upper limit
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/', // Include special characters
            ],
            'password_confirmation' => 'required_with:password|same:password',
            'profile_picture' => $this->getProfilePictureRules($uploadConfig),
            '_method' => 'sometimes|string|in:PUT,PATCH',
        ];
    }

    /**
     * Get dynamic profile picture validation rules based on configuration.
     */
    protected function getProfilePictureRules(array $config): array
    {
        $rules = [
            'sometimes',
            'nullable',
            'file',
        ];

        // Add file type validation
        if (!empty($config['allowed_mimes'])) {
            $rules[] = File::types(array_map(function($mime) {
                return str_replace('image/', '', $mime);
            }, $config['allowed_mimes']));
        } else {
            $rules[] = 'image';
        }

        // Add size validation
        $maxSize = $config['max_size'] ?? 2048;
        $rules[] = "max:{$maxSize}";

        // Add dimensions validation with auto-resize support
        if (!empty($config['dimensions'])) {
            $dimensions = $config['dimensions'];
            $autoResizeEnabled = $config['auto_resize']['enabled'] ?? true;
            
            $dimensionRule = 'dimensions:';
            $dimensionParts = [];
            
            // Always validate minimum dimensions
            if (isset($dimensions['min_width'])) {
                $dimensionParts[] = "min_width={$dimensions['min_width']}";
            }
            if (isset($dimensions['min_height'])) {
                $dimensionParts[] = "min_height={$dimensions['min_height']}";
            }
            
            // Only validate maximum dimensions if auto-resize is disabled
            if (!$autoResizeEnabled) {
                if (isset($dimensions['max_width'])) {
                    $dimensionParts[] = "max_width={$dimensions['max_width']}";
                }
                if (isset($dimensions['max_height'])) {
                    $dimensionParts[] = "max_height={$dimensions['max_height']}";
                }
            }
            
            if (!empty($dimensionParts)) {
                $rules[] = $dimensionRule . implode(',', $dimensionParts);
            }
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        $uploadConfig = config('upload.profile_pictures', []);
        $maxSize = $uploadConfig['max_size'] ?? 2048;
        $dimensions = $uploadConfig['dimensions'] ?? [];
        $allowedTypes = $uploadConfig['allowed_extensions'] ?? ['jpeg', 'png', 'jpg', 'gif', 'webp'];
        
        return [
            // Username messages
            'username.unique' => 'Username sudah digunakan oleh pengguna lain.',
            'username.string' => 'Username harus berupa teks.',
            'username.min' => 'Username harus minimal 3 karakter.',
            'username.max' => 'Username tidak boleh lebih dari 50 karakter.',
            'username.regex' => 'Username hanya boleh mengandung huruf, angka, titik, garis bawah, dan tanda hubung.',
            
            // Name messages
            'name.string' => 'Nama harus berupa teks.',
            'name.min' => 'Nama harus minimal 2 karakter.',
            'name.max' => 'Nama tidak boleh lebih dari 100 karakter.',
            'name.regex' => 'Nama hanya boleh mengandung huruf, spasi, tanda hubung, dan apostrof.',
            
            // Password messages
            'password.min' => 'Password harus minimal 8 karakter.',
            'password.max' => 'Password tidak boleh lebih dari 128 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.regex' => 'Password harus mengandung minimal satu huruf kecil, satu huruf besar, satu angka, dan satu karakter khusus (@$!%*?&).',
            'password_confirmation.required_with' => 'Konfirmasi password wajib diisi jika mengubah password.',
            'password_confirmation.same' => 'Konfirmasi password harus sama dengan password.',
            
            // Profile picture messages (dynamic based on config)
            'profile_picture.file' => 'File profil harus berupa file yang valid.',
            'profile_picture.image' => 'File harus berupa gambar.',
            'profile_picture.mimes' => 'Gambar harus berformat: ' . implode(', ', $allowedTypes) . '.',
            'profile_picture.max' => "Ukuran gambar tidak boleh lebih dari {$maxSize}KB.",
            'profile_picture.dimensions' => $this->getDimensionMessage($dimensions),
        ];
    }

    /**
     * Get dynamic dimension validation message with auto-resize support.
     */
    protected function getDimensionMessage(array $dimensions): string
    {
        $uploadConfig = config('upload.profile_pictures', []);
        $autoResizeEnabled = $uploadConfig['auto_resize']['enabled'] ?? true;
        
        $parts = [];
        
        if (isset($dimensions['min_width']) && isset($dimensions['min_height'])) {
            $parts[] = "minimal {$dimensions['min_width']}x{$dimensions['min_height']} piksel";
        }
        
        // Only mention max dimensions if auto-resize is disabled
        if (!$autoResizeEnabled && isset($dimensions['max_width']) && isset($dimensions['max_height'])) {
            $parts[] = "maksimal {$dimensions['max_width']}x{$dimensions['max_height']} piksel";
        }
        
        if (empty($parts)) {
            return 'Dimensi gambar tidak valid.';
        }
        
        $message = 'Dimensi gambar harus ' . implode(' dan ', $parts) . '.';
        
        // Add auto-resize information if enabled
        if ($autoResizeEnabled && isset($dimensions['max_width']) && isset($dimensions['max_height'])) {
            $message .= " Gambar yang lebih besar dari {$dimensions['max_width']}x{$dimensions['max_height']} piksel akan otomatis diresize.";
        }
        
        return $message;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize username
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower(trim($this->username))
            ]);
        }
        
        // Sanitize name
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name)
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
             'username' => 'username',
             'name' => 'nama',
             'password' => 'password',
             'password_confirmation' => 'konfirmasi password',
             'profile_picture' => 'foto profil',
         ];
     }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional security validations
            $this->validateSecurityConstraints($validator);
            
            // Rate limiting check for sensitive operations
            $this->checkRateLimit($validator);
        });
    }

    /**
     * Perform additional security validations.
     */
    protected function validateSecurityConstraints($validator): void
    {
        // Check for suspicious username patterns
        if ($this->has('username')) {
            $username = $this->input('username');
            $currentUser = $this->user();
            
            // Only validate if username is different from current username
            if ($username !== $currentUser->username) {
                // Block common admin/system usernames
                $blockedUsernames = ['admin', 'administrator', 'root', 'system', 'api', 'test'];
                if (in_array(strtolower($username), $blockedUsernames)) {
                    $validator->errors()->add('username', 'Username tidak diperbolehkan.');
                }
                
                // Check for consecutive special characters
                if (preg_match('/[._-]{2,}/', $username)) {
                    $validator->errors()->add('username', 'Username tidak boleh mengandung karakter khusus berturut-turut.');
                }
            }
        }
        
        // Validate password strength beyond regex
        if ($this->has('password')) {
            $password = $this->input('password');
            
            // Check for common weak passwords
            $weakPasswords = ['password', '12345678', 'qwerty123', 'admin123'];
            if (in_array(strtolower($password), $weakPasswords)) {
                $validator->errors()->add('password', 'Password terlalu umum dan mudah ditebak.');
            }
            
            // Check for username in password
            if ($this->has('username') && stripos($password, $this->input('username')) !== false) {
                $validator->errors()->add('password', 'Password tidak boleh mengandung username.');
            }
        }
    }

    /**
     * Check rate limiting for sensitive operations.
     */
    protected function checkRateLimit($validator): void
    {
        $user = $this->user();
        
        if (!$user) {
            return;
        }
        
        // Check if user is trying to change password too frequently
        if ($this->has('password')) {
            $lastPasswordChange = $user->password_changed_at ?? $user->updated_at;
            $hoursSinceLastChange = now()->diffInHours($lastPasswordChange);
            
            if ($hoursSinceLastChange < 1) {
                $validator->errors()->add('password', 'Password hanya dapat diubah sekali dalam 1 jam.');
            }
        }
        
        // Check profile picture upload frequency
        if ($this->hasFile('profile_picture')) {
            $cacheKey = "profile_upload_limit:" . $user->id;
            $uploadCount = cache()->get($cacheKey, 0);
            
            if ($uploadCount >= 5) {
                $validator->errors()->add('profile_picture', 'Terlalu banyak upload foto profil. Coba lagi dalam 1 jam.');
            } else {
                cache()->put($cacheKey, $uploadCount + 1, now()->addHour());
            }
        }
    }

    /**
     * Get the validated data from the request with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Remove sensitive fields from logs if needed
        if (is_array($validated) && isset($validated['password'])) {
            // Log the password change attempt (without the actual password)
            logger()->info('User password change attempt', [
                'user_id' => $this->user()?->id,
                'ip' => $this->ip(),
                'user_agent' => $this->userAgent(),
            ]);
        }
        
        return $validated;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // Log failed validation attempts for security monitoring
        logger()->warning('Profile update validation failed', [
            'user_id' => $this->user()?->id,
            'errors' => $validator->errors()->toArray(),
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ]);
        
        parent::failedValidation($validator);
    }
}