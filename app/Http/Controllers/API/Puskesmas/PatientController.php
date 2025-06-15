<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\PatientRequest;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
        
        // Search by name, NIK, BPJS, or phone number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
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

    public function export(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        
        $query = Patient::where('puskesmas_id', $puskesmasId);
        
        // Filter by disease type if specified
        if ($request->has('disease_type')) {
            if ($request->disease_type === 'ht') {
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null');
            } elseif ($request->disease_type === 'dm') {
                $query->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            } elseif ($request->disease_type === 'both') {
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null')
                      ->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            }
        }
        
        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }
        
        // Year filter
        if ($request->has('year')) {
            $year = $request->year;
            $diseaseType = $request->disease_type ?? null;
            
            $results = $query->get();
            
            $filteredResults = $results->filter(function ($patient) use ($year, $diseaseType) {
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
            
            $patients = $filteredResults;
        } else {
            $patients = $query->get();
        }
        
        // Format data for export
        $exportData = $patients->map(function ($patient) {
            $htYears = $this->safeGetYears($patient->ht_years);
            $dmYears = $this->safeGetYears($patient->dm_years);
            
            return [
                'id' => $patient->id,
                'nik' => $patient->nik,
                'bpjs_number' => $patient->bpjs_number,
                'medical_record_number' => $patient->medical_record_number,
                'name' => $patient->name,
                'address' => $patient->address,
                'phone_number' => $patient->phone_number,
                'gender' => $patient->gender,
                'birth_date' => $patient->birth_date ? $patient->birth_date->format('Y-m-d') : null,
                'age' => $patient->age,
                'has_ht' => !empty($htYears),
                'has_dm' => !empty($dmYears),
                'ht_years' => implode(', ', $htYears),
                'dm_years' => implode(', ', $dmYears),
                'created_at' => $patient->created_at ? $patient->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $patient->updated_at ? $patient->updated_at->format('Y-m-d H:i:s') : null,
            ];
        });
        
        return response()->json([
             'message' => 'Data pasien berhasil diekspor',
             'data' => $exportData,
             'total' => $exportData->count(),
             'puskesmas' => $request->user()->puskesmas->name,
         ]);
     }

    public function exportToExcel(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        $puskesmasName = $request->user()->puskesmas->name;
        
        $query = Patient::where('puskesmas_id', $puskesmasId);
        
        // Filter by disease type if specified
        if ($request->has('disease_type')) {
            if ($request->disease_type === 'ht') {
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null');
            } elseif ($request->disease_type === 'dm') {
                $query->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            } elseif ($request->disease_type === 'both') {
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null')
                      ->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            }
        }
        
        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }
        
        // Year filter
        if ($request->has('year')) {
            $year = $request->year;
            $diseaseType = $request->disease_type ?? null;
            
            $results = $query->get();
            
            $filteredResults = $results->filter(function ($patient) use ($year, $diseaseType) {
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
            
            $patients = $filteredResults;
        } else {
            $patients = $query->get();
        }
        
        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setTitle('Daftar Pasien');
        
        // Set main title based on disease type
        $title = 'Daftar Pasien';
        if ($request->has('disease_type')) {
            if ($request->disease_type === 'ht') {
                $title = 'Daftar Pasien Penderita Hipertensi';
            } elseif ($request->disease_type === 'dm') {
                $title = 'Daftar Pasien Penderita Diabetes Melitus';
            } elseif ($request->disease_type === 'both') {
                $title = 'Daftar Pasien Penderita Hipertensi dan Diabetes Melitus';
            }
        }
        
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set puskesmas name
        $sheet->setCellValue('A2', $puskesmasName);
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set headers
        $headers = [
            'A3' => 'Nama Lengkap',
            'B3' => 'NIK',
            'C3' => 'Tanggal Lahir',
            'D3' => 'Jenis Kelamin',
            'E3' => 'Alamat',
            'F3' => 'Nomor Telepon',
            'G3' => 'Nomor BPJS',
            'H3' => 'WhatsApp'
        ];
        
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }
        
        // Style headers
        $sheet->getStyle('A3:H3')->getFont()->setBold(true);
        $sheet->getStyle('A3:H3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');
        $sheet->getStyle('A3:H3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Add data
        $row = 4;
        foreach ($patients as $patient) {
            $genderText = $patient->gender === 'male' ? 'Pria' : ($patient->gender === 'female' ? 'Wanita' : '');
            $birthDate = $patient->birth_date ? $patient->birth_date->format('d/m/Y') : '';
            $whatsappLink = $patient->phone_number ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $patient->phone_number) : '';
            
            $sheet->setCellValue('A' . $row, $patient->name);
            $sheet->setCellValue('B' . $row, $patient->nik);
            $sheet->setCellValue('C' . $row, $birthDate);
            $sheet->setCellValue('D' . $row, $genderText);
            $sheet->setCellValue('E' . $row, $patient->address);
            $sheet->setCellValue('F' . $row, $patient->phone_number);
            $sheet->setCellValue('G' . $row, $patient->bpjs_number);
            $sheet->setCellValue('H' . $row, $whatsappLink);
            
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Add borders
        $lastRow = $row - 1;
        $sheet->getStyle('A3:H' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Create filename
        $filename = 'daftar_pasien_' . str_replace(' ', '_', strtolower($puskesmasName)) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Create reports directory if it doesn't exist
        $reportsPath = storage_path('app/public/reports');
        if (!is_dir($reportsPath)) {
            mkdir($reportsPath, 0755, true);
        }
        
        // Save file
        $finalPath = $reportsPath . DIRECTORY_SEPARATOR . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($finalPath);
        
        return response()->download($finalPath)->deleteFileAfterSend(true);
    }
}