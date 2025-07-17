<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Formatters\ExcelExportFormatter;
use App\Services\Statistics\StatisticsService;

class CreateExcelTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:create-templates {--force : Overwrite existing templates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Excel templates for statistics export';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating Excel templates...');
        
        // Pastikan direktori resources/excel ada
        $excelDir = resource_path('excel');
        if (!is_dir($excelDir)) {
            mkdir($excelDir, 0755, true);
            $this->info('Created directory: ' . $excelDir);
        }
        
        $templates = [
            'quarterly' => 'quarterly.xlsx',
            'monthly' => 'monthly.xlsx', 
            'all' => 'all.xlsx',
            'puskesmas' => 'puskesmas.xlsx'
        ];
        
        foreach ($templates as $type => $filename) {
            $this->createSimpleTemplate($type, $filename);
        }
        
        $this->info('All Excel templates created successfully!');
    }
    
    /**
     * Create template with proper structure and headers
     */
    private function createSimpleTemplate(string $type, string $filename)
    {
        $this->info("Creating {$filename}...");
        
        $filePath = resource_path('excel/' . $filename);
        
        // Skip jika file sudah ada dan tidak menggunakan --force
        if (file_exists($filePath) && !$this->option('force')) {
            $this->warn("Template {$filename} already exists. Use --force to overwrite.");
            return;
        }
        
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan');
            
            // Set main title
            $sheet->setCellValue('A1', 'LAPORAN PELAYANAN KESEHATAN PADA PENDERITA HIPERTENSI');
            $sheet->setCellValue('A2', 'TAHUN 2025 - ' . $this->getPeriodLabel($type));
            
            // Create table headers based on type
            $this->createTableHeaders($sheet, $type);
            
            // Apply styling
            $this->applyBasicStyling($sheet, $type);
            
            // Add sample data rows
            $this->addSampleData($sheet, $type);
            
            // Simpan file
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            
            $this->info("✓ Created: {$filename}");
            
        } catch (\Exception $e) {
            $this->error("Failed to create {$filename}: " . $e->getMessage());
        }
    }
    
    /**
     * Create table headers based on template type
     */
    private function createTableHeaders($sheet, string $type)
    {
        // Basic headers
        $sheet->setCellValue('A4', 'NO');
        $sheet->setCellValue('B4', 'NAMA PUSKESMAS');
        $sheet->setCellValue('C4', 'SASARAN');
        
        if ($type === 'monthly') {
            // Monthly headers - 12 months
            $months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 
                      'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
            
            $col = 'D';
            foreach ($months as $month) {
                $sheet->setCellValue($col . '3', $month);
                $sheet->setCellValue($col . '4', 'L');
                $sheet->setCellValue(++$col . '4', 'P');
                $sheet->setCellValue(++$col . '4', 'TOTAL');
                $sheet->setCellValue(++$col . '4', 'TS');
                $sheet->setCellValue(++$col . '4', '%S');
                $col++;
            }
        } elseif ($type === 'quarterly') {
            // Quarterly headers - 4 quarters
            $quarters = ['TRIWULAN I', 'TRIWULAN II', 'TRIWULAN III', 'TRIWULAN IV'];
            
            $col = 'D';
            foreach ($quarters as $quarter) {
                $sheet->setCellValue($col . '3', $quarter);
                $sheet->setCellValue($col . '4', 'L');
                $sheet->setCellValue(++$col . '4', 'P');
                $sheet->setCellValue(++$col . '4', 'TOTAL');
                $sheet->setCellValue(++$col . '4', 'TS');
                $sheet->setCellValue(++$col . '4', '%S');
                $col++;
            }
        } elseif ($type === 'all') {
            // Comprehensive report - months + quarters + yearly
            $periods = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
                       'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
                       'TRIWULAN I', 'TRIWULAN II', 'TRIWULAN III', 'TRIWULAN IV', 'TAHUNAN'];
            
            $col = 'D';
            foreach ($periods as $period) {
                $sheet->setCellValue($col . '3', $period);
                $sheet->setCellValue($col . '4', 'L');
                $sheet->setCellValue(++$col . '4', 'P');
                $sheet->setCellValue(++$col . '4', 'TOTAL');
                $sheet->setCellValue(++$col . '4', 'TS');
                $sheet->setCellValue(++$col . '4', '%S');
                $col++;
            }
        } else {
            // Puskesmas template - basic structure
            $sheet->setCellValue('D3', 'DATA PEMERIKSAAN');
            $sheet->setCellValue('D4', 'L');
            $sheet->setCellValue('E4', 'P');
            $sheet->setCellValue('F4', 'TOTAL');
            $sheet->setCellValue('G4', 'TS');
            $sheet->setCellValue('H4', '%S');
        }
    }
    
    /**
     * Apply basic styling to the sheet
     */
    private function applyBasicStyling($sheet, string $type)
    {
        // Merge title cells
        $lastCol = $type === 'all' ? 'BZ' : ($type === 'monthly' ? 'BL' : ($type === 'quarterly' ? 'X' : 'H'));
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        
        // Center align titles
        $sheet->getStyle('A1:' . $lastCol . '2')->getAlignment()->setHorizontal('center');
        
        // Bold headers
        $sheet->getStyle('A1:' . $lastCol . '4')->getFont()->setBold(true);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(12);
        
        // Auto-size other columns
        for ($col = 'D'; $col <= $lastCol; $col++) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
    }
    
    /**
     * Add sample data rows
     */
    private function addSampleData($sheet, string $type)
    {
        // Add sample puskesmas data
        $sampleData = [
            ['1', 'Puskesmas 1', '220'],
            ['2', 'Puskesmas 2', '191'],
            ['3', 'Puskesmas 3', '128'],
            ['4', 'Puskesmas 4', '137'],
            ['5', 'Puskesmas 5', '97']
        ];
        
        $row = 5;
        foreach ($sampleData as $data) {
            $sheet->setCellValue('A' . $row, $data[0]);
            $sheet->setCellValue('B' . $row, $data[1]);
            $sheet->setCellValue('C' . $row, $data[2]);
            $row++;
        }
        
        // Add total row
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, 'KESELURUHAN');
        $sheet->setCellValue('C' . $row, '=SUM(C5:C' . ($row-1) . ')');
        
        // Merge total cells
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
    }
    
    /**
     * Get period label for template type
     */
    private function getPeriodLabel(string $type): string
    {
        switch ($type) {
            case 'all':
                return 'LAPORAN KOMPREHENSIF (BULANAN + TRIWULAN + TAHUNAN)';
            case 'monthly':
                return 'LAPORAN BULANAN';
            case 'quarterly':
                return 'LAPORAN TRIWULAN';
            case 'puskesmas':
                return 'TEMPLATE PUSKESMAS';
            default:
                return 'LAPORAN KESEHATAN';
        }
    }
}
