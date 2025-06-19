<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class FileUploadRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '10', string $decayMinutes = '60'): Response
    {
        // Only apply rate limiting to requests with file uploads
        if (!$request->hasFile('profile_picture')) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'message' => 'Terlalu banyak percobaan upload. Silakan coba lagi dalam ' . $seconds . ' detik.',
                'retry_after' => $seconds,
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        // Clear the rate limiter on successful upload
        if ($response->getStatusCode() === 200) {
            RateLimiter::clear($key);
        }
        
        return $response;
    }
    
    /**
     * Resolve the rate limiting signature for the request.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $request->user()?->id ?? 'guest';
        $ip = $request->ip();
        
        return 'file_upload:' . $userId . ':' . $ip;
    }
}