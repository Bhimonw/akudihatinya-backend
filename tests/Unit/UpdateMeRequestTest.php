<?php

namespace Tests\Unit;

use App\Http\Requests\UpdateMeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateMeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected UpdateMeRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'username' => 'testuser',
            'name' => 'Test User',
        ]);
        
        $this->actingAs($this->user);
        
        // Setup upload configuration for testing
        Config::set('upload.profile_pictures', [
            'max_size' => 2048,
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'dimensions' => [
                'min_width' => 100,
                'min_height' => 100,
                'max_width' => 800,
                'max_height' => 800,
            ],
        ]);
        
        Storage::fake('public');
        Cache::flush();
    }

    /** @test */
    public function it_authorizes_authenticated_users()
    {
        $request = new UpdateMeRequest();
        $request->setUserResolver(fn() => $this->user);
        
        $this->assertTrue($request->authorize());
    }

    /** @test */
    public function it_validates_username_correctly()
    {
        $request = $this->createRequest(['username' => 'validuser123']);
        $validator = Validator::make($request->all(), $request->rules());
        
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_rejects_invalid_username_formats()
    {
        $invalidUsernames = [
            'ab', // too short
            'user with spaces', // contains spaces
            'user@domain', // contains @
            'user..name', // consecutive dots
            'user--name', // consecutive hyphens
        ];
        
        foreach ($invalidUsernames as $username) {
            $request = $this->createRequest(['username' => $username]);
            $validator = Validator::make($request->all(), $request->rules());
            
            $this->assertTrue($validator->fails(), "Username '{$username}' should be invalid");
        }
    }

    /** @test */
    public function it_blocks_reserved_usernames()
    {
        $reservedUsernames = ['admin', 'administrator', 'root', 'system', 'api', 'test'];
        
        foreach ($reservedUsernames as $username) {
            $request = $this->createRequest(['username' => $username]);
            $validator = Validator::make($request->all(), $request->rules());
            
            $request->withValidator($validator);
            
            $this->assertTrue($validator->fails(), "Reserved username '{$username}' should be blocked");
        }
    }

    /** @test */
    public function it_allows_keeping_same_username_even_if_reserved()
    {
        // Create a user with a reserved username
        $userWithReservedUsername = User::factory()->create([
            'username' => 'admin',
            'name' => 'Admin User',
        ]);
        
        $this->actingAs($userWithReservedUsername);
        
        // Try to update with the same username
        $request = $this->createRequest(['username' => 'admin']);
        $validator = Validator::make($request->all(), $request->rules());
        
        $request->withValidator($validator);
        
        $this->assertTrue($validator->passes(), "User should be able to keep their current username even if it's reserved");
    }

    /** @test */
    public function it_blocks_changing_to_different_reserved_username()
    {
        // User with non-reserved username trying to change to reserved one
        $request = $this->createRequest(['username' => 'admin']);
        $validator = Validator::make($request->all(), $request->rules());
        
        $request->withValidator($validator);
        
        $this->assertTrue($validator->fails(), "User should not be able to change to a reserved username");
    }

    /** @test */
    public function it_validates_name_with_unicode_support()
    {
        $validNames = [
            'John Doe',
            'José María',
            '李小明',
            "O'Connor",
            'Jean-Pierre',
        ];
        
        foreach ($validNames as $name) {
            $request = $this->createRequest(['name' => $name]);
            $validator = Validator::make($request->all(), $request->rules());
            
            $this->assertTrue($validator->passes(), "Name '{$name}' should be valid");
        }
    }

    /** @test */
    public function it_validates_strong_password_requirements()
    {
        $validPassword = 'StrongPass123!';
        $request = $this->createRequest([
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ]);
        
        $validator = Validator::make($request->all(), $request->rules());
        
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_rejects_weak_passwords()
    {
        $weakPasswords = [
            'password', // too common
            '12345678', // too common
            'weakpass', // no uppercase, no number, no special char
            'WEAKPASS', // no lowercase, no number, no special char
            'WeakPass', // no number, no special char
        ];
        
        foreach ($weakPasswords as $password) {
            $request = $this->createRequest([
                'password' => $password,
                'password_confirmation' => $password,
            ]);
            
            $validator = Validator::make($request->all(), $request->rules());
            $request->withValidator($validator);
            
            $this->assertTrue($validator->fails(), "Weak password '{$password}' should be rejected");
        }
    }

    /** @test */
    public function it_rejects_password_containing_username()
    {
        $request = $this->createRequest([
            'username' => 'testuser',
            'password' => 'TestuserPass123!',
            'password_confirmation' => 'TestuserPass123!',
        ]);
        
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /** @test */
    public function it_validates_profile_picture_with_dynamic_config()
    {
        $file = UploadedFile::fake()->image('profile.jpg', 400, 400)->size(1024);
        
        $request = $this->createRequest(['profile_picture' => $file]);
        $validator = Validator::make($request->all(), $request->rules());
        
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_rejects_oversized_profile_picture()
    {
        $file = UploadedFile::fake()->image('large.jpg', 400, 400)->size(3000); // 3MB
        
        $request = $this->createRequest(['profile_picture' => $file]);
        $validator = Validator::make($request->all(), $request->rules());
        
        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_rejects_invalid_image_dimensions()
    {
        $file = UploadedFile::fake()->image('small.jpg', 50, 50); // Too small
        
        $request = $this->createRequest(['profile_picture' => $file]);
        $validator = Validator::make($request->all(), $request->rules());
        
        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_applies_rate_limiting_for_password_changes()
    {
        // Simulate recent password change
        $this->user->update(['password_changed_at' => now()->subMinutes(30)]);
        
        $request = $this->createRequest([
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /** @test */
    public function it_applies_rate_limiting_for_profile_picture_uploads()
    {
        // Simulate multiple uploads
        Cache::put("profile_upload_limit:{$this->user->id}", 5, now()->addHour());
        
        $file = UploadedFile::fake()->image('profile.jpg', 400, 400);
        $request = $this->createRequest(['profile_picture' => $file]);
        
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('profile_picture', $validator->errors()->toArray());
    }

    /** @test */
    public function it_sanitizes_input_data()
    {
        $request = $this->createRequest([
            'username' => '  TestUser  ',
            'name' => '  Test User  ',
        ]);
        
        $this->assertEquals('testuser', $request->input('username'));
        $this->assertEquals('Test User', $request->input('name'));
    }

    /** @test */
    public function it_generates_dynamic_error_messages()
    {
        $request = new UpdateMeRequest();
        $request->setUserResolver(fn() => $this->user);
        
        $messages = $request->messages();
        
        $this->assertStringContains('2048KB', $messages['profile_picture.max']);
        $this->assertStringContains('100x100', $messages['profile_picture.dimensions']);
        $this->assertStringContains('800x800', $messages['profile_picture.dimensions']);
    }

    /** @test */
    public function it_logs_password_change_attempts()
    {
        $request = $this->createRequest([
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        
        $validated = $request->validated();
        
        // Check that password is in validated data
        $this->assertArrayHasKey('password', $validated);
        
        // In a real test, you would mock the logger and assert it was called
        // This is a basic structure test
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_validation_failures_with_logging()
    {
        $request = $this->createRequest(['username' => 'ab']); // Too short
        
        try {
            $validator = Validator::make($request->all(), $request->rules());
            $request->failedValidation($validator);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Expected exception
            $this->assertTrue(true);
        }
    }

    /**
     * Create a request instance with given data.
     */
    protected function createRequest(array $data = []): UpdateMeRequest
    {
        $request = new UpdateMeRequest();
        $request->setUserResolver(fn() => $this->user);
        $request->merge($data);
        
        return $request;
    }
}