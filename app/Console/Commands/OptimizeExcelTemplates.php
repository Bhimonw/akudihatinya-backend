<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Formatters\ExcelExportFormatter;
use App\Services\Statistics\StatisticsService;

class OptimizeExcelTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:optimize {--backup : Create backup of original files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize Excel templates by removing unnecessary columns and improving efficiency';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Excel template optimization...');
        
        $templatesPath = resource_path('excel');
        $backupPath = $templatesPath . '/backup';
        
        // Create backup directory if requested
        if ($this->option('backup')) {
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }
            $this->info('Creating backups...');
        }
        
        $templates = [
            'quarterly.xlsx' => 'quarterly',
            'monthly.xlsx' => 'monthly', 
            'all.xlsx' => 'all',
            'puskesmas.xlsx' => 'puskesmas'
        ];
        
        foreach ($templates as $filename => $type) {
            $this->optimizeTemplate($templatesPath, $filename, $type, $backupPath);
        }
        
        $this->info('Excel template optimization completed!');
    }
    
    /**
     * Optimize individual template
     */
    private function optimizeTemplate(string $templatesPath, string $filename, string $type, string $backupPath)
    {
        $filePath = $templatesPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            $this->warn("Template {$filename} not found, skipping...");
            return;
        }
        
        $this->info("Optimizing {$filename}...");
        
        // Create backup if requested
        if ($this->option('backup')) {
            copy($filePath, $backupPath . '/' . $filename);
        }
        
        try {
            // Load existing template
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Get formatter instance
            $statisticsService = app(StatisticsService::class);
            $formatter = new ExcelExportFormatter($statisticsService);
            
            // Create optimized template based on type
            $optimizedSpreadsheet = new Spreadsheet();
            
            switch ($type) {
                case 'quarterly':
                    $formatter->formatQuarterlyExcel($optimizedSpreadsheet, '<tipe_penyakit>', date('Y'), []);
                    break;
                case 'monthly':
                    $formatter->formatMonthlyExcel($optimizedSpreadsheet, '<tipe_penyakit>', date('Y'), []);
                    break;
                case 'all':
                    $formatter->formatAllExcel($optimizedSpreadsheet, '<tipe_penyakit>', date('Y'), []);
                    break;
                case 'puskesmas':
                    $formatter->formatPuskesmasExcel($optimizedSpreadsheet, '<disease_type>', date('Y'), null);
                    break;
            }
            
            // Save optimized template
            $writer = new Xlsx($optimizedSpreadsheet);
            $writer->save($filePath);
            
            $this->info("✓ {$filename} optimized successfully");
            
        } catch (\Exception $e) {
            $this->error("Failed to optimize {$filename}: " . $e->getMessage());
        }
    }
}
