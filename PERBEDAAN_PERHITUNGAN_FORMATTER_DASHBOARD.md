# Perbedaan Perhitungan Persentase: Formatter vs Dashboard

## Masalah yang Ditemukan

Terdapat **perbedaan fundamental** dalam cara perhitungan persentase antara:
1. **Formatter Excel** (AdminAllFormatter.php)
2. **Dashboard/API** (StatisticsController.php)

## Detail Perbedaan

### 1. AdminAllFormatter.php (Excel Export)

#### Perhitungan Persentase Bulanan:
```php
// Baris 575-580
$percentageStandard = $monthData['total'] > 0 ? 
    round(($monthData['standard'] / $monthData['total']) * 100, 2) : 0;
```

**Formula**: `(standard / total) × 100`
- **Tujuan**: Persentase pasien standar dari total pasien yang diperiksa
- **Contoh**: Jika 80 pasien standar dari 100 total pasien = 80%

#### Perhitungan Persentase Tahunan:
```php
// Baris 433-436
$achievementPercentage = $sasaran > 0 ? 
    round(($yearlyTotal['standard'] / $sasaran) * 100, 2) : 0;
```

**Formula**: `(standard / sasaran_tahunan) × 100`
- **Tujuan**: Persentase pencapaian target tahunan

### 2. StatisticsController.php (Dashboard/API)

#### Perhitungan Achievement Percentage:
```php
// Baris 214 & 250
'achievement_percentage' => $htTarget > 0 ? 
    round(((int)$htData['summary']['standard'] / $htTarget) * 100, 2) : 0
```

**Formula**: `(standard / target_tahunan) × 100`
- **Tujuan**: Persentase pencapaian target tahunan

#### Perhitungan Persentase Bulanan:
```php
// Baris 420
'percentage' => $yearlyTarget > 0 ? 
    round(((int)$monthData['standard'] / $yearlyTarget) * 100, 2) : 0
```

**Formula**: `(standard_bulanan / target_tahunan) × 100`
- **Tujuan**: Persentase pencapaian bulanan terhadap target tahunan

## Analisis Masalah

### Inkonsistensi yang Terjadi:

1. **Persentase Bulanan**:
   - **Formatter**: `standard/total` (persentase kualitas pelayanan)
   - **Dashboard**: `standard/yearly_target` (persentase pencapaian target)

2. **Interpretasi Berbeda**:
   - **Formatter**: Fokus pada kualitas pelayanan (berapa % pasien mendapat pelayanan standar)
   - **Dashboard**: Fokus pada pencapaian target (berapa % target tahunan tercapai)

## Dampak Masalah

1. **Kebingungan Pengguna**: Data yang sama menampilkan persentase berbeda
2. **Inkonsistensi Laporan**: Excel dan dashboard menunjukkan angka berbeda
3. **Kesalahan Interpretasi**: Stakeholder bisa salah memahami performa

## Solusi yang Direkomendasikan

### Opsi 1: Standarisasi ke Dashboard (Direkomendasikan)

**Ubah AdminAllFormatter.php** agar konsisten dengan dashboard:

```php
// Untuk persentase bulanan - gunakan target tahunan
$monthlyPercentage = $yearlyTarget > 0 ? 
    round(($monthData['standard'] / $yearlyTarget) * 100, 2) : 0;

// Untuk persentase triwulan - gunakan target tahunan
$quarterPercentage = $yearlyTarget > 0 ? 
    round(($quarterTotal['standard'] / $yearlyTarget) * 100, 2) : 0;
```

### Opsi 2: Tambahkan Kolom Terpisah

**Tambahkan dua jenis persentase** di Excel:
1. **% Kualitas**: `standard/total` (persentase pasien standar)
2. **% Pencapaian**: `standard/target` (persentase pencapaian target)

### Opsi 3: Standarisasi ke Formatter

**Ubah StatisticsController.php** untuk menggunakan perhitungan kualitas:

```php
// Untuk persentase bulanan
'percentage' => $monthData['total'] > 0 ? 
    round(((int)$monthData['standard'] / (int)$monthData['total']) * 100, 2) : 0
```

## Rekomendasi Implementasi

### Langkah 1: Pilih Standar
**Gunakan Opsi 1** - standarisasi ke dashboard karena:
- Dashboard adalah sumber utama monitoring
- Pencapaian target lebih relevan untuk manajemen
- Konsisten dengan tujuan sistem monitoring PTM

### Langkah 2: Update AdminAllFormatter.php

1. **Ubah perhitungan persentase bulanan**:
   ```php
   // Ganti baris 575-580
   $percentageStandard = $yearlyTarget > 0 ? 
       round(($monthData['standard'] / $yearlyTarget) * 100, 2) : 0;
   ```

2. **Ubah perhitungan persentase triwulan**:
   ```php
   // Ganti baris 590-595
   $quarterPercentage = $yearlyTarget > 0 ? 
       round(($quarterTotal['standard'] / $yearlyTarget) * 100, 2) : 0;
   ```

3. **Pastikan akses ke yearlyTarget** di semua method yang membutuhkan

### Langkah 3: Update Template Excel

1. **Ubah header kolom** dari "% Standar" menjadi "% Pencapaian Target"
2. **Update dokumentasi** template Excel
3. **Tambahkan keterangan** di footer template

### Langkah 4: Testing

1. **Test konsistensi** antara Excel dan dashboard
2. **Validasi perhitungan** dengan data sample
3. **User acceptance testing** dengan stakeholder

## File yang Perlu Dimodifikasi

1. `app/Formatters/AdminAllFormatter.php`
2. `app/Formatters/AdminMonthlyFormatter.php` (jika ada)
3. `app/Formatters/AdminQuarterlyFormatter.php` (jika ada)
4. `resources/excel/all.xlsx` (template)
5. `resources/excel/monthly.xlsx` (template)
6. `resources/excel/quarterly.xlsx` (template)

## Catatan Penting

⚠️ **Perubahan ini akan mengubah interpretasi data Excel yang sudah ada**

- Koordinasikan dengan stakeholder sebelum implementasi
- Buat backup template Excel yang lama
- Update dokumentasi pengguna
- Berikan training kepada user tentang perubahan interpretasi

## Kesimpulan

Perbedaan perhitungan ini adalah masalah serius yang perlu segera diselesaikan untuk memastikan konsistensi data dan mencegah kesalahan interpretasi. Standarisasi ke metode dashboard (pencapaian target) adalah solusi terbaik karena lebih relevan untuk monitoring dan evaluasi program PTM.