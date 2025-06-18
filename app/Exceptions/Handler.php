<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }
        
        if ($exception instanceof ModelNotFoundException) {
            $model = class_basename($exception->getModel());
            
            if ($model === 'YearlyTarget') {
                return response()->json([
                    'error' => 'yearly_target_not_found',
                    'message' => 'ID sasaran tahunan tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'error' => 'resource_not_found',
                'message' => 'Data tidak ditemukan'
            ], 404);
        }
        
        return parent::render($request, $exception);
    }
}
