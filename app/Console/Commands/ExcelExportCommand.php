<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Facades\Excel;
use App\Formatters\Validators\ExcelDataValidator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExcelExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:export 
                            {type : The type of report (yearly, monthly, quarterly, puskesmas)}
                            {year : The year for the report}
                            {--month= : The month for monthly reports (1-12)}
                            {--quarter= : The quarter for quarterly reports (1-4)}
                            {--puskesmas= : The puskesmas name for puskesmas reports}
                            {--output= : Custom output path}
                            {--sample : Use sample data for testing}
                            {--validate-only : Only validate data without generating export}
                            {--format=xlsx : Output format (xlsx, xls)}
                            {--template= : Custom template file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Excel export for nutrition data reports';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->info('Starting Excel export process...');
            
            // Get and validate parameters
            $params = $this->getValidatedParameters();
            
            // Get data
            $data = $this->getData($params);
            
            // Validate data if requested
            if ($this->option('validate-only')) {
                return $this->validateData($data, $params);
            }
            
            // Generate export
            $filePath = $this->generateExport($data, $params);
            
            $this->info("Excel export completed successfully!");
            $this->info("File saved to: {$filePath}");
            
            // Show file info
            $this->showFileInfo($filePath);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");
            Log::error('Excel export command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $this->arguments() + $this->options()
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Get and validate command parameters.
     *
     * @return array
     */
    protected function getValidatedParameters()
    {
        $type = $this->argument('type');
        $year = (int) $this->argument('year');
        $month = $this->option('month') ? (int) $this->option('month') : null;
        $quarter = $this->option('quarter') ? (int) $this->option('quarter') : null;
        $puskesmas = $this->option('puskesmas');
        
        // Validate report type
        $validTypes = Excel::getReportTypes();
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid report type. Must be one of: " . implode(', ', $validTypes));
        }
        
        // Validate year
        $currentYear = date('Y');
        if ($year < 2000 || $year > $currentYear + 5) {
            throw new \InvalidArgumentException("Invalid year. Must be between 2000 and " . ($currentYear + 5));
        }
        
        // Validate month for monthly reports
        if ($type === 'monthly') {
            if ($month === null || $month < 1 || $month > 12) {
                throw new \InvalidArgumentException("Month is required for monthly reports and must be between 1-12");
            }
        }
        
        // Validate quarter for quarterly reports
        if ($type === 'quarterly') {
            if ($quarter === null || $quarter < 1 || $quarter > 4) {
                throw new \InvalidArgumentException("Quarter is required for quarterly reports and must be between 1-4");
            }
        }
        
        // Validate puskesmas name for puskesmas reports
        if ($type === 'puskesmas') {
            if (empty($puskesmas)) {
                throw new \InvalidArgumentException("Puskesmas name is required for puskesmas reports");
            }
        }
        
        return [
            'type' => $type,
            'year' => $year,
            'month' => $month,
            'quarter' => $quarter,
            'puskesmas' => $puskesmas,
            'output' => $this->option('output'),
            'sample' => $this->option('sample'),
            'format' => $this->option('format'),
            'template' => $this->option('template'),
        ];
    }

    /**
     * Get data for export.
     *
     * @param array $params
     * @return array
     */
    protected function getData(array $params)
    {
        if ($params['sample']) {
            $this->info('Using sample data for testing...');
            return $this->generateSampleData($params);
        }
        
        // In a real application, this would fetch data from database
        // For now, we'll use sample data
        $this->warn('No data source configured. Using sample data.');
        return $this->generateSampleData($params);
    }

    /**
     * Generate sample data for testing.
     *
     * @param array $params
     * @return array
     */
    protected function generateSampleData(array $params)
    {
        $sampleSize = config('excel.development.mock_data.sample_size', 10);
        $data = [];
        
        for ($i = 1; $i <= $sampleSize; $i++) {
            $puskesmas = [
                'nama_puskesmas' => "Puskesmas Sample {$i}",
                'sasaran' => rand(100, 1000),
                'monthly_data' => []
            ];
            
            // Generate monthly data
            for ($month = 1; $month <= 12; $month++) {
                $male = rand(10, 50);
                $female = rand(10, 50);
                $total = $male + $female;
                $standard = rand(0, $total);
                $nonStandard = $total - $standard;
                
                $puskesmas['monthly_data'][$month] = [
                    'male' => $male,
                    'female' => $female,
                    'total' => $total,
                    'standard' => $standard,
                    'non_standard' => $nonStandard,
                    'percentage' => $total > 0 ? round(($standard / $total) * 100, 2) : 0
                ];
            }
            
            $data[] = $puskesmas;
        }
        
        return $data;
    }

    /**
     * Validate data only.
     *
     * @param array $data
     * @param array $params
     * @return int
     */
    protected function validateData(array $data, array $params)
    {
        $this->info('Validating data...');
        
        $validator = app(ExcelDataValidator::class);
        
        // Validate export parameters
        $isValid = Excel::validate($data, $params['type'], $params['year'], $params['month'], $params['quarter']);
        
        if ($isValid) {
            $this->info('✓ Data validation passed');
            $this->info("✓ Found " . count($data) . " puskesmas records");
            
            // Show data summary
            $this->showDataSummary($data, $params);
            
            return Command::SUCCESS;
        } else {
            $this->error('✗ Data validation failed');
            return Command::FAILURE;
        }
    }

    /**
     * Generate Excel export.
     *
     * @param array $data
     * @param array $params
     * @return string
     */
    protected function generateExport(array $data, array $params)
    {
        $this->info('Generating Excel export...');
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Generate export
        $filePath = Excel::save(
            $params['type'],
            $data,
            $params['year'],
            $params['month'],
            $params['quarter'],
            $params['puskesmas'],
            $params['output']
        );
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        // Show performance metrics
        $duration = round($endTime - $startTime, 2);
        $memoryUsed = $this->formatBytes($endMemory - $startMemory);
        $peakMemory = $this->formatBytes(memory_get_peak_usage(true));
        
        $this->info("Export completed in {$duration} seconds");
        $this->info("Memory used: {$memoryUsed}, Peak: {$peakMemory}");
        
        return $filePath;
    }

    /**
     * Show file information.
     *
     * @param string $filePath
     * @return void
     */
    protected function showFileInfo(string $filePath)
    {
        if (file_exists($filePath)) {
            $fileSize = $this->formatBytes(filesize($filePath));
            $this->info("File size: {$fileSize}");
            
            // Show file permissions
            $permissions = substr(sprintf('%o', fileperms($filePath)), -4);
            $this->info("Permissions: {$permissions}");
        }
    }

    /**
     * Show data summary.
     *
     * @param array $data
     * @param array $params
     * @return void
     */
    protected function showDataSummary(array $data, array $params)
    {
        $this->info('\nData Summary:');
        $this->info('─────────────');
        
        $totalPuskesmas = count($data);
        $this->info("Total Puskesmas: {$totalPuskesmas}");
        
        if (!empty($data)) {
            // Calculate totals
            $totalSasaran = array_sum(array_column($data, 'sasaran'));
            $this->info("Total Sasaran: {$totalSasaran}");
            
            // Show sample puskesmas names
            $sampleNames = array_slice(array_column($data, 'nama_puskesmas'), 0, 3);
            $this->info("Sample Puskesmas: " . implode(', ', $sampleNames));
            if ($totalPuskesmas > 3) {
                $this->info("... and " . ($totalPuskesmas - 3) . " more");
            }
        }
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}