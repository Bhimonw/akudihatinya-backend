<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\DmExaminationRequest;
use App\Http\Resources\DmExaminationResource;
use App\Models\DmExamination;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DmExaminationController extends Controller
{
    public function index(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;

        // Buat query dasar
        $baseQuery = DmExamination::where('puskesmas_id', $puskesmasId)
            ->with('patient');

        // Terapkan filter
        if ($request->has('year')) {
            $baseQuery->where('year', $request->year);
        }

        if ($request->has('month')) {
            $baseQuery->where('month', $request->month);
        }

        if ($request->has('is_archived')) {
            $baseQuery->where('is_archived', $request->is_archived);
        }

        if ($request->has('patient_id')) {
            $baseQuery->where('patient_id', $request->patient_id);
        }

        // Dapatkan tanggal dan pasien unik sebagai dasar paginasi
        $uniqueExamDates = DB::table('dm_examinations')
            ->select('patient_id', 'examination_date')
            ->where('puskesmas_id', $puskesmasId)
            ->when($request->has('year'), function ($q) use ($request) {
                return $q->where('year', $request->year);
            })
            ->when($request->has('month'), function ($q) use ($request) {
                return $q->where('month', $request->month);
            })
            ->when($request->has('is_archived'), function ($q) use ($request) {
                return $q->where('is_archived', $request->is_archived);
            })
            ->when($request->has('patient_id'), function ($q) use ($request) {
                return $q->where('patient_id', $request->patient_id);
            })
            ->distinct()
            ->orderBy('examination_date', 'desc')
            ->paginate($request->per_page ?? 15);

        // Siapkan data hasil
        $result = [];
        $patientIds = [];

        // Kumpulkan semua ID pasien yang perlu diambil
        foreach ($uniqueExamDates as $item) {
            $patientIds[] = $item->patient_id;
        }

        // Ambil semua data pasien sekaligus
        $patients = Patient::whereIn('id', $patientIds)->get()->keyBy('id');

        // Ambil semua pemeriksaan sekaligus
        $allExaminations = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $patientIds)
            ->get();

        // Kelompokkan pemeriksaan berdasarkan pasien dan tanggal
        $groupedExams = [];
        foreach ($allExaminations as $exam) {
            $key = $exam->patient_id . '_' . $exam->examination_date->format('Y-m-d');
            if (!isset($groupedExams[$key])) {
                $groupedExams[$key] = [
                    'id' => $exam->id,
                    'patient_id' => $exam->patient_id,
                    'patient_name' => $patients[$exam->patient_id]->name,
                    'puskesmas_id' => $exam->puskesmas_id,
                    'examination_date' => $exam->examination_date->format('Y-m-d'),
                    'examination_results' => [
                        'hba1c' => null,
                        'gdp' => null,
                        'gd2jpp' => null,
                        'gdsp' => null
                    ],
                    'year' => $exam->year,
                    'month' => $exam->month,
                    'is_archived' => $exam->is_archived
                ];
            }
            $groupedExams[$key]['examination_results'][$exam->examination_type] = $exam->result;
        }

        // Buat array hasil sesuai urutan pagination
        foreach ($uniqueExamDates as $item) {
            $key = $item->patient_id . '_' . Carbon::parse($item->examination_date)->format('Y-m-d');
            if (isset($groupedExams[$key])) {
                $result[] = $groupedExams[$key];
            }
        }

        // Kembalikan hasil dengan format JSON yang rapi
        return response()->json([
            'data' => $result,
            'links' => [
                'first' => $uniqueExamDates->url(1),
                'last' => $uniqueExamDates->url($uniqueExamDates->lastPage()),
                'prev' => $uniqueExamDates->previousPageUrl(),
                'next' => $uniqueExamDates->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $uniqueExamDates->currentPage(),
                'from' => $uniqueExamDates->firstItem(),
                'last_page' => $uniqueExamDates->lastPage(),
                'path' => $request->url(),
                'per_page' => $uniqueExamDates->perPage(),
                'to' => $uniqueExamDates->lastItem(),
                'total' => $uniqueExamDates->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        // Validasi request
        $request->validate([
            'patient_id' => [
                'required',
                'exists:patients,id',
                function ($attribute, $value, $fail) use ($request) {
                    $patient = \App\Models\Patient::find($value);

                    if (!$patient) {
                        $fail('Pasien tidak ditemukan.');
                        return;
                    }

                    if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'examinations' => 'required|array',
            'examinations.hba1c' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gd2jpp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdsp' => 'nullable|numeric|min:0|max:1000',
        ]);

        $patientId = $request->patient_id;
        $examinationDate = $request->examination_date;
        $puskesmasId = $request->user()->puskesmas->id;
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;

        // Pastikan pasien memiliki DM
        $patient = Patient::findOrFail($patientId);
        if (!$patient->has_dm) {
            $patient->update(['has_dm' => true]);
        }

        DB::beginTransaction();
        try {
            $createdExaminations = [];

            // Buat pemeriksaan baru untuk setiap tipe yang memiliki nilai (INSERT murni)
            foreach ($request->examinations as $type => $result) {
                // Hanya buat record jika nilai tidak null
                if ($result !== null) {
                    $examination = DmExamination::create([
                        'patient_id' => $patientId,
                        'puskesmas_id' => $puskesmasId,
                        'examination_date' => $examinationDate,
                        'examination_type' => $type,
                        'result' => $result,
                        'year' => $year,
                        'month' => $month,
                        'is_archived' => $isArchived,
                    ]);

                    $createdExaminations[] = $examination;
                }
            }

            DB::commit();

            // Format hasil untuk respons - gunakan data yang baru dibuat
            $examinationResults = [
                'hba1c' => null,
                'gdp' => null,
                'gd2jpp' => null,
                'gdsp' => null
            ];

            foreach ($createdExaminations as $exam) {
                $examinationResults[$exam->examination_type] = $exam->result;
            }

            // Base ID for response - use first created exam
            $baseId = count($createdExaminations) > 0 ? $createdExaminations[0]->id : null;

            // Buat respons dengan data pemeriksaan yang baru dibuat
            $responseData = [
                'id' => $baseId,
                'patient_id' => $patientId,
                'patient_name' => $patient->name,
                'puskesmas_id' => $puskesmasId,
                'examination_date' => $examinationDate,
                'examination_results' => $examinationResults,
                'year' => $year,
                'month' => $month,
                'is_archived' => $isArchived
            ];

            return response()->json([
                'message' => 'Pemeriksaan Diabetes Mellitus berhasil ditambahkan',
                'examination' => $responseData,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan pemeriksaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $patientId)
    {
        $patient = Patient::find($patientId);

        if (!$patient) {
            return response()->json([
                'message' => 'Pasien tidak ditemukan.'
            ], 404);
        }

        $filterYear = $request->query('year');
        $filterMonth = $request->query('month');
        $types = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        $result = [];

        // Pilih tahun-tahun: semua dari dm_years atau hanya 1 jika difilter
        $years = $filterYear ? [$filterYear] : ($patient->dm_years ?? []);

        foreach ($years as $year) {
            // Pilih bulan: 1-12 atau hanya 1 jika difilter
            $months = $filterMonth ? [$filterMonth] : range(1, 12);

            foreach ($months as $month) {
                $monthlyData = [];

                foreach ($types as $type) {
                    $exam = DmExamination::where('patient_id', $patientId)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->where('examination_type', $type)
                        ->first();

                    $monthlyData[$type] = $exam ? $exam->result : null;
                }

                $result[$year][$month] = $monthlyData;
            }
        }

        return response()->json([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'puskesmas_id' => $patient->puskesmas_id,
            'examinations_by_year' => $result
        ]);
    }

    public function update(Request $request, DmExamination $dmExamination)
    {
        if ($dmExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Tidak diizinkan',
            ], 403);
        }

        // Validasi request untuk single examination update
        $request->validate([
            'examination_type' => 'required|in:hba1c,gdp,gd2jpp,gdsp',
            'result' => 'required|numeric|min:0|max:1000',
            'examination_date' => 'required|date|before_or_equal:today',
        ]);

        $date = Carbon::parse($request->examination_date);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;

        // Update pemeriksaan yang sudah ada (UPDATE yang sebenarnya)
        $dmExamination->update([
            'examination_date' => $request->examination_date,
            'examination_type' => $request->examination_type,
            'result' => $request->result,
            'year' => $year,
            'month' => $month,
            'is_archived' => $isArchived,
        ]);

        return response()->json([
            'message' => 'Pemeriksaan Diabetes Mellitus berhasil diupdate',
            'examination' => new DmExaminationResource($dmExamination),
        ]);
    }

    /**
     * Update multiple examinations for the same date (batch update)
     * This method handles the complex case of updating multiple examination types
     */
    public function updateBatch(Request $request)
    {
        // Validasi request
        $request->validate([
            'patient_id' => [
                'required',
                'exists:patients,id',
                function ($attribute, $value, $fail) use ($request) {
                    $patient = \App\Models\Patient::find($value);

                    if (!$patient) {
                        $fail('Pasien tidak ditemukan.');
                        return;
                    }

                    if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'examinations' => 'required|array',
            'examinations.hba1c' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gd2jpp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdsp' => 'nullable|numeric|min:0|max:1000',
        ]);

        $patientId = $request->patient_id;
        $examinationDate = $request->examination_date;
        $puskesmasId = $request->user()->puskesmas->id;
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;

        DB::beginTransaction();
        try {
            $updatedExaminations = [];
            
            // Update atau create pemeriksaan untuk setiap tipe
            foreach ($request->examinations as $type => $result) {
                if ($result !== null) {
                    // Cari pemeriksaan yang sudah ada
                    $existingExam = DmExamination::where('patient_id', $patientId)
                        ->where('puskesmas_id', $puskesmasId)
                        ->whereDate('examination_date', $examinationDate)
                        ->where('examination_type', $type)
                        ->first();

                    if ($existingExam) {
                        // UPDATE yang sebenarnya jika sudah ada
                        $existingExam->update([
                            'result' => $result,
                            'examination_date' => $examinationDate,
                            'year' => $year,
                            'month' => $month,
                            'is_archived' => $isArchived,
                        ]);
                        $updatedExaminations[] = $existingExam;
                    } else {
                        // CREATE baru jika belum ada
                        $newExam = DmExamination::create([
                            'patient_id' => $patientId,
                            'puskesmas_id' => $puskesmasId,
                            'examination_date' => $examinationDate,
                            'examination_type' => $type,
                            'result' => $result,
                            'year' => $year,
                            'month' => $month,
                            'is_archived' => $isArchived,
                        ]);
                        $updatedExaminations[] = $newExam;
                    }
                }
            }

            DB::commit();

            // Format hasil untuk respons
            $examinationResults = [
                'hba1c' => null,
                'gdp' => null,
                'gd2jpp' => null,
                'gdsp' => null
            ];

            foreach ($updatedExaminations as $exam) {
                $examinationResults[$exam->examination_type] = $exam->result;
            }

            // Base ID for response
            $baseId = count($updatedExaminations) > 0 ? $updatedExaminations[0]->id : null;

            $patient = Patient::findOrFail($patientId);
            $responseData = [
                'id' => $baseId,
                'patient_id' => $patientId,
                'patient_name' => $patient->name,
                'puskesmas_id' => $puskesmasId,
                'examination_date' => $examinationDate,
                'examination_results' => $examinationResults,
                'year' => $year,
                'month' => $month,
                'is_archived' => $isArchived
            ];

            return response()->json([
                'message' => 'Pemeriksaan Diabetes Mellitus berhasil diupdate',
                'examination' => $responseData,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui pemeriksaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, DmExamination $dmExamination)
    {
        if ($dmExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Tidak diizinkan',
            ], 403);
        }

        // Hapus semua pemeriksaan pada tanggal yang sama untuk pasien yang sama
        $deletedCount = DmExamination::where('patient_id', $dmExamination->patient_id)
            ->where('examination_date', $dmExamination->examination_date)
            ->where('puskesmas_id', $dmExamination->puskesmas_id)
            ->delete();

        return response()->json([
            'message' => "Berhasil menghapus {$deletedCount} pemeriksaan Diabetes Mellitus pada tanggal {$dmExamination->examination_date->format('d/m/Y')}",
        ]);
    }
}
