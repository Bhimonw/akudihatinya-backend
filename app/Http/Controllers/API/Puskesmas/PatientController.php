<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\PatientRequest;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        
        $query = Patient::where('puskesmas_id', $puskesmasId);
        
        // Filter by disease type - modified for cross-platform compatibility
        if ($request->has('disease_type')) {
            if ($request->disease_type === 'ht') {
                // Get patients with non-empty ht_years using collection filtering
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null');
            } elseif ($request->disease_type === 'dm') {
                // Get patients with non-empty dm_years using collection filtering
                $query->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            } elseif ($request->disease_type === 'both') {
                // Get patients with both non-empty arrays
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null')
                      ->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            }
        }
        
        // Search by name, NIK, or BPJS
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%");
            });
        }
        
        // Handle year filtering in PHP instead of database query
        if ($request->has('year')) {
            $year = $request->year;
            $diseaseType = $request->disease_type ?? null;
            
            // Get base results without pagination
            $results = $query->get();
            
            // Filter results in PHP for cross-platform compatibility
            $filteredResults = $results->filter(function ($patient) use ($year, $diseaseType) {
                // Safely get the year arrays
                $htYears = $this->safeGetYears($patient->ht_years);
                $dmYears = $this->safeGetYears($patient->dm_years);
                
                if ($diseaseType === 'ht') {
                    return in_array($year, $htYears);
                } elseif ($diseaseType === 'dm') {
                    return in_array($year, $dmYears);
                } elseif ($diseaseType === 'both') {
                    return in_array($year, $htYears) && in_array($year, $dmYears);
                } else {
                    return in_array($year, $htYears) || in_array($year, $dmYears);
                }
            });
            
            // Create a custom paginator
            $perPage = $request->per_page ?? 15;
            $page = $request->page ?? 1;
            $items = $filteredResults->forPage($page, $perPage);
            
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $filteredResults->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            
            return new PatientCollection($paginator);
        }
        
        // Standard pagination if no year filtering
        $patients = $query->paginate($request->per_page ?? 15);
        
        return new PatientCollection($patients);
    }
    
    /**
     * Safely get years array from various possible formats
     */
    private function safeGetYears($years)
    {
        // If it's null, return empty array
        if (is_null($years)) {
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($years)) {
            return $years;
        }
        
        // If it's a string, try to decode it
        if (is_string($years)) {
            $decoded = json_decode($years, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Default fallback
        return [];
    }
    
    public function store(PatientRequest $request)
    {
        $data = $request->validated();
        $data['puskesmas_id'] = $request->user()->puskesmas->id;
        
        // Initialize empty arrays for years if not provided
        $data['ht_years'] = $data['ht_years'] ?? [];
        $data['dm_years'] = $data['dm_years'] ?? [];
        
        $patient = Patient::create($data);
        
        return response()->json([
            'message' => 'Pasien berhasil ditambahkan',
            'patient' => new PatientResource($patient),
        ], 201);
    }
    
    public function show(Request $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        return response()->json([
            'patient' => new PatientResource($patient),
        ]);
    }
    
    public function update(PatientRequest $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $data = $request->validated();
        
        $patient->update($data);
        
        return response()->json([
            'message' => 'Pasien berhasil diupdate',
            'patient' => new PatientResource($patient),
        ]);
    }
    
    public function addExaminationYear(Request $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $request->validate([
            'year' => 'required|integer',
            'examination_type' => 'required|in:ht,dm',
        ]);
        
        $year = $request->year;
        $type = $request->examination_type;
        
        if ($type === 'ht') {
            $patient->addHtYear($year);
        } else {
            $patient->addDmYear($year);
        }
        
        $patient->save();
        
        return response()->json([
            'message' => 'Tahun pemeriksaan berhasil ditambahkan',
            'patient' => new PatientResource($patient),
        ]);
    }
    
    public function removeExaminationYear(Request $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $request->validate([
            'year' => 'required|integer',
            'examination_type' => 'required|in:ht,dm',
        ]);
        
        $year = $request->year;
        $type = $request->examination_type;
        
        if ($type === 'ht') {
            $patient->removeHtYear($year);
        } else {
            $patient->removeDmYear($year);
        }
        
        $patient->save();
        
        return response()->json([
            'message' => 'Tahun pemeriksaan berhasil dihapus',
            'patient' => new PatientResource($patient),
        ]);
    }
    
    public function destroy(Request $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $patient->delete();
        
        return response()->json([
            'message' => 'Pasien berhasil dihapus',
        ]);
    }
}