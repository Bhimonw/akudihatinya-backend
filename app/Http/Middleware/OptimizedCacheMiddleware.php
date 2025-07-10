<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optimized Cache Middleware
 * 
 * Features:
 * - Intelligent response caching
 * - User-specific cache keys
 * - Rate limiting integration
 * - Cache invalidation on mutations
 * - Performance monitoring
 */
class OptimizedCacheMiddleware
{
    /**
     * Cache TTL in minutes
     */
    private const CACHE_TTL = 15;
    private const LONG_CACHE_TTL = 60;
    
    /**
     * Routes that should be cached
     */
    private const CACHEABLE_ROUTES = [
        'api.statistics.*',
        'api.patients.index',
        'api.dashboard.*',
        'api.reports.*'
    ];
    
    /**
     * Routes that should have longer cache
     */
    private const LONG_CACHE_ROUTES = [
        'api.statistics.yearly',
        'api.reports.annual'
    ];
    
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip caching for non-GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }
        
        // Skip caching if not cacheable route
        if (!$this->isCacheableRoute($request)) {
            return $next($request);
        }
        
        // Apply rate limiting
        $this->applyRateLimiting($request);
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Try to get from cache
        $cachedResponse = Cache::tags($this->getCacheTags($request))
            ->get($cacheKey);
            
        if ($cachedResponse) {
            return $this->createResponseFromCache($cachedResponse);
        }
        
        // Process request
        $response = $next($request);
        
        // Cache successful responses
        if ($response->getStatusCode() === 200) {
            $this->cacheResponse($request, $response, $cacheKey);
        }
        
        return $response;
    }
    
    /**
     * Check if route should be cached
     */
    private function isCacheableRoute(Request $request): bool
    {
        $routeName = $request->route()->getName();
        
        foreach (self::CACHEABLE_ROUTES as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Apply rate limiting
     */
    private function applyRateLimiting(Request $request): void
    {
        $key = $this->getRateLimitKey($request);
        $maxAttempts = $this->getRateLimitMaxAttempts($request);
        $decayMinutes = 1;
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            abort(429, 'Too Many Requests');
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
    }
    
    /**
     * Generate rate limit key
     */
    private function getRateLimitKey(Request $request): string
    {
        $user = Auth::user();
        $userId = $user ? $user->id : $request->ip();
        $route = $request->route()->getName();
        
        return "rate_limit:{$userId}:{$route}";
    }
    
    /**
     * Get rate limit max attempts based on route
     */
    private function getRateLimitMaxAttempts(Request $request): int
    {
        $routeName = $request->route()->getName();
        
        // Different limits for different route types
        if (str_contains($routeName, 'statistics')) {
            return 30; // Statistics can be called more frequently
        }
        
        if (str_contains($routeName, 'reports')) {
            return 10; // Reports are more expensive
        }
        
        return 60; // Default limit
    }
    
    /**
     * Generate cache key for request
     */
    private function generateCacheKey(Request $request): string
    {
        $user = Auth::user();
        $userId = $user ? $user->id : 'guest';
        $puskesmasId = $user ? $user->puskesmas_id : 'all';
        
        $route = $request->route()->getName();
        $params = $request->query();
        
        // Sort parameters for consistent cache keys
        ksort($params);
        
        $keyParts = [
            'response_cache',
            $route,
            $userId,
            $puskesmasId,
            md5(serialize($params))
        ];
        
        return implode(':', $keyParts);
    }
    
    /**
     * Get cache tags for request
     */
    private function getCacheTags(Request $request): array
    {
        $user = Auth::user();
        $tags = ['responses'];
        
        if ($user) {
            $tags[] = "user:{$user->id}";
            if ($user->puskesmas_id) {
                $tags[] = "puskesmas:{$user->puskesmas_id}";
            }
        }
        
        $routeName = $request->route()->getName();
        
        if (str_contains($routeName, 'statistics')) {
            $tags[] = 'statistics';
        }
        
        if (str_contains($routeName, 'patients')) {
            $tags[] = 'patients';
        }
        
        if (str_contains($routeName, 'reports')) {
            $tags[] = 'reports';
        }
        
        return $tags;
    }
    
    /**
     * Cache the response
     */
    private function cacheResponse(Request $request, Response $response, string $cacheKey): void
    {
        $ttl = $this->getCacheTTL($request);
        $tags = $this->getCacheTags($request);
        
        $cacheData = [
            'content' => $response->getContent(),
            'headers' => $response->headers->all(),
            'status' => $response->getStatusCode(),
            'cached_at' => now()->toISOString()
        ];
        
        Cache::tags($tags)->put($cacheKey, $cacheData, $ttl);
    }
    
    /**
     * Get cache TTL based on route
     */
    private function getCacheTTL(Request $request): int
    {
        $routeName = $request->route()->getName();
        
        foreach (self::LONG_CACHE_ROUTES as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return self::LONG_CACHE_TTL;
            }
        }
        
        return self::CACHE_TTL;
    }
    
    /**
     * Create response from cached data
     */
    private function createResponseFromCache(array $cachedData): Response
    {
        $response = new Response(
            $cachedData['content'],
            $cachedData['status'],
            $cachedData['headers']
        );
        
        // Add cache headers
        $response->headers->set('X-Cache', 'HIT');
        $response->headers->set('X-Cache-Time', $cachedData['cached_at']);
        
        return $response;
    }
    
    /**
     * Invalidate cache for specific tags
     */
    public static function invalidateCache(array $tags): void
    {
        Cache::tags(array_merge(['responses'], $tags))->flush();
    }
    
    /**
     * Invalidate cache for user
     */
    public static function invalidateUserCache(int $userId): void
    {
        Cache::tags(['responses', "user:{$userId}"])->flush();
    }
    
    /**
     * Invalidate cache for puskesmas
     */
    public static function invalidatePuskesmasCache(int $puskesmasId): void
    {
        Cache::tags(['responses', "puskesmas:{$puskesmasId}"])->flush();
    }
    
    /**
     * Get cache statistics
     */
    public static function getCacheStatistics(): array
    {
        try {
            $redis = Cache::getRedis();
            $info = $redis->info('memory');
            
            return [
                'cache_driver' => config('cache.default'),
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'total_keys' => $redis->dbsize(),
                'cache_hit_rate' => $redis->info('stats')['keyspace_hits'] ?? 0,
                'cache_miss_rate' => $redis->info('stats')['keyspace_misses'] ?? 0
            ];
        } catch (\Exception $e) {
            return [
                'cache_driver' => config('cache.default'),
                'error' => 'Unable to retrieve cache statistics'
            ];
        }
    }
    
    /**
     * Warm up cache for common requests
     */
    public static function warmUpCache(): void
    {
        // This would be called by a scheduled job
        // to pre-populate cache with frequently accessed data
        
        $commonRoutes = [
            'api.statistics.dashboard',
            'api.patients.index',
        ];
        
        foreach ($commonRoutes as $route) {
            // Make internal requests to warm up cache
            // Implementation would depend on your specific needs
        }
    }
}