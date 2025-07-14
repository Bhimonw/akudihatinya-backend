<?php

namespace App\Console\Commands;

use App\Services\System\NewYearSetupService;
use Illuminate\Console\Command;

class SetupNewYear extends Command
{
    protected $signature = 'year:setup {year?} {--confirm}';
    
    protected $description = 'Setup new year by clearing examination data while preserving patient data';
    
    public function handle(NewYearSetupService $setupService)
    {
        $year = $this->argument('year') ?? date('Y');
        
        $this->info("Setting up new year: {$year}");
        $this->info('This will:');
        $this->info('- Clear all HT and DM examination data');
        $this->info('- Preserve all patient data');
        $this->info('- Create yearly targets for the new year');
        $this->info('- Clear monthly statistics cache');
        
        if (!$this->option('confirm')) {
            if (!$this->confirm('Are you sure you want to proceed? This action cannot be undone.')) {
                $this->info('Operation cancelled.');
                return 1;
            }
        }
        
        $this->info('Starting new year setup...');
        
        $result = $setupService->setupNewYear($year);
        
        $this->info('Cleared ' . $result['cleared_ht'] . ' HT examinations.');
        $this->info('Cleared ' . $result['cleared_dm'] . ' DM examinations.');
        $this->info('Preserved ' . $result['preserved_patients'] . ' patients.');
        $this->info('Created ' . $result['created_targets'] . ' yearly targets.');
        $this->info('Cleared monthly statistics cache.');
        
        $this->info('New year setup completed successfully!');
        
        return 0;
    }
}