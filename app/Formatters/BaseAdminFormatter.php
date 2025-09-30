<?php

namespace App\Formatters;

use App\Services\Statistics\StatisticsService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Base class untuk semua admin formatter
 * Menyediakan fungsi-fungsi umum untuk formatting Excel
 */
abstract class BaseAdminFormatter
{
    protected $statisticsService;
    protected $sheet;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Format spreadsheet - method abstract yang harus diimplementasi oleh child class
     */
    abstract public function format(Spreadsheet $spreadsheet, string $diseaseType, int $year, array $statistics): Spreadsheet;

    /**
     * Mendapatkan data statistik berdasarkan parameter
     */
    protected function getStatisticsData(string $diseaseType, int $year, ?int $puskesmasId = null): array
    {
        return $this->statisticsService->getStatisticsData($diseaseType, $year, $puskesmasId);
    }

    /**
     * Menghitung total capaian tahunan
     */
    protected function getYearlySummary(array $data): array
    {
        $totalAchievement = 0;
        $totalService = 0;
        
        foreach ($data as $puskesmasData) {
            if (isset($puskesmasData['yearly_achievement'])) {
                $totalAchievement += $puskesmasData['yearly_achievement'];
            }
            if (isset($puskesmasData['yearly_service'])) {
                $totalService += $puskesmasData['yearly_service'];
            }
        }
        
        $achievementPercentage = $totalService > 0 ? ($totalAchievement / $totalService) * 100 : 0;
        
        return [
            'total_achievement' => $totalAchievement,
            'total_service' => $totalService,
            'achievement_percentage' => round($achievementPercentage, 2)
        ];
    }

    /**
     * Mengganti placeholder dalam template
     */
    protected function replacePlaceholders(Spreadsheet $spreadsheet, array $replacements): void
    {
        // Tambahkan placeholder berformat <> berdasarkan data yang ada
        $additionalReplacements = $this->getAngleBracketPlaceholders($replacements);
        $allReplacements = array_merge($replacements, $additionalReplacements);
        
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            
            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cell = $sheet->getCell($col . $row);
                    $value = $cell->getValue();
                    
                    if (is_string($value)) {
                        foreach ($allReplacements as $placeholder => $replacement) {
                            $value = str_replace($placeholder, $replacement, $value);
                        }
                        $cell->setValue($value);
                    }
                }
            }
        }
    }
    
    /**
     * Mendapatkan placeholder berformat <> berdasarkan data yang tersedia
     */
    protected function getAngleBracketPlaceholders(array $replacements): array
    {
        $anglePlaceholders = [];
        
        // Mapping dari placeholder {{}} ke placeholder <>
        if (isset($replacements['{{DISEASE_TYPE}}'])) {
            $anglePlaceholders['<tipe_penyakit>'] = $replacements['{{DISEASE_TYPE}}'];
        }
        
        if (isset($replacements['{{YEAR}}'])) {
            $anglePlaceholders['<tahun>'] = $replacements['{{YEAR}}'];
        }
        
        if (isset($replacements['{{PUSKESMAS_NAME}}'])) {
            $anglePlaceholders['<puskesmas>'] = $replacements['{{PUSKESMAS_NAME}}'];
        }
        
        // Placeholder untuk sasaran
        if (isset($replacements['{{TARGET}}'])) {
            $anglePlaceholders['<sasaran>'] = $replacements['{{TARGET}}'];
        } else {
            // Default value untuk <sasaran>
            $anglePlaceholders['<sasaran>'] = 'Target Pencapaian';
        }
        
        // Placeholder khusus untuk periode waktu
         if (isset($replacements['{{MONTH}}']) || isset($replacements['{{QUARTER}}'])) {
             $period = '';
             if (isset($replacements['{{MONTH}}']) && is_numeric($replacements['{{MONTH}}'])) {
                 $period = 'Bulan ' . $this->getMonthName($replacements['{{MONTH}}']);
                 $anglePlaceholders['<mulai>'] = $this->getMonthName($replacements['{{MONTH}}']);
                 $anglePlaceholders['<akhir>'] = $this->getMonthName($replacements['{{MONTH}}']);
             } elseif (isset($replacements['{{QUARTER}}'])) {
                 // Extract quarter number from string like "Triwulan 1"
                 $quarterStr = $replacements['{{QUARTER}}'];
                 if (preg_match('/\d+/', $quarterStr, $matches)) {
                     $quarterNum = intval($matches[0]);
                     $quarterMonths = $this->getQuarterMonths($quarterNum);
                     $anglePlaceholders['<mulai>'] = $this->getMonthName($quarterMonths[0]);
                     $anglePlaceholders['<akhir>'] = $this->getMonthName(end($quarterMonths));
                 } else {
                     $anglePlaceholders['<mulai>'] = $quarterStr;
                     $anglePlaceholders['<akhir>'] = $quarterStr;
                 }
             }
         } else {
             // Default untuk laporan tahunan
             $anglePlaceholders['<mulai>'] = 'Januari';
             $anglePlaceholders['<akhir>'] = 'Desember';
         }
        
        return $anglePlaceholders;
     }
     
     /**
      * Dapatkan bulan-bulan dalam triwulan
      */
     protected function getQuarterMonths(int $quarter): array
     {
         switch ($quarter) {
             case 1:
                 return [1, 2, 3]; // Jan, Feb, Mar
             case 2:
                 return [4, 5, 6]; // Apr, May, Jun
             case 3:
                 return [7, 8, 9]; // Jul, Aug, Sep
             case 4:
                 return [10, 11, 12]; // Oct, Nov, Dec
             default:
                 return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
         }
     }

    /**
     * Menerapkan style pada range sel
     */
    protected function applyStyles(Spreadsheet $spreadsheet, string $range, array $styles): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle($range)->applyFromArray($styles);
    }

    /**
     * Menerapkan format angka pada range sel
     */
    protected function applyNumberFormat(Spreadsheet $spreadsheet, string $range, string $format = NumberFormat::FORMAT_NUMBER): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode($format);
    }

    /**
     * Menerapkan format persentase pada range sel
     */
    protected function applyPercentageFormat(Spreadsheet $spreadsheet, string $range): void
    {
        $this->applyNumberFormat($spreadsheet, $range, NumberFormat::FORMAT_PERCENTAGE_00);
    }

    /**
     * Menerapkan border pada range sel
     */
    protected function applyBorder(Spreadsheet $spreadsheet, string $range, string $borderStyle = Border::BORDER_THIN): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle($borderStyle);
    }

    /**
     * Menerapkan alignment pada range sel
     */
    protected function applyAlignment(Spreadsheet $spreadsheet, string $range, string $horizontal = Alignment::HORIZONTAL_CENTER, string $vertical = Alignment::VERTICAL_CENTER): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal($horizontal)
            ->setVertical($vertical);
    }

    /**
     * Mendapatkan nama bulan dalam bahasa Indonesia
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $months[$month] ?? '';
    }

    /**
     * Mendapatkan label penyakit
     */
    protected function getDiseaseLabel(string $diseaseType): string
    {
        switch ($diseaseType) {
            case 'ht':
                return 'Hipertensi';
            case 'dm':
                return 'Diabetes Melitus';
            default:
                return 'Hipertensi & Diabetes Melitus';
        }
    }

    /**
     * Format data untuk ditampilkan di Excel
     */
    protected function formatDataForExcel($value): string
    {
        if (is_null($value)) {
            return '0';
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        return (string) $value;
    }

    /**
     * Menghitung persentase dengan penanganan pembagian nol
     */
    protected function calculatePercentage($numerator, $denominator): float
    {
        if ($denominator == 0) {
            return 0;
        }
        
        return round(($numerator / $denominator) * 100, 2);
    }
}