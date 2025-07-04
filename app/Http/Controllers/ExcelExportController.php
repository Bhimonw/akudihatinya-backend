<?php

namespace App\Http\Controllers;

use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk menangani export Excel
 * Menyediakan API endpoints untuk berbagai jenis laporan Excel
 */
class ExcelExportController extends Controller
{
    protected $excelExportService;

    public function __construct(ExcelExportService $excelExportService)
    {
        $this->excelExportService = $excelExportService;
    }

    /**
     * Export laporan tahunan komprehensif (all.xlsx)
     * 
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function exportAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'sometimes|string|in:ht,dm,both',
            'year' => 'sometimes|integer|min:2020|max:' . (date('Y') + 1),
            'download' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $diseaseType = $request->get('disease_type', 'ht');
            $year = $request->get('year', date('Y'));
            $download = $request->get('download', false);

            if ($download) {
                return $this->excelExportService->streamDownload($diseaseType, $year, 'all');
            }

            $result = $this->excelExportService->exportAll($diseaseType, $year);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Export berhasil' : 'Export gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in exportAll', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export laporan bulanan (monthly.xlsx)
     * 
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function exportMonthly(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'sometimes|string|in:ht,dm,both',
            'year' => 'sometimes|integer|min:2020|max:' . (date('Y') + 1),
            'download' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $diseaseType = $request->get('disease_type', 'ht');
            $year = $request->get('year', date('Y'));
            $download = $request->get('download', false);

            if ($download) {
                return $this->excelExportService->streamDownload($diseaseType, $year, 'monthly');
            }

            $result = $this->excelExportService->exportMonthly($diseaseType, $year);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Export berhasil' : 'Export gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in exportMonthly', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export laporan triwulan (quarterly.xlsx)
     * 
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function exportQuarterly(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'sometimes|string|in:ht,dm,both',
            'year' => 'sometimes|integer|min:2020|max:' . (date('Y') + 1),
            'download' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $diseaseType = $request->get('disease_type', 'ht');
            $year = $request->get('year', date('Y'));
            $download = $request->get('download', false);

            if ($download) {
                return $this->excelExportService->streamDownload($diseaseType, $year, 'quarterly');
            }

            $result = $this->excelExportService->exportQuarterly($diseaseType, $year);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Export berhasil' : 'Export gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in exportQuarterly', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export laporan per puskesmas (puskesmas.xlsx)
     * 
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function exportPuskesmas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'puskesmas_id' => 'required|integer|exists:puskesmas,id',
            'disease_type' => 'sometimes|string|in:ht,dm,both',
            'year' => 'sometimes|integer|min:2020|max:' . (date('Y') + 1),
            'download' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $puskesmasId = $request->get('puskesmas_id');
            $diseaseType = $request->get('disease_type', 'ht');
            $year = $request->get('year', date('Y'));
            $download = $request->get('download', false);

            if ($download) {
                return $this->excelExportService->streamDownload($diseaseType, $year, 'puskesmas');
            }

            $result = $this->excelExportService->exportPuskesmas($puskesmasId, $diseaseType, $year);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Export berhasil' : 'Export gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in exportPuskesmas', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export template puskesmas kosong
     * 
     * @param Request $request
     * @return JsonResponse|Response
     */
    public function exportPuskesmasTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'sometimes|string|in:ht,dm,both',
            'year' => 'sometimes|integer|min:2020|max:' . (date('Y') + 1),
            'download' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $diseaseType = $request->get('disease_type', 'ht');
            $year = $request->get('year', date('Y'));
            $download = $request->get('download', false);

            if ($download) {
                return $this->excelExportService->streamDownload($diseaseType, $year, 'puskesmas');
            }

            $result = $this->excelExportService->exportPuskesmasTemplate($diseaseType, $year);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Export berhasil' : 'Export gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in exportPuskesmasTemplate', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export semua jenis laporan sekaligus (batch export)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function exportBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'sometimes|string|in:ht,dm,both',
            'year' => 'sometimes|integer|min:2020|max:' . (date('Y') + 1)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $diseaseType = $request->get('disease_type', 'ht');
            $year = $request->get('year', date('Y'));

            $result = $this->excelExportService->exportBatch($diseaseType, $year);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Batch export berhasil' : 'Batch export gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in exportBatch', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat batch export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download file yang sudah di-export
     * 
     * @param Request $request
     * @return Response|JsonResponse
     */
    public function downloadFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
            'filename' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filePath = $request->get('file_path');
            $filename = $request->get('filename');

            return $this->excelExportService->downloadFile($filePath, $filename);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in downloadFile', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat download file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get informasi tentang jenis export yang tersedia
     * 
     * @return JsonResponse
     */
    public function getExportInfo()
    {
        try {
            $exportTypes = $this->excelExportService->getAvailableExportTypes();
            $diseaseTypes = $this->excelExportService->getAvailableDiseaseTypes();

            return response()->json([
                'success' => true,
                'data' => [
                    'export_types' => $exportTypes,
                    'disease_types' => $diseaseTypes,
                    'available_years' => range(2020, date('Y') + 1)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in getExportInfo', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil informasi export',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup file export lama
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function cleanupOldFiles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days_old' => 'sometimes|integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $daysOld = $request->get('days_old', 30);
            $result = $this->excelExportService->cleanupOldFiles($daysOld);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Cleanup berhasil' : 'Cleanup gagal',
                'data' => $result
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in cleanupOldFiles', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat cleanup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status export (untuk monitoring)
     * 
     * @return JsonResponse
     */
    public function getExportStatus()
    {
        try {
            // Implementasi monitoring status export
            // Bisa ditambahkan logic untuk cek status export yang sedang berjalan
            
            return response()->json([
                'success' => true,
                'data' => [
                    'service_status' => 'active',
                    'last_export' => 'Informasi export terakhir bisa ditambahkan di sini',
                    'available_formatters' => [
                        'AdminAllFormatter',
                        'AdminMonthlyFormatter', 
                        'AdminQuarterlyFormatter',
                        'PuskesmasFormatter'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ExcelExportController: Error in getExportStatus', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status export',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}