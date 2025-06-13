<?php

namespace App\Console\Commands;

use App\Services\StatisticsCacheService;
use Illuminate\Console\Command;

class RebuildStatisticsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistics:rebuild-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the monthly statistics cache from existing examination data';

    /**
     * Execute the console command.
     */
    public function handle(StatisticsCacheService $cacheService)
    {
        $startTime = microtime(true);
        
        try {
            $cacheService->rebuildAllCache();
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            echo "Cache rebuilt successfully in {$executionTime} seconds.\n";
            
            return 0;
        } catch (\Exception $e) {
            echo 'Error rebuilding cache: ' . $e->getMessage() . "\n";
            return 1;
        }
    }
}