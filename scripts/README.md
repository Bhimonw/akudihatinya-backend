# Scripts Directory

Folder ini berisi script-script utilitas untuk development dan testing.

## File Scripts

### `check_users.php`
Script untuk memeriksa data user di database dan melakukan testing autentikasi.
- Menampilkan semua user yang ada
- Melakukan verifikasi password
- Testing Auth::attempt
- Menampilkan data Puskesmas

### `test_api_login.ps1`
Script PowerShell untuk testing API login endpoint.
- Menguji endpoint `/api/login`
- Menggunakan kredensial puskesmas1
- Menampilkan response lengkap dari API
- Menangani error dengan detail

### `test_login_api.php`
Script PHP untuk testing API login secara langsung.
- Testing internal Laravel authentication
- Verifikasi password hashing
- Testing Auth::attempt method

## Cara Penggunaan

### Menjalankan check_users.php
```bash
php scripts/check_users.php
```

### Menjalankan test_api_login.ps1
```powershell
powershell -ExecutionPolicy Bypass -File scripts/test_api_login.ps1
```

### Menjalankan test_login_api.php
```bash
php scripts/test_login_api.php
```

## Catatan
- Pastikan Laravel development server berjalan sebelum menjalankan script API testing
- Script ini hanya untuk development dan testing, jangan digunakan di production