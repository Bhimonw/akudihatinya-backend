# Setup Tahun Baru - Dokumentasi

Dokumentasi ini menjelaskan cara melakukan setup tahun baru yang mempertahankan data pasien namun mengosongkan data pemeriksaan.

## 🎯 Tujuan

Setup tahun baru bertujuan untuk:
- **Mempertahankan** semua data pasien yang sudah ada
- **Mengosongkan** semua data pemeriksaan HT dan DM
- **Membuat** target tahunan baru untuk semua puskesmas
- **Membersihkan** cache statistik bulanan
- **Mereset** tahun pemeriksaan pada data pasien

## 🛠️ Command yang Tersedia

### 1. Setup Tahun Baru Lengkap

```bash
# Setup tahun baru dengan konfirmasi interaktif
php artisan year:setup

# Setup untuk tahun tertentu
php artisan year:setup 2025

# Setup tanpa konfirmasi (untuk otomatisasi)
php artisan year:setup 2025 --confirm
```

**Fungsi:**
- Menghapus semua data pemeriksaan HT dan DM
- Mempertahankan semua data pasien
- Membuat target tahunan baru
- Membersihkan cache statistik
- Mereset `ht_years` dan `dm_years` pada tabel patients

### 2. Membuat Target Tahunan Saja

```bash
# Membuat target untuk tahun saat ini
php artisan targets:create-yearly

# Membuat target untuk tahun tertentu
php artisan targets:create-yearly 2025

# Update target yang sudah ada tanpa konfirmasi
php artisan targets:create-yearly 2025 --force
```

**Fungsi:**
- Membuat target HT dan DM untuk semua puskesmas
- Dapat memperbarui target yang sudah ada
- Tidak menghapus data pemeriksaan

### 3. Arsip Data Tahun Sebelumnya (Existing)

```bash
# Arsip data pemeriksaan tahun sebelumnya
php artisan examinations:archive
```

## ⏰ Penjadwalan Otomatis

Sistem telah dikonfigurasi untuk menjalankan setup tahun baru secara otomatis:

```php
// Di app/Console/Kernel.php
$schedule->command('year:setup --confirm')->yearly()->at('01:00');
```

**Jadwal:**
- **Tanggal**: 1 Januari setiap tahun
- **Waktu**: 01:00 (dini hari)
- **Mode**: Otomatis tanpa konfirmasi

## 📊 Data yang Dipengaruhi

### ✅ Data yang Dipertahankan
- **Tabel `patients`**: Semua data pasien tetap ada
- **Tabel `puskesmas`**: Data puskesmas tidak berubah
- **Tabel `users`**: Data pengguna tidak berubah

### 🗑️ Data yang Dihapus
- **Tabel `ht_examinations`**: Semua data pemeriksaan HT
- **Tabel `dm_examinations`**: Semua data pemeriksaan DM
- **Tabel `monthly_statistics_cache`**: Cache statistik bulanan

### 🔄 Data yang Direset
- **`patients.ht_years`**: Direset ke array kosong `[]`
- **`patients.dm_years`**: Direset ke array kosong `[]`

### 🎯 Data yang Dibuat
- **Tabel `yearly_targets`**: Target baru untuk tahun yang ditentukan

## 🔧 Konfigurasi Target Default

Target tahunan dibuat berdasarkan nama puskesmas:

```php
$targetValues = [
    'Puskesmas 4' => 137,
    'Puskesmas 6' => 97,
    'default' => rand(100, 300) // Untuk puskesmas lainnya
];
```

## ⚠️ Peringatan Penting

1. **Backup Data**: Selalu lakukan backup database sebelum menjalankan setup tahun baru
2. **Tidak Dapat Dibatalkan**: Proses penghapusan data pemeriksaan tidak dapat dibatalkan
3. **Konfirmasi**: Command akan meminta konfirmasi kecuali menggunakan flag `--confirm`
4. **Downtime**: Proses ini dapat memakan waktu tergantung jumlah data

## 📝 Contoh Penggunaan

### Skenario 1: Setup Manual Tahun Baru

```bash
# 1. Backup database terlebih dahulu
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# 2. Jalankan setup tahun baru
php artisan year:setup 2025

# 3. Verifikasi hasil
php artisan tinker
>>> App\Models\Patient::count(); // Harus sama dengan sebelumnya
>>> App\Models\HtExamination::count(); // Harus 0
>>> App\Models\DmExamination::count(); // Harus 0
>>> App\Models\YearlyTarget::where('year', 2025)->count(); // Harus > 0
```

### Skenario 2: Membuat Target Saja

```bash
# Jika hanya ingin membuat target tanpa menghapus data pemeriksaan
php artisan targets:create-yearly 2025
```

### Skenario 3: Setup Otomatis via Cron

```bash
# Tambahkan ke crontab untuk menjalankan Laravel scheduler
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 🔍 Monitoring dan Logging

Command akan memberikan output detail tentang:
- Jumlah data HT yang dihapus
- Jumlah data DM yang dihapus
- Jumlah pasien yang dipertahankan
- Jumlah target yang dibuat
- Status pembersihan cache

## 🆘 Troubleshooting

### Error: "Class not found"
```bash
# Regenerate autoload
composer dump-autoload
```

### Error: Database connection
```bash
# Periksa konfigurasi database
php artisan config:cache
php artisan config:clear
```

### Rollback jika terjadi masalah
```bash
# Restore dari backup
mysql -u username -p database_name < backup_20241231.sql
```

## 📚 File Terkait

- **Command**: `app/Console/Commands/SetupNewYear.php`
- **Service**: `app/Services/NewYearSetupService.php`
- **Command**: `app/Console/Commands/CreateYearlyTargets.php`
- **Kernel**: `app/Console/Kernel.php`
- **Routes**: `routes/console.php`