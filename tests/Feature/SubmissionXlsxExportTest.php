<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_unauthenticated_user_cannot_export_xlsx(): void
    {
        $response = $this->getJson('/api/admin/submissions/export/xlsx?form_slug=test');
        $response->assertStatus(401);
    }

    public function test_authenticated_admin_can_export_xlsx(): void
    {
        $config = FormConfig::create([
            'title' => 'Xlsx Export Form',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::factory()->count(3)->create([
            'form_config_id' => $config->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export/xlsx?form_slug=' . $config->slug);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_xlsx_export_respects_date_filter(): void
    {
        $config = FormConfig::create([
            'title' => 'Date Filter Form',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-15 10:00:00']);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-20 10:00:00']);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-25 10:00:00']);
        Submission::factory()->create(['form_config_id' => $config->id, 'submitted_at' => '2026-01-30 10:00:00']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export/xlsx?form_slug=' . $config->slug . '&date_from=2026-01-20&date_to=2026-01-25');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_xlsx_export_respects_search_filter(): void
    {
        $config = FormConfig::create([
            'title' => 'Search Filter Form',
            'fields' => [
                ['id' => 'field_name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true, 'order' => 0],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::factory()->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_name' => 'John Doe'],
        ]);
        Submission::factory()->create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_name' => 'Jane Smith'],
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export/xlsx?form_slug=' . $config->slug . '&search=John');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}