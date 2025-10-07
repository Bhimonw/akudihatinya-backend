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
        // In this formatter context, statistics are passed from callers; avoid coupling to service.
        return [];
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
            $lastIndex = $this->columnToIndex($highestColumn);

            for ($row = 1; $row <= $highestRow; $row++) {
                // Iterate columns using numeric index to avoid invalid coordinates
                for ($ci = 1; $ci <= $lastIndex; $ci++) {
                    $col = $this->indexToColumn($ci);
                    $cell = $sheet->getCell($col . $row);
                    $value = $cell->getValue();
                    // Siapkan regex map satu kali per sel
                    $regexMap = [
                        '/<\s*tahun\s*>/i' => (string)($replacements['{{YEAR}}'] ?? ''),
                        '/<\s*tipe[_\s]*penyakit\s*>/i' => (string)($replacements['{{DISEASE_TYPE}}'] ?? ''),
                        '/<\s*puskesmas\s*>/i' => (string)($replacements['{{PUSKESMAS_NAME}}'] ?? ''),
                        '/<\s*sasaran\s*>/i' => (string)($replacements['{{TARGET}}'] ?? 'Target Pencapaian'),
                        '/<\s*mulai\s*>/i' => (string)($this->getAngleBracketPlaceholders($replacements)['<mulai>'] ?? ''),
                        '/<\s*akhir\s*>/i' => (string)($this->getAngleBracketPlaceholders($replacements)['<akhir>'] ?? ''),
                    ];

                    if (is_string($value)) {
                        // Plain string replacement
                        foreach ($allReplacements as $placeholder => $replacement) {
                            $value = str_replace($placeholder, $replacement, $value);
                        }
                        foreach ($regexMap as $pattern => $rep) {
                            if ($rep !== '') {
                                $value = preg_replace($pattern, $rep, $value);
                            }
                        }
                        $cell->setValue($value);
                    } elseif ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        // Replace within RichText runs without losing formatting
                        foreach ($value->getRichTextElements() as $element) {
                            // TextElement or RichTextRun both have getText()/setText()
                            $text = method_exists($element, 'getText') ? $element->getText() : null;
                            if ($text === null) continue;
                            foreach ($allReplacements as $placeholder => $replacement) {
                                $text = str_replace($placeholder, $replacement, $text);
                            }
                            foreach ($regexMap as $pattern => $rep) {
                                if ($rep !== '') {
                                    $text = preg_replace($pattern, $rep, $text);
                                }
                            }
                            if (method_exists($element, 'setText')) {
                                $element->setText($text);
                            }
                        }
                        // Assign back to ensure recalculation
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
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
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
     * Ensure standard list/table headers exist and match the data columns used by formatters.
     * This writes header labels (No, Nama Puskesmas, Sasaran, Total, etc.) at the specified header row.
     */
    protected function ensureListHeaders(string $diseaseType, int $headerRow = 7): void
    {
        $sheet = $this->sheet;

        // Basic columns
        $sheet->setCellValue('A' . $headerRow, 'No');
        $sheet->setCellValue('B' . $headerRow, 'Nama Puskesmas');

        // HT columns
        $sheet->setCellValue('C' . $headerRow, 'Sasaran');
        $sheet->setCellValue('D' . $headerRow, 'Total');
        $sheet->setCellValue('E' . $headerRow, 'Terkendali');
        $sheet->setCellValue('F' . $headerRow, 'Tidak Terkendali');
        $sheet->setCellValue('G' . $headerRow, 'Laki-laki');
        $sheet->setCellValue('H' . $headerRow, 'Perempuan');
        $sheet->setCellValue('I' . $headerRow, '%S');

        // DM columns (offset when diseaseType is 'all')
        if ($diseaseType === 'all') {
            $sheet->setCellValue('J' . $headerRow, 'Sasaran DM');
            $sheet->setCellValue('K' . $headerRow, 'Total DM');
            $sheet->setCellValue('L' . $headerRow, 'Terkendali DM');
            $sheet->setCellValue('M' . $headerRow, 'Tidak Terkendali DM');
            $sheet->setCellValue('N' . $headerRow, 'Laki-laki DM');
            $sheet->setCellValue('O' . $headerRow, 'Perempuan DM');
            $sheet->setCellValue('P' . $headerRow, '%S DM');
        } else {
            // If only DM or only HT will be handled by specific formatters, but write a minimal set for DM
            if ($diseaseType === 'dm') {
                $sheet->setCellValue('C' . $headerRow, 'Sasaran');
                $sheet->setCellValue('D' . $headerRow, 'Total');
                $sheet->setCellValue('E' . $headerRow, 'Terkendali');
                $sheet->setCellValue('F' . $headerRow, 'Tidak Terkendali');
                $sheet->setCellValue('G' . $headerRow, 'Laki-laki');
                $sheet->setCellValue('H' . $headerRow, 'Perempuan');
                $sheet->setCellValue('I' . $headerRow, '%S');
            }
        }
    }

    /**
     * Ensure headers for puskesmas detail/patient lists match the columns used by PuskesmasFormatter.
     */
    protected function ensurePuskesmasHeaders(int $headerRow = 7): void
    {
        $sheet = $this->sheet;

        $sheet->setCellValue('A' . $headerRow, 'No');
        $sheet->setCellValue('B' . $headerRow, 'Nama Pasien');
        $sheet->setCellValue('C' . $headerRow, 'NIK');
        $sheet->setCellValue('D' . $headerRow, 'Umur');
        $sheet->setCellValue('E' . $headerRow, 'Jenis Kelamin');
        $sheet->setCellValue('F' . $headerRow, 'Alamat');
        $sheet->setCellValue('G' . $headerRow, 'Tanggal Kunjungan');

        // HT/DM patient columns start at H
        $sheet->setCellValue('H' . $headerRow, 'H - Kol1');
        $sheet->setCellValue('I' . $headerRow, 'H - Kol2');
        $sheet->setCellValue('J' . $headerRow, 'H - Status');
        $sheet->setCellValue('K' . $headerRow, 'H - Medication');
        $sheet->setCellValue('L' . $headerRow, 'Keterangan');
    }

    /**
     * Increment Excel column label (A -> B, Z -> AA, etc.)
     */
    protected function incrementColumn(string $column): string
    {
        // Use PHP string increment which correctly advances Excel-like column labels
        ++$column;
        return $column;
    }

    /**
     * Convert Excel column label (e.g., 'A', 'Z', 'AA') to 1-based index.
     */
    protected function columnToIndex(string $column): int
    {
        $column = strtoupper($column);
        $len = strlen($column);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index;
    }

    /**
     * Return true if column A is after column B (A > B)
     */
    protected function isColumnAfter(string $a, string $b): bool
    {
        return $this->columnToIndex($a) > $this->columnToIndex($b);
    }

    /**
     * Find the last data column by scanning header rows for candidate labels.
     * Returns the column letter or null if not found.
     */
    protected function getLastDataColumnByHeaders(array $candidates, int $rowStart = 1, int $rowEnd = 12): ?string
    {
        $sheet = $this->sheet;
        $highestColumn = $sheet->getHighestColumn();
        $lastIndex = $this->columnToIndex($highestColumn);
        $foundCol = null;
        // Normalisasi kandidat: hapus semua non-alfanumerik agar tahan terhadap spasi/baris baru
        $normCands = array_map(function ($s) {
            $s = strtolower($s);
            $s = preg_replace('/[^a-z0-9]/i', '', $s);
            return $s;
        }, $candidates);
        for ($row = $rowStart; $row <= $rowEnd; $row++) {
            for ($ci = 1; $ci <= $lastIndex; $ci++) {
                $col = $this->indexToColumn($ci);
                $val = $sheet->getCell($col . $row)->getValue();
                // Ambil teks plain jika RichText
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                }
                if (!is_string($val)) continue;
                $normVal = strtolower($val);
                $normVal = preg_replace('/[^a-z0-9]/i', '', $normVal);
                foreach ($normCands as $nc) {
                    if ($nc !== '' && (strpos($normVal, $nc) !== false || strpos($nc, $normVal) !== false)) {
                        if ($foundCol === null || $this->isColumnAfter($col, $foundCol)) {
                            $foundCol = $col;
                        }
                    }
                }
            }
        }
        if ($foundCol !== null) return $foundCol;
        // Fallback: cari kolom terakhir yang ada isian pada rentang baris header
        $lastNonEmpty = null;
        for ($row = $rowStart; $row <= $rowEnd; $row++) {
            $rowLast = null;
            for ($ci = 1; $ci <= $lastIndex; $ci++) {
                $col = $this->indexToColumn($ci);
                $val = $sheet->getCell($col . $row)->getValue();
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                }
                if ($val !== null && $val !== '') {
                    if ($rowLast === null || $this->isColumnAfter($col, $rowLast)) {
                        $rowLast = $col;
                    }
                }
            }
            if ($rowLast !== null && ($lastNonEmpty === null || $this->isColumnAfter($rowLast, $lastNonEmpty))) {
                $lastNonEmpty = $rowLast;
            }
        }
        return $lastNonEmpty; // Bisa null jika header benar-benar kosong
    }

    /**
     * Check if any of the header labels exists within the header scan rows.
     */
    protected function hasHeaderLabel(array $labels, int $rowStart = 1, int $rowEnd = 12): bool
    {
        $sheet = $this->sheet;
        $highestColumn = $sheet->getHighestColumn();
        $lastIndex = $this->columnToIndex($highestColumn);
        $normLabels = array_map(function ($s) {
            $s = strtolower($s);
            $s = preg_replace('/[^a-z0-9]/i', '', $s);
            return $s;
        }, $labels);
        for ($row = $rowStart; $row <= $rowEnd; $row++) {
            for ($ci = 1; $ci <= $lastIndex; $ci++) {
                $col = $this->indexToColumn($ci);
                $val = $sheet->getCell($col . $row)->getValue();
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                }
                if (!is_string($val) || $val === '') continue;
                $normVal = preg_replace('/[^a-z0-9]/i', '', strtolower($val));
                foreach ($normLabels as $nl) {
                    if ($nl !== '' && (strpos($normVal, $nl) !== false || strpos($nl, $normVal) !== false)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Convert 1-based column index to Excel column label (e.g., 1 -> 'A', 27 -> 'AA').
     */
    protected function indexToColumn(int $index): string
    {
        $column = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $index = intdiv($index - 1, 26);
        }
        return $column;
    }

    /**
     * Safely set a cell value only if within the allowed column bound.
     * If $lastAllowedCol is null, the value is always set.
     */
    protected function safeSet(string $col, int $row, $value, ?string $lastAllowedCol = null): void
    {
        if ($lastAllowedCol !== null && $this->isColumnAfter($col, $lastAllowedCol)) {
            return; // Do not write beyond template area
        }
        $this->sheet->setCellValue($col . $row, $value);
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
