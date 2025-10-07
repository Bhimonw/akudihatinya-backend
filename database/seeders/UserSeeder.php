<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user (idempotent)
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('dinas123'),
                'name' => 'Administrator',
                'role' => 'admin',
                'puskesmas_id' => null,
            ]
        );
    }
}