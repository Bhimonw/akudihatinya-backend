<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Services\StatisticsService;
use App\Formatters\PuskesmasFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Auth;

class PuskesmasExportService
{
    protected $statisticsService;
    protected $puskesmasFormatter;

    public function __construct(
        StatisticsService $statisticsService,
        PuskesmasFormatter $puskesmasFormatter
    ) {
        $this->statisticsService = $statisticsService;
        $this->puskesmasFormatter = $puskesmasFormatter;
    }

    /**
     * Export puskesmas statistics to Excel
     */
    public function exportPuskesmasStatistics($diseaseType = 'dm', $year, $puskesmasId = null)
    {
        // Ensure disease type is valid
        $diseaseType = in_array($diseaseType, ['dm', 'ht']) ? $diseaseType : 'dm';

        // If no puskesmas ID provided and user is not admin, use their puskesmas
        if (!$puskesmasId && !Auth::user()->isAdmin()) {
            $puskesmasId = Auth::user()->puskesmas_id;
        }

        // Load the puskesmas template
        $templatePath = resource_path('excel/puskesmas.xlsx');
        $spreadsheet = IOFactory::load($templatePath);

        // Format the spreadsheet with data
        $spreadsheet = $this->puskesmasFormatter->format($spreadsheet, $diseaseType, $year, $puskesmasId);

        // Generate filename
        $filename = $this->generateFilename($diseaseType, $year, $puskesmasId);

        // Create reports directory if it doesn't exist
        $reportsPath = storage_path('app/public/reports');
        if (!is_dir($reportsPath)) {
            mkdir($reportsPath, 0755, true);
        }

        // Save file
        $finalPath = $reportsPath . DIRECTORY_SEPARATOR . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($finalPath);

        return response()->download($finalPath)->deleteFileAfterSend(true);
    }

    /**
     * Generate filename for the export
     */
    protected function generateFilename($diseaseType, $year, $puskesmasId = null)
    {
        $filename = sprintf('laporan_puskesmas_%s_%d', $diseaseType, $year);

        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            if ($puskesmas) {
                $puskesmasName = str_replace(' ', '_', strtolower($puskesmas->name));
                $filename .= '_' . $puskesmasName;
            }
        }

        $filename .= '.xlsx';

        return $filename;
    }

    /**
     * Export HT statistics for puskesmas
     */
    public function exportHtStatistics($year, $puskesmasId = null)
    {
        return $this->exportPuskesmasStatistics('ht', $year, $puskesmasId);
    }

    /**
     * Export DM statistics for puskesmas
     */
    public function exportDmStatistics($year, $puskesmasId = null)
    {
        return $this->exportPuskesmasStatistics('dm', $year, $puskesmasId);
    }

    /**
     * Get available years for export
     */
    public function getAvailableYears($puskesmasId = null)
    {
        $query = YearlyTarget::query();

        if ($puskesmasId) {
            $query->where('puskesmas_id', $puskesmasId);
        }

        return $query->distinct('year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
    }

    /**
     * Get puskesmas list for admin
     */
    public function getPuskesmasList()
    {
        return Puskesmas::orderBy('name')->get(['id', 'name']);
    }
}
