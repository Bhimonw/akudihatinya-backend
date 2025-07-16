<?php

namespace App\Formatters\Strategies;

use Illuminate\Support\Facades\Log;
use App\Traits\Calculation\PercentageCalculationTrait;

/**
 * Base class untuk semua formatter strategies
 * Menyediakan implementasi umum dan helper methods
 */
abstract class BaseFormatterStrategy implements FormatterStrategyInterface
{
    use PercentageCalculationTrait;

    /**
     * Strategy name untuk logging
     */
    protected string $name;

    /**
     * Default options untuk strategy
     */
    protected array $defaultOptions = [];

    public function __construct()
    {
        $this->name = static::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $options = []): bool
    {
        // Basic validation - dapat di-override oleh child classes
        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        return true;
    }

    /**
     * Merge options dengan default options
     *
     * @param array $options User provided options
     * @return array Merged options
     */
    protected function mergeOptions(array $options): array
    {
        return array_merge($this->defaultOptions, $options);
    }

    /**
     * Log strategy execution
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $level Log level
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        $context['strategy'] = $this->getName();
        Log::$level($message, $context);
    }

    /**
     * Format number dengan thousand separator
     *
     * @param mixed $number Number to format
     * @return string Formatted number
     */
    protected function formatNumber($number): string
    {
        $num = $number ?? 0;
        if (is_numeric($num) && $num == intval($num)) {
            return number_format($num, 0, '', '.');
        }
        return number_format($num, 2, '.', '');
    }

    /**
     * Format percentage
     *
     * @param mixed $value Percentage value
     * @param int $decimals Number of decimal places
     * @return string Formatted percentage
     */
    protected function formatPercentage($value, int $decimals = 2): string
    {
        return number_format($value ?? 0, $decimals, '.', '') . '%';
    }

    /**
     * Get month name in Indonesian
     *
     * @param int $month Month number (1-12)
     * @return string Month name
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        return $months[$month] ?? 'Bulan Tidak Valid';
    }

    /**
     * Get disease type label in Indonesian
     *
     * @param string $diseaseType Disease type code
     * @return string Disease type label
     */
    protected function getDiseaseTypeLabel(string $diseaseType): string
    {
        $labels = [
            'ht' => 'Hipertensi',
            'dm' => 'Diabetes Melitus',
            'both' => 'Hipertensi dan Diabetes Melitus'
        ];
        
        return $labels[$diseaseType] ?? 'Hipertensi';
    }

    /**
     * Calculate yearly totals from monthly data
     *
     * @param array $monthlyData Monthly data array
     * @return array Yearly totals
     */
    protected function calculateYearlyTotals(array $monthlyData): array
    {
        $totals = ['male' => 0, 'female' => 0, 'standard' => 0, 'non_standard' => 0, 'total' => 0];
        
        foreach ($monthlyData as $data) {
            $totals['male'] += $data['male'] ?? 0;
            $totals['female'] += $data['female'] ?? 0;
            $totals['standard'] += $data['standard'] ?? 0;
            $totals['non_standard'] += $data['non_standard'] ?? 0;
            $totals['total'] += $data['total'] ?? 0;
        }
        
        return $totals;
    }

    /**
     * Validate year parameter
     *
     * @param int $year Year to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If year is invalid
     */
    protected function validateYear(int $year): bool
    {
        $currentYear = date('Y');
        if ($year < 2020 || $year > $currentYear + 1) {
            throw new \InvalidArgumentException("Invalid year: {$year}");
        }
        return true;
    }

    /**
     * Validate disease type parameter
     *
     * @param string $diseaseType Disease type to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If disease type is invalid
     */
    protected function validateDiseaseType(string $diseaseType): bool
    {
        $validTypes = ['ht', 'dm', 'both'];
        if (!in_array($diseaseType, $validTypes)) {
            throw new \InvalidArgumentException("Invalid disease type: {$diseaseType}");
        }
        return true;
    }
}