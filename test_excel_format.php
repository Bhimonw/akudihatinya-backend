<?php

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

echo "Testing Excel Format Improvements...\n";

// Test 1: Monthly Report Format
echo "\n1. Testing Monthly Report Format...\n";
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set title
$sheet->setCellValue('A1', 'LAPORAN BULANAN STATISTIK HIPERTENSI DAN DIABETES MELLITUS');
$sheet->mergeCells('A1:M1');

// Set headers
$headers = ['No', 'Nama Puskesmas', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'];
$headerCols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
foreach ($headers as $col => $header) {
    $sheet->setCellValue($headerCols[$col] . '3', $header);
}

// Add sample data
for ($row = 4; $row <= 8; $row++) {
    $sheet->setCellValue("A{$row}", $row - 3);
    $sheet->setCellValue("B{$row}", "Puskesmas " . ($row - 3));
    $dataCols = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
    foreach ($dataCols as $col) {
        $sheet->setCellValue($col . $row, rand(10, 100));
    }
}

// Apply styling
$lastRow = 8;
$lastCol = 'O'; // Column O = 15

// Title styling
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Header styling
$sheet->getStyle('A3:' . $lastCol . '3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6E6FA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
]);

// Data area borders (only statistics area)
$sheet->getStyle('A3:' . $lastCol . $lastRow)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// Set optimal column widths
$columnWidths = ['A' => 12, 'B' => 25, 'C' => 8, 'D' => 8, 'E' => 8, 'F' => 8, 'G' => 8, 'H' => 8, 'I' => 8, 'J' => 8, 'K' => 8, 'L' => 8, 'M' => 8, 'N' => 8, 'O' => 10];
foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Set row heights
$sheet->getRowDimension(1)->setRowHeight(25);
$sheet->getRowDimension(3)->setRowHeight(20);

// Set page margins and orientation
$sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

// Add footer
$footerRow = $lastRow + 3;
$sheet->setCellValue("A{$footerRow}", 'Tanggal Pembuatan: ' . date('d/m/Y H:i:s'));
$sheet->setCellValue("A" . ($footerRow + 1), 'Sistem: Akudihatinya Backend');
$sheet->setCellValue("A" . ($footerRow + 2), 'Keterangan: L/P = Laki-laki/Perempuan, TS/%S = Target/Persentase Standar');

// Footer styling (no borders)
$sheet->getStyle("A{$footerRow}:A" . ($footerRow + 2))->applyFromArray([
    'font' => ['size' => 9],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]]
]);

$writer = new Xlsx($spreadsheet);
$writer->save('test_monthly_format.xlsx');
echo "✅ Monthly format created: test_monthly_format.xlsx\n";

// Test 2: Quarterly Report Format
echo "\n2. Testing Quarterly Report Format...\n";
$spreadsheet2 = new Spreadsheet();
$sheet2 = $spreadsheet2->getActiveSheet();

// Set title
$sheet2->setCellValue('A1', 'LAPORAN TRIWULANAN STATISTIK HIPERTENSI DAN DIABETES MELLITUS');
$sheet2->mergeCells('A1:G1');

// Set headers
$headers2 = ['No', 'Nama Puskesmas', 'TW I', 'TW II', 'TW III', 'TW IV', 'Total'];
$headerCols2 = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
foreach ($headers2 as $col => $header) {
    $sheet2->setCellValue($headerCols2[$col] . '3', $header);
}

// Add sample data
for ($row = 4; $row <= 8; $row++) {
    $sheet2->setCellValue("A{$row}", $row - 3);
    $sheet2->setCellValue("B{$row}", "Puskesmas " . ($row - 3));
    $dataCols2 = ['C', 'D', 'E', 'F', 'G'];
    foreach ($dataCols2 as $col) {
        $sheet2->setCellValue($col . $row, rand(50, 300));
    }
}

// Apply styling for quarterly
$lastRow2 = 8;
$lastCol2 = 'G';

// Title styling
$sheet2->getStyle('A1:' . $lastCol2 . '1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Header styling
$sheet2->getStyle('A3:' . $lastCol2 . '3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6E6FA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
]);

// Data area borders (only statistics area)
$sheet2->getStyle('A3:' . $lastCol2 . $lastRow2)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// Set optimal column widths for quarterly
$columnWidths2 = ['A' => 12, 'B' => 25, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12];
foreach ($columnWidths2 as $col => $width) {
    $sheet2->getColumnDimension($col)->setWidth($width);
}

// Set row heights
$sheet2->getRowDimension(1)->setRowHeight(25);
$sheet2->getRowDimension(3)->setRowHeight(20);

// Set page margins and orientation
$sheet2->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
$sheet2->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

// Add footer
$footerRow2 = $lastRow2 + 3;
$sheet2->setCellValue("A{$footerRow2}", 'Tanggal Pembuatan: ' . date('d/m/Y H:i:s'));
$sheet2->setCellValue("A" . ($footerRow2 + 1), 'Sistem: Akudihatinya Backend');
$sheet2->setCellValue("A" . ($footerRow2 + 2), 'Keterangan: TW = Triwulan, L/P = Laki-laki/Perempuan');

// Footer styling (no borders)
$sheet2->getStyle("A{$footerRow2}:A" . ($footerRow2 + 2))->applyFromArray([
    'font' => ['size' => 9],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]]
]);

$writer2 = new Xlsx($spreadsheet2);
$writer2->save('test_quarterly_format.xlsx');
echo "✅ Quarterly format created: test_quarterly_format.xlsx\n";

echo "\n🎉 Test completed! Check the generated Excel files:\n";
echo "- test_monthly_format.xlsx\n";
echo "- test_quarterly_format.xlsx\n";
echo "\nFormat improvements applied:\n";
echo "✅ Proper table borders (only statistics area)\n";
echo "✅ Optimal column widths\n";
echo "✅ Consistent row heights\n";
echo "✅ Proper page margins (0.5 inch)\n";
echo "✅ Landscape orientation\n";
echo "✅ Clean footer without borders\n";
echo "✅ No extra tables outside statistics area\n";