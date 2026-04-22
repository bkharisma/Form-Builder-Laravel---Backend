<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/reports/submissions-over-time?form_slug=test');
        $response->assertStatus(401);
    }

    public function test_submissions_over_time_default_30_days(): void
    {
        $config = FormConfig::create([
            'title' => 'Report Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => now()->subDays(5)]);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => now()->subDays(10)]);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => now()->subDays(40)]);

        $response = $this->actingAs($this->admin)->getJson('/api/reports/submissions-over-time?form_slug=' . $config->slug);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['date', 'count']
        ]);

        $data = $response->json();
        $this->assertCount(2, $data);
    }

    public function test_submissions_over_time_with_custom_date_range(): void
    {
        $config = FormConfig::create([
            'title' => 'Report Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-10 10:00:00']);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-15 10:00:00']);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-20 10:00:00']);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-25 10:00:00']);

        $response = $this->actingAs($this->admin)->getJson('/api/reports/submissions-over-time?form_slug=' . $config->slug . '&date_from=2026-01-10&date_to=2026-01-15');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_submissions_over_time_groups_by_date(): void
    {
        $config = FormConfig::create([
            'title' => 'Report Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::factory()->count(3)->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-10 10:00:00']);
        Submission::factory()->count(2)->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-10 14:00:00']);

        $response = $this->actingAs($this->admin)->getJson('/api/reports/submissions-over-time?form_slug=' . $config->slug . '&date_from=2026-01-10&date_to=2026-01-10');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals(5, $data[0]['count']);
    }

    public function test_empty_data_returns_empty_array(): void
    {
        $config = FormConfig::create([
            'title' => 'Report Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/reports/submissions-over-time?form_slug=' . $config->slug . '&date_from=2026-01-01&date_to=2026-01-31');

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    public function test_legacy_by_institution_endpoint_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/reports/by-institution');
        $response->assertStatus(404);
    }

    public function test_legacy_by_purpose_endpoint_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/reports/by-purpose');
        $response->assertStatus(404);
    }

    public function test_legacy_by_host_endpoint_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/reports/by-host');
        $response->assertStatus(404);
    }
}