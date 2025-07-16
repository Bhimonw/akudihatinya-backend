<?php

namespace App\Formatters\Builders;

use App\Constants\ExcelConstants;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

/**
 * Excel Style Builder Class
 * 
 * Implements Builder pattern for Excel styling operations.
 * Provides fluent interface for applying consistent styling across Excel sheets.
 */
class ExcelStyleBuilder
{
    private Worksheet $sheet;
    private array $appliedStyles = [];

    /**
     * Constructor
     * 
     * @param Worksheet $sheet The worksheet to apply styles to
     */
    public function __construct(Worksheet $sheet)
    {
        $this->sheet = $sheet;
    }

    /**
     * Create a new instance for fluent interface
     * 
     * @param Worksheet $sheet The worksheet to style
     * @return self
     */
    public static function create(Worksheet $sheet): self
    {
        return new self($sheet);
    }

    /**
     * Apply header styling (bold font)
     * 
     * @param string $range Cell range (e.g., 'A1:E1')
     * @param int $fontSize Font size (default from constants)
     * @return self
     */
    public function setHeaderStyle(string $range, int $fontSize = null): self
    {
        try {
            $fontSize = $fontSize ?? ExcelConstants::FONT_SIZES['HEADER'];
            
            $this->sheet->getStyle($range)
                ->getFont()
                ->setBold(true)
                ->setSize($fontSize);

            $this->appliedStyles[] = "Header style applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply header style', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Apply title styling (larger, bold font)
     * 
     * @param string $range Cell range
     * @param int $fontSize Font size (default from constants)
     * @return self
     */
    public function setTitleStyle(string $range, int $fontSize = null): self
    {
        try {
            $fontSize = $fontSize ?? ExcelConstants::FONT_SIZES['TITLE'];
            
            $this->sheet->getStyle($range)
                ->getFont()
                ->setBold(true)
                ->setSize($fontSize);

            $this->appliedStyles[] = "Title style applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply title style', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Apply footer styling (smaller, italic font)
     * 
     * @param string $range Cell range
     * @param int $fontSize Font size (default from constants)
     * @return self
     */
    public function setFooterStyle(string $range, int $fontSize = null): self
    {
        try {
            $fontSize = $fontSize ?? ExcelConstants::FONT_SIZES['FOOTER'];
            
            $this->sheet->getStyle($range)
                ->getFont()
                ->setItalic(true)
                ->setSize($fontSize);

            $this->appliedStyles[] = "Footer style applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply footer style', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Set background color for cells
     * 
     * @param string $range Cell range
     * @param string $color RGB hex color (without #)
     * @return self
     */
    public function setBackgroundColor(string $range, string $color = null): self
    {
        try {
            $color = $color ?? ExcelConstants::COLORS['HEADER_BACKGROUND'];
            
            $this->sheet->getStyle($range)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB($color);

            $this->appliedStyles[] = "Background color {$color} applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply background color', [
                'range' => $range,
                'color' => $color,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Apply borders to cells
     * 
     * @param string $range Cell range
     * @param string $borderStyle Border style (default: thin)
     * @return self
     */
    public function setBorders(string $range, string $borderStyle = Border::BORDER_THIN): self
    {
        try {
            $this->sheet->getStyle($range)
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle($borderStyle);

            $this->appliedStyles[] = "Borders applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply borders', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Apply outline borders (border around the range)
     * 
     * @param string $range Cell range
     * @param string $borderStyle Border style
     * @return self
     */
    public function setOutlineBorders(string $range, string $borderStyle = Border::BORDER_MEDIUM): self
    {
        try {
            $this->sheet->getStyle($range)
                ->getBorders()
                ->getOutline()
                ->setBorderStyle($borderStyle);

            $this->appliedStyles[] = "Outline borders applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply outline borders', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Set text alignment
     * 
     * @param string $range Cell range
     * @param string $horizontal Horizontal alignment
     * @param string $vertical Vertical alignment
     * @return self
     */
    public function setAlignment(string $range, string $horizontal = Alignment::HORIZONTAL_CENTER, string $vertical = Alignment::VERTICAL_CENTER): self
    {
        try {
            $this->sheet->getStyle($range)
                ->getAlignment()
                ->setHorizontal($horizontal)
                ->setVertical($vertical);

            $this->appliedStyles[] = "Alignment applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply alignment', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Set text wrapping
     * 
     * @param string $range Cell range
     * @param bool $wrap Whether to wrap text
     * @return self
     */
    public function setTextWrap(string $range, bool $wrap = true): self
    {
        try {
            $this->sheet->getStyle($range)
                ->getAlignment()
                ->setWrapText($wrap);

            $this->appliedStyles[] = "Text wrap applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply text wrap', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Auto-size columns
     * 
     * @param string $startColumn Starting column (e.g., 'A')
     * @param string $endColumn Ending column (e.g., 'Z')
     * @return self
     */
    public function autoSizeColumns(string $startColumn = 'A', string $endColumn = null): self
    {
        try {
            if ($endColumn === null) {
                $endColumn = $this->sheet->getHighestColumn();
            }

            $startIndex = Coordinate::columnIndexFromString($startColumn);
            $endIndex = Coordinate::columnIndexFromString($endColumn);

            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $this->sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $this->appliedStyles[] = "Auto-size applied to columns {$startColumn}:{$endColumn}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to auto-size columns', [
                'start_column' => $startColumn,
                'end_column' => $endColumn,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Set specific column width
     * 
     * @param string $column Column letter
     * @param float $width Column width
     * @return self
     */
    public function setColumnWidth(string $column, float $width): self
    {
        try {
            $this->sheet->getColumnDimension($column)->setWidth($width);
            
            $this->appliedStyles[] = "Column {$column} width set to {$width}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to set column width', [
                'column' => $column,
                'width' => $width,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Set row height
     * 
     * @param int $row Row number
     * @param float $height Row height
     * @return self
     */
    public function setRowHeight(int $row, float $height): self
    {
        try {
            $this->sheet->getRowDimension($row)->setRowHeight($height);
            
            $this->appliedStyles[] = "Row {$row} height set to {$height}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to set row height', [
                'row' => $row,
                'height' => $height,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Merge cells
     * 
     * @param string $range Cell range to merge (e.g., 'A1:C1')
     * @return self
     */
    public function mergeCells(string $range): self
    {
        try {
            $this->sheet->mergeCells($range);
            
            $this->appliedStyles[] = "Cells merged: {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to merge cells', [
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Apply number format
     * 
     * @param string $range Cell range
     * @param string $format Number format code
     * @return self
     */
    public function setNumberFormat(string $range, string $format): self
    {
        try {
            $this->sheet->getStyle($range)
                ->getNumberFormat()
                ->setFormatCode($format);

            $this->appliedStyles[] = "Number format applied to {$range}";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to apply number format', [
                'range' => $range,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return $this;
        }
    }

    /**
     * Apply percentage format
     * 
     * @param string $range Cell range
     * @param int $decimals Number of decimal places
     * @return self
     */
    public function setPercentageFormat(string $range, int $decimals = 2): self
    {
        $format = '0.' . str_repeat('0', $decimals) . '%';
        return $this->setNumberFormat($range, $format);
    }

    /**
     * Apply complete header styling (background, borders, font, alignment)
     * 
     * @param string $range Cell range
     * @param string $backgroundColor Background color
     * @param int $fontSize Font size
     * @return self
     */
    public function applyCompleteHeaderStyle(string $range, string $backgroundColor = null, int $fontSize = null): self
    {
        $backgroundColor = $backgroundColor ?? ExcelConstants::COLORS['HEADER_BACKGROUND'];
        $fontSize = $fontSize ?? ExcelConstants::FONT_SIZES['HEADER'];

        return $this
            ->setHeaderStyle($range, $fontSize)
            ->setBackgroundColor($range, $backgroundColor)
            ->setBorders($range)
            ->setAlignment($range);
    }

    /**
     * Apply complete total row styling
     * 
     * @param string $range Cell range
     * @param string $backgroundColor Background color
     * @return self
     */
    public function applyTotalRowStyle(string $range, string $backgroundColor = null): self
    {
        $backgroundColor = $backgroundColor ?? ExcelConstants::COLORS['TOTAL_ROW_BACKGROUND'];

        return $this
            ->setHeaderStyle($range)
            ->setBackgroundColor($range, $backgroundColor)
            ->setBorders($range)
            ->setAlignment($range);
    }

    /**
     * Apply data cell styling
     * 
     * @param string $range Cell range
     * @return self
     */
    public function applyDataCellStyle(string $range): self
    {
        return $this
            ->setBorders($range)
            ->setAlignment($range, Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
    }

    /**
     * Apply footer styling with left alignment
     * 
     * @param string $range Cell range
     * @return self
     */
    public function applyCompleteFooterStyle(string $range): self
    {
        return $this
            ->setFooterStyle($range)
            ->setAlignment($range, Alignment::HORIZONTAL_LEFT, Alignment::VERTICAL_CENTER);
    }

    /**
     * Reset auto-size for all columns (useful for performance)
     * 
     * @return self
     */
    public function resetAutoSize(): self
    {
        try {
            $highestColumn = $this->sheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            for ($i = 1; $i <= $highestColumnIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $this->sheet->getColumnDimension($col)->setAutoSize(false);
            }

            $this->appliedStyles[] = "Auto-size reset for all columns";
            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to reset auto-size', ['error' => $e->getMessage()]);
            return $this;
        }
    }

    /**
     * Get applied styles log
     * 
     * @return array Array of applied style descriptions
     */
    public function getAppliedStyles(): array
    {
        return $this->appliedStyles;
    }

    /**
     * Clear applied styles log
     * 
     * @return self
     */
    public function clearStylesLog(): self
    {
        $this->appliedStyles = [];
        return $this;
    }

    /**
     * Log all applied styles
     * 
     * @param string $context Context for logging
     * @return self
     */
    public function logAppliedStyles(string $context = 'Excel styling'): self
    {
        if (!empty($this->appliedStyles)) {
            Log::info($context, ['applied_styles' => $this->appliedStyles]);
        }
        
        return $this;
    }

    /**
     * Validate range format
     * 
     * @param string $range Cell range
     * @return bool
     * @throws InvalidArgumentException If range is invalid
     */
    private function validateRange(string $range): bool
    {
        if (empty($range)) {
            throw new InvalidArgumentException('Range cannot be empty');
        }

        // Basic validation for range format (A1 or A1:B2)
        if (!preg_match('/^[A-Z]+\d+(:[A-Z]+\d+)?$/', $range)) {
            throw new InvalidArgumentException("Invalid range format: {$range}");
        }

        return true;
    }
}