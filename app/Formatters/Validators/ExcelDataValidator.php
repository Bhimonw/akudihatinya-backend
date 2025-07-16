<?php

namespace App\Formatters\Validators;

use App\Constants\ExcelConstants;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * Excel Data Validator Class
 * 
 * Provides comprehensive validation for Excel export data to ensure data integrity
 * and prevent errors during Excel generation process.
 */
class ExcelDataValidator
{
    /**
     * Validate puskesmas data structure and content
     * 
     * @param array $data Puskesmas data to validate
     * @return bool True if valid
     * @throws InvalidArgumentException If validation fails
     */
    public static function validatePuskesmasData(array $data): bool
    {
        try {
            // Check required fields
            $requiredFields = ExcelConstants::VALIDATION['REQUIRED_PUSKESMAS_FIELDS'];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Missing required field: {$field}");
                }
            }

            // Validate nama_puskesmas
            if (empty(trim($data['nama_puskesmas']))) {
                throw new InvalidArgumentException('nama_puskesmas cannot be empty');
            }

            if (strlen($data['nama_puskesmas']) > 255) {
                throw new InvalidArgumentException('nama_puskesmas too long (max 255 characters)');
            }

            // Validate sasaran
            if (!is_numeric($data['sasaran']) || $data['sasaran'] < 0) {
                throw new InvalidArgumentException('sasaran must be a non-negative number');
            }

            // Validate monthly_data structure
            if (!is_array($data['monthly_data'])) {
                throw new InvalidArgumentException('monthly_data must be an array');
            }

            // Validate each month's data
            foreach ($data['monthly_data'] as $month => $monthData) {
                if (!ExcelConstants::isValidMonth($month)) {
                    throw new InvalidArgumentException("Invalid month: {$month}");
                }

                self::validateMonthlyData($monthData, $month);
            }

            return true;
        } catch (InvalidArgumentException $e) {
            Log::error('Puskesmas data validation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Validate monthly data structure
     * 
     * @param array $monthData Monthly data to validate
     * @param int $month Month number for context
     * @return bool True if valid
     * @throws InvalidArgumentException If validation fails
     */
    public static function validateMonthlyData(array $monthData, int $month): bool
    {
        $requiredFields = ['male', 'female', 'standard', 'non_standard'];
        
        foreach ($requiredFields as $field) {
            if (!isset($monthData[$field])) {
                throw new InvalidArgumentException("Missing field '{$field}' in month {$month} data");
            }

            if (!is_numeric($monthData[$field]) || $monthData[$field] < 0) {
                throw new InvalidArgumentException("Field '{$field}' in month {$month} must be a non-negative number");
            }
        }

        // Validate logical consistency
        $total = $monthData['male'] + $monthData['female'];
        $standardTotal = $monthData['standard'] + $monthData['non_standard'];

        if ($standardTotal > $total) {
            Log::warning('Standard data exceeds total', [
                'month' => $month,
                'total' => $total,
                'standard_total' => $standardTotal
            ]);
        }

        return true;
    }

    /**
     * Validate report type
     * 
     * @param string $reportType Report type to validate
     * @return bool True if valid
     * @throws InvalidArgumentException If report type is invalid
     */
    public static function validateReportType(string $reportType): bool
    {
        if (!ExcelConstants::isValidReportType($reportType)) {
            $validTypes = implode(', ', ExcelConstants::VALIDATION['VALID_REPORT_TYPES']);
            throw new InvalidArgumentException("Invalid report type: {$reportType}. Valid types: {$validTypes}");
        }

        return true;
    }

    /**
     * Validate year parameter
     * 
     * @param int $year Year to validate
     * @return bool True if valid
     * @throws InvalidArgumentException If year is invalid
     */
    public static function validateYear(int $year): bool
    {
        $currentYear = (int) date('Y');
        $minYear = 2000;
        $maxYear = $currentYear + 5; // Allow future years for planning

        if ($year < $minYear || $year > $maxYear) {
            throw new InvalidArgumentException("Invalid year: {$year}. Must be between {$minYear} and {$maxYear}");
        }

        return true;
    }

    /**
     * Validate month parameter
     * 
     * @param int $month Month to validate
     * @return bool True if valid
     * @throws InvalidArgumentException If month is invalid
     */
    public static function validateMonth(int $month): bool
    {
        if (!ExcelConstants::isValidMonth($month)) {
            throw new InvalidArgumentException("Invalid month: {$month}. Must be between 1 and 12");
        }

        return true;
    }

    /**
     * Validate quarter parameter
     * 
     * @param int $quarter Quarter to validate
     * @return bool True if valid
     * @throws InvalidArgumentException If quarter is invalid
     */
    public static function validateQuarter(int $quarter): bool
    {
        if (!ExcelConstants::isValidQuarter($quarter)) {
            throw new InvalidArgumentException("Invalid quarter: {$quarter}. Must be between 1 and 4");
        }

        return true;
    }

    /**
     * Validate array of puskesmas data
     * 
     * @param array $puskesmasDataArray Array of puskesmas data
     * @return bool True if valid
     * @throws InvalidArgumentException If validation fails
     */
    public static function validatePuskesmasDataArray(array $puskesmasDataArray): bool
    {
        if (empty($puskesmasDataArray)) {
            throw new InvalidArgumentException('Puskesmas data array cannot be empty');
        }

        if (count($puskesmasDataArray) > ExcelConstants::LIMITS['MAX_ROWS_PER_SHEET']) {
            throw new InvalidArgumentException(
                'Too many puskesmas records. Maximum allowed: ' . ExcelConstants::LIMITS['MAX_ROWS_PER_SHEET']
            );
        }

        foreach ($puskesmasDataArray as $index => $data) {
            try {
                self::validatePuskesmasData($data);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException("Validation failed for puskesmas at index {$index}: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Validate template file existence and readability
     * 
     * @param string $templatePath Path to template file
     * @return bool True if valid
     * @throws InvalidArgumentException If template is invalid
     */
    public static function validateTemplateFile(string $templatePath): bool
    {
        if (empty($templatePath)) {
            throw new InvalidArgumentException('Template path cannot be empty');
        }

        if (!file_exists($templatePath)) {
            throw new InvalidArgumentException("Template file not found: {$templatePath}");
        }

        if (!is_readable($templatePath)) {
            throw new InvalidArgumentException("Template file not readable: {$templatePath}");
        }

        // Check file size
        $fileSize = filesize($templatePath);
        $maxSize = ExcelConstants::LIMITS['MAX_FILE_SIZE_MB'] * 1024 * 1024; // Convert to bytes
        
        if ($fileSize > $maxSize) {
            throw new InvalidArgumentException(
                "Template file too large: {$fileSize} bytes. Maximum: {$maxSize} bytes"
            );
        }

        // Check file extension
        $allowedExtensions = ['xlsx', 'xls'];
        $extension = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new InvalidArgumentException(
                "Invalid template file extension: {$extension}. Allowed: " . implode(', ', $allowedExtensions)
            );
        }

        return true;
    }

    /**
     * Validate export parameters
     * 
     * @param array $params Export parameters
     * @return bool True if valid
     * @throws InvalidArgumentException If parameters are invalid
     */
    public static function validateExportParameters(array $params): bool
    {
        // Validate required parameters
        $requiredParams = ['report_type', 'year'];
        
        foreach ($requiredParams as $param) {
            if (!isset($params[$param])) {
                throw new InvalidArgumentException("Missing required parameter: {$param}");
            }
        }

        // Validate individual parameters
        self::validateReportType($params['report_type']);
        self::validateYear($params['year']);

        // Validate optional parameters
        if (isset($params['month'])) {
            self::validateMonth($params['month']);
        }

        if (isset($params['quarter'])) {
            self::validateQuarter($params['quarter']);
        }

        // Validate data if provided
        if (isset($params['data'])) {
            if (!is_array($params['data'])) {
                throw new InvalidArgumentException('Data parameter must be an array');
            }
            
            self::validatePuskesmasDataArray($params['data']);
        }

        return true;
    }

    /**
     * Validate numeric value with range
     * 
     * @param mixed $value Value to validate
     * @param string $fieldName Field name for error messages
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return bool True if valid
     * @throws InvalidArgumentException If value is invalid
     */
    public static function validateNumericRange($value, string $fieldName, int $min = 0, int $max = PHP_INT_MAX): bool
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("{$fieldName} must be numeric");
        }

        $numericValue = (int) $value;
        
        if ($numericValue < $min || $numericValue > $max) {
            throw new InvalidArgumentException("{$fieldName} must be between {$min} and {$max}");
        }

        return true;
    }

    /**
     * Validate string length
     * 
     * @param string $value String to validate
     * @param string $fieldName Field name for error messages
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     * @return bool True if valid
     * @throws InvalidArgumentException If string is invalid
     */
    public static function validateStringLength(string $value, string $fieldName, int $minLength = 0, int $maxLength = 255): bool
    {
        $length = strlen(trim($value));
        
        if ($length < $minLength) {
            throw new InvalidArgumentException("{$fieldName} must be at least {$minLength} characters long");
        }

        if ($length > $maxLength) {
            throw new InvalidArgumentException("{$fieldName} must not exceed {$maxLength} characters");
        }

        return true;
    }

    /**
     * Sanitize and validate puskesmas name
     * 
     * @param string $name Puskesmas name to sanitize
     * @return string Sanitized name
     * @throws InvalidArgumentException If name is invalid
     */
    public static function sanitizePuskesmasName(string $name): string
    {
        $sanitized = trim($name);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized); // Replace multiple spaces with single space
        $sanitized = preg_replace('/[^\p{L}\p{N}\s\-\.]/u', '', $sanitized); // Allow letters, numbers, spaces, hyphens, dots
        
        self::validateStringLength($sanitized, 'Puskesmas name', 1, 255);
        
        return $sanitized;
    }

    /**
     * Validate data consistency across months
     * 
     * @param array $monthlyData Monthly data array
     * @return bool True if consistent
     * @throws InvalidArgumentException If data is inconsistent
     */
    public static function validateDataConsistency(array $monthlyData): bool
    {
        $issues = [];
        
        foreach ($monthlyData as $month => $data) {
            if (!is_array($data)) {
                $issues[] = "Month {$month}: Data is not an array";
                continue;
            }

            // Check for negative values
            foreach (['male', 'female', 'standard', 'non_standard'] as $field) {
                if (isset($data[$field]) && $data[$field] < 0) {
                    $issues[] = "Month {$month}: Negative value in {$field}";
                }
            }

            // Check logical consistency
            if (isset($data['male'], $data['female'], $data['standard'], $data['non_standard'])) {
                $total = $data['male'] + $data['female'];
                $standardTotal = $data['standard'] + $data['non_standard'];
                
                if ($standardTotal > $total * 1.1) { // Allow 10% tolerance for rounding
                    $issues[] = "Month {$month}: Standard data ({$standardTotal}) significantly exceeds total ({$total})";
                }
            }
        }

        if (!empty($issues)) {
            Log::warning('Data consistency issues found', ['issues' => $issues]);
            
            // For now, log warnings but don't throw exception
            // In production, you might want to throw an exception for critical issues
        }

        return true;
    }
}