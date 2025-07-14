<?php

namespace App\Observers;

use App\Models\DmExamination;
use App\Services\Statistics\RealTimeStatisticsService;
use Carbon\Carbon;

class DmExaminationObserver
{
    private RealTimeStatisticsService $statisticsService;

    public function __construct(RealTimeStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function creating(DmExamination $examination)
    {
        // Set year and month from examination_date
        $date = Carbon::parse($examination->examination_date);
        $examination->year = $date->year;
        $examination->month = $date->month;
    }

    public function created(DmExamination $examination)
    {
        // Process examination data and update statistics in real-time
        $this->statisticsService->processExaminationData($examination, 'dm');
    }
}