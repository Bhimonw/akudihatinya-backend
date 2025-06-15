<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class PatientPhoneNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all patients without phone numbers
        $patients = Patient::whereNull('phone_number')->get();
        
        $this->command->info('Updating ' . $patients->count() . ' patients with fake phone numbers...');
        
        foreach ($patients as $patient) {
            // Generate fake Indonesian phone number
            $phoneNumber = $this->generateFakePhoneNumber();
            
            $patient->update([
                'phone_number' => $phoneNumber
            ]);
        }
        
        $this->command->info('Phone numbers updated successfully!');
    }
    
    /**
     * Generate fake Indonesian phone number
     */
    private function generateFakePhoneNumber(): string
    {
        // Indonesian mobile prefixes
        $prefixes = ['0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853', '0857', '0858'];
        
        // Random prefix
        $prefix = $prefixes[array_rand($prefixes)];
        
        // Generate 7-8 random digits
        $digits = str_pad(rand(1000000, 99999999), 8, '0', STR_PAD_LEFT);
        
        return $prefix . $digits;
    }
}
