<?php

namespace App\Http\Controllers\API\Shared;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PuskesmasExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PuskesmasExportController extends Controller
{
    protected $puskesmasExportService;

    public function __construct(PuskesmasExportService $puskesmasExportService)
    {
        $this->puskesmasExportService = $puskesmasExportService;
    }

    /**
     * Export puskesmas statistics
     */
    public function exportStatistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'disease_type' => 'required|in:ht,dm',
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'puskesmas_id' => 'nullable|integer|exists:puskesmas,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $diseaseType = $request->input('disease_type');
            $year = $request->input('year');
            $puskesmasId = $request->input('puskesmas_id');

            // If user is not admin, restrict to their puskesmas
            if (!Auth::user()->isAdmin()) {
                $puskesmasId = Auth::user()->puskesmas_id;
            }

            return $this->puskesmasExportService->exportPuskesmasStatistics(
                $diseaseType,
                $year,
                $puskesmasId
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export HT statistics for puskesmas
     */
    public function exportHtStatistics(Request $request)
    {
        $request->merge(['disease_type' => 'ht']);
        return $this->exportStatistics($request);
    }

    /**
     * Export DM statistics for puskesmas
     */
    public function exportDmStatistics(Request $request)
    {
        $request->merge(['disease_type' => 'dm']);
        return $this->exportStatistics($request);
    }

    /**
     * Get available years for export
     */
    public function getAvailableYears(Request $request)
    {
        try {
            $puskesmasId = null;

            // If user is not admin, restrict to their puskesmas
            if (!Auth::user()->isAdmin()) {
                $puskesmasId = Auth::user()->puskesmas_id;
            } else {
                $puskesmasId = $request->input('puskesmas_id');
            }

            $years = $this->puskesmasExportService->getAvailableYears($puskesmasId);

            return response()->json([
                'success' => true,
                'data' => $years
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get available years: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get puskesmas list (admin only)
     */
    public function getPuskesmasList()
    {
        try {
            // Only admin can access this
            if (!Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $puskesmasList = $this->puskesmasExportService->getPuskesmasList();

            return response()->json([
                'success' => true,
                'data' => $puskesmasList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get puskesmas list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get export options for current user
     */
    public function getExportOptions()
    {
        try {
            $user = Auth::user();
            $options = [
                'disease_types' => [
                    ['value' => 'ht', 'label' => 'Hipertensi'],
                    ['value' => 'dm', 'label' => 'Diabetes Melitus']
                ],
                'years' => $this->puskesmasExportService->getAvailableYears(
                    $user->isAdmin() ? null : $user->puskesmas_id
                )
            ];

            if ($user->isAdmin()) {
                $options['puskesmas_list'] = $this->puskesmasExportService->getPuskesmasList();
            }

            return response()->json([
                'success' => true,
                'data' => $options
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get export options: ' . $e->getMessage()
            ], 500);
        }
    }
}
