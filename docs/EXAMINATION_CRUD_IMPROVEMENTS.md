# Perbaikan CRUD Pemeriksaan - INSERT vs UPDATE

## Masalah yang Diperbaiki

Sebelumnya, sistem pemeriksaan DM (Diabetes Mellitus) memiliki masalah dalam logika CRUD:

### Masalah Lama:
- **Method `store()`**: Melakukan DELETE + INSERT alih-alih INSERT murni
- **Method `update()`**: Melakukan DELETE + INSERT alih-alih UPDATE yang sebenarnya
- **Kehilangan History**: Data pemeriksaan lama hilang setiap kali ada perubahan
- **ID Berubah**: ID pemeriksaan berubah setiap kali ada update
- **Tidak Konsisten**: Logika berbeda antara HT dan DM examination

### Solusi yang Diimplementasikan:

#### 1. Method `store()` - INSERT Murni
```php
// SEBELUM: DELETE + INSERT
DmExamination::where(...)->delete();
$examination = DmExamination::create($data);

// SESUDAH: INSERT Murni
$examination = DmExamination::create($data);
```

#### 2. Method `update()` - UPDATE Sebenarnya
```php
// SEBELUM: DELETE + INSERT
DmExamination::where(...)->delete();
DmExamination::create($newData);

// SESUDAH: UPDATE yang sebenarnya
$dmExamination->update($newData);
```

#### 3. Method `updateBatch()` - Batch Update/Create
Method baru untuk menangani update multiple examination types:
```php
// Cek apakah data sudah ada
if ($existingExam) {
    // UPDATE yang sebenarnya
    $existingExam->update($data);
} else {
    // CREATE baru jika belum ada
    DmExamination::create($data);
}
```

## Endpoint yang Tersedia

### 1. Create New Examination (INSERT)
```
POST /api/puskesmas/dm-examinations
```
**Behavior**: Selalu membuat record baru dengan ID unik

### 2. Update Single Examination (UPDATE)
```
PUT /api/puskesmas/dm-examinations/{id}
```
**Behavior**: Update record yang sudah ada, ID tetap sama

### 3. Batch Update/Create (MIXED)
```
PUT /api/puskesmas/dm-examinations-batch
```
**Behavior**: 
- UPDATE jika data sudah ada
- CREATE jika data belum ada

## Struktur Database yang Benar

Sekarang setiap pemeriksaan adalah entitas unik:

| id | patient_id | examination_date | examination_type | result | created_at | updated_at |
|----|------------|------------------|------------------|--------|------------|------------|
| 5  | 1          | 2025-06-20       | hba1c           | 7.2    | ...        | ...        |
| 26 | 1          | 2025-06-20       | gdp             | 120    | ...        | ...        |
| 27 | 1          | 2025-06-20       | hba1c           | 6.8    | ...        | ...        |

**Catatan**: Record dengan ID 5 dan 27 adalah dua pemeriksaan HbA1c yang berbeda pada tanggal yang sama, masing-masing dengan history yang terjaga.

## Keuntungan Implementasi Baru

1. **Audit Trail Terjaga**: Semua history pemeriksaan tersimpan
2. **ID Konsisten**: ID tidak berubah saat update
3. **Logika Jelas**: 
   - `store()` = INSERT baru
   - `update()` = UPDATE existing
   - `updateBatch()` = Mixed UPDATE/CREATE
4. **Konsistensi**: Logika sama dengan HtExamination
5. **Data Integrity**: Tidak ada data yang hilang

## Migration yang Diperlukan

Tidak ada perubahan struktur database yang diperlukan. Perubahan hanya pada logika aplikasi.

## Testing

Untuk memastikan implementasi bekerja dengan benar:

1. **Test INSERT**: Buat pemeriksaan baru, pastikan ID unik
2. **Test UPDATE**: Update pemeriksaan existing, pastikan ID tetap sama
3. **Test Batch**: Update multiple types, pastikan logic UPDATE/CREATE benar
4. **Test History**: Pastikan data lama tidak hilang

## Backward Compatibility

Implementasi ini backward compatible dengan frontend yang sudah ada, karena:
- Endpoint yang sama tetap tersedia
- Response format tetap sama
- Hanya logika internal yang berubah