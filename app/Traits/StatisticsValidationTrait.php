<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Trait untuk validasi yang konsisten di StatisticsController
 * Memisahkan logika validasi dari controller
 */
trait StatisticsValidationTrait
{
    /**
     * Validasi request untuk endpoint index
     */
    protected function validateIndexRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm',
            'page.integer' => 'Parameter page harus berupa angka',
            'page.min' => 'Parameter page minimal 1',
            'per_page.integer' => 'Parameter per_page harus berupa angka',
            'per_page.min' => 'Parameter per_page minimal 1',
            'per_page.max' => 'Parameter per_page maksimal 100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk dashboard statistics
     */
    protected function validateDashboardRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'nullable|in:all,ht,dm'
        ], [
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk dashboard statistics endpoint
     */
    protected function validateDashboardStatisticsRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm'
        ], [
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm'
        ]);
    }

    /**
     * Validasi request untuk export statistics
     */
    protected function validateExportRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm',
            'table_type' => 'nullable|in:all,quarterly,monthly,puskesmas',
            'format' => 'nullable|in:pdf,excel',
            'name' => 'nullable|string|max:255'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm',
            'table_type.in' => 'Parameter table_type harus salah satu dari: all, quarterly, monthly, puskesmas',
            'format.in' => 'Parameter format harus salah satu dari: pdf, excel',
            'name.string' => 'Parameter name harus berupa string',
            'name.max' => 'Parameter name maksimal 255 karakter'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk monitoring report
     */
    protected function validateMonitoringRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:pdf,excel'
        ], [
            'format.required' => 'Parameter format wajib diisi',
            'format.in' => 'Parameter format harus salah satu dari: pdf, excel'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk admin statistics
     */
    protected function validateAdminRequest(Request $request)
    {
        // Cek apakah user adalah admin
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'name' => 'nullable|string|max:255'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm',
            'page.integer' => 'Parameter page harus berupa angka',
            'page.min' => 'Parameter page minimal 1',
            'per_page.integer' => 'Parameter per_page harus berupa angka',
            'per_page.min' => 'Parameter per_page minimal 1',
            'per_page.max' => 'Parameter per_page maksimal 100',
            'name.string' => 'Parameter name harus berupa string',
            'name.max' => 'Parameter name maksimal 255 karakter'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk puskesmas PDF export
     */
    protected function validatePuskesmasPdfRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk quarterly PDF export
     */
    protected function validateQuarterlyPdfRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'disease_type' => 'nullable|in:all,ht,dm'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        return null;
    }

    /**
     * Validasi request untuk admin statistics endpoint
     */
    protected function validateAdminStatisticsRequest(Request $request)
    {
        // Cek apakah user adalah admin
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'name' => 'nullable|string|max:255'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm',
            'page.integer' => 'Parameter page harus berupa angka',
            'page.min' => 'Parameter page minimal 1',
            'per_page.integer' => 'Parameter per_page harus berupa angka',
            'per_page.min' => 'Parameter per_page minimal 1',
            'per_page.max' => 'Parameter per_page maksimal 100',
            'name.string' => 'Parameter name harus berupa string',
            'name.max' => 'Parameter name maksimal 255 karakter'
        ]);

        return $validator;
    }

    /**
     * Validasi request untuk puskesmas export
     */
    protected function validatePuskesmasExportRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'disease_type' => 'nullable|in:all,ht,dm',
            'format' => 'nullable|in:excel,pdf'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'month.integer' => 'Parameter bulan harus berupa angka',
            'month.min' => 'Parameter bulan minimal 1',
            'month.max' => 'Parameter bulan maksimal 12',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm',
            'format.in' => 'Parameter format harus salah satu dari: excel, pdf'
        ]);

        return $validator;
    }

    /**
     * Validasi request untuk puskesmas quarterly export
     */
    protected function validatePuskesmasQuarterlyExportRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'quarter' => 'required|integer|min:1|max:4',
            'disease_type' => 'nullable|in:all,ht,dm',
            'format' => 'nullable|in:excel,pdf'
        ], [
            'year.required' => 'Parameter tahun wajib diisi',
            'year.integer' => 'Parameter tahun harus berupa angka',
            'year.min' => 'Parameter tahun minimal 2020',
            'year.max' => 'Parameter tahun maksimal ' . (date('Y') + 1),
            'quarter.required' => 'Parameter quarter wajib diisi',
            'quarter.integer' => 'Parameter quarter harus berupa angka',
            'quarter.min' => 'Parameter quarter minimal 1',
            'quarter.max' => 'Parameter quarter maksimal 4',
            'disease_type.in' => 'Parameter disease_type harus salah satu dari: all, ht, dm',
            'format.in' => 'Parameter format harus salah satu dari: excel, pdf'
        ]);

        return $validator;
    }

    /**
     * Response success yang konsisten
     */
    protected function successResponse($message, $data = null, $meta = null)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];

        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response, 200);
    }

    /**
     * Response error yang konsisten
     */
    protected function errorResponse($message, $errors = null, $statusCode = 400)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Response untuk data tidak ditemukan
     */
    protected function notFoundResponse($message = 'Data tidak ditemukan')
    {
        return $this->errorResponse($message, null, 404);
    }

    /**
     * Response untuk akses ditolak
     */
    protected function forbiddenResponse($message = 'Akses ditolak')
    {
        return $this->errorResponse($message, null, 403);
    }

    /**
     * Response untuk server error
     */
    protected function serverErrorResponse($message = 'Terjadi kesalahan server')
    {
        return $this->errorResponse($message, null, 500);
    }
}