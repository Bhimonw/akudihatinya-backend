<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PuskesmasNotFoundException extends Exception
{
    protected $puskesmasId;
    protected $context;

    public function __construct($puskesmasId = null, array $context = [], $message = null, $code = 0, Exception $previous = null)
    {
        $this->puskesmasId = $puskesmasId;
        $this->context = $context;

        $message = $message ?: "Puskesmas dengan ID '{$puskesmasId}' tidak ditemukan";

        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'puskesmas_not_found',
            'message' => $this->getMessage(),
            'data' => [
                'puskesmas_id' => $this->puskesmasId,
                'suggestions' => [
                    'Pastikan ID Puskesmas yang digunakan valid',
                    'Periksa apakah Puskesmas masih aktif dalam sistem',
                    'Hubungi administrator jika masalah berlanjut'
                ]
            ]
        ], 404);
    }

    /**
     * Get the context data for logging
     */
    public function getContext(): array
    {
        return array_merge($this->context, [
            'puskesmas_id' => $this->puskesmasId,
            'exception_class' => static::class
        ]);
    }

    /**
     * Get the Puskesmas ID that caused the exception
     */
    public function getPuskesmasId()
    {
        return $this->puskesmasId;
    }
}
