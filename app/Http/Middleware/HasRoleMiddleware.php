<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware yang lebih fleksibel untuk role-based access control
 * Dapat menerima multiple roles dan kondisi OR/AND
 */
class HasRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Akses ditolak. Anda harus login terlebih dahulu.',
            ], 401);
        }
        
        // If no roles specified, just check authentication
        if (empty($roles)) {
            return $next($request);
        }
        
        // Check if user has any of the required roles
        if (!in_array($user->role, $roles)) {
            $allowedRoles = implode(', ', array_map('ucfirst', $roles));
            return response()->json([
                'message' => "Akses ditolak. Role yang diizinkan: {$allowedRoles}.",
                'user_role' => ucfirst($user->role),
                'required_roles' => $roles
            ], 403);
        }
        
        return $next($request);
    }
}