# Dokumentasi Template Excel

## Struktur Direktori
- `resources/templates/` - Direktori utama untuk template
  - `excel/` - Subdirektori untuk template Excel yang digunakan oleh ExcelServiceProvider

## File Template
- `all.xlsx` - Template untuk laporan keseluruhan
- `monthly.xlsx` - Template untuk laporan bulanan
- `quarterly.xlsx` - Template untuk laporan triwulan
- `puskesmas.xlsx` - Template untuk laporan puskesmas

## Placeholder yang Tersedia
Aplikasi menggunakan dua jenis placeholder:
1. Placeholder dengan format `{{VARIABLE}}` - Digunakan dalam kode
2. Placeholder dengan format `<variable>` - Digunakan dalam file Excel

### Placeholder dengan Format `<variable>`
Berikut adalah daftar placeholder yang tersedia untuk digunakan dalam template Excel:

| Placeholder | Deskripsi | Contoh Nilai |
|-------------|-----------|--------------|
| `<tipe_penyakit>` | Tipe penyakit yang dilaporkan | HT, DM |
| `<tahun>` | Tahun laporan | 2023 |
| `<puskesmas>` | Nama puskesmas | Puskesmas Kecamatan A |
| `<sasaran>` | Target pencapaian | 1250 |
| `<mulai>` | Periode awal laporan | Januari, Triwulan 1 |
| `<akhir>` | Periode akhir laporan | Desember, Triwulan 4 |

### Penggunaan dalam Template Excel
Placeholder ini dapat dimasukkan ke dalam sel Excel dan akan otomatis diganti dengan nilai yang sesuai saat laporan dibuat.

## Implementasi
Placeholder diimplementasikan di kelas `BaseAdminFormatter` dengan metode `getAngleBracketPlaceholders()` yang mengembalikan array berisi pasangan placeholder dan nilai.

```php
// Contoh penggunaan
$anglePlaceholders = $this->getAngleBracketPlaceholders($replacements);
// $anglePlaceholders berisi array dengan key berupa placeholder dan value berupa nilai yang akan digunakan
```

## Catatan Pengembangan
- Pastikan semua placeholder ditulis dengan benar, tanpa spasi tambahan (contoh: gunakan `<mulai>` bukan `<mulai >`)
- Untuk menambahkan placeholder baru, perbarui metode `getAngleBracketPlaceholders()` di `BaseAdminFormatter.php`