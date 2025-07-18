<?php

namespace App\Formatters;

use App\Formatters\AdminAllFormatter;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\PuskesmasFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Factory untuk membuat formatter yang sesuai berdasarkan template dan konteks
 */
class DynamicFormatterFactory
{
    /**
     * Membuat formatter yang sesuai berdasarkan parameter
     */
    public static function createFormatter(
        string $templateType,
        string $diseaseType = 'all',
        ?int $month = null,
        ?int $quarter = null
    ): BaseAdminFormatter {
        $user = Auth::user();
        
        // Jika user adalah puskesmas atau template type adalah puskesmas
        if ($templateType === 'puskesmas' || (!$user->isAdmin() && $user->puskesmas_id)) {
            return app(PuskesmasFormatter::class);
        }
        
        // Untuk admin, tentukan formatter berdasarkan jenis laporan
        return self::getAdminFormatter($templateType, $diseaseType, $month, $quarter);
    }
    
    /**
     * Mendapatkan formatter admin yang sesuai
     */
    private static function getAdminFormatter(
        string $templateType,
        string $diseaseType,
        ?int $month,
        ?int $quarter
    ): BaseAdminFormatter {
        // Jika template type sudah spesifik
        switch ($templateType) {
            case 'all':
                return app(AdminAllFormatter::class);
            case 'monthly':
                return app(AdminMonthlyFormatter::class);
            case 'quarterly':
                return app(AdminQuarterlyFormatter::class);
        }
        
        // Auto-detect berdasarkan parameter
        if ($month) {
            return app(AdminMonthlyFormatter::class);
        }
        
        if ($quarter) {
            return app(AdminQuarterlyFormatter::class);
        }
        
        // Default ke all formatter
        return app(AdminAllFormatter::class);
    }
    
    /**
     * Mendapatkan path template yang sesuai
     */
    public static function getTemplatePath(
        string $templateType,
        string $diseaseType = 'all',
        ?int $month = null,
        ?int $quarter = null
    ): string {
        $user = Auth::user();
        
        // Jika user adalah puskesmas atau template type adalah puskesmas
        if ($templateType === 'puskesmas' || (!$user->isAdmin() && $user->puskesmas_id)) {
            return resource_path('templates/puskesmas.xlsx');
        }
        
        // Untuk admin, tentukan template berdasarkan jenis laporan
        return self::getAdminTemplatePath($templateType, $diseaseType, $month, $quarter);
    }
    
    /**
     * Mendapatkan path template admin yang sesuai
     */
    private static function getAdminTemplatePath(
        string $templateType,
        string $diseaseType,
        ?int $month,
        ?int $quarter
    ): string {
        // Jika template type sudah spesifik
        switch ($templateType) {
            case 'all':
                return resource_path('templates/all.xlsx');
            case 'monthly':
                return resource_path('templates/monthly.xlsx');
            case 'quarterly':
                return resource_path('templates/quarterly.xlsx');
        }
        
        // Auto-detect berdasarkan parameter
        if ($month) {
            return resource_path('templates/monthly.xlsx');
        }
        
        if ($quarter) {
            return resource_path('templates/quarterly.xlsx');
        }
        
        // Default ke all template
        return resource_path('templates/all.xlsx');
    }
    
    /**
     * Validasi keberadaan template
     */
    public static function validateTemplate(string $templatePath): bool
    {
        if (!file_exists($templatePath)) {
            Log::error('Template Excel tidak ditemukan', [
                'template_path' => $templatePath
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Mendapatkan daftar template yang tersedia
     */
    public static function getAvailableTemplates(): array
    {
        $templatesPath = resource_path('templates');
        $templates = [];
        
        if (is_dir($templatesPath)) {
            $files = scandir($templatesPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'xlsx') {
                    $templateName = pathinfo($file, PATHINFO_FILENAME);
                    $templates[$templateName] = $templatesPath . DIRECTORY_SEPARATOR . $file;
                }
            }
        }
        
        return $templates;
    }
    
    /**
     * Mendapatkan formatter berdasarkan nama template file
     */
    public static function createFormatterFromTemplate(string $templateName): BaseAdminFormatter
    {
        switch ($templateName) {
            case 'all':
                return app(AdminAllFormatter::class);
            case 'monthly':
                return app(AdminMonthlyFormatter::class);
            case 'quarterly':
                return app(AdminQuarterlyFormatter::class);
            case 'puskesmas':
                return app(PuskesmasFormatter::class);
            default:
                Log::warning('Template tidak dikenal, menggunakan AdminAllFormatter', [
                    'template_name' => $templateName
                ]);
                return app(AdminAllFormatter::class);
        }
    }
}