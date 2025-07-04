<?php

namespace App\Services;

use App\Formatters\AdminAllFormatter;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\PuskesmasFormatter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;

/**
 * Service untuk menangani export Excel menggunakan formatter yang telah dibuat
 * Menyediakan interface yang mudah untuk export berbagai jenis laporan
 */
class ExcelExportService
{
    protected $adminAllFormatter;
    protected $adminMonthlyFormatter;
    protected $adminQuarterlyFormatter;
    protected $puskesmasFormatter;

    public function __construct(
        AdminAllFormatter $adminAllFormatter,
        AdminMonthlyFormatter $adminMonthlyFormatter,
        AdminQuarterlyFormatter $adminQuarterlyFormatter,
        PuskesmasFormatter $puskesmasFormatter
    ) {
        $this->adminAllFormatter = $adminAllFormatter;
        $this->adminMonthlyFormatter = $adminMonthlyFormatter;
        $this->adminQuarterlyFormatter = $adminQuarterlyFormatter;
        $this->puskesmasFormatter = $puskesmasFormatter;
    }

    /**
     * Export laporan tahunan komprehensif (all.xlsx)
     */
    public function exportAll(string $diseaseType = 'ht', int $year = null): array
    {
        try {
            $year = $year ?? date('Y');
            
            Log::info('ExcelExportService: Starting all.xlsx export', [
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            $spreadsheet = $this->adminAllFormatter->format($diseaseType, $year);
            $filename = $this->adminAllFormatter->getFilename($diseaseType, $year);
            
            $filePath = $this->saveSpreadsheet($spreadsheet, $filename);
            
            Log::info('ExcelExportService: Successfully exported all.xlsx', [
                'filename' => $filename,
                'file_path' => $filePath
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'file_path' => $filePath,
                'download_url' => Storage::url($filePath)
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error exporting all.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export laporan bulanan (monthly.xlsx)
     */
    public function exportMonthly(string $diseaseType = 'ht', int $year = null): array
    {
        try {
            $year = $year ?? date('Y');
            
            Log::info('ExcelExportService: Starting monthly.xlsx export', [
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            $spreadsheet = $this->adminMonthlyFormatter->format($diseaseType, $year);
            $filename = $this->adminMonthlyFormatter->getFilename($diseaseType, $year);
            
            $filePath = $this->saveSpreadsheet($spreadsheet, $filename);
            
            Log::info('ExcelExportService: Successfully exported monthly.xlsx', [
                'filename' => $filename,
                'file_path' => $filePath
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'file_path' => $filePath,
                'download_url' => Storage::url($filePath)
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error exporting monthly.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export laporan triwulan (quarterly.xlsx)
     */
    public function exportQuarterly(string $diseaseType = 'ht', int $year = null): array
    {
        try {
            $year = $year ?? date('Y');
            
            Log::info('ExcelExportService: Starting quarterly.xlsx export', [
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            $spreadsheet = $this->adminQuarterlyFormatter->format($diseaseType, $year);
            $filename = $this->adminQuarterlyFormatter->getFilename($diseaseType, $year);
            
            $filePath = $this->saveSpreadsheet($spreadsheet, $filename);
            
            Log::info('ExcelExportService: Successfully exported quarterly.xlsx', [
                'filename' => $filename,
                'file_path' => $filePath
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'file_path' => $filePath,
                'download_url' => Storage::url($filePath)
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error exporting quarterly.xlsx', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export laporan per puskesmas (puskesmas.xlsx)
     */
    public function exportPuskesmas(int $puskesmasId, string $diseaseType = 'ht', int $year = null): array
    {
        try {
            $year = $year ?? date('Y');
            
            Log::info('ExcelExportService: Starting puskesmas.xlsx export', [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            $spreadsheet = $this->puskesmasFormatter->format($diseaseType, $year, [
                'puskesmas_id' => $puskesmasId
            ]);
            
            // Get puskesmas data untuk filename
            $puskesmasData = $this->puskesmasFormatter->getPuskesmasSpecificData($puskesmasId, $diseaseType, $year);
            $filename = $this->puskesmasFormatter->getFilename($diseaseType, $year, $puskesmasData);
            
            $filePath = $this->saveSpreadsheet($spreadsheet, $filename);
            
            Log::info('ExcelExportService: Successfully exported puskesmas.xlsx', [
                'puskesmas_id' => $puskesmasId,
                'filename' => $filename,
                'file_path' => $filePath
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'file_path' => $filePath,
                'download_url' => Storage::url($filePath)
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error exporting puskesmas.xlsx', [
                'error' => $e->getMessage(),
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export template puskesmas kosong
     */
    public function exportPuskesmasTemplate(string $diseaseType = 'ht', int $year = null): array
    {
        try {
            $year = $year ?? date('Y');
            
            Log::info('ExcelExportService: Starting puskesmas template export', [
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            $spreadsheet = $this->puskesmasFormatter->formatTemplate($diseaseType, $year);
            $filename = $this->puskesmasFormatter->getFilename($diseaseType, $year);
            
            $filePath = $this->saveSpreadsheet($spreadsheet, $filename);
            
            Log::info('ExcelExportService: Successfully exported puskesmas template', [
                'filename' => $filename,
                'file_path' => $filePath
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'file_path' => $filePath,
                'download_url' => Storage::url($filePath)
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error exporting puskesmas template', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export semua jenis laporan sekaligus (batch export)
     */
    public function exportAll(string $diseaseType = 'ht', int $year = null): array
    {
        try {
            $year = $year ?? date('Y');
            
            Log::info('ExcelExportService: Starting batch export', [
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            $results = [];
            
            // Export all reports
            $results['all'] = $this->exportAll($diseaseType, $year);
            $results['monthly'] = $this->exportMonthly($diseaseType, $year);
            $results['quarterly'] = $this->exportQuarterly($diseaseType, $year);
            $results['template'] = $this->exportPuskesmasTemplate($diseaseType, $year);
            
            // Check if all exports were successful
            $allSuccess = collect($results)->every(function ($result) {
                return $result['success'] ?? false;
            });
            
            Log::info('ExcelExportService: Completed batch export', [
                'disease_type' => $diseaseType,
                'year' => $year,
                'all_success' => $allSuccess
            ]);
            
            return [
                'success' => $allSuccess,
                'results' => $results,
                'summary' => [
                    'total_files' => count($results),
                    'successful' => collect($results)->where('success', true)->count(),
                    'failed' => collect($results)->where('success', false)->count()
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error in batch export', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Download file sebagai response
     */
    public function downloadFile(string $filePath, string $filename = null): Response
    {
        try {
            if (!Storage::exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            
            $filename = $filename ?? basename($filePath);
            
            return response()->download(
                Storage::path($filePath),
                $filename,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error downloading file', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'filename' => $filename
            ]);
            
            return response()->json([
                'error' => 'File download failed: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Stream download file (untuk file besar)
     */
    public function streamDownload(string $diseaseType, int $year, string $reportType = 'all'): Response
    {
        try {
            $formatter = $this->getFormatterByType($reportType);
            $spreadsheet = $formatter->format($diseaseType, $year);
            $filename = $formatter->getFilename($diseaseType, $year);
            
            return response()->streamDownload(function() use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]);
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error streaming download', [
                'error' => $e->getMessage(),
                'disease_type' => $diseaseType,
                'year' => $year,
                'report_type' => $reportType
            ]);
            
            return response()->json([
                'error' => 'Stream download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get formatter berdasarkan tipe laporan
     */
    protected function getFormatterByType(string $reportType)
    {
        switch ($reportType) {
            case 'all':
                return $this->adminAllFormatter;
            case 'monthly':
                return $this->adminMonthlyFormatter;
            case 'quarterly':
                return $this->adminQuarterlyFormatter;
            case 'puskesmas':
                return $this->puskesmasFormatter;
            default:
                throw new \InvalidArgumentException("Invalid report type: {$reportType}");
        }
    }

    /**
     * Simpan spreadsheet ke storage
     */
    protected function saveSpreadsheet($spreadsheet, string $filename): string
    {
        $directory = 'exports/excel/' . date('Y/m');
        $filePath = $directory . '/' . $filename;
        
        // Ensure directory exists
        Storage::makeDirectory($directory);
        
        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->save(Storage::path($filePath));
        
        return $filePath;
    }

    /**
     * Cleanup old export files
     */
    public function cleanupOldFiles(int $daysOld = 30): array
    {
        try {
            $cutoffDate = now()->subDays($daysOld);
            $exportDirectory = 'exports/excel';
            
            $files = Storage::allFiles($exportDirectory);
            $deletedFiles = [];
            
            foreach ($files as $file) {
                $lastModified = Storage::lastModified($file);
                
                if ($lastModified < $cutoffDate->timestamp) {
                    Storage::delete($file);
                    $deletedFiles[] = $file;
                }
            }
            
            Log::info('ExcelExportService: Cleaned up old files', [
                'days_old' => $daysOld,
                'deleted_count' => count($deletedFiles)
            ]);
            
            return [
                'success' => true,
                'deleted_count' => count($deletedFiles),
                'deleted_files' => $deletedFiles
            ];
            
        } catch (\Exception $e) {
            Log::error('ExcelExportService: Error cleaning up files', [
                'error' => $e->getMessage(),
                'days_old' => $daysOld
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available export types
     */
    public function getAvailableExportTypes(): array
    {
        return [
            'all' => [
                'name' => 'Laporan Tahunan Komprehensif',
                'description' => 'Rekap data bulanan, triwulan, dan total tahunan',
                'formatter' => AdminAllFormatter::class
            ],
            'monthly' => [
                'name' => 'Laporan Bulanan',
                'description' => 'Detail data bulanan dengan klasifikasi S/TS',
                'formatter' => AdminMonthlyFormatter::class
            ],
            'quarterly' => [
                'name' => 'Laporan Triwulan',
                'description' => 'Ringkasan capaian per triwulan',
                'formatter' => AdminQuarterlyFormatter::class
            ],
            'puskesmas' => [
                'name' => 'Laporan Per Puskesmas',
                'description' => 'Template individual untuk puskesmas',
                'formatter' => PuskesmasFormatter::class
            ]
        ];
    }

    /**
     * Get available disease types
     */
    public function getAvailableDiseaseTypes(): array
    {
        return [
            'ht' => 'Hipertensi',
            'dm' => 'Diabetes Melitus',
            'both' => 'Hipertensi dan Diabetes Melitus'
        ];
    }
}