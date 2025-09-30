<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use Illuminate\Database\Seeder;
// Tambahan model dan helper
use App\Models\Patient;
use App\Models\YearlyTarget;
use Carbon\Carbon;

class PuskesmasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hanya seed untuk 5 puskesmas yang sudah ada (berdasarkan nama)
        $puskesmasNames = [
            'ALUH-ALUH',
            'BERUNTUNG BARU',
            'GAMBUT',
            'KERTAK HANYAR',
            'TATAH MAKMUR',
        ];

        $currentYear = Carbon::now()->year;
        $years = [$currentYear, $currentYear - 1];
        $diseaseTypes = ['ht', 'dm'];

        $totalPatientsCreated = 0;
        $totalPuskesmasProcessed = 0;
        $this->command?->info('Memulai seeding YearlyTarget dan Patient untuk 5 puskesmas...');

        foreach ($puskesmasNames as $name) {
            $this->command?->info("Memproses puskesmas: {$name}");
            $puskesmas = Puskesmas::where('name', $name)->first();
            if (!$puskesmas) {
                $this->command?->warn("- Dilewati: Puskesmas dengan nama '{$name}' tidak ditemukan di database.");
                continue;
            }

            $totalPuskesmasProcessed++;

            // Seed YearlyTarget untuk 2 tahun (tahun ini dan tahun lalu) untuk HT & DM
            foreach ($years as $year) {
                foreach ($diseaseTypes as $type) {
                    YearlyTarget::updateOrCreate(
                        [
                            'puskesmas_id' => $puskesmas->id,
                            'year' => $year,
                            'disease_type' => $type,
                        ],
                        [
                            // Nilai target contoh; silakan sesuaikan jika diperlukan
                            'target_count' => $type === 'ht' ? 120 : 100,
                        ]
                    );
                }
            }
            $this->command?->info("- YearlyTarget dipastikan untuk tahun {$years[1]} dan {$years[0]} (HT & DM)");

            // Seed 10 pasien contoh per puskesmas
            $createdForThisPuskesmas = 0;
            for ($i = 1; $i <= 10; $i++) {
                // Bangun NIK stabil 16 digit agar idempoten: PPPP II XXXXXXXXXX
                // PPPP: id puskesmas 4 digit (zero pad), II: index 2 digit, tail 10 digit konstan
                $nik = str_pad((string)$puskesmas->id, 4, '0', STR_PAD_LEFT)
                    . str_pad((string)$i, 2, '0', STR_PAD_LEFT)
                    . '1234567890';

                $gender = $i % 2 === 0 ? 'male' : 'female';
                $birthDate = Carbon::now()->subYears(rand(30, 65))->subDays(rand(0, 365));
                $hasHt = $i % 2 === 0; // sebagian pasien punya HT
                $hasDm = $i % 3 === 0; // sebagian pasien punya DM

                // Tentukan tahun-tahun kunjungan untuk HT/DM (array)
                $htYears = $hasHt ? [ $currentYear - ($i % 2) ] : [];
                $dmYears = $hasDm ? [ $currentYear - ($i % 3) ] : [];

                $patient = Patient::firstOrCreate(
                    [
                        'nik' => $nik,
                    ],
                    [
                        'puskesmas_id' => $puskesmas->id,
                        'bpjs_number' => null,
                        'name' => "Sample Patient {$name} {$i}",
                        'address' => 'Alamat ' . $name,
                        'phone_number' => null,
                        'gender' => $gender,
                        'birth_date' => $birthDate->toDateString(),
                        'age' => $birthDate->age,
                        // has_ht/has_dm dihitung dari accessor berdasarkan ht_years/dm_years
                        'ht_years' => $htYears,
                        'dm_years' => $dmYears,
                        // medical_record_number nullable (opsional)
                    ]
                );

                if ($patient->wasRecentlyCreated) {
                    $createdForThisPuskesmas++;
                    $totalPatientsCreated++;
                }
            }

            $this->command?->info("- Pasien baru dibuat: {$createdForThisPuskesmas} (total kumulatif: {$totalPatientsCreated})");
        }

        $this->command?->info("Selesai. Puskesmas diproses: {$totalPuskesmasProcessed}, pasien baru dibuat: {$totalPatientsCreated}.");
    }
}