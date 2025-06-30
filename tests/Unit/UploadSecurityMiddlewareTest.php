<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\UploadSecurityMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class UploadSecurityMiddlewareTest extends TestCase
{
    protected UploadSecurityMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->middleware = new UploadSecurityMiddleware();
        
        // Setup test configuration
        Config::set('upload.security.rate_limit.enabled', true);
        Config::set('upload.security.rate_limit.max_attempts', 5);
        Config::set('upload.security.rate_limit.decay_minutes', 60);
        Config::set('upload.security.max_file_size', 2048); // 2MB
        Config::set('upload.security.allowed_mimes', ['image/jpeg', 'image/png', 'image/jpg']);
        Config::set('upload.security.allowed_extensions', ['jpeg', 'jpg', 'png']);
        Config::set('upload.security.scan_content', true);
        Config::set('upload.security.check_dimensions', true);
        Config::set('upload.security.min_width', 50);
        Config::set('upload.security.min_height', 50);
        Config::set('upload.security.max_width', 2048);
        Config::set('upload.security.max_height', 2048);
        Config::set('upload.logging.security_events', true);
    }

    /** @test */
    public function it_allows_valid_upload_request()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Mock a valid file
        $file = UploadedFile::fake()->image('valid.jpg', 300, 300)->size(1000);
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
    }

    /** @test */
    public function it_blocks_oversized_files()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Mock an oversized file (3MB > 2MB limit)
        $file = UploadedFile::fake()->image('large.jpg', 1000, 1000)->size(3000);
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(413, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('too large', $responseData['message']);
    }

    /** @test */
    public function it_blocks_invalid_mime_types()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Mock a file with invalid MIME type
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(415, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Invalid file type', $responseData['message']);
    }

    /** @test */
    public function it_blocks_invalid_extensions()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Mock a file with invalid extension
        $file = UploadedFile::fake()->create('image.bmp', 1000, 'image/bmp');
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(415, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Invalid file extension', $responseData['message']);
    }

    /** @test */
    public function it_blocks_suspicious_filenames()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Mock a file with suspicious filename
        $file = UploadedFile::fake()->image('script.php.jpg', 300, 300);
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Suspicious filename', $responseData['message']);
    }

    /** @test */
    public function it_blocks_images_with_invalid_dimensions()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Mock an image that's too small
        $file = UploadedFile::fake()->image('tiny.jpg', 30, 30); // Below 50x50 minimum
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Invalid image dimensions', $responseData['message']);
    }

    /** @test */
    public function it_enforces_rate_limiting()
    {
        // Clear any existing rate limits
        RateLimiter::clear('upload_security:1');
        
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        $file = UploadedFile::fake()->image('valid.jpg', 300, 300);
        $request->files->set('profile_picture', $file);
        
        // Make requests up to the limit (5)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
            $this->assertEquals(200, $response->getStatusCode());
        }
        
        // The 6th request should be rate limited
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(429, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Too many upload attempts', $responseData['message']);
    }

    /** @test */
    public function it_allows_requests_without_files()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // No files attached
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_multiple_files()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Add multiple valid files
        $file1 = UploadedFile::fake()->image('image1.jpg', 300, 300);
        $file2 = UploadedFile::fake()->image('image2.png', 400, 400);
        
        $request->files->set('profile_picture', $file1);
        $request->files->set('cover_image', $file2);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_blocks_if_one_file_is_invalid()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        // Add one valid and one invalid file
        $validFile = UploadedFile::fake()->image('valid.jpg', 300, 300);
        $invalidFile = UploadedFile::fake()->create('invalid.pdf', 1000, 'application/pdf');
        
        $request->files->set('profile_picture', $validFile);
        $request->files->set('document', $invalidFile);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        $this->assertEquals(415, $response->getStatusCode());
    }

    /** @test */
    public function it_can_be_disabled_via_configuration()
    {
        // Disable rate limiting
        Config::set('upload.security.rate_limit.enabled', false);
        
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'ip' => '127.0.0.1'];
        });
        
        $file = UploadedFile::fake()->image('valid.jpg', 300, 300);
        $request->files->set('profile_picture', $file);
        
        // Should work even with many requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['success' => true]);
            });
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /** @test */
    public function it_handles_guest_users()
    {
        $request = Request::create('/upload', 'POST');
        $request->setUserResolver(function () {
            return null; // Guest user
        });
        
        $file = UploadedFile::fake()->image('valid.jpg', 300, 300);
        $request->files->set('profile_picture', $file);
        
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
        
        // Should still work for guest users (using IP for rate limiting)
        $this->assertEquals(200, $response->getStatusCode());
    }
}