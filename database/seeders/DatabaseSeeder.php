<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class, // create admin
            PuskesmasListSeeder::class, // create configured puskesmas and users
            // PuskesmasSeeder::class, // add targets/patients for subset (legacy sample) - file not exists
            // ExaminationSeeder::class, // file not exists
        ]);
    }
}
