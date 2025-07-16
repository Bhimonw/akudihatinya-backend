# Formatter Strategies

Direktori ini berisi strategy patterns untuk memisahkan logika kompleks dari formatter utama.

## Struktur Direktori

```
Strategies/
├── DataFormatting/
│   ├── DashboardDataStrategy.php
│   ├── AdminDataStrategy.php
│   ├── ExcelDataStrategy.php
│   └── PdfDataStrategy.php
├── Export/
│   ├── ExcelExportStrategy.php
│   ├── PdfExportStrategy.php
│   └── CsvExportStrategy.php
├── Calculation/
│   ├── StatisticsCalculationStrategy.php
│   ├── PercentageCalculationStrategy.php
│   └── TotalCalculationStrategy.php
└── Validation/
    ├── InputValidationStrategy.php
    ├── DataValidationStrategy.php
    └── ExportValidationStrategy.php
```

## Tujuan Modularisasi

### 1. Pemisahan Tanggung Jawab
- **DataFormatting**: Menangani format data untuk berbagai output
- **Export**: Menangani logika ekspor ke berbagai format
- **Calculation**: Menangani perhitungan statistik dan persentase
- **Validation**: Menangani validasi input dan data

### 2. Mengurangi Kompleksitas
- Memecah metode besar menjadi strategy yang lebih kecil
- Mengurangi cyclomatic complexity
- Meningkatkan readability dan maintainability

### 3. Reusability
- Strategy dapat digunakan oleh multiple formatter
- Mengurangi duplikasi kode
- Memudahkan testing

## Implementasi Strategy Pattern

### Interface Strategy
```php
interface FormatterStrategyInterface
{
    public function execute(array $data, array $options = []): array;
}
```

### Context (Formatter)
```php
class StatisticsFormatter
{
    private $dataStrategy;
    private $calculationStrategy;
    
    public function setDataStrategy(DataFormattingStrategyInterface $strategy)
    {
        $this->dataStrategy = $strategy;
    }
    
    public function formatData(array $data, array $options = [])
    {
        return $this->dataStrategy->execute($data, $options);
    }
}
```

## Keuntungan

1. **Single Responsibility**: Setiap strategy memiliki satu tanggung jawab
2. **Open/Closed Principle**: Mudah menambah strategy baru tanpa mengubah kode existing
3. **Dependency Injection**: Strategy dapat di-inject untuk testing
4. **Performance**: Dapat mengoptimalkan strategy tertentu
5. **Maintainability**: Kode lebih mudah dipelihara dan di-debug