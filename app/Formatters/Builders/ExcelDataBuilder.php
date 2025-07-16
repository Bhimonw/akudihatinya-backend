<?php

namespace App\Formatters\Builders;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Formatters\Calculators\StatisticsCalculator;
use Illuminate\Support\Facades\Log;

/**
 * Builder untuk mengisi data Excel dengan berbagai jenis laporan
 */
class ExcelDataBuilder
{
    protected $sheet;
    protected $statisticsCalculator;
    protected $currentRow = 7;
    protected $reportType;
    
    // Column mappings dari ExcelHeaderBuilder
    protected $monthColumns;
    protected $quarterColumns;
    protected $totalColumns;
    protected $quarterOnlyColumns;
    protected $quarterTotalColumns;

    public function __construct(Worksheet $sheet, StatisticsCalculator $statisticsCalculator)
    {
        $this->sheet = $sheet;
        $this->statisticsCalculator = $statisticsCalculator;
        $this->initializeColumnMappings();
    }

    /**
     * Initialize column mappings
     */
    private function initializeColumnMappings(): void
    {
        $this->monthColumns = [
            1 => ['D', 'E', 'F', 'G', 'H'],
            2 => ['I', 'J', 'K', 'L', 'M'],
            3 => ['N', 'O', 'P', 'Q', 'R'],
            4 => ['S', 'T', 'U', 'V', 'W'],
            5 => ['X', 'Y', 'Z', 'AA', 'AB'],
            6 => ['AC', 'AD', 'AE', 'AF', 'AG'],
            7 => ['AH', 'AI', 'AJ', 'AK', 'AL'],
            8 => ['AM', 'AN', 'AO', 'AP', 'AQ'],
            9 => ['AR', 'AS', 'AT', 'AU', 'AV'],
            10 => ['AW', 'AX', 'AY', 'AZ', 'BA'],
            11 => ['BB', 'BC', 'BD', 'BE', 'BF'],
            12 => ['BG', 'BH', 'BI', 'BJ', 'BK']
        ];
        
        $this->quarterColumns = [
            1 => ['BL', 'BM', 'BN', 'BO', 'BP'],
            2 => ['BQ', 'BR', 'BS', 'BT', 'BU'],
            3 => ['BV', 'BW', 'BX', 'BY', 'BZ'],
            4 => ['CA', 'CB', 'CC', 'CD', 'CE']
        ];
        
        $this->totalColumns = ['CF', 'CG', 'CH', 'CI', 'CJ'];
        
        $this->quarterOnlyColumns = [
            1 => ['D', 'E', 'F', 'G', 'H'],
            2 => ['I', 'J', 'K', 'L', 'M'],
            3 => ['N', 'O', 'P', 'Q', 'R'],
            4 => ['S', 'T', 'U', 'V', 'W']
        ];
        
        $this->quarterTotalColumns = ['X', 'Y', 'Z', 'AA', 'AB'];
    }

    /**
     * Fill data puskesmas berdasarkan jenis laporan
     */
    public function fillPuskesmasData(array $puskesmasData, int $year, string $diseaseType, string $reportType): void
    {
        $this->reportType = $reportType;
        $this->currentRow = 8; // Reset ke baris data pertama
        
        $config = $this->getDataFillConfig();
        
        // Bersihkan baris template jika ada
        $this->cleanupTemplateRows($config['maxRows']);
        
        // Proses data puskesmas
        $this->processPuskesmasData($puskesmasData, $year, $diseaseType, $config);
        
        // Finalisasi data sheet
        $this->finalizeDataSheet($config);
    }

    /**
     * Get konfigurasi untuk pengisian data
     */
    private function getDataFillConfig(): array
    {
        return [
            'startRow' => 8,
            'maxRows' => 1000,
            'batchSize' => 50,
            'logProgress' => true
        ];
    }

    /**
     * Bersihkan baris template yang mungkin ada
     */
    private function cleanupTemplateRows(int $maxRows): void
    {
        $highestRow = $this->sheet->getHighestRow();
        
        if ($highestRow > 7) {
            // Hapus data lama mulai dari baris 8
            for ($row = 8; $row <= min($highestRow, $maxRows); $row++) {
                $this->sheet->removeRow($row, 1);
                $highestRow--;
                $row--; // Adjust karena baris sudah dihapus
            }
        }
    }

    /**
     * Proses data puskesmas dengan batch processing
     */
    private function processPuskesmasData(array $puskesmasData, int $year, string $diseaseType, array $config): void
    {
        $totalPuskesmas = count($puskesmasData);
        $processed = 0;
        
        foreach ($puskesmasData as $index => $puskesmas) {
            $this->fillPuskesmasRowData($puskesmas, $index + 1, $year, $diseaseType);
            $processed++;
            
            // Log progress setiap batch
            if ($config['logProgress'] && $processed % $config['batchSize'] === 0) {
                Log::info('Processing puskesmas data', [
                    'processed' => $processed,
                    'total' => $totalPuskesmas,
                    'percentage' => round(($processed / $totalPuskesmas) * 100, 2)
                ]);
            }
        }
        
        Log::info('Completed processing puskesmas data', [
            'total_processed' => $processed,
            'report_type' => $this->reportType
        ]);
    }

    /**
     * Isi data untuk satu baris puskesmas
     */
    private function fillPuskesmasRowData(array $puskesmas, int $rowNumber, int $year, string $diseaseType): void
    {
        // Isi informasi dasar puskesmas
        $this->fillBasicPuskesmasInfo($puskesmas, $rowNumber);
        
        // Isi data statistik berdasarkan jenis laporan
        $this->fillStatisticalData($puskesmas, $year, $diseaseType);
        
        $this->currentRow++;
    }

    /**
     * Isi informasi dasar puskesmas (nomor, nama, sasaran)
     */
    private function fillBasicPuskesmasInfo(array $puskesmas, int $rowNumber): void
    {
        $this->sheet->setCellValue('A' . $this->currentRow, $rowNumber);
        $this->sheet->setCellValue('B' . $this->currentRow, $puskesmas['name'] ?? 'Puskesmas ' . $rowNumber);
        $this->sheet->setCellValue('C' . $this->currentRow, $puskesmas['target'] ?? 0);
    }

    /**
     * Isi data statistik berdasarkan jenis laporan
     */
    private function fillStatisticalData(array $puskesmas, int $year, string $diseaseType): void
    {
        switch ($this->reportType) {
            case 'all':
                $this->fillAllReportData($puskesmas, $year, $diseaseType);
                break;
            case 'monthly':
                $this->fillMonthlyReportData($puskesmas, $year, $diseaseType);
                break;
            case 'quarterly':
                $this->fillQuarterlyReportData($puskesmas, $year, $diseaseType);
                break;
            case 'puskesmas':
                $this->fillPuskesmasTemplateData($puskesmas, $year, $diseaseType);
                break;
        }
    }

    /**
     * Isi data untuk laporan komprehensif (all)
     */
    private function fillAllReportData(array $puskesmas, int $year, string $diseaseType): void
    {
        // Isi data bulanan
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData = $this->statisticsCalculator->getMonthlyData($puskesmas, $month, $year, $diseaseType);
            $this->fillMonthlyColumns($monthlyData, $month);
        }
        
        // Isi data triwulan
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $this->statisticsCalculator->getQuarterlyData($puskesmas, $quarter, $year, $diseaseType);
            $this->fillQuarterColumns($quarterData, $quarter);
        }
        
        // Isi data total tahunan
        $yearlyData = $this->statisticsCalculator->getYearlyData($puskesmas, $year, $diseaseType);
        $this->fillTotalColumns($yearlyData);
    }

    /**
     * Isi data untuk laporan bulanan
     */
    private function fillMonthlyReportData(array $puskesmas, int $year, string $diseaseType): void
    {
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData = $this->statisticsCalculator->getMonthlyData($puskesmas, $month, $year, $diseaseType);
            $this->fillMonthlyColumns($monthlyData, $month);
        }
    }

    /**
     * Isi data untuk laporan triwulan
     */
    private function fillQuarterlyReportData(array $puskesmas, int $year, string $diseaseType): void
    {
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterData = $this->statisticsCalculator->getQuarterlyData($puskesmas, $quarter, $year, $diseaseType);
            $this->fillQuarterOnlyColumns($quarterData, $quarter);
        }
        
        // Isi total tahunan untuk quarterly
        $yearlyData = $this->statisticsCalculator->getYearlyData($puskesmas, $year, $diseaseType);
        $this->fillQuarterTotalColumns($yearlyData);
    }

    /**
     * Isi data untuk template puskesmas
     */
    private function fillPuskesmasTemplateData(array $puskesmas, int $year, string $diseaseType): void
    {
        // Template puskesmas biasanya kosong atau dengan data contoh
        for ($month = 1; $month <= 12; $month++) {
            $emptyData = ['male' => 0, 'female' => 0, 'total' => 0, 'target_achieved' => 0, 'percentage' => 0];
            $this->fillMonthlyColumns($emptyData, $month);
        }
    }

    /**
     * Isi kolom bulanan
     */
    private function fillMonthlyColumns(array $data, int $month): void
    {
        $columns = $this->monthColumns[$month];
        
        $this->sheet->setCellValue($columns[0] . $this->currentRow, $data['male'] ?? 0);
        $this->sheet->setCellValue($columns[1] . $this->currentRow, $data['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $this->currentRow, $data['total'] ?? 0);
        $this->sheet->setCellValue($columns[3] . $this->currentRow, $data['target_achieved'] ?? 0);
        $this->sheet->setCellValue($columns[4] . $this->currentRow, $data['percentage'] ?? 0);
    }

    /**
     * Isi kolom triwulan
     */
    private function fillQuarterColumns(array $data, int $quarter): void
    {
        $columns = $this->quarterColumns[$quarter];
        
        $this->sheet->setCellValue($columns[0] . $this->currentRow, $data['male'] ?? 0);
        $this->sheet->setCellValue($columns[1] . $this->currentRow, $data['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $this->currentRow, $data['total'] ?? 0);
        $this->sheet->setCellValue($columns[3] . $this->currentRow, $data['target_achieved'] ?? 0);
        $this->sheet->setCellValue($columns[4] . $this->currentRow, $data['percentage'] ?? 0);
    }

    /**
     * Isi kolom total tahunan
     */
    private function fillTotalColumns(array $data): void
    {
        $this->sheet->setCellValue($this->totalColumns[0] . $this->currentRow, $data['male'] ?? 0);
        $this->sheet->setCellValue($this->totalColumns[1] . $this->currentRow, $data['female'] ?? 0);
        $this->sheet->setCellValue($this->totalColumns[2] . $this->currentRow, $data['total'] ?? 0);
        $this->sheet->setCellValue($this->totalColumns[3] . $this->currentRow, $data['target_achieved'] ?? 0);
        $this->sheet->setCellValue($this->totalColumns[4] . $this->currentRow, $data['percentage'] ?? 0);
    }

    /**
     * Isi kolom triwulan untuk laporan quarterly
     */
    private function fillQuarterOnlyColumns(array $data, int $quarter): void
    {
        $columns = $this->quarterOnlyColumns[$quarter];
        
        $this->sheet->setCellValue($columns[0] . $this->currentRow, $data['male'] ?? 0);
        $this->sheet->setCellValue($columns[1] . $this->currentRow, $data['female'] ?? 0);
        $this->sheet->setCellValue($columns[2] . $this->currentRow, $data['total'] ?? 0);
        $this->sheet->setCellValue($columns[3] . $this->currentRow, $data['target_achieved'] ?? 0);
        $this->sheet->setCellValue($columns[4] . $this->currentRow, $data['percentage'] ?? 0);
    }

    /**
     * Isi kolom total untuk laporan quarterly
     */
    private function fillQuarterTotalColumns(array $data): void
    {
        $this->sheet->setCellValue($this->quarterTotalColumns[0] . $this->currentRow, $data['male'] ?? 0);
        $this->sheet->setCellValue($this->quarterTotalColumns[1] . $this->currentRow, $data['female'] ?? 0);
        $this->sheet->setCellValue($this->quarterTotalColumns[2] . $this->currentRow, $data['total'] ?? 0);
        $this->sheet->setCellValue($this->quarterTotalColumns[3] . $this->currentRow, $data['target_achieved'] ?? 0);
        $this->sheet->setCellValue($this->quarterTotalColumns[4] . $this->currentRow, $data['percentage'] ?? 0);
    }

    /**
     * Finalisasi data sheet dengan menambahkan baris total dan footer
     */
    private function finalizeDataSheet(array $config): void
    {
        // Tambahkan baris total jika diperlukan
        $this->addTotalRow();
        
        // Tambahkan footer informasi
        $this->addFooterInfo();
    }

    /**
     * Tambahkan baris total
     */
    private function addTotalRow(): void
    {
        $totalRowBuilder = new ExcelTotalRowBuilder($this->sheet, $this->statisticsCalculator);
        $totalRowBuilder->addTotalRow($this->currentRow, $this->reportType, [
            'monthColumns' => $this->monthColumns,
            'quarterColumns' => $this->quarterColumns,
            'totalColumns' => $this->totalColumns,
            'quarterOnlyColumns' => $this->quarterOnlyColumns,
            'quarterTotalColumns' => $this->quarterTotalColumns
        ]);
        
        $this->currentRow++;
    }

    /**
     * Tambahkan footer informasi
     */
    private function addFooterInfo(): void
    {
        $this->currentRow += 2; // Skip 2 baris
        
        $this->sheet->setCellValue('A' . $this->currentRow, 'Keterangan:');
        $this->currentRow++;
        $this->sheet->setCellValue('A' . $this->currentRow, 'L = Laki-laki');
        $this->currentRow++;
        $this->sheet->setCellValue('A' . $this->currentRow, 'P = Perempuan');
        $this->currentRow++;
        $this->sheet->setCellValue('A' . $this->currentRow, 'TS = Target Sasaran');
        $this->currentRow++;
        $this->sheet->setCellValue('A' . $this->currentRow, '%S = Persentase Sasaran');
    }

    /**
     * Get current row number
     */
    public function getCurrentRow(): int
    {
        return $this->currentRow;
    }

    /**
     * Get last data column based on report type
     */
    public function getLastDataColumn(): string
    {
        switch ($this->reportType) {
            case 'all':
                return 'CJ'; // Total columns end
            case 'monthly':
                return 'BK'; // December columns end
            case 'quarterly':
                return 'AB'; // Quarter total columns end
            case 'puskesmas':
                return 'BK'; // December columns end
            default:
                return 'CJ';
        }
    }
}