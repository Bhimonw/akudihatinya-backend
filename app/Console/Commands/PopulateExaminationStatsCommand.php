<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Services\Statistics\RealTimeStatisticsService;
use Illuminate\Support\Facades\DB;

class PopulateExaminationStatsCommand extends Command
{
    protected $signature = 'examinations:populate-stats {--year=} {--batch-size=1000}';
    protected $description = 'Populate pre-calculated statistics for existing examination data';

    protected $realTimeStatisticsService;

    public function __construct(RealTimeStatisticsService $realTimeStatisticsService)
    {
        parent::__construct();
        $this->realTimeStatisticsService = $realTimeStatisticsService;
    }

    public function handle()
    {
        $year = $this->option('year') ?? date('Y');
        $batchSize = $this->option('batch-size');

        $this->info("Populating examination statistics for year {$year}...");

        // Process HT Examinations
        $this->info('Processing HT Examinations...');
        $this->processHtExaminations($year, $batchSize);

        // Process DM Examinations
        $this->info('Processing DM Examinations...');
        $this->processDmExaminations($year, $batchSize);

        // Recalculate monthly cache
        $this->info('Recalculating monthly cache...');
        $this->recalculateMonthlyCache($year);

        $this->info('Examination statistics population completed!');
    }

    private function processHtExaminations($year, $batchSize)
    {
        $query = HtExamination::with(['patient', 'puskesmas'])
            ->where('year', $year)
            ->whereNull('is_controlled'); // Only process records that haven't been calculated

        $total = $query->count();
        $processed = 0;

        $this->output->progressStart($total);

        $query->chunk($batchSize, function ($examinations) use (&$processed) {
            foreach ($examinations as $examination) {
                // Calculate statistics
                $examination->calculateStatistics();

                // Update the record
                $examination->save();

                $processed++;
                $this->output->progressAdvance();
            }
        });

        $this->output->progressFinish();
        $this->info("Processed {$processed} HT examinations.");
    }

    private function processDmExaminations($year, $batchSize)
    {
        $query = DmExamination::with(['patient', 'puskesmas'])
            ->where('year', $year)
            ->whereNull('is_controlled'); // Only process records that haven't been calculated

        $total = $query->count();
        $processed = 0;

        $this->output->progressStart($total);

        $query->chunk($batchSize, function ($examinations) use (&$processed) {
            foreach ($examinations as $examination) {
                // Calculate statistics
                $examination->calculateStatistics();

                // Update the record
                $examination->save();

                $processed++;
                $this->output->progressAdvance();
            }
        });

        $this->output->progressFinish();
        $this->info("Processed {$processed} DM examinations.");
    }

    private function recalculateMonthlyCache($year)
    {
        // Get all puskesmas
        $puskesmasIds = DB::table('puskesmas')->pluck('id');

        foreach ($puskesmasIds as $puskesmasId) {
            for ($month = 1; $month <= 12; $month++) {
                // Recalculate HT cache
                $this->realTimeStatisticsService->recalculateMonthlyCache(
                    $puskesmasId,
                    'ht',
                    $year,
                    $month
                );

                // Recalculate DM cache
                $this->realTimeStatisticsService->recalculateMonthlyCache(
                    $puskesmasId,
                    'dm',
                    $year,
                    $month
                );
            }
        }

        $this->info('Monthly cache recalculation completed.');
    }
}
