<?php

namespace App\Traits\Calculation;

/**
 * Trait untuk perhitungan persentase yang konsisten
 * Menyediakan method standar untuk berbagai jenis perhitungan persentase
 */
trait PercentageCalculationTrait
{
    /**
     * Calculate achievement percentage allowing values above 100%
     * Digunakan untuk kasus di mana pencapaian bisa melebihi target (>100%)
     * 
     * @param float|int $numerator Nilai pencapaian
     * @param float|int $denominator Target/sasaran
     * @param int $decimals Jumlah desimal
     * @return float Persentase pencapaian (bisa >100%)
     */
    protected function calculateAchievementPercentage($numerator, $denominator, int $decimals = 2): float
    {
        if ($denominator == 0) {
            return 0;
        }

        $percentage = round(($numerator / $denominator) * 100, $decimals);

        // Hanya pastikan tidak negatif, tapi izinkan >100%
        return max(0, $percentage);
    }

    /**
     * Calculate standard percentage constrained to 0-100%
     * Digunakan untuk persentase yang secara logis tidak bisa melebihi 100%
     * (contoh: persentase pasien standar dari total pasien)
     * 
     * @param float|int $numerator Nilai yang dihitung
     * @param float|int $denominator Total nilai
     * @param int $decimals Jumlah desimal
     * @return float Persentase (0-100%)
     */
    protected function calculateStandardPercentage($numerator, $denominator, int $decimals = 2): float
    {
        if ($denominator == 0) {
            return 0;
        }

        $percentage = round(($numerator / $denominator) * 100, $decimals);

        // Pastikan dalam range 0-100%
        return max(0, min(100, $percentage));
    }

    /**
     * Calculate percentage with custom constraints
     * Untuk kasus khusus dengan batasan tertentu
     * 
     * @param float|int $numerator Nilai yang dihitung
     * @param float|int $denominator Total nilai
     * @param float $minValue Nilai minimum
     * @param float|null $maxValue Nilai maksimum (null = tidak ada batas)
     * @param int $decimals Jumlah desimal
     * @return float Persentase dengan batasan
     */
    protected function calculateConstrainedPercentage($numerator, $denominator, float $minValue = 0, ?float $maxValue = null, int $decimals = 2): float
    {
        if ($denominator == 0) {
            return 0;
        }

        $percentage = round(($numerator / $denominator) * 100, $decimals);

        // Terapkan batasan minimum
        $percentage = max($minValue, $percentage);

        // Terapkan batasan maksimum jika ada
        if ($maxValue !== null) {
            $percentage = min($maxValue, $percentage);
        }

        return $percentage;
    }

    /**
     * Format percentage for display
     * Memformat persentase untuk ditampilkan dengan simbol %
     * 
     * @param float $percentage Nilai persentase
     * @param bool $includeSymbol Apakah menyertakan simbol %
     * @return string Persentase terformat
     */
    protected function formatPercentage(float $percentage, bool $includeSymbol = true): string
    {
        $formatted = number_format($percentage, 2, '.', '');

        return $includeSymbol ? $formatted . '%' : $formatted;
    }

    /**
     * Calculate multiple percentages at once
     * Menghitung beberapa persentase sekaligus untuk efisiensi
     * 
     * @param array $data Array dengan key 'numerator' dan 'denominator'
     * @param string $type Tipe perhitungan: 'achievement', 'standard', atau 'constrained'
     * @param array $constraints Batasan untuk tipe 'constrained'
     * @param int $decimals Jumlah desimal
     * @return array Array persentase hasil perhitungan
     */
    protected function calculateMultiplePercentages(array $data, string $type = 'achievement', array $constraints = [], int $decimals = 2): array
    {
        $results = [];

        foreach ($data as $key => $values) {
            $numerator = $values['numerator'] ?? 0;
            $denominator = $values['denominator'] ?? 0;

            switch ($type) {
                case 'standard':
                    $results[$key] = $this->calculateStandardPercentage($numerator, $denominator, $decimals);
                    break;
                case 'constrained':
                    $minValue = $constraints['min'] ?? 0;
                    $maxValue = $constraints['max'] ?? null;
                    $results[$key] = $this->calculateConstrainedPercentage($numerator, $denominator, $minValue, $maxValue, $decimals);
                    break;
                case 'achievement':
                default:
                    $results[$key] = $this->calculateAchievementPercentage($numerator, $denominator, $decimals);
                    break;
            }
        }

        return $results;
    }
}
