<?php

namespace Database\Seeders;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExaminationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->info('Memulai seeding pemeriksaan untuk pasien yang ada...');
        
        // Ambil tahun saat ini dan tahun lalu untuk data pemeriksaan
        $currentYear = Carbon::now()->year;
        $lastYear = $currentYear - 1;
        $years = [$currentYear, $lastYear];
        
        // Ambil semua puskesmas
        $puskesmasList = Puskesmas::all();
        
        $totalHtExaminations = 0;
        $totalDmExaminations = 0;
        
        foreach ($puskesmasList as $puskesmas) {
            $this->command?->info("Memproses pemeriksaan untuk puskesmas: {$puskesmas->name}");
            
            // Ambil semua pasien dari puskesmas ini
            $patients = Patient::where('puskesmas_id', $puskesmas->id)->get();
            
            if ($patients->isEmpty()) {
                $this->command?->warn("- Dilewati: Tidak ada pasien di puskesmas {$puskesmas->name}");
                continue;
            }
            
            $htExamsForPuskesmas = 0;
            $dmExamsForPuskesmas = 0;
            
            foreach ($patients as $patient) {
                // Buat pemeriksaan HT untuk pasien dengan riwayat HT
                if ($patient->has_ht) {
                    $htExamsCreated = $this->createHtExaminations($patient, $puskesmas, $years);
                    $htExamsForPuskesmas += $htExamsCreated;
                    $totalHtExaminations += $htExamsCreated;
                }
                
                // Buat pemeriksaan DM untuk pasien dengan riwayat DM
                if ($patient->has_dm) {
                    $dmExamsCreated = $this->createDmExaminations($patient, $puskesmas, $years);
                    $dmExamsForPuskesmas += $dmExamsCreated;
                    $totalDmExaminations += $dmExamsCreated;
                }
            }
            
            $this->command?->info("- Pemeriksaan HT dibuat: {$htExamsForPuskesmas}");
            $this->command?->info("- Pemeriksaan DM dibuat: {$dmExamsForPuskesmas}");
        }
        
        $this->command?->info("Selesai. Total pemeriksaan HT dibuat: {$totalHtExaminations}, total pemeriksaan DM dibuat: {$totalDmExaminations}");
    }
    
    /**
     * Buat pemeriksaan HT untuk pasien
     */
    private function createHtExaminations(Patient $patient, Puskesmas $puskesmas, array $years): int
    {
        $examsCreated = 0;
        
        // Untuk setiap tahun dalam riwayat HT pasien
        foreach ($years as $year) {
            // Pastikan tahun ini ada dalam riwayat HT pasien
            $htYears = $this->safeGetYears($patient->ht_years);
            if (!in_array($year, $htYears)) {
                continue;
            }
            
            // Buat 1-4 pemeriksaan per tahun (acak)
            $examsPerYear = rand(1, 4);
            
            for ($i = 0; $i < $examsPerYear; $i++) {
                // Tentukan bulan secara acak (1-12)
                $month = rand(1, 12);
                
                // Tentukan tanggal dalam bulan tersebut
                $day = rand(1, 28); // Hindari masalah dengan bulan Februari
                $examinationDate = Carbon::createFromDate($year, $month, $day);
                
                // Jika tanggal pemeriksaan di masa depan, lewati
                if ($examinationDate->isFuture()) {
                    continue;
                }
                
                // Buat nilai tekanan darah acak
                // Sebagian besar pasien terkontrol (70%), sebagian tidak (30%)
                $isControlled = rand(1, 10) <= 7;
                
                if ($isControlled) {
                    // Tekanan darah terkontrol
                    $systolic = rand(90, 139);
                    $diastolic = rand(60, 89);
                } else {
                    // Tekanan darah tidak terkontrol (tinggi)
                    $systolic = rand(140, 200);
                    $diastolic = rand(90, 120);
                }
                
                // Cek apakah sudah ada pemeriksaan pada tanggal yang sama
                $existingExam = HtExamination::where('patient_id', $patient->id)
                    ->where('puskesmas_id', $puskesmas->id)
                    ->whereDate('examination_date', $examinationDate)
                    ->first();
                
                if (!$existingExam) {
                    // Buat pemeriksaan baru
                    HtExamination::create([
                        'patient_id' => $patient->id,
                        'puskesmas_id' => $puskesmas->id,
                        'examination_date' => $examinationDate,
                        'systolic' => $systolic,
                        'diastolic' => $diastolic,
                        'year' => $year,
                        'month' => $month,
                        'is_archived' => $year < Carbon::now()->year,
                        'is_controlled' => $isControlled,
                        'is_first_visit_this_month' => true, // Akan diupdate oleh observer
                        'is_standard_patient' => rand(0, 1), // Acak untuk demo
                        'patient_gender' => $patient->gender,
                    ]);
                    
                    $examsCreated++;
                }
            }
        }
        
        return $examsCreated;
    }
    
    /**
     * Buat pemeriksaan DM untuk pasien
     */
    private function createDmExaminations(Patient $patient, Puskesmas $puskesmas, array $years): int
    {
        $examsCreated = 0;
        
        // Untuk setiap tahun dalam riwayat DM pasien
        foreach ($years as $year) {
            // Pastikan tahun ini ada dalam riwayat DM pasien
            $dmYears = $this->safeGetYears($patient->dm_years);
            if (!in_array($year, $dmYears)) {
                continue;
            }
            
            // Buat 1-4 pemeriksaan per tahun (acak)
            $examsPerYear = rand(1, 4);
            
            for ($i = 0; $i < $examsPerYear; $i++) {
                // Tentukan bulan secara acak (1-12)
                $month = rand(1, 12);
                
                // Tentukan tanggal dalam bulan tersebut
                $day = rand(1, 28); // Hindari masalah dengan bulan Februari
                $examinationDate = Carbon::createFromDate($year, $month, $day);
                
                // Jika tanggal pemeriksaan di masa depan, lewati
                if ($examinationDate->isFuture()) {
                    continue;
                }
                
                // Pilih tipe pemeriksaan secara acak
                $examinationType = $this->getRandomExaminationType();
                
                // Buat nilai hasil pemeriksaan acak
                // Sebagian besar pasien terkontrol (70%), sebagian tidak (30%)
                $isControlled = rand(1, 10) <= 7;
                $result = $this->generateDmResult($examinationType, $isControlled);
                
                // Cek apakah sudah ada pemeriksaan pada tanggal yang sama dengan tipe yang sama
                $existingExam = DmExamination::where('patient_id', $patient->id)
                    ->where('puskesmas_id', $puskesmas->id)
                    ->whereDate('examination_date', $examinationDate)
                    ->where('examination_type', $examinationType)
                    ->first();
                
                if (!$existingExam) {
                    // Buat pemeriksaan baru
                    DmExamination::create([
                        'patient_id' => $patient->id,
                        'puskesmas_id' => $puskesmas->id,
                        'examination_date' => $examinationDate,
                        'examination_type' => $examinationType,
                        'result' => $result,
                        'year' => $year,
                        'month' => $month,
                        'is_archived' => $year < Carbon::now()->year,
                        'is_controlled' => $isControlled,
                        'is_first_visit_this_month' => true, // Akan diupdate oleh observer
                        'is_standard_patient' => rand(0, 1), // Acak untuk demo
                        'patient_gender' => $patient->gender,
                    ]);
                    
                    $examsCreated++;
                }
            }
        }
        
        return $examsCreated;
    }
    
    /**
     * Dapatkan tipe pemeriksaan DM secara acak
     */
    private function getRandomExaminationType(): string
    {
        $types = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        return $types[array_rand($types)];
    }
    
    /**
     * Generate hasil pemeriksaan DM berdasarkan tipe dan status kontrol
     */
    private function generateDmResult(string $type, bool $isControlled): float
    {
        switch ($type) {
            case 'hba1c':
                return $isControlled ? rand(50, 69) / 10 : rand(70, 120) / 10; // 5.0-6.9 vs 7.0-12.0
            case 'gdp':
                return $isControlled ? rand(80, 125) : rand(126, 250); // <126 vs >126
            case 'gd2jpp':
                return $isControlled ? rand(80, 199) : rand(200, 350); // <200 vs >200
            case 'gdsp':
                return $isControlled ? rand(80, 199) : rand(200, 350); // <200 vs >200
            default:
                return 0;
        }
    }
    
    /**
     * Ambil array tahun dengan aman (handle jika null atau format tidak valid)
     */
    private function safeGetYears($years): array
    {
        if (empty($years)) {
            return [];
        }
        
        if (is_string($years)) {
            try {
                $years = json_decode($years, true);
            } catch (\Exception $e) {
                return [];
            }
        }
        
        return is_array($years) ? $years : [];
    }
}