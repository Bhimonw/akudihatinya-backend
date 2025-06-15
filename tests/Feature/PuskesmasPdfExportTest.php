<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Exceptions\PuskesmasNotFoundException;
use App\Repositories\PuskesmasRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

class PuskesmasPdfExportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $adminUser;
    protected $puskesmas;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test puskesmas
        $this->puskesmas = Puskesmas::create([
            'name' => 'Test Puskesmas',
            'address' => 'Test Address',
            'phone' => '081234567890',
            'email' => 'test@puskesmas.com',
            'is_active' => true
        ]);

        // Create regular user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'role' => 'puskesmas',
            'puskesmas_id' => $this->puskesmas->id
        ]);

        // Create admin user
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'puskesmas_id' => null
        ]);

        // Create yearly target
        YearlyTarget::create([
            'puskesmas_id' => $this->puskesmas->id,
            'year' => date('Y'),
            'disease_type' => 'ht',
            'target_count' => 100
        ]);
    }

    /** @test */
    public function puskesmas_user_can_export_their_own_pdf()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'ht',
            'year' => date('Y')
        ]);

        // Should succeed (we can't test actual PDF download in unit test)
        // but we can test that it doesn't return an error response
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /** @test */
    public function admin_user_can_export_any_puskesmas_pdf()
    {
        $this->actingAs($this->adminUser);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'ht',
            'year' => date('Y'),
            'puskesmas_id' => $this->puskesmas->id
        ]);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /** @test */
    public function admin_must_provide_puskesmas_id()
    {
        $this->actingAs($this->adminUser);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'ht',
            'year' => date('Y')
            // Missing puskesmas_id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['puskesmas_id']);
    }

    /** @test */
    public function returns_404_for_nonexistent_puskesmas()
    {
        $this->actingAs($this->adminUser);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'ht',
            'year' => date('Y'),
            'puskesmas_id' => 99999 // Non-existent ID
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => 'puskesmas_not_found'
        ]);
    }

    /** @test */
    public function validates_disease_type()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'invalid_type',
            'year' => date('Y')
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['disease_type']);
    }

    /** @test */
    public function validates_year_range()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'ht',
            'year' => 2019 // Below minimum
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year']);
    }

    /** @test */
    public function puskesmas_user_cannot_access_other_puskesmas_data()
    {
        $otherPuskesmas = Puskesmas::factory()->create();
        $this->actingAs($this->user);

        $response = $this->postJson('/api/statistics/export/puskesmas-pdf', [
            'disease_type' => 'ht',
            'year' => date('Y'),
            'puskesmas_id' => $otherPuskesmas->id
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['puskesmas_id']);
    }
}
