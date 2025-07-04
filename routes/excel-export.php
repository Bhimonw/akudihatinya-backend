<?php

use App\Http\Controllers\ExcelExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Excel Export Routes
|--------------------------------------------------------------------------
|
| Routes untuk menangani export Excel berbagai jenis laporan
| Semua routes memerlukan autentikasi dan authorization yang sesuai
|
*/

// Group routes dengan middleware auth dan prefix
Route::middleware(['auth:sanctum'])->prefix('excel-export')->group(function () {
    
    // Get informasi export yang tersedia
    Route::get('/info', [ExcelExportController::class, 'getExportInfo'])
        ->name('excel.export.info');
    
    // Get status export service
    Route::get('/status', [ExcelExportController::class, 'getExportStatus'])
        ->name('excel.export.status');
    
    // Export laporan tahunan komprehensif (all.xlsx)
    Route::post('/all', [ExcelExportController::class, 'exportAll'])
        ->name('excel.export.all');
    
    // Export laporan bulanan (monthly.xlsx)
    Route::post('/monthly', [ExcelExportController::class, 'exportMonthly'])
        ->name('excel.export.monthly');
    
    // Export laporan triwulan (quarterly.xlsx)
    Route::post('/quarterly', [ExcelExportController::class, 'exportQuarterly'])
        ->name('excel.export.quarterly');
    
    // Export laporan per puskesmas (puskesmas.xlsx)
    Route::post('/puskesmas', [ExcelExportController::class, 'exportPuskesmas'])
        ->name('excel.export.puskesmas');
    
    // Export template puskesmas kosong
    Route::post('/puskesmas/template', [ExcelExportController::class, 'exportPuskesmasTemplate'])
        ->name('excel.export.puskesmas.template');
    
    // Export semua jenis laporan sekaligus (batch export)
    Route::post('/batch', [ExcelExportController::class, 'exportBatch'])
        ->name('excel.export.batch');
    
    // Download file yang sudah di-export
    Route::get('/download', [ExcelExportController::class, 'downloadFile'])
        ->name('excel.export.download');
    
    // Cleanup file export lama (admin only)
    Route::delete('/cleanup', [ExcelExportController::class, 'cleanupOldFiles'])
        ->middleware('role:admin')
        ->name('excel.export.cleanup');
});

// Routes untuk direct download (dengan parameter di URL)
Route::middleware(['auth:sanctum'])->prefix('excel-download')->group(function () {
    
    // Direct download all.xlsx
    Route::get('/all/{diseaseType?}/{year?}', function ($diseaseType = 'ht', $year = null) {
        $controller = app(ExcelExportController::class);
        $request = request();
        $request->merge([
            'disease_type' => $diseaseType,
            'year' => $year ?? date('Y'),
            'download' => true
        ]);
        return $controller->exportAll($request);
    })->name('excel.download.all');
    
    // Direct download monthly.xlsx
    Route::get('/monthly/{diseaseType?}/{year?}', function ($diseaseType = 'ht', $year = null) {
        $controller = app(ExcelExportController::class);
        $request = request();
        $request->merge([
            'disease_type' => $diseaseType,
            'year' => $year ?? date('Y'),
            'download' => true
        ]);
        return $controller->exportMonthly($request);
    })->name('excel.download.monthly');
    
    // Direct download quarterly.xlsx
    Route::get('/quarterly/{diseaseType?}/{year?}', function ($diseaseType = 'ht', $year = null) {
        $controller = app(ExcelExportController::class);
        $request = request();
        $request->merge([
            'disease_type' => $diseaseType,
            'year' => $year ?? date('Y'),
            'download' => true
        ]);
        return $controller->exportQuarterly($request);
    })->name('excel.download.quarterly');
    
    // Direct download puskesmas.xlsx
    Route::get('/puskesmas/{puskesmasId}/{diseaseType?}/{year?}', function ($puskesmasId, $diseaseType = 'ht', $year = null) {
        $controller = app(ExcelExportController::class);
        $request = request();
        $request->merge([
            'puskesmas_id' => $puskesmasId,
            'disease_type' => $diseaseType,
            'year' => $year ?? date('Y'),
            'download' => true
        ]);
        return $controller->exportPuskesmas($request);
    })->name('excel.download.puskesmas');
    
    // Direct download puskesmas template
    Route::get('/puskesmas/template/{diseaseType?}/{year?}', function ($diseaseType = 'ht', $year = null) {
        $controller = app(ExcelExportController::class);
        $request = request();
        $request->merge([
            'disease_type' => $diseaseType,
            'year' => $year ?? date('Y'),
            'download' => true
        ]);
        return $controller->exportPuskesmasTemplate($request);
    })->name('excel.download.puskesmas.template');
});

/*
|--------------------------------------------------------------------------
| API Documentation Routes
|--------------------------------------------------------------------------
|
| Contoh penggunaan API:
|
| 1. Get informasi export:
|    GET /api/excel-export/info
|
| 2. Export all.xlsx:
|    POST /api/excel-export/all
|    Body: {"disease_type": "ht", "year": 2024}
|
| 3. Direct download all.xlsx:
|    GET /api/excel-download/all/ht/2024
|
| 4. Export dengan download langsung:
|    POST /api/excel-export/all
|    Body: {"disease_type": "ht", "year": 2024, "download": true}
|
| 5. Batch export:
|    POST /api/excel-export/batch
|    Body: {"disease_type": "ht", "year": 2024}
|
| 6. Download file yang sudah di-export:
|    GET /api/excel-export/download?file_path=exports/excel/2024/01/filename.xlsx
|
| 7. Cleanup file lama (admin only):
|    DELETE /api/excel-export/cleanup
|    Body: {"days_old": 30}
|
*/