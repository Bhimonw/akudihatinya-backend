# Development Tools

Folder ini berisi file-file tools untuk development dan IDE support.

## File Development Tools

### `_ide_helper.php`
File helper untuk IDE autocomplete dan intellisense:
- Laravel Facades autocomplete
- Helper functions
- Service container bindings
- Custom macros

### `_ide_helper_models.php`
File helper untuk Model autocomplete:
- Eloquent model properties
- Relationships
- Scopes
- Accessors dan Mutators

## Cara Generate Ulang

### Generate IDE Helper
```bash
php artisan ide-helper:generate
```

### Generate Model Helper
```bash
php artisan ide-helper:models
```

### Generate Meta File
```bash
php artisan ide-helper:meta
```

### Generate Semua
```bash
php artisan ide-helper:generate
php artisan ide-helper:models --nowrite
php artisan ide-helper:meta
```

## Konfigurasi IDE

### PhpStorm
- File ini akan otomatis dideteksi oleh PhpStorm
- Pastikan indexing sudah selesai
- Restart IDE jika diperlukan

### VS Code
- Install extension PHP Intelephense
- Tambahkan path ke settings.json jika diperlukan

## Catatan
- File ini di-generate otomatis, jangan edit manual
- Tambahkan ke .gitignore jika tidak ingin di-commit
- Re-generate setelah menambah model atau facade baru