<?php

namespace App\Formatters\Strategies;

/**
 * Interface untuk semua formatter strategies
 * Menyediakan kontrak standar untuk implementasi strategy pattern
 */
interface FormatterStrategyInterface
{
    /**
     * Execute strategy dengan data dan options yang diberikan
     *
     * @param array $data Data yang akan diproses
     * @param array $options Options tambahan untuk processing
     * @return array Hasil processing
     */
    public function execute(array $data, array $options = []): array;

    /**
     * Validate input data sebelum processing
     *
     * @param array $data Data yang akan divalidasi
     * @param array $options Options untuk validasi
     * @return bool True jika valid
     * @throws \InvalidArgumentException Jika data tidak valid
     */
    public function validate(array $data, array $options = []): bool;

    /**
     * Get strategy name untuk logging dan debugging
     *
     * @return string Nama strategy
     */
    public function getName(): string;
}