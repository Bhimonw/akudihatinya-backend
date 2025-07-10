# Update Perhitungan Standar Bulanan

## Perubahan yang Dilakukan

### 1. Logika Perhitungan Standar Per Bulan

**Sebelumnya:**
- Perhitungan standar menggunakan logika kumulatif dari bulan pertama sampai bulan saat ini
- Jika ada gap di bulan manapun, pasien dianggap non-standar untuk semua bulan setelahnya

**Sekarang:**
- Perhitungan standar dilakukan per bulan secara independen
- Jika bulan itu pasien standar dan sebelumnya belum ada yang tidak hadir, maka di bulan itu tetap dianggap standar
- Pengecekan tiap bulan berbeda dan tidak mempengaruhi status bulan berikutnya

### 2. Kriteria Pasien Standar

Pasien dianggap **standar** untuk bulan tertentu jika:
1. Pasien melakukan kunjungan di bulan tersebut (first visit of the month)
2. Tidak ada gap/kekosongan kunjungan dari bulan pertama kunjungan tahun itu sampai bulan saat ini
3. Jika ini adalah bulan pertama kunjungan di tahun tersebut, otomatis dianggap standar

### 3. Perhitungan Persentase Bulanan

Persentase per bulan dihitung dengan formula:
```
persentase_bulan = (jumlah_pasien_standar_bulan_ini / target_tahunan) × 100
```

**Catatan Penting:**
- Target yang digunakan adalah target tahunan, bukan target bulanan
- Perhitungan menggunakan jumlah pasien standar pada bulan tersebut
- Setiap bulan memiliki perhitungan independen

## File yang Dimodifikasi

1. **app/Models/DmExamination.php**
   - Method `calculateIfStandardPatient()` - Updated logika perhitungan standar
   - Menambahkan kondisi khusus untuk bulan pertama kunjungan

2. **app/Models/HtExamination.php**
   - Method `calculateIfStandardPatient()` - Updated logika perhitungan standar
   - Menambahkan kondisi khusus untuk bulan pertama kunjungan

3. **app/Services/StatisticsCacheService.php**
   - Method `checkIfPatientIsStandard()` - Updated logika perhitungan standar
   - Konsistensi dengan perubahan di model

## Dampak Perubahan

### Positif:
- Perhitungan lebih akurat per bulan
- Pasien yang kembali setelah absen tidak kehilangan status standar secara permanen
- Logika lebih sesuai dengan kebutuhan bisnis

### Yang Perlu Diperhatikan:
- Data historis mungkin perlu direcalculate untuk konsistensi
- Perlu testing untuk memastikan perhitungan berjalan dengan benar
- Observer dan cache perlu diupdate jika ada perubahan data lama

## Contoh Skenario

**Skenario 1: Pasien Konsisten**
- Jan: Kunjungan pertama → Standar ✓
- Feb: Kunjungan → Standar ✓ (tidak ada gap)
- Mar: Kunjungan → Standar ✓ (tidak ada gap)

**Skenario 2: Pasien dengan Gap**
- Jan: Kunjungan pertama → Standar ✓
- Feb: Tidak ada kunjungan
- Mar: Kunjungan → Non-standar ✗ (ada gap di Feb)
- Apr: Kunjungan → Non-standar ✗ (masih ada gap di Feb)

**Skenario 3: Pasien Mulai di Tengah Tahun**
- Jun: Kunjungan pertama → Standar ✓ (bulan pertama)
- Jul: Kunjungan → Standar ✓ (tidak ada gap)
- Aug: Tidak ada kunjungan
- Sep: Kunjungan → Non-standar ✗ (ada gap di Aug)

## Testing yang Disarankan

1. Test perhitungan standar untuk berbagai skenario kunjungan
2. Test perhitungan persentase bulanan
3. Test konsistensi antara model dan service
4. Test performance untuk data dalam jumlah besar
5. Test recalculation untuk data historis