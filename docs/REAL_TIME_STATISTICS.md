# ⚡ Real-Time Statistics Implementation

Sistem ini mengimplementasikan perhitungan statistik real-time untuk data examination yang dijalankan saat ada input dari user (puskesmas), sehingga tidak membebani dashboard dan data akan lebih cepat dalam penyajiannya.

## Fitur Utama

### 1. Pre-calculated Statistics
Data examination langsung dihitung dan disimpan sebagai angka di database dengan kolom tambahan:
- `is_controlled`: Boolean untuk menentukan apakah hasil examination terkontrol
- `is_first_visit_this_month`: Boolean untuk menentukan apakah ini kunjungan pertama pasien di bulan tersebut
- `is_standard_patient`: Boolean untuk menentukan apakah pasien termasuk "standard" (rutin kontrol)
- `patient_gender`: Gender pasien (disimpan untuk menghindari join)

### 2. Real-Time Processing
Setiap kali ada input examination baru:
- Statistik langsung dihitung menggunakan `calculateStatistics()` method
- Cache monthly statistics otomatis diperbarui
- Status "standard patient" diperbarui untuk semua examination pasien di tahun yang sama

### 3. Fast Dashboard Retrieval
Dashboard menggunakan data pre-calculated dari cache untuk performa optimal.

## File yang Dimodifikasi/Dibuat

### Migration
- `2025_01_15_000000_add_precalculated_stats_to_examinations.php`: Menambah kolom pre-calculated statistics

### Models
- `HtExamination.php`: Ditambah method `calculateStatistics()` dan helper methods
- `DmExamination.php`: Ditambah method `calculateStatistics()` dan helper methods

### Services
- `RealTimeStatisticsService.php`: Service baru untuk menangani real-time calculation dan cache updates

### Observers
- `HtExaminationObserver.php`: Diperbarui untuk menggunakan `RealTimeStatisticsService`
- `DmExaminationObserver.php`: Diperbarui untuk menggunakan `RealTimeStatisticsService`

### Controllers
- `StatisticsController.php`: Diperbarui untuk menggunakan `RealTimeStatisticsService` pada semua endpoint dashboard

### Commands
- `PopulateExaminationStats.php`: Command untuk mengisi data pre-calculated statistics untuk data existing

## Cara Kerja

### 1. Input Examination Baru
```
User Input → Observer (creating) → Set year/month
           → Observer (created) → RealTimeStatisticsService.processExaminationData()
           → Calculate statistics → Update cache → Update patient standard status
```

### 2. Dashboard Request
```
Dashboard Request → RealTimeStatisticsService.getFastDashboardStats()
                 → Retrieve from MonthlyStatisticsCache (pre-calculated)
                 → Return fast response
```

### 3. Standard Patient Calculation
Pasien dianggap "standard" jika:
- Memiliki kunjungan di setiap bulan sejak kunjungan pertama di tahun tersebut
- Contoh: Jika pertama kali datang di bulan Maret, maka harus ada kunjungan di Maret, April, Mei, dst.

## Command Usage

### Populate Statistics untuk Data Existing
```bash
# Untuk tahun tertentu
php artisan examinations:populate-stats --year=2024

# Dengan batch size custom
php artisan examinations:populate-stats --year=2024 --batch-size=500
```

## Performance Benefits

1. **Faster Dashboard Loading**: Data sudah pre-calculated, tidak perlu query kompleks
2. **Real-time Updates**: Cache otomatis update saat ada input baru
3. **Reduced Database Load**: Mengurangi beban query kompleks pada dashboard
4. **Scalable**: Sistem dapat menangani volume data yang besar dengan performa konsisten

## Database Schema Changes

### ht_examinations table
```sql
ALTER TABLE ht_examinations ADD COLUMN is_controlled BOOLEAN;
ALTER TABLE ht_examinations ADD COLUMN is_first_visit_this_month BOOLEAN;
ALTER TABLE ht_examinations ADD COLUMN is_standard_patient BOOLEAN;
ALTER TABLE ht_examinations ADD COLUMN patient_gender ENUM('male', 'female');
```

### dm_examinations table
```sql
ALTER TABLE dm_examinations ADD COLUMN is_controlled BOOLEAN;
ALTER TABLE dm_examinations ADD COLUMN is_first_visit_this_month BOOLEAN;
ALTER TABLE dm_examinations ADD COLUMN is_standard_patient BOOLEAN;
ALTER TABLE dm_examinations ADD COLUMN patient_gender ENUM('male', 'female');
```

## Monitoring

Untuk memantau performa sistem:
1. Monitor waktu response dashboard
2. Check log untuk error pada real-time processing
3. Verifikasi konsistensi data antara examination dan cache

## Troubleshooting

### Jika Data Tidak Sinkron
```bash
# Recalculate semua statistics
php artisan examinations:populate-stats --year=2024
```

### Jika Cache Tidak Update
Periksa Observer apakah berjalan dengan benar dan pastikan `RealTimeStatisticsService` terdaftar di service container.