<?php

namespace App\Formatters\Helpers;

use App\Constants\ExcelConstants;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use InvalidArgumentException;

/**
 * Column Manager Helper Class
 * 
 * Handles Excel column operations and mappings for different report types.
 * This class centralizes column-related logic to improve maintainability.
 */
class ColumnManager
{
    /**
     * Get column letters for a specific month
     * 
     * @param int $month Month number (1-12)
     * @return array Array of column letters
     * @throws InvalidArgumentException If month is invalid
     */
    public static function getMonthColumns(int $month): array
    {
        if (!ExcelConstants::isValidMonth($month)) {
            throw new InvalidArgumentException("Invalid month: {$month}. Must be between 1 and 12.");
        }
        
        return ExcelConstants::getMonthColumns($month);
    }

    /**
     * Get column letters for a specific quarter
     * 
     * @param int $quarter Quarter number (1-4)
     * @return array Array of column letters
     * @throws InvalidArgumentException If quarter is invalid
     */
    public static function getQuarterColumns(int $quarter): array
    {
        if (!ExcelConstants::isValidQuarter($quarter)) {
            throw new InvalidArgumentException("Invalid quarter: {$quarter}. Must be between 1 and 4.");
        }
        
        return ExcelConstants::getQuarterColumns($quarter);
    }

    /**
     * Get total columns for reports
     * 
     * @return array Array of total column letters
     */
    public static function getTotalColumns(): array
    {
        return ExcelConstants::TOTAL_COLUMNS;
    }

    /**
     * Get quarter-only columns
     * 
     * @return array Array of quarter-only column letters
     */
    public static function getQuarterOnlyColumns(): array
    {
        return ExcelConstants::QUARTER_ONLY_COLUMNS;
    }

    /**
     * Get quarter total columns
     * 
     * @return array Array of quarter total column letters
     */
    public static function getQuarterTotalColumns(): array
    {
        return ExcelConstants::QUARTER_TOTAL_COLUMNS;
    }

    /**
     * Increment column letter by specified amount
     * 
     * @param string $column Starting column letter (e.g., 'A', 'AB')
     * @param int $increment Number of columns to increment
     * @return string New column letter
     * @throws InvalidArgumentException If column is invalid
     */
    public static function incrementColumn(string $column, int $increment = 1): string
    {
        try {
            $columnIndex = Coordinate::columnIndexFromString($column);
            $newColumnIndex = $columnIndex + $increment;
            
            if ($newColumnIndex < 1) {
                throw new InvalidArgumentException("Column increment results in invalid column index: {$newColumnIndex}");
            }
            
            return Coordinate::stringFromColumnIndex($newColumnIndex);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid column: {$column}. Error: " . $e->getMessage());
        }
    }

    /**
     * Decrement column letter by specified amount
     * 
     * @param string $column Starting column letter
     * @param int $decrement Number of columns to decrement
     * @return string New column letter
     * @throws InvalidArgumentException If column is invalid or results in invalid index
     */
    public static function decrementColumn(string $column, int $decrement = 1): string
    {
        return self::incrementColumn($column, -$decrement);
    }

    /**
     * Get column range string (e.g., 'A1:E10')
     * 
     * @param string $startColumn Starting column letter
     * @param string $endColumn Ending column letter
     * @param int $startRow Starting row number
     * @param int $endRow Ending row number
     * @return string Range string
     */
    public static function getColumnRange(string $startColumn, string $endColumn, int $startRow, int $endRow): string
    {
        return "{$startColumn}{$startRow}:{$endColumn}{$endRow}";
    }

    /**
     * Get all columns for a specific report type
     * 
     * @param string $reportType Type of report ('all', 'monthly', 'quarterly', 'puskesmas')
     * @return array Array of all relevant columns for the report type
     * @throws InvalidArgumentException If report type is invalid
     */
    public static function getAllColumnsForReportType(string $reportType): array
    {
        if (!ExcelConstants::isValidReportType($reportType)) {
            throw new InvalidArgumentException("Invalid report type: {$reportType}");
        }

        $columns = [];

        switch ($reportType) {
            case 'all':
                // Include all month columns + totals
                for ($month = 1; $month <= 12; $month++) {
                    $columns = array_merge($columns, self::getMonthColumns($month));
                }
                $columns = array_merge($columns, self::getTotalColumns());
                break;

            case 'monthly':
                // Include all month columns + totals
                for ($month = 1; $month <= 12; $month++) {
                    $columns = array_merge($columns, self::getMonthColumns($month));
                }
                $columns = array_merge($columns, self::getTotalColumns());
                break;

            case 'quarterly':
                // Include all quarter columns + quarter totals + yearly totals
                for ($quarter = 1; $quarter <= 4; $quarter++) {
                    $columns = array_merge($columns, self::getQuarterColumns($quarter));
                }
                $columns = array_merge($columns, self::getQuarterOnlyColumns());
                $columns = array_merge($columns, self::getQuarterTotalColumns());
                break;

            case 'puskesmas':
                // Include basic columns for puskesmas data
                $columns = ['A', 'B', 'C']; // NO, NAMA PUSKESMAS, SASARAN
                break;
        }

        return array_unique($columns);
    }

    /**
     * Get column index from column letter
     * 
     * @param string $column Column letter
     * @return int Column index (1-based)
     * @throws InvalidArgumentException If column is invalid
     */
    public static function getColumnIndex(string $column): int
    {
        try {
            return Coordinate::columnIndexFromString($column);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid column: {$column}. Error: " . $e->getMessage());
        }
    }

    /**
     * Get column letter from column index
     * 
     * @param int $index Column index (1-based)
     * @return string Column letter
     * @throws InvalidArgumentException If index is invalid
     */
    public static function getColumnLetter(int $index): string
    {
        if ($index < 1) {
            throw new InvalidArgumentException("Invalid column index: {$index}. Must be greater than 0.");
        }

        try {
            return Coordinate::stringFromColumnIndex($index);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid column index: {$index}. Error: " . $e->getMessage());
        }
    }

    /**
     * Check if column exists in a specific month's columns
     * 
     * @param string $column Column letter to check
     * @param int $month Month number (1-12)
     * @return bool True if column exists in month's columns
     */
    public static function isColumnInMonth(string $column, int $month): bool
    {
        if (!ExcelConstants::isValidMonth($month)) {
            return false;
        }

        $monthColumns = self::getMonthColumns($month);
        return in_array($column, $monthColumns);
    }

    /**
     * Check if column exists in a specific quarter's columns
     * 
     * @param string $column Column letter to check
     * @param int $quarter Quarter number (1-4)
     * @return bool True if column exists in quarter's columns
     */
    public static function isColumnInQuarter(string $column, int $quarter): bool
    {
        if (!ExcelConstants::isValidQuarter($quarter)) {
            return false;
        }

        $quarterColumns = self::getQuarterColumns($quarter);
        return in_array($column, $quarterColumns);
    }

    /**
     * Get the last column for a specific report type
     * 
     * @param string $reportType Type of report
     * @return string Last column letter
     * @throws InvalidArgumentException If report type is invalid
     */
    public static function getLastColumnForReportType(string $reportType): string
    {
        $allColumns = self::getAllColumnsForReportType($reportType);
        
        if (empty($allColumns)) {
            throw new InvalidArgumentException("No columns found for report type: {$reportType}");
        }

        // Convert to indices, find max, convert back to letter
        $indices = array_map([self::class, 'getColumnIndex'], $allColumns);
        $maxIndex = max($indices);
        
        return self::getColumnLetter($maxIndex);
    }

    /**
     * Generate column sequence from start to end
     * 
     * @param string $startColumn Starting column letter
     * @param string $endColumn Ending column letter
     * @return array Array of column letters in sequence
     * @throws InvalidArgumentException If columns are invalid or start > end
     */
    public static function getColumnSequence(string $startColumn, string $endColumn): array
    {
        $startIndex = self::getColumnIndex($startColumn);
        $endIndex = self::getColumnIndex($endColumn);

        if ($startIndex > $endIndex) {
            throw new InvalidArgumentException("Start column ({$startColumn}) must be before end column ({$endColumn})");
        }

        $sequence = [];
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $sequence[] = self::getColumnLetter($i);
        }

        return $sequence;
    }
}