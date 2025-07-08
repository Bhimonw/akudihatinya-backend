<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\User;
use App\Models\YearlyTarget;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Models\MonthlyStatisticsCache;
use App\Services\StatisticsCacheService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UpdatedDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data - compatible with SQLite
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
            $this->truncateTables();
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $this->truncateTables();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // 1. Buat admin
        $this->createAdmin();
        
        // 2. Buat puskesmas berdasarkan daftar resmi (25 puskesmas total)
        $puskesmasNames = [
            'ALUH-ALUH',
            'BERUNTUNG BARU',
            'GAMBUT',
            'KERTAK HANYAR',
            'TATAH MAKMUR',
            'SUNGAI TABUK 1',
            'SUNGAI TABUK 2',
            'SUNGAI TABUK 3',
            'MARTAPURA 1',
            'MARTAPURA 2',
            'MARTAPURA TIMUR',
            'MARTAPURA BARAT',
            'ASTAMBUL',
            'KARANG INTAN 1',
            'KARANG INTAN 2',
            'ARANIO',
            'SUNGAI PINANG',
            'PARAMASAN',
            'PENGARON',
            'SAMBUNG MAKMUR',
            'MATARAMAN',
            'SIMPANG EMPAT 1',
            'SIMPANG EMPAT 2',
            'TELAGA BAUNTUNG',
            'CINTAPURI DARUSSALAM'
        ];
        
        $puskesmasList = [];
        
        foreach ($puskesmasNames as $name) {
            $puskesmas = $this->createPuskesmas($name);
            $puskesmasList[] = $puskesmas;
            
            // 3. Buat target tahunan untuk setiap puskesmas
            $this->createYearlyTargets($puskesmas);
            
            // 4. Buat pasien dan pemeriksaan untuk setiap puskesmas
            $this->createPatientsForPuskesmas($puskesmas);
        }

        // 5. Rebuild cache statistik setelah semua data selesai
        $this->command->info('Building statistics cache...');
        $cacheService = app(StatisticsCacheService::class);
        $cacheService->rebuildAllCache();
        $this->command->info('Statistics cache built successfully.');
    }
    
    private function truncateTables(): void
    {
        // Order matters because of foreign key constraints
        DB::table('monthly_statistics_cache')->truncate();
        DB::table('dm_examinations')->truncate();
        DB::table('ht_examinations')->truncate();
        DB::table('yearly_targets')->truncate();
        DB::table('patients')->truncate();
        DB::table('puskesmas')->truncate();
        DB::table('users')->truncate();
    }
    
    private function createAdmin(): void
    {
        User::create([
            'username' => 'admin',
            'password' => Hash::make('password'),
            'name' => 'Admin Dinas Kesehatan',
            'role' => 'admin',
        ]);
    }
    
    private function createPuskesmas(string $name): Puskesmas
    {
        // Create user
        $user = User::create([
            'username' => strtolower(str_replace([' ', '-'], '', $name)),
            'password' => Hash::make('password'),
            'name' => $name,
            'role' => 'puskesmas',
        ]);
        
        // Create puskesmas
        $puskesmas = Puskesmas::create([
            'user_id' => $user->id,
            'name' => $name,
        ]);
        
        // Link user to puskesmas
        $user->update(['puskesmas_id' => $puskesmas->id]);
        
        return $puskesmas;
    }
    
    private function createYearlyTargets(Puskesmas $puskesmas): void
    {
        // Target untuk HT (Hipertensi)
        YearlyTarget::create([
            'puskesmas_id' => $puskesmas->id,
            'year' => 2025,
            'disease_type' => 'ht',
            'target' => rand(800, 1200), // Target bervariasi per puskesmas
        ]);
        
        // Target untuk DM (Diabetes Mellitus)
        YearlyTarget::create([
            'puskesmas_id' => $puskesmas->id,
            'year' => 2025,
            'disease_type' => 'dm',
            'target' => rand(600, 1000), // Target bervariasi per puskesmas
        ]);
    }
    
    private function createPatientsForPuskesmas(Puskesmas $puskesmas): void
    {
        $faker = \Faker\Factory::create('id_ID');
        
        // Generate patients for each month in 2025
        for ($month = 1; $month <= 12; $month++) {
            $patientsCount = rand(50, 150); // Variasi jumlah pasien per bulan
            
            for ($i = 0; $i < $patientsCount; $i++) {
                $patient = Patient::create([
                    'puskesmas_id' => $puskesmas->id,
                    'name' => $faker->name,
                    'nik' => $faker->unique()->numerify('################'),
                    'birth_date' => $faker->dateTimeBetween('-80 years', '-18 years'),
                    'gender' => $faker->randomElement(['male', 'female']),
                    'address' => $faker->address,
                    'phone' => $faker->phoneNumber,
                    'bpjs_number' => $faker->optional(0.8)->numerify('#############'),
                    'created_at' => Carbon::create(2025, $month, rand(1, 28)),
                    'updated_at' => Carbon::create(2025, $month, rand(1, 28)),
                ]);
                
                // Create HT examination (80% chance)
                if (rand(1, 100) <= 80) {
                    $this->createHtExamination($patient, $month);
                }
                
                // Create DM examination (60% chance)
                if (rand(1, 100) <= 60) {
                    $this->createDmExamination($patient, $month);
                }
            }
        }
    }
    
    private function createHtExamination(Patient $patient, int $month): void
    {
        $faker = \Faker\Factory::create();
        
        $systolic = rand(110, 180);
        $diastolic = rand(70, 110);
        $isStandard = ($systolic < 140 && $diastolic < 90);
        
        HtExamination::create([
            'patient_id' => $patient->id,
            'examination_date' => Carbon::create(2025, $month, rand(1, 28)),
            'systolic_pressure' => $systolic,
            'diastolic_pressure' => $diastolic,
            'is_standard' => $isStandard,
            'notes' => $faker->optional(0.3)->sentence,
        ]);
    }
    
    private function createDmExamination(Patient $patient, int $month): void
    {
        $faker = \Faker\Factory::create();
        
        $bloodSugar = rand(80, 300);
        $isStandard = ($bloodSugar < 200);
        
        DmExamination::create([
            'patient_id' => $patient->id,
            'examination_date' => Carbon::create(2025, $month, rand(1, 28)),
            'blood_sugar_level' => $bloodSugar,
            'is_standard' => $isStandard,
            'notes' => $faker->optional(0.3)->sentence,
        ]);
    }
}