# DB Maintenance & Diagnostic Tools (Archived)

Semua skrip di folder ini sebelumnya berada di root project. Dipindahkan agar tidak mengotori root dan tidak ikut ter-deploy secara tidak sengaja.

PERINGATAN: Banyak skrip bersifat destruktif (drop tables, reset database, hapus data). Jangan jalankan di production.

## Struktur
| File | Fungsi Singkat | Destruktif? |
|------|----------------|-------------|
| `create_yearly_targets.php` | Buat yearly targets jika belum ada | ❌ |
| `reset_yearly_targets.php` | Set semua target_count ke 0 (tahun berjalan) | ⚠ Partial data loss |
| `clean_examinations_and_targets.php` | Hapus semua examinations & cache, buat target 0 | ✅ Ya |
| `reset_database.php` | Drop & recreate database (koneksi PDO langsung) | ✅ Ya (total) |
| `drop_all_tables.php` | Drop semua tabel | ✅ Ya (total) |
| `create_fresh_database.php` | Buat DB baru kosong | ✅ Ya (jika target DB dipakai) |
| `check_db_connection.php` | Cek koneksi PDO & daftar tabel | ❌ |
| `check_users_count.php` | Statistik user & pagination test | ❌ |
| `simulate_api.php` | Simulasi response dashboard admin | ❌ |
| `repair_users_table.php` | Perbaikan kolom/struktur user (legacy) | ⚠ |
| `final_status.php` | Rekap final status audit | ❌ |
| `final_check_correct_columns.php` | Validasi kolom tabel | ❌ |
| `verify_mysql_storage.php` | Verifikasi penggunaan storage MySQL | ❌ |
| `verify_final_setup.php` | Verifikasi final setup (post migration) | ❌ |
| `verify_all_puskesmas_clean.php` | Verifikasi tidak ada data pemeriksaan | ❌ |
| `check_table_structure.php` | Dump struktur semua tabel | ❌ |
| `check_examinations_detailed.php` | Detail data examinations | ❌ |

## Best Practice Modern
Ganti skrip ad-hoc ini dengan:
1. Artisan Commands (app/Console/Commands)
2. Feature / Unit Tests untuk validasi otomatis
3. Database seeders & migrations yang idempotent

## Rekomendasi Penghapusan
Skrip destruktif sebaiknya dihapus permanen setelah yakin tidak dipakai lagi: `reset_database`, `drop_all_tables`, `clean_examinations_and_targets`.

## Cara Menjalankan (Contoh)
```bash
php development/manual-tests/db-tools/check_users_count.php
php development/manual-tests/db-tools/create_yearly_targets.php
```

---
Jika ingin memigrasikan fungsi ini ke Artisan command, buat kelas di `app/Console/Commands` lalu registrasikan di `app/Console/Kernel.php`.