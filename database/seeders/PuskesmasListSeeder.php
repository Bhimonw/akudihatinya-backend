<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PuskesmasListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = [
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

        $created = 0;
        $updatedUserLinks = 0;

        foreach ($names as $name) {
            // Create a user per puskesmas if not exists
            $username = $this->slugUsername($name);

            $user = User::firstOrCreate(
                ['username' => $username],
                [
                    'password' => Hash::make('puskesmas123'),
                    'name' => $name,
                    'role' => 'puskesmas',
                    'puskesmas_id' => null,
                ]
            );

            // Create Puskesmas linked to user
            $pusk = Puskesmas::firstOrCreate(
                ['name' => $name],
                ['user_id' => $user->id]
            );

            // Ensure linkage both ways
            if ($pusk->user_id !== $user->id) {
                $pusk->user_id = $user->id;
                $pusk->save();
            }

            if ($user->puskesmas_id !== $pusk->id) {
                $user->puskesmas_id = $pusk->id;
                $user->save();
                $updatedUserLinks++;
            }

            if ($pusk->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->info("Puskesmas created: {$created}, user links updated: {$updatedUserLinks}");
        $this->command?->info('Default password for puskesmas users: puskesmas123');
    }

    private function slugUsername(string $name): string
    {
        // Normalize to lowercase, remove non-alnum, replace spaces with underscores
        $base = strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9\s-]/', '', $base);
        $base = preg_replace('/\s+/', '_', $base);
        $username = 'pkm_' . $base;

        // Ensure uniqueness if somehow taken by appending a numeric suffix
        $candidate = $username;
        $i = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $username . '_' . $i;
            $i++;
        }
        return $candidate;
    }
}
