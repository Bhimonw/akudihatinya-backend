<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Puskesmas;
use App\Services\ProfileUpdateService;
use App\Services\ProfilePictureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class ProfileUpdateServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected ProfileUpdateService $service;
    protected $mockProfilePictureService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockProfilePictureService = Mockery::mock(ProfilePictureService::class);
        $this->service = new ProfileUpdateService($this->mockProfilePictureService);
    }
    
    /** @test */
    public function it_can_update_user_profile_with_basic_data()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'role' => 'admin'
        ]);
        
        $data = [
            'name' => 'New Name'
        ];
        
        $updatedUser = $this->service->updateProfile($user, $data);
        
        $this->assertEquals('New Name', $updatedUser->name);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name'
        ]);
    }
    
    /** @test */
    public function it_hashes_password_when_updating()
    {
        $user = User::factory()->create();
        
        $data = [
            'password' => 'newpassword123'
        ];
        
        $updatedUser = $this->service->updateProfile($user, $data);
        
        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));
    }
    
    /** @test */
    public function it_can_update_puskesmas_name_for_puskesmas_role()
    {
        $puskesmas = Puskesmas::factory()->create(['name' => 'Old Puskesmas']);
        $user = User::factory()->create([
            'role' => 'puskesmas',
            'puskesmas_id' => $puskesmas->id
        ]);
        
        $data = [
            'puskesmas_name' => 'New Puskesmas Name'
        ];
        
        $updatedUser = $this->service->updateProfile($user, $data);
        
        $this->assertEquals('New Puskesmas Name', $updatedUser->puskesmas->name);
    }
    
    /** @test */
    public function it_cannot_update_puskesmas_name_for_admin_role()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $data = [
            'puskesmas_name' => 'Should Not Update'
        ];
        
        $filteredData = $this->service->filterValidFields($user, $data);
        
        $this->assertArrayNotHasKey('puskesmas_name', $filteredData);
    }
    
    /** @test */
    public function it_can_upload_profile_picture()
    {
        Storage::fake('public');
        
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');
        
        $this->mockProfilePictureService
            ->shouldReceive('uploadProfilePicture')
            ->once()
            ->with($file, null, $user->id)
            ->andReturn('path/to/uploaded/image.jpg');
        
        $data = [];
        
        $updatedUser = $this->service->updateProfile($user, $data, $file);
        
        $this->assertEquals('path/to/uploaded/image.jpg', $updatedUser->profile_picture);
    }
    
    /** @test */
    public function it_filters_invalid_fields_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $data = [
            'name' => 'Valid Name',
            'role' => 'should_not_update',
            'puskesmas_name' => 'should_not_update_for_admin'
        ];
        
        $filteredData = $this->service->filterValidFields($user, $data);
        
        $this->assertArrayHasKey('name', $filteredData);
        $this->assertArrayNotHasKey('role', $filteredData);
        $this->assertArrayNotHasKey('puskesmas_name', $filteredData);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}