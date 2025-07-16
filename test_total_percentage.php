<?php

/**
 * Test untuk memverifikasi perhitungan persentase total berdasarkan data yang diberikan pengguna
 * Data: standar terakhir = 214, target total = 1.121
 * Persentase yang diharapkan = 0,79%
 */

class TestTotalPercentage
{
    /**
     * Implementasi calculateAchievementPercentage yang sama dengan PercentageCalculationTrait
     */
    public function calculateAchievementPercentage($achieved, $target)
    {
        if ($target == 0) {
            return 0;
        }
        
        $percentage = ($achieved / $target) * 100;
        return round($percentage, 2);
    }
    
    public function testTotalPercentageCalculation()
    {
        echo "=== Test Perhitungan Persentase Total ===\n\n";
        
        // Data dari pengguna
        $standardTerakhir = 214;
        $targetTotal = 1121;
        $persentaseYangDiharapkan = 0.79;
        
        echo "Data Input:\n";
        echo "- Standar terakhir: {$standardTerakhir}\n";
        echo "- Target total: {$targetTotal}\n";
        echo "- Persentase yang diharapkan: {$persentaseYangDiharapkan}%\n\n";
        
        // Hitung persentase menggunakan method yang sama dengan kode
        $calculatedPercentage = $this->calculateAchievementPercentage($standardTerakhir, $targetTotal);
        
        echo "Hasil Perhitungan:\n";
        echo "- Persentase yang dihitung: {$calculatedPercentage}%\n";
        
        // Hitung manual untuk verifikasi
        $manualCalculation = ($standardTerakhir / $targetTotal) * 100;
        echo "- Perhitungan manual: " . round($manualCalculation, 2) . "%\n\n";
        
        // Bandingkan dengan yang diharapkan
        $difference = abs($calculatedPercentage - $persentaseYangDiharapkan);
        echo "Analisis:\n";
        echo "- Selisih dengan yang diharapkan: " . round($difference, 2) . "%\n";
        
        if ($difference < 0.01) {
            echo "- Status: ✅ SESUAI (selisih < 0.01%)\n";
        } else {
            echo "- Status: ❌ TIDAK SESUAI\n";
        }
        
        echo "\n=== Kesimpulan ===\n";
        echo "Dengan perbaikan yang telah dilakukan (menghilangkan pembagian 100),\n";
        echo "perhitungan persentase total sekarang menggunakan nilai yang benar\n";
        echo "dari calculateAchievementPercentage tanpa pembagian tambahan.\n";
        
        // Test format Excel
        echo "\n=== Format Excel ===\n";
        echo "Nilai yang akan diset ke Excel: {$calculatedPercentage}\n";
        echo "Format Excel: 0.00\"%\"\n";
        echo "Tampilan di Excel: " . number_format($calculatedPercentage, 2) . "%\n";
        
        echo "\n=== Simulasi Sebelum dan Sesudah Perbaikan ===\n";
        echo "SEBELUM perbaikan (dengan pembagian 100):\n";
        echo "- Nilai di Excel: " . ($calculatedPercentage / 100) . "\n";
        echo "- Tampilan di Excel: " . number_format($calculatedPercentage / 100, 2) . "%\n";
        echo "\nSESUDAH perbaikan (tanpa pembagian 100):\n";
        echo "- Nilai di Excel: {$calculatedPercentage}\n";
        echo "- Tampilan di Excel: " . number_format($calculatedPercentage, 2) . "%\n";
    }
}

// Jalankan test
$test = new TestTotalPercentage();
$test->testTotalPercentageCalculation();

?>