<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PuskesmasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $puskesmasList = [
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
            'CINTAPURI DARUSSALAM',
        ];

        foreach ($puskesmasList as $puskesmasName) {
            // Generate username from puskesmas name
            $username = Str::slug($puskesmasName, '');
            $username = strtolower($username);
            
            // Generate password: puskesmas name + 123
            $passwordBase = Str::slug($puskesmasName, '');
            $passwordBase = strtolower($passwordBase);
            $password = $passwordBase . '123';

            // Create user first
            $user = User::create([
                'username' => $username,
                'password' => Hash::make($password),
                'name' => 'User ' . $puskesmasName,
                'role' => 'puskesmas',
                'puskesmas_id' => null, // Will be updated after puskesmas is created
            ]);

            // Create puskesmas
            $puskesmas = Puskesmas::create([
                'name' => $puskesmasName,
                'user_id' => $user->id,
            ]);

            // Update user's puskesmas_id
            $user->update([
                'puskesmas_id' => $puskesmas->id,
            ]);
        }
    }
}