<?php

namespace App\Formatters\Calculators;

use App\Constants\ExcelConstants;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * Statistics Calculator Class
 * 
 * Handles all statistical calculations for Excel reports.
 * This class centralizes calculation logic to improve maintainability and testability.
 */
class StatisticsCalculator
{
    /**
     * Calculate monthly totals from puskesmas data
     * 
     * @param array $puskesmasData Array of puskesmas data
     * @param int $month Month number (1-12)
     * @return array Calculated totals with keys: male, female, total, standard, non_standard, percentage
     * @throws InvalidArgumentException If month is invalid
     */
    public static function calculateMonthTotal(array $puskesmasData, int $month): array
    {
        if (!ExcelConstants::isValidMonth($month)) {
            throw new InvalidArgumentException("Invalid month: {$month}. Must be between 1 and 12.");
        }

        $totals = [
            'male' => 0,
            'female' => 0,
            'total' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'percentage' => 0.0
        ];

        try {
            foreach ($puskesmasData as $data) {
                if (!isset($data['monthly_data'][$month])) {
                    continue;
                }

                $monthData = $data['monthly_data'][$month];
                
                $totals['male'] += (int) ($monthData['male'] ?? 0);
                $totals['female'] += (int) ($monthData['female'] ?? 0);
                $totals['standard'] += (int) ($monthData['standard'] ?? 0);
                $totals['non_standard'] += (int) ($monthData['non_standard'] ?? 0);
            }

            // Calculate derived values
            $totals['total'] = $totals['male'] + $totals['female'];
            $totals['percentage'] = self::calculateStandardPercentage(
                $totals['standard'], 
                $totals['total']
            );

            return $totals;
        } catch (\Exception $e) {
            Log::error('Error calculating month total', [
                'month' => $month,
                'error' => $e->getMessage(),
                'data_count' => count($puskesmasData)
            ]);
            
            return $totals; // Return zeros on error
        }
    }

    /**
     * Calculate quarterly totals from puskesmas data
     * 
     * @param array $puskesmasData Array of puskesmas data
     * @param int $quarter Quarter number (1-4)
     * @return array Calculated totals
     * @throws InvalidArgumentException If quarter is invalid
     */
    public static function calculateQuarterTotal(array $puskesmasData, int $quarter): array
    {
        if (!ExcelConstants::isValidQuarter($quarter)) {
            throw new InvalidArgumentException("Invalid quarter: {$quarter}. Must be between 1 and 4.");
        }

        $totals = [
            'male' => 0,
            'female' => 0,
            'total' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'percentage' => 0.0
        ];

        try {
            // Get months for this quarter
            $months = self::getMonthsForQuarter($quarter);

            foreach ($puskesmasData as $data) {
                foreach ($months as $month) {
                    if (!isset($data['monthly_data'][$month])) {
                        continue;
                    }

                    $monthData = $data['monthly_data'][$month];
                    
                    $totals['male'] += (int) ($monthData['male'] ?? 0);
                    $totals['female'] += (int) ($monthData['female'] ?? 0);
                    $totals['standard'] += (int) ($monthData['standard'] ?? 0);
                    $totals['non_standard'] += (int) ($monthData['non_standard'] ?? 0);
                }
            }

            // Calculate derived values
            $totals['total'] = $totals['male'] + $totals['female'];
            $totals['percentage'] = self::calculateStandardPercentage(
                $totals['standard'], 
                $totals['total']
            );

            return $totals;
        } catch (\Exception $e) {
            Log::error('Error calculating quarter total', [
                'quarter' => $quarter,
                'error' => $e->getMessage(),
                'data_count' => count($puskesmasData)
            ]);
            
            return $totals; // Return zeros on error
        }
    }

    /**
     * Calculate yearly totals from all monthly data
     * 
     * @param array $puskesmasData Array of puskesmas data
     * @return array Calculated yearly totals
     */
    public static function calculateYearlyTotalFromAll(array $puskesmasData): array
    {
        $totals = [
            'male' => 0,
            'female' => 0,
            'total' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'percentage' => 0.0
        ];

        try {
            foreach ($puskesmasData as $data) {
                for ($month = 1; $month <= 12; $month++) {
                    if (!isset($data['monthly_data'][$month])) {
                        continue;
                    }

                    $monthData = $data['monthly_data'][$month];
                    
                    $totals['male'] += (int) ($monthData['male'] ?? 0);
                    $totals['female'] += (int) ($monthData['female'] ?? 0);
                    $totals['standard'] += (int) ($monthData['standard'] ?? 0);
                    $totals['non_standard'] += (int) ($monthData['non_standard'] ?? 0);
                }
            }

            // Calculate derived values
            $totals['total'] = $totals['male'] + $totals['female'];
            $totals['percentage'] = self::calculateStandardPercentage(
                $totals['standard'], 
                $totals['total']
            );

            return $totals;
        } catch (\Exception $e) {
            Log::error('Error calculating yearly total', [
                'error' => $e->getMessage(),
                'data_count' => count($puskesmasData)
            ]);
            
            return $totals; // Return zeros on error
        }
    }

    /**
     * Calculate standard percentage with proper validation
     * 
     * @param int $standard Number of standard cases
     * @param int $total Total number of cases
     * @return float Percentage (0-100), capped at 100%
     */
    public static function calculateStandardPercentage(int $standard, int $total): float
    {
        // Handle edge cases
        if ($total <= 0) {
            return 0.0;
        }

        if ($standard < 0) {
            Log::warning('Negative standard value detected', [
                'standard' => $standard,
                'total' => $total
            ]);
            return 0.0;
        }

        if ($standard > $total) {
            Log::warning('Standard value exceeds total', [
                'standard' => $standard,
                'total' => $total
            ]);
            // Cap at 100% but log the anomaly
            return 100.0;
        }

        $percentage = ($standard / $total) * 100;
        
        // Ensure percentage doesn't exceed 100%
        return min($percentage, 100.0);
    }

    /**
     * Calculate totals for a specific data category across all puskesmas
     * 
     * @param array $puskesmasData Array of puskesmas data
     * @param string $category Data category name
     * @param int $month Month number (optional, if null calculates for all months)
     * @return array Calculated totals for the category
     */
    public static function calculateCategoryTotal(array $puskesmasData, string $category, ?int $month = null): array
    {
        $totals = [
            'male' => 0,
            'female' => 0,
            'total' => 0,
            'standard' => 0,
            'non_standard' => 0,
            'percentage' => 0.0
        ];

        try {
            foreach ($puskesmasData as $data) {
                if ($month !== null) {
                    // Calculate for specific month
                    if (!ExcelConstants::isValidMonth($month)) {
                        throw new InvalidArgumentException("Invalid month: {$month}");
                    }
                    
                    $monthData = $data['monthly_data'][$month][$category] ?? [];
                    self::addToTotals($totals, $monthData);
                } else {
                    // Calculate for all months
                    for ($m = 1; $m <= 12; $m++) {
                        $monthData = $data['monthly_data'][$m][$category] ?? [];
                        self::addToTotals($totals, $monthData);
                    }
                }
            }

            // Calculate derived values
            $totals['total'] = $totals['male'] + $totals['female'];
            $totals['percentage'] = self::calculateStandardPercentage(
                $totals['standard'], 
                $totals['total']
            );

            return $totals;
        } catch (\Exception $e) {
            Log::error('Error calculating category total', [
                'category' => $category,
                'month' => $month,
                'error' => $e->getMessage()
            ]);
            
            return $totals;
        }
    }

    /**
     * Get months for a specific quarter
     * 
     * @param int $quarter Quarter number (1-4)
     * @return array Array of month numbers
     */
    private static function getMonthsForQuarter(int $quarter): array
    {
        $quarterMonths = [
            1 => [1, 2, 3],   // Q1: Jan, Feb, Mar
            2 => [4, 5, 6],   // Q2: Apr, May, Jun
            3 => [7, 8, 9],   // Q3: Jul, Aug, Sep
            4 => [10, 11, 12] // Q4: Oct, Nov, Dec
        ];

        return $quarterMonths[$quarter] ?? [];
    }

    /**
     * Add month data to running totals
     * 
     * @param array &$totals Reference to totals array
     * @param array $monthData Month data to add
     */
    private static function addToTotals(array &$totals, array $monthData): void
    {
        $totals['male'] += (int) ($monthData['male'] ?? 0);
        $totals['female'] += (int) ($monthData['female'] ?? 0);
        $totals['standard'] += (int) ($monthData['standard'] ?? 0);
        $totals['non_standard'] += (int) ($monthData['non_standard'] ?? 0);
    }

    /**
     * Validate calculation input data
     * 
     * @param array $data Data to validate
     * @return bool True if valid
     * @throws InvalidArgumentException If data is invalid
     */
    public static function validateCalculationData(array $data): bool
    {
        $requiredFields = ExcelConstants::VALIDATION['REQUIRED_PUSKESMAS_FIELDS'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate monthly_data structure
        if (!is_array($data['monthly_data'])) {
            throw new InvalidArgumentException('monthly_data must be an array');
        }

        return true;
    }

    /**
     * Calculate growth rate between two periods
     * 
     * @param float $currentValue Current period value
     * @param float $previousValue Previous period value
     * @return float Growth rate as percentage
     */
    public static function calculateGrowthRate(float $currentValue, float $previousValue): float
    {
        if ($previousValue == 0) {
            return $currentValue > 0 ? 100.0 : 0.0;
        }

        return (($currentValue - $previousValue) / $previousValue) * 100;
    }

    /**
     * Calculate average from array of values
     * 
     * @param array $values Array of numeric values
     * @return float Average value
     */
    public static function calculateAverage(array $values): float
    {
        $numericValues = array_filter($values, 'is_numeric');
        
        if (empty($numericValues)) {
            return 0.0;
        }

        return array_sum($numericValues) / count($numericValues);
    }

    /**
     * Calculate median from array of values
     * 
     * @param array $values Array of numeric values
     * @return float Median value
     */
    public static function calculateMedian(array $values): float
    {
        $numericValues = array_filter($values, 'is_numeric');
        
        if (empty($numericValues)) {
            return 0.0;
        }

        sort($numericValues);
        $count = count($numericValues);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($numericValues[$middle - 1] + $numericValues[$middle]) / 2;
        } else {
            return $numericValues[$middle];
        }
    }

    /**
     * Calculate standard deviation
     * 
     * @param array $values Array of numeric values
     * @return float Standard deviation
     */
    public static function calculateStandardDeviation(array $values): float
    {
        $numericValues = array_filter($values, 'is_numeric');
        
        if (count($numericValues) < 2) {
            return 0.0;
        }

        $average = self::calculateAverage($numericValues);
        $squaredDifferences = array_map(function($value) use ($average) {
            return pow($value - $average, 2);
        }, $numericValues);

        $variance = array_sum($squaredDifferences) / (count($numericValues) - 1);
        return sqrt($variance);
    }
}