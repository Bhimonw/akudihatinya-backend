<?php

namespace App\Console\Commands;

use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CreateYearlyTargets extends Command
{
    protected $signature = 'targets:create-yearly {year?} {--force}';
    
    protected $description = 'Create yearly targets for all puskesmas for specified year';
    
    public function handle()
    {
        $year = $this->argument('year') ?? Carbon::now()->year;
        
        $this->info("Creating yearly targets for year: {$year}");
        
        // Check if targets already exist
        $existingTargets = YearlyTarget::where('year', $year)->count();
        
        if ($existingTargets > 0 && !$this->option('force')) {
            $this->warn("Targets for year {$year} already exist ({$existingTargets} targets found).");
            if (!$this->confirm('Do you want to update existing targets?')) {
                $this->info('Operation cancelled.');
                return 1;
            }
        }
        
        $puskesmas = Puskesmas::all();
        $createdCount = 0;
        $updatedCount = 0;
        
        $this->output->progressStart($puskesmas->count() * 2); // 2 targets per puskesmas
        
        foreach ($puskesmas as $puskesmasItem) {
            // Create/Update HT target
            $htTarget = YearlyTarget::updateOrCreate(
                [
                    'puskesmas_id' => $puskesmasItem->id,
                    'disease_type' => 'ht',
                    'year' => $year
                ],
                [
                    'target_count' => $this->getDefaultTarget($puskesmasItem->name, 'ht')
                ]
            );
            
            if ($htTarget->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }
            
            $this->output->progressAdvance();
            
            // Create/Update DM target
            $dmTarget = YearlyTarget::updateOrCreate(
                [
                    'puskesmas_id' => $puskesmasItem->id,
                    'disease_type' => 'dm',
                    'year' => $year
                ],
                [
                    'target_count' => $this->getDefaultTarget($puskesmasItem->name, 'dm')
                ]
            );
            
            if ($dmTarget->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }
            
            $this->output->progressAdvance();
        }
        
        $this->output->progressFinish();
        
        $this->info("Created {$createdCount} new targets.");
        $this->info("Updated {$updatedCount} existing targets.");
        $this->info('Yearly targets creation completed successfully!');
        
        return 0;
    }
    
    /**
     * Get default target based on puskesmas name and disease type
     */
    private function getDefaultTarget($puskesmasName, $diseaseType)
    {
        // Default target values based on existing seeder logic
        $targetValues = [
            'Puskesmas 4' => 137,
            'Puskesmas 6' => 97,
            'default' => rand(100, 300)
        ];
        
        return $targetValues[$puskesmasName] ?? $targetValues['default'];
    }
}