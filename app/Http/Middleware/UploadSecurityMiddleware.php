<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class UploadSecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if upload security is enabled
        if (!Config::get('upload.security.scan_uploads', false)) {
            return $next($request);
        }

        $userId = auth()->id();
        $userKey = 'upload_attempts_user_' . $userId;
        $ipKey = 'upload_attempts_ip_' . $request->ip();

        // Rate limiting per user (10 uploads per minute)
        if (RateLimiter::tooManyAttempts($userKey, 10)) {
            $this->logSecurityEvent('rate_limit_exceeded_user', $request, [
                'user_id' => $userId,
                'attempts' => RateLimiter::attempts($userKey)
            ]);
            
            return response()->json([
                'error' => 'Terlalu banyak percobaan upload. Coba lagi dalam beberapa menit.'
            ], 429);
        }

        // Rate limiting per IP (20 uploads per minute)
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $this->logSecurityEvent('rate_limit_exceeded_ip', $request, [
                'ip' => $request->ip(),
                'attempts' => RateLimiter::attempts($ipKey)
            ]);
            
            return response()->json([
                'error' => 'Terlalu banyak percobaan upload dari IP ini.'
            ], 429);
        }

        // Validate request has file
        if (!$request->hasFile('profile_picture')) {
            RateLimiter::hit($userKey, 60); // 1 minute
            RateLimiter::hit($ipKey, 60);
            
            return response()->json([
                'error' => 'File tidak ditemukan dalam request.'
            ], 400);
        }

        $file = $request->file('profile_picture');

        // Basic security checks
        $securityCheck = $this->performSecurityChecks($file, $request);
        if ($securityCheck !== true) {
            RateLimiter::hit($userKey, 300); // 5 minutes penalty for security violations
            RateLimiter::hit($ipKey, 300);
            
            return response()->json([
                'error' => $securityCheck
            ], 400);
        }

        // Increment rate limiter counters for successful validation
        RateLimiter::hit($userKey, 60);
        RateLimiter::hit($ipKey, 60);

        return $next($request);
    }

    /**
     * Perform security checks on uploaded file
     */
    private function performSecurityChecks($file, Request $request)
    {
        try {
            // Check if file is valid
            if (!$file->isValid()) {
                $this->logSecurityEvent('invalid_file_upload', $request, [
                    'error' => $file->getErrorMessage()
                ]);
                return 'File upload tidak valid.';
            }

            // Check file size limits
            $maxSize = Config::get('upload.profile_pictures.max_size', 2048) * 1024; // Convert to bytes
            if ($file->getSize() > $maxSize) {
                $this->logSecurityEvent('file_size_exceeded', $request, [
                    'file_size' => $file->getSize(),
                    'max_size' => $maxSize
                ]);
                return 'Ukuran file terlalu besar.';
            }

            // Check MIME type
            $allowedMimes = Config::get('upload.profile_pictures.allowed_mimes', []);
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                $this->logSecurityEvent('invalid_mime_type', $request, [
                    'mime_type' => $file->getMimeType(),
                    'allowed_mimes' => $allowedMimes
                ]);
                return 'Tipe file tidak diizinkan.';
            }

            // Check file extension
            $allowedExtensions = Config::get('upload.profile_pictures.allowed_extensions', []);
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions)) {
                $this->logSecurityEvent('invalid_file_extension', $request, [
                    'extension' => $extension,
                    'allowed_extensions' => $allowedExtensions
                ]);
                return 'Ekstensi file tidak diizinkan.';
            }

            // Check for suspicious file names
            $filename = $file->getClientOriginalName();
            if ($this->isSuspiciousFilename($filename)) {
                $this->logSecurityEvent('suspicious_filename', $request, [
                    'filename' => $filename
                ]);
                return 'Nama file tidak diizinkan.';
            }

            // Advanced content validation if enabled
            if (Config::get('upload.security.validate_file_content', true)) {
                $contentCheck = $this->validateFileContent($file, $request);
                if ($contentCheck !== true) {
                    return $contentCheck;
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->logSecurityEvent('security_check_error', $request, [
                'error' => $e->getMessage()
            ]);
            return 'Gagal memvalidasi file.';
        }
    }

    /**
     * Validate file content for security
     */
    private function validateFileContent($file, Request $request)
    {
        try {
            // Check if file is actually an image
            $imageInfo = getimagesize($file->getPathname());
            if (!$imageInfo) {
                $this->logSecurityEvent('invalid_image_content', $request, [
                    'filename' => $file->getClientOriginalName()
                ]);
                return 'File bukan gambar yang valid.';
            }

            // Check image dimensions
            $minWidth = Config::get('upload.profile_pictures.dimensions.min_width', 0);
            $minHeight = Config::get('upload.profile_pictures.dimensions.min_height', 0);
            $maxWidth = Config::get('upload.profile_pictures.dimensions.max_width', 5000);
            $maxHeight = Config::get('upload.profile_pictures.dimensions.max_height', 5000);

            if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
                $this->logSecurityEvent('image_too_small', $request, [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'min_width' => $minWidth,
                    'min_height' => $minHeight
                ]);
                return "Dimensi gambar terlalu kecil. Minimal {$minWidth}x{$minHeight} pixels.";
            }

            if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
                $this->logSecurityEvent('image_too_large', $request, [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'max_width' => $maxWidth,
                    'max_height' => $maxHeight
                ]);
                return "Dimensi gambar terlalu besar. Maksimal {$maxWidth}x{$maxHeight} pixels.";
            }

            // Check for embedded malicious content (basic check)
            $fileContent = file_get_contents($file->getPathname());
            $suspiciousPatterns = [
                '<?php',
                '<script',
                'javascript:',
                'vbscript:',
                'onload=',
                'onerror='
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (stripos($fileContent, $pattern) !== false) {
                    $this->logSecurityEvent('malicious_content_detected', $request, [
                        'pattern' => $pattern,
                        'filename' => $file->getClientOriginalName()
                    ]);
                    return 'File mengandung konten yang tidak diizinkan.';
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->logSecurityEvent('content_validation_error', $request, [
                'error' => $e->getMessage()
            ]);
            return 'Gagal memvalidasi konten file.';
        }
    }

    /**
     * Check for suspicious filenames
     */
    private function isSuspiciousFilename(string $filename): bool
    {
        $suspiciousPatterns = [
            '/\.(php|phtml|php3|php4|php5|phar)$/i',
            '/\.(js|vbs|bat|cmd|com|pif|scr|exe)$/i',
            '/\.(htaccess|\.htpasswd)$/i',
            '/\.\./',
            '/[<>:"|?*]/',
            '/^(con|prn|aux|nul|com[1-9]|lpt[1-9])$/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log security events
     */
    private function logSecurityEvent(string $event, Request $request, array $context = [])
    {
        if (Config::get('upload.logging.enabled', true)) {
            Log::warning('Upload security event: ' . $event, array_merge([
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ], $context));
        }
    }
}