<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ExcelExportService;
use App\Formatters\AdminAllFormatter;
use App\Formatters\AdminMonthlyFormatter;
use App\Formatters\AdminQuarterlyFormatter;
use App\Formatters\PuskesmasFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $excelExportService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup fake storage
        Storage::fake('local');
        
        // Create test user
        $this->user = \App\Models\User::factory()->create([
            'role' => 'admin'
        ]);
        
        // Mock ExcelExportService
        $this->excelExportService = $this->app->make(ExcelExportService::class);
    }

    /** @test */
    public function test_can_get_export_info()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/excel-export/info');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'export_types',
                    'disease_types',
                    'available_years'
                ]
            ]);
    }

    /** @test */
    public function test_can_export_all_report()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/all', [
                'disease_type' => 'ht',
                'year' => 2024
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'filename',
                    'file_path',
                    'download_url'
                ]
            ]);
    }

    /** @test */
    public function test_can_export_monthly_report()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/monthly', [
                'disease_type' => 'dm',
                'year' => 2024
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function test_can_export_quarterly_report()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/quarterly', [
                'disease_type' => 'ht',
                'year' => 2024
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function test_can_export_puskesmas_report()
    {
        // Create test puskesmas
        $puskesmas = \App\Models\Puskesmas::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/puskesmas', [
                'puskesmas_id' => $puskesmas->id,
                'disease_type' => 'ht',
                'year' => 2024
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function test_can_export_puskesmas_template()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/puskesmas/template', [
                'disease_type' => 'ht',
                'year' => 2024
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function test_can_batch_export()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/batch', [
                'disease_type' => 'ht',
                'year' => 2024
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'results',
                    'summary' => [
                        'total_files',
                        'successful',
                        'failed'
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_validation_fails_with_invalid_disease_type()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/all', [
                'disease_type' => 'invalid',
                'year' => 2024
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['disease_type']);
    }

    /** @test */
    public function test_validation_fails_with_invalid_year()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/all', [
                'disease_type' => 'ht',
                'year' => 2019 // Too old
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    /** @test */
    public function test_puskesmas_export_requires_valid_puskesmas_id()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/puskesmas', [
                'puskesmas_id' => 999999, // Non-existent
                'disease_type' => 'ht',
                'year' => 2024
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['puskesmas_id']);
    }

    /** @test */
    public function test_can_direct_download_all_report()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/excel-download/all/ht/2024');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function test_can_direct_download_monthly_report()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/excel-download/monthly/dm/2024');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function test_can_direct_download_quarterly_report()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/excel-download/quarterly/ht/2024');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function test_can_direct_download_puskesmas_template()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/excel-download/puskesmas/template/ht/2024');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function test_admin_can_cleanup_old_files()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/excel-export/cleanup', [
                'days_old' => 30
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'deleted_count'
                ]
            ]);
    }

    /** @test */
    public function test_non_admin_cannot_cleanup_files()
    {
        // Create non-admin user
        $regularUser = \App\Models\User::factory()->create([
            'role' => 'user'
        ]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->deleteJson('/api/excel-export/cleanup', [
                'days_old' => 30
            ]);

        $response->assertStatus(403); // Forbidden
    }

    /** @test */
    public function test_unauthenticated_user_cannot_access_export_endpoints()
    {
        $response = $this->getJson('/api/excel-export/info');
        $response->assertStatus(401); // Unauthorized

        $response = $this->postJson('/api/excel-export/all');
        $response->assertStatus(401); // Unauthorized
    }

    /** @test */
    public function test_export_with_download_flag_returns_file()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/excel-export/all', [
                'disease_type' => 'ht',
                'year' => 2024,
                'download' => true
            ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function test_get_export_status()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/excel-export/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'service_status',
                    'available_formatters'
                ]
            ]);
    }

    /** @test */
    public function test_formatter_classes_exist()
    {
        $this->assertTrue(class_exists(AdminAllFormatter::class));
        $this->assertTrue(class_exists(AdminMonthlyFormatter::class));
        $this->assertTrue(class_exists(AdminQuarterlyFormatter::class));
        $this->assertTrue(class_exists(PuskesmasFormatter::class));
    }

    /** @test */
    public function test_formatters_can_be_instantiated()
    {
        $statisticsService = $this->app->make(\App\Services\StatisticsService::class);
        
        $adminAllFormatter = new AdminAllFormatter($statisticsService);
        $this->assertInstanceOf(AdminAllFormatter::class, $adminAllFormatter);
        
        $adminMonthlyFormatter = new AdminMonthlyFormatter($statisticsService);
        $this->assertInstanceOf(AdminMonthlyFormatter::class, $adminMonthlyFormatter);
        
        $adminQuarterlyFormatter = new AdminQuarterlyFormatter($statisticsService);
        $this->assertInstanceOf(AdminQuarterlyFormatter::class, $adminQuarterlyFormatter);
        
        $puskesmasFormatter = new PuskesmasFormatter($statisticsService);
        $this->assertInstanceOf(PuskesmasFormatter::class, $puskesmasFormatter);
    }

    /** @test */
    public function test_excel_export_service_can_be_instantiated()
    {
        $service = $this->app->make(ExcelExportService::class);
        $this->assertInstanceOf(ExcelExportService::class, $service);
    }

    /** @test */
    public function test_formatters_have_required_methods()
    {
        $statisticsService = $this->app->make(\App\Services\StatisticsService::class);
        
        $formatters = [
            new AdminAllFormatter($statisticsService),
            new AdminMonthlyFormatter($statisticsService),
            new AdminQuarterlyFormatter($statisticsService),
            new PuskesmasFormatter($statisticsService)
        ];
        
        foreach ($formatters as $formatter) {
            $this->assertTrue(method_exists($formatter, 'format'));
            $this->assertTrue(method_exists($formatter, 'getFilename'));
            $this->assertTrue(method_exists($formatter, 'validateInput'));
        }
    }

    /** @test */
    public function test_export_service_has_required_methods()
    {
        $service = $this->app->make(ExcelExportService::class);
        
        $requiredMethods = [
            'exportAll',
            'exportMonthly', 
            'exportQuarterly',
            'exportPuskesmas',
            'exportPuskesmasTemplate',
            'exportBatch',
            'downloadFile',
            'streamDownload',
            'cleanupOldFiles',
            'getAvailableExportTypes',
            'getAvailableDiseaseTypes'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($service, $method), "Method {$method} not found in ExcelExportService");
        }
    }

    /** @test */
    public function test_disease_types_are_valid()
    {
        $service = $this->app->make(ExcelExportService::class);
        $diseaseTypes = $service->getAvailableDiseaseTypes();
        
        $this->assertArrayHasKey('ht', $diseaseTypes);
        $this->assertArrayHasKey('dm', $diseaseTypes);
        $this->assertEquals('Hipertensi', $diseaseTypes['ht']);
        $this->assertEquals('Diabetes Melitus', $diseaseTypes['dm']);
    }

    /** @test */
    public function test_export_types_are_valid()
    {
        $service = $this->app->make(ExcelExportService::class);
        $exportTypes = $service->getAvailableExportTypes();
        
        $expectedTypes = ['all', 'monthly', 'quarterly', 'puskesmas'];
        
        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, $exportTypes);
            $this->assertArrayHasKey('name', $exportTypes[$type]);
            $this->assertArrayHasKey('description', $exportTypes[$type]);
            $this->assertArrayHasKey('formatter', $exportTypes[$type]);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup any created files
        Storage::fake('local');
        
        parent::tearDown();
    }
}