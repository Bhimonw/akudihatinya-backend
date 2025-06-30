<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProfilePictureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class ProfilePictureServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProfilePictureService $service;
    protected $testDisk = 'testing';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup test disk
        Config::set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('app/testing'),
            'url' => env('APP_URL').'/storage/testing',
            'visibility' => 'public',
        ]);

        // Override upload configuration for testing
        Config::set('upload.profile_pictures.disk', $this->testDisk);
        Config::set('upload.profile_pictures.path', 'test-profile-pictures');
        Config::set('upload.profile_pictures.max_size', 2048); // 2MB
        Config::set('upload.profile_pictures.allowed_mimes', ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
        Config::set('upload.profile_pictures.allowed_extensions', ['jpeg', 'jpg', 'png', 'gif', 'webp']);
        Config::set('upload.profile_pictures.max_width', 1024);
        Config::set('upload.profile_pictures.max_height', 1024);
        Config::set('upload.profile_pictures.min_width', 50);
        Config::set('upload.profile_pictures.min_height', 50);
        Config::set('upload.profile_pictures.optimize_images', true);
        Config::set('upload.profile_pictures.preserve_transparency', true);
        Config::set('upload.profile_pictures.jpeg_quality', 85);
        Config::set('upload.profile_pictures.webp_quality', 85);
        Config::set('upload.profile_pictures.png_compression', 6);
        Config::set('upload.security.generate_unique_names', true);
        Config::set('upload.logging.enabled', false); // Disable logging for tests

        $this->service = new ProfilePictureService();
        
        // Create test storage directory
        Storage::fake($this->testDisk);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        Storage::disk($this->testDisk)->deleteDirectory('test-profile-pictures');
        parent::tearDown();
    }

    /** @test */
    public function it_can_upload_valid_jpeg_image()
    {
        $user = User::factory()->create();
        
        // Create a fake JPEG image
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500)->size(1000); // 1MB
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('size', $result);
        
        // Check file exists in storage
        $this->assertTrue(Storage::disk($this->testDisk)->exists('test-profile-pictures/' . $result['filename']));
    }

    /** @test */
    public function it_can_upload_valid_png_image()
    {
        $user = User::factory()->create();
        
        $file = UploadedFile::fake()->image('profile.png', 400, 400)->size(800);
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        $this->assertTrue($result['success']);
        $this->assertStringContains('.png', $result['filename']);
    }

    /** @test */
    public function it_rejects_file_too_large()
    {
        $user = User::factory()->create();
        
        // Create file larger than max size (2MB)
        $file = UploadedFile::fake()->image('large.jpg', 2000, 2000)->size(3000); // 3MB
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('too large', $result['message']);
    }

    /** @test */
    public function it_rejects_invalid_file_type()
    {
        $user = User::factory()->create();
        
        // Create a non-image file
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('Invalid file type', $result['message']);
    }

    /** @test */
    public function it_rejects_invalid_file_extension()
    {
        $user = User::factory()->create();
        
        // Create file with invalid extension
        $file = UploadedFile::fake()->create('image.bmp', 1000, 'image/bmp');
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('Invalid file extension', $result['message']);
    }

    /** @test */
    public function it_generates_unique_filename()
    {
        $user = User::factory()->create();
        
        $file1 = UploadedFile::fake()->image('profile.jpg', 300, 300);
        $file2 = UploadedFile::fake()->image('profile.jpg', 300, 300);
        
        $result1 = $this->service->uploadProfilePicture($file1, $user->id);
        $result2 = $this->service->uploadProfilePicture($file2, $user->id);
        
        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertNotEquals($result1['filename'], $result2['filename']);
    }

    /** @test */
    public function it_deletes_old_profile_picture()
    {
        $user = User::factory()->create();
        
        // Upload first image
        $file1 = UploadedFile::fake()->image('profile1.jpg', 300, 300);
        $result1 = $this->service->uploadProfilePicture($file1, $user->id);
        $this->assertTrue($result1['success']);
        
        // Verify first image exists
        $this->assertTrue(Storage::disk($this->testDisk)->exists('test-profile-pictures/' . $result1['filename']));
        
        // Update user with first image filename
        $user->update(['profile_picture' => $result1['filename']]);
        
        // Upload second image
        $file2 = UploadedFile::fake()->image('profile2.jpg', 300, 300);
        $result2 = $this->service->uploadProfilePicture($file2, $user->id);
        $this->assertTrue($result2['success']);
        
        // First image should be deleted, second should exist
        $this->assertFalse(Storage::disk($this->testDisk)->exists('test-profile-pictures/' . $result1['filename']));
        $this->assertTrue(Storage::disk($this->testDisk)->exists('test-profile-pictures/' . $result2['filename']));
    }

    /** @test */
    public function it_can_get_profile_picture_url()
    {
        $filename = 'test-image.jpg';
        $url = $this->service->getProfilePictureUrl($filename);
        
        $this->assertIsString($url);
        $this->assertStringContains($filename, $url);
    }

    /** @test */
    public function it_can_check_file_exists()
    {
        // Create a test file
        Storage::disk($this->testDisk)->put('test-profile-pictures/test.jpg', 'fake content');
        
        $this->assertTrue($this->service->fileExists('test.jpg'));
        $this->assertFalse($this->service->fileExists('nonexistent.jpg'));
    }

    /** @test */
    public function it_handles_missing_gd_extension_gracefully()
    {
        // This test would need to mock the extension_loaded function
        // For now, we'll just ensure the service doesn't crash
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('profile.jpg', 300, 300);
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        // Should still work even if optimization fails
        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_validates_image_dimensions()
    {
        $user = User::factory()->create();
        
        // Create image smaller than minimum dimensions
        $file = UploadedFile::fake()->image('small.jpg', 30, 30); // Below 50x50 minimum
        
        $result = $this->service->uploadProfilePicture($file, $user->id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('dimensions', strtolower($result['message']));
    }

    /** @test */
    public function it_handles_storage_errors_gracefully()
    {
        $user = User::factory()->create();
        
        // Use a non-existent disk to trigger storage error
        Config::set('upload.profile_pictures.disk', 'nonexistent');
        $service = new ProfilePictureService();
        
        $file = UploadedFile::fake()->image('profile.jpg', 300, 300);
        
        $result = $service->uploadProfilePicture($file, $user->id);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    /** @test */
    public function it_respects_configuration_settings()
    {
        // Test that service uses configuration correctly
        $this->assertEquals($this->testDisk, $this->service->getDisk());
        $this->assertEquals('test-profile-pictures', $this->service->getPath());
        $this->assertEquals(2048, $this->service->getMaxFileSize());
        $this->assertEquals(['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'], $this->service->getAllowedMimes());
    }

    /** @test */
    public function it_can_handle_cdn_urls()
    {
        // Test CDN URL generation
        Config::set('upload.cdn.enabled', true);
        Config::set('upload.cdn.base_url', 'https://cdn.example.com');
        
        $service = new ProfilePictureService();
        $url = $service->getProfilePictureUrl('test.jpg');
        
        $this->assertStringContains('cdn.example.com', $url);
    }
}