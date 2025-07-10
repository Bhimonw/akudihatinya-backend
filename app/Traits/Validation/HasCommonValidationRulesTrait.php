<?php

namespace App\Traits\Validation;

use App\Constants\ValidationConstants;
use Illuminate\Validation\Rule;

/**
 * Trait untuk aturan validasi yang umum digunakan
 * Mengurangi duplikasi kode dan memastikan konsistensi
 */
trait HasCommonValidationRulesTrait
{
    /**
     * Aturan validasi untuk field name
     */
    protected function getNameRules(bool $required = false, bool $nullable = true): array
    {
        $rules = [];
        
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }
        
        if ($nullable) {
            $rules[] = 'nullable';
        }
        
        return array_merge($rules, [
            'string',
            'min:' . ValidationConstants::NAME_MIN_LENGTH,
            'max:' . ValidationConstants::NAME_MAX_LENGTH,
            'regex:' . ValidationConstants::NAME_REGEX
        ]);
    }
    
    /**
     * Aturan validasi untuk field username
     */
    protected function getUsernameRules(bool $required = false, bool $nullable = true, ?int $ignoreUserId = null): array
    {
        $rules = [];
        
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }
        
        if ($nullable) {
            $rules[] = 'nullable';
        }
        
        $rules = array_merge($rules, [
            'string',
            'min:' . ValidationConstants::USERNAME_MIN_LENGTH,
            'max:' . ValidationConstants::USERNAME_MAX_LENGTH,
            'regex:' . ValidationConstants::USERNAME_REGEX
        ]);
        
        // Add unique rule if needed
        if ($ignoreUserId !== null) {
            $rules[] = Rule::unique('users')->ignore($ignoreUserId);
        } else {
            $rules[] = 'unique:users,username';
        }
        
        return $rules;
    }
    
    /**
     * Aturan validasi untuk field password
     */
    protected function getPasswordRules(bool $required = false, bool $confirmed = true): array
    {
        $rules = [];
        
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }
        
        $rules = array_merge($rules, [
            'string',
            'min:' . ValidationConstants::PASSWORD_MIN_LENGTH,
            'max:' . ValidationConstants::PASSWORD_MAX_LENGTH,
            'regex:' . ValidationConstants::PASSWORD_REGEX
        ]);
        
        if ($confirmed) {
            $rules[] = 'confirmed';
        }
        
        return $rules;
    }
    
    /**
     * Aturan validasi untuk field puskesmas_name
     */
    protected function getPuskesmasNameRules(bool $required = false, bool $nullable = true): array
    {
        $rules = [];
        
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }
        
        if ($nullable) {
            $rules[] = 'nullable';
        }
        
        return array_merge($rules, [
            'string',
            'min:' . ValidationConstants::NAME_MIN_LENGTH,
            'max:' . ValidationConstants::NAME_MAX_LENGTH,
            'regex:' . ValidationConstants::NAME_REGEX
        ]);
    }
    
    /**
     * Aturan validasi untuk profile picture
     */
    protected function getProfilePictureRules(bool $required = false, bool $nullable = true): array
    {
        $rules = [];
        
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }
        
        if ($nullable) {
            $rules[] = 'nullable';
        }
        
        return array_merge($rules, [
            'image',
            'mimes:' . implode(',', ValidationConstants::PROFILE_PICTURE_MIMES),
            'max:' . ValidationConstants::PROFILE_PICTURE_MAX_SIZE,
            'dimensions:min_width=' . ValidationConstants::PROFILE_PICTURE_MIN_WIDTH . 
            ',min_height=' . ValidationConstants::PROFILE_PICTURE_MIN_HEIGHT . 
            ',max_width=' . ValidationConstants::PROFILE_PICTURE_MAX_WIDTH . 
            ',max_height=' . ValidationConstants::PROFILE_PICTURE_MAX_HEIGHT
        ]);
    }
    
    /**
     * Aturan validasi alternatif untuk profile picture dengan maksimal 800x800
     */
    protected function getProfilePictureAlternativeRules(bool $required = false, bool $nullable = true): array
    {
        $rules = [];
        
        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }
        
        if ($nullable) {
            $rules[] = 'nullable';
        }
        
        return array_merge($rules, [
            'image',
            'mimes:' . implode(',', ValidationConstants::PROFILE_PICTURE_MIMES),
            'max:' . ValidationConstants::PROFILE_PICTURE_MAX_SIZE,
            'dimensions:min_width=' . ValidationConstants::PROFILE_PICTURE_MIN_WIDTH . 
            ',min_height=' . ValidationConstants::PROFILE_PICTURE_MIN_HEIGHT . 
            ',max_width=' . ValidationConstants::PROFILE_PICTURE_ALT_MAX_WIDTH . 
            ',max_height=' . ValidationConstants::PROFILE_PICTURE_ALT_MAX_HEIGHT
        ]);
    }
    
    /**
     * Mendapatkan pesan error kustom
     */
    protected function getCommonErrorMessages(): array
    {
        return ValidationConstants::ERROR_MESSAGES;
    }
}