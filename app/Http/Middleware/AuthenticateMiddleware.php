<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class AuthenticateMiddleware extends Middleware
{
    /**
     * Override: Return JSON response if unauthenticated (expired token, dsb)
     */
    protected function unauthenticated($request, array $guards)
    {
        abort(response()->json([
            'message' => 'Unauthenticated.',
        ], 401));
    }

    protected function redirectTo($request)
    {
        return null;
    }
}