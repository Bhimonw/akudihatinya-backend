<?php

namespace Tests\Unit\Traits;

use App\Constants\ValidationConstants;
use App\Traits\Validation\HasCommonValidationRulesTrait;
use Tests\TestCase;

class HasCommonValidationRulesTest extends TestCase
{
    use HasCommonValidationRulesTrait;
    
    /** @test */
    public function it_returns_correct_name_rules_when_required()
    {
        $rules = $this->getNameRules(true, false);
        
        $this->assertContains('required', $rules);
        $this->assertNotContains('sometimes', $rules);
        $this->assertNotContains('nullable', $rules);
        $this->assertContains('string', $rules);
        $this->assertContains('min:' . ValidationConstants::NAME_MIN_LENGTH, $rules);
        $this->assertContains('max:' . ValidationConstants::NAME_MAX_LENGTH, $rules);
        $this->assertContains('regex:' . ValidationConstants::NAME_REGEX, $rules);
    }
    
    /** @test */
    public function it_returns_correct_name_rules_when_optional()
    {
        $rules = $this->getNameRules(false, true);
        
        $this->assertContains('sometimes', $rules);
        $this->assertContains('nullable', $rules);
        $this->assertNotContains('required', $rules);
    }
    
    /** @test */
    public function it_returns_correct_username_rules_with_unique_ignore()
    {
        $rules = $this->getUsernameRules(false, true, 123);
        
        $this->assertContains('sometimes', $rules);
        $this->assertContains('nullable', $rules);
        $this->assertContains('string', $rules);
        $this->assertContains('min:' . ValidationConstants::USERNAME_MIN_LENGTH, $rules);
        $this->assertContains('max:' . ValidationConstants::USERNAME_MAX_LENGTH, $rules);
        $this->assertContains('regex:' . ValidationConstants::USERNAME_REGEX, $rules);
        
        // Check if unique rule with ignore is present
        $hasUniqueRule = false;
        foreach ($rules as $rule) {
            if (is_object($rule) && method_exists($rule, 'ignore')) {
                $hasUniqueRule = true;
                break;
            }
        }
        $this->assertTrue($hasUniqueRule);
    }
    
    /** @test */
    public function it_returns_correct_password_rules_with_confirmation()
    {
        $rules = $this->getPasswordRules(true, true);
        
        $this->assertContains('required', $rules);
        $this->assertContains('string', $rules);
        $this->assertContains('min:' . ValidationConstants::PASSWORD_MIN_LENGTH, $rules);
        $this->assertContains('max:' . ValidationConstants::PASSWORD_MAX_LENGTH, $rules);
        $this->assertContains('regex:' . ValidationConstants::PASSWORD_REGEX, $rules);
        $this->assertContains('confirmed', $rules);
    }
    
    /** @test */
    public function it_returns_correct_password_rules_without_confirmation()
    {
        $rules = $this->getPasswordRules(false, false);
        
        $this->assertContains('sometimes', $rules);
        $this->assertNotContains('confirmed', $rules);
    }
    
    /** @test */
    public function it_returns_correct_puskesmas_name_rules()
    {
        $rules = $this->getPuskesmasNameRules(true, false);
        
        $this->assertContains('required', $rules);
        $this->assertNotContains('nullable', $rules);
        $this->assertContains('string', $rules);
        $this->assertContains('min:' . ValidationConstants::NAME_MIN_LENGTH, $rules);
        $this->assertContains('max:' . ValidationConstants::NAME_MAX_LENGTH, $rules);
        $this->assertContains('regex:' . ValidationConstants::NAME_REGEX, $rules);
    }
    
    /** @test */
    public function it_returns_correct_profile_picture_rules()
    {
        $rules = $this->getProfilePictureRules(false, true);
        
        $this->assertContains('sometimes', $rules);
        $this->assertContains('nullable', $rules);
        $this->assertContains('image', $rules);
        $this->assertContains('mimes:' . implode(',', ValidationConstants::PROFILE_PICTURE_MIMES), $rules);
        $this->assertContains('max:' . ValidationConstants::PROFILE_PICTURE_MAX_SIZE, $rules);
        
        $expectedDimensions = 'dimensions:min_width=' . ValidationConstants::PROFILE_PICTURE_MIN_WIDTH . 
            ',min_height=' . ValidationConstants::PROFILE_PICTURE_MIN_HEIGHT . 
            ',max_width=' . ValidationConstants::PROFILE_PICTURE_MAX_WIDTH . 
            ',max_height=' . ValidationConstants::PROFILE_PICTURE_MAX_HEIGHT;
        $this->assertContains($expectedDimensions, $rules);
    }
    
    /** @test */
    public function it_returns_common_error_messages()
    {
        $messages = $this->getCommonErrorMessages();
        
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('name.regex', $messages);
        $this->assertArrayHasKey('username.regex', $messages);
        $this->assertArrayHasKey('password.regex', $messages);
        $this->assertArrayHasKey('puskesmas_name.regex', $messages);
        $this->assertArrayHasKey('profile_picture.image', $messages);
        
        $this->assertEquals(ValidationConstants::ERROR_MESSAGES, $messages);
    }
}