<?php

namespace Tests\Unit\Formatters;

use App\Formatters\AdminQuarterlyFormatter;
use App\Services\Statistics\StatisticsService;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class AdminQuarterlyFormatterTest extends TestCase
{
    /**
     * Test format quarterly data
     */
    public function testFormatQuarterlyData()
    {
        // Buat data sampel
        $statistics = $this->getSampleStatistics();
        
        // Mock StatisticsService
        $statisticsService = Mockery::mock(StatisticsService::class);
        
        // Load template Excel
        $templatePath = resource_path('templates/quarterly.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        
        // Format data menggunakan AdminQuarterlyFormatter
        $formatter = new AdminQuarterlyFormatter($statisticsService);
        $result = $formatter->format($spreadsheet, 'ht', 2023, $statistics, 1);
        
        // Ambil sheet aktif
        $sheet = $result->getActiveSheet();
        
        // Verifikasi header triwulan
        $this->assertEquals('TRIWULAN I', $sheet->getCell('D6')->getValue());
        
        // Verifikasi nama bulan
        $this->assertEquals('JANUARI', $sheet->getCell('D7')->getValue());
        $this->assertEquals('FEBRUARI', $sheet->getCell('G7')->getValue());
        $this->assertEquals('MARET', $sheet->getCell('J7')->getValue());
        $this->assertEquals('TOTAL TW I', $sheet->getCell('M7')->getValue());
        
        // Verifikasi header kolom
        $this->assertEquals('S', $sheet->getCell('D8')->getValue());
        $this->assertEquals('TS', $sheet->getCell('E8')->getValue());
        $this->assertEquals('%S', $sheet->getCell('F8')->getValue());
        
        // Verifikasi data puskesmas
        $this->assertEquals('Puskesmas Test 1', $sheet->getCell('B9')->getValue());
        
        // Simpan hasil untuk inspeksi visual jika diperlukan
        $outputPath = storage_path('app/test_quarterly_output.xlsx');
        $writer = IOFactory::createWriter($result, 'Xlsx');
        $writer->save($outputPath);
        
        echo "File test disimpan di: $outputPath\n";
    }
    
    /**
     * Buat data sampel untuk testing
     */
    private function getSampleStatistics()
    {
        return [
            [
                'puskesmas_name' => 'Puskesmas Test 1',
                'ht' => [
                    'target' => 100,
                    'total_patients' => 80,
                    'standard_patients' => 60,
                    'non_standard_patients' => 20,
                    'male_patients' => 35,
                    'female_patients' => 45
                ],
                'monthly_data' => [
                    1 => [ // Januari
                        'ht' => [
                            'target' => 30,
                            'total_patients' => 25,
                            'standard_patients' => 20,
                            'non_standard_patients' => 5,
                            'male_patients' => 12,
                            'female_patients' => 13
                        ]
                    ],
                    2 => [ // Februari
                        'ht' => [
                            'target' => 30,
                            'total_patients' => 28,
                            'standard_patients' => 22,
                            'non_standard_patients' => 6,
                            'male_patients' => 13,
                            'female_patients' => 15
                        ]
                    ],
                    3 => [ // Maret
                        'ht' => [
                            'target' => 40,
                            'total_patients' => 27,
                            'standard_patients' => 18,
                            'non_standard_patients' => 9,
                            'male_patients' => 10,
                            'female_patients' => 17
                        ]
                    ],
                    4 => [ // April
                        'ht' => [
                            'target' => 35,
                            'total_patients' => 30,
                            'standard_patients' => 25,
                            'non_standard_patients' => 5,
                            'male_patients' => 14,
                            'female_patients' => 16
                        ]
                    ]
                ]
            ],
            [
                'puskesmas_name' => 'Puskesmas Test 2',
                'ht' => [
                    'target' => 120,
                    'total_patients' => 90,
                    'standard_patients' => 70,
                    'non_standard_patients' => 20,
                    'male_patients' => 40,
                    'female_patients' => 50
                ],
                'monthly_data' => [
                    1 => [ // Januari
                        'ht' => [
                            'target' => 40,
                            'total_patients' => 30,
                            'standard_patients' => 25,
                            'non_standard_patients' => 5,
                            'male_patients' => 15,
                            'female_patients' => 15
                        ]
                    ],
                    2 => [ // Februari
                        'ht' => [
                            'target' => 40,
                            'total_patients' => 32,
                            'standard_patients' => 24,
                            'non_standard_patients' => 8,
                            'male_patients' => 14,
                            'female_patients' => 18
                        ]
                    ],
                    3 => [ // Maret
                        'ht' => [
                            'target' => 40,
                            'total_patients' => 28,
                            'standard_patients' => 21,
                            'non_standard_patients' => 7,
                            'male_patients' => 11,
                            'female_patients' => 17
                        ]
                    ],
                    4 => [ // April
                        'ht' => [
                            'target' => 40,
                            'total_patients' => 35,
                            'standard_patients' => 28,
                            'non_standard_patients' => 7,
                            'male_patients' => 16,
                            'female_patients' => 19
                        ]
                    ]
                ]
            ]
        ];
    }
}