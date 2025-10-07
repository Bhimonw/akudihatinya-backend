# Manual Diagnostic Tests

Folder ini berisi skrip/manual page yang sebelumnya tersebar di root atau `public/` dan TIDAK dipakai dalam pipeline otomatis.

## Daftar File

| File | Fungsi | Catatan Keamanan |
|------|--------|------------------|
| `test_admin_api.php` | Query langsung ke database & output statistik DM/HT untuk verifikasi | Jangan deploy ke production; hanya jalankan lokal / sandbox |
| `test-api.html` | Halaman HTML untuk memanggil endpoint statistik admin secara manual dengan token | Sekarang TIDAK lagi berada di `public/` agar tidak ter-ekspos |

## Cara Pakai `test_admin_api.php`

```bash
php development/manual-tests/test_admin_api.php
```

Output akan menampilkan ringkasan perhitungan (target, pasien unik, dll) untuk cross-check dengan frontend.

## Cara Pakai `test-api.html`

File ini sekarang hanya dokumentasi / referensi. Jika perlu uji manual di browser:
1. Salin file ke sementara: `cp development/manual-tests/test-api.html public/test-temp.html`
2. Buka di browser: `http://localhost:8000/test-temp.html` (jika memakai dev server yang serve folder `public`)
3. MASUKKAN token admin secara manual (jangan commit token)
4. Hapus kembali file temp: `rm public/test-temp.html`

## Kenapa Dipindahkan?

- Menghindari accidental exposure di production
- Memisahkan artefak debugging vs kode aplikasi resmi
- Mempermudah audit keamanan & deploy artifact minim

## Best Practice

- Gunakan PHPUnit Feature tests untuk kasus baru alih-alih menambah skrip manual
- Jangan commit perubahan dengan token / kredensial
- Hapus file sementara yang disalin ke `public/` setelah selesai

---
Folder ini boleh dihapus sepenuhnya setelah semua verifikasi digantikan oleh automated tests.