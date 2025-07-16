<?php

require_once 'vendor/autoload.php';

// Test percentage calculation with monthly target
echo "Testing Percentage Calculation with Monthly Target:\n";
echo "================================================\n";

// Data from the image (first few rows)
$testData = [
    ['standard' => 68, 'yearly_target' => 1121, 'expected_percentage' => '0.60%'],
    ['standard' => 8, 'yearly_target' => 1121, 'expected_percentage' => '0.71%'],
    ['standard' => 82, 'yearly_target' => 1121, 'expected_percentage' => '0.42%'],
    ['standard' => 338, 'yearly_target' => 1121, 'expected_percentage' => '1.95%'],
    ['standard' => 310, 'yearly_target' => 1121, 'expected_percentage' => '2.58%'],
    ['standard' => 87, 'yearly_target' => 1121, 'expected_percentage' => '0.35%'],
    ['standard' => 61, 'yearly_target' => 1121, 'expected_percentage' => '0.35%'],
    ['standard' => 87, 'yearly_target' => 1121, 'expected_percentage' => '0.42%']
];

foreach ($testData as $index => $data) {
    $monthlyTarget = $data['yearly_target'] / 12;
    $calculated = round(($data['standard'] / $monthlyTarget) * 100, 2);
    echo "Row " . ($index + 1) . ": Standard={$data['standard']}, Monthly Target=" . round($monthlyTarget, 2) . "\n";
    echo "  Calculated: {$calculated}%\n";
    echo "  Expected: {$data['expected_percentage']}\n";
    $expected = floatval(str_replace('%', '', $data['expected_percentage']));
    echo "  Match: " . (abs($calculated - $expected) < 0.1 ? 'YES' : 'NO') . "\n";
    echo "\n";
}

// Test with total from image
echo "Total Calculation (should be sum of all monthly percentages):\n";
echo "Total Standard: 1121, Yearly Target: 1121\n";
$totalPercentage = round((1121 / 1121) * 100, 2);
echo "Yearly Achievement: {$totalPercentage}%\n";
echo "Expected from image: 0.79% (this seems incorrect)\n";
echo "\n";

echo "Analysis:\n";
echo "After fixing the calculation to use monthly targets:\n";
echo "- Monthly percentages should be calculated against monthly target (yearly/12)\n";
echo "- This gives more meaningful percentages for monthly achievement\n";
echo "- The sum of monthly achievements should relate to yearly achievement\n";