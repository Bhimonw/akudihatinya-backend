<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\YearlyTargetRequest;
use App\Http\Resources\YearlyTargetResource;
use App\Models\YearlyTarget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class YearlyTargetController extends Controller
{
    public function index(Request $request)
    {
        $query = YearlyTarget::with('puskesmas');

        // Filters
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('disease_type')) {
            $query->where('disease_type', $request->disease_type);
        }
        if ($request->filled('puskesmas_id')) {
            $query->where('puskesmas_id', $request->puskesmas_id);
        }
        // Search by puskesmas name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('puskesmas', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Single resource fetch when unique key provided
        if ($request->filled(['puskesmas_id', 'disease_type', 'year'])) {
            $target = $query->first();
            if (!$target) {
                return response()->json([
                    'error' => 'yearly_target_not_found',
                    'message' => 'ID sasaran tahunan tidak ditemukan'
                ], 404);
            }
            return response()->json([
                'target' => new YearlyTargetResource($target),
            ]);
        }

        $perPage = min((int)($request->get('per_page', 15)), 100);
        $page = (int)($request->get('page', 1));

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderBy('puskesmas_id')
            ->paginate($perPage, ['*'], 'page', $page);

        // Build standardized response similar to UserResource collection
        return response()->json([
            'data' => YearlyTargetResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]
        ]);
    }
    
    public function store(YearlyTargetRequest $request)
    {
        $target = YearlyTarget::updateOrCreate(
            [
                'puskesmas_id' => $request->puskesmas_id,
                'disease_type' => $request->disease_type,
                'year' => $request->year,
            ],
            [
                'target_count' => $request->target_count,
            ]
        );
        
        return response()->json([
            'message' => 'Sasaran tahunan berhasil disimpan',
            'target' => new YearlyTargetResource($target),
        ]);
    }
    

    public function update(YearlyTargetRequest $request)
    {
        // Update berdasarkan query dengan puskesmas_id
        $target = YearlyTarget::where('puskesmas_id', $request->puskesmas_id)
            ->where('disease_type', $request->disease_type)
            ->where('year', $request->year)
            ->first();

        if (!$target) {
            return response()->json([
                'error' => 'yearly_target_not_found',
                'message' => 'ID sasaran tahunan tidak ditemukan'
            ], 404);
        }

        $target->update([
            'target_count' => $request->target_count,
        ]);

        return response()->json([
            'message' => 'Target tahunan berhasil diperbarui',
            'target' => new YearlyTargetResource($target),
        ]);
    }
    
    public function destroy(Request $request)
    {
        // Validasi parameter yang diperlukan
        $request->validate([
            'puskesmas_id' => 'required|integer',
            'disease_type' => 'required|string|in:ht,dm',
            'year' => 'required|integer'
        ]);
        
        $target = YearlyTarget::where('puskesmas_id', $request->puskesmas_id)
            ->where('disease_type', $request->disease_type)
            ->where('year', $request->year)
            ->first();
        
        if (!$target) {
            return response()->json([
                'error' => 'yearly_target_not_found',
                'message' => 'ID sasaran tahunan tidak ditemukan'
            ], 404);
        }
        
        $target->delete();

        return response()->json([
            'message' => 'Target tahunan berhasil dihapus',
        ]);
    }
}
