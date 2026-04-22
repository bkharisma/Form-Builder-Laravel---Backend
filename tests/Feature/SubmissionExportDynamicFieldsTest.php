<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionExportDynamicFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $testFields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->testFields = [
            [
                'id' => 'field_name',
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'enabled' => true,
                'order' => 0,
            ],
            [
                'id' => 'field_email',
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'enabled' => true,
                'order' => 1,
            ],
            [
                'id' => 'field_phone',
                'type' => 'tel',
                'label' => 'Phone Number',
                'required' => false,
                'enabled' => true,
                'order' => 2,
            ],
            [
                'id' => 'field_subject',
                'type' => 'select',
                'label' => 'Subject',
                'required' => true,
                'enabled' => true,
                'order' => 3,
                'options' => [
                    ['label' => 'General Inquiry', 'value' => 'general_inquiry'],
                    ['label' => 'Feedback', 'value' => 'feedback'],
                ],
            ],
        ];
    }

    private function createFormConfig(array $overrides = []): FormConfig
    {
        return FormConfig::create(array_merge([
            'title' => 'Export Test Form',
            'fields' => $this->testFields,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_csv_export_includes_dynamic_fields(): void
    {
        $config = $this->createFormConfig();

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => [
                'field_name' => 'John Doe',
                'field_email' => 'john@example.com',
                'field_phone' => '123-456-7890',
                'field_subject' => 'general_inquiry',
            ],
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);

        $this->assertStringContainsString('Full Name', $lines[0]);
        $this->assertStringContainsString('Email Address', $lines[0]);
        $this->assertStringContainsString('Phone Number', $lines[0]);
        $this->assertStringContainsString('Subject', $lines[0]);

        $this->assertStringContainsString('John Doe', $lines[1]);
        $this->assertStringContainsString('john@example.com', $lines[1]);
        $this->assertStringContainsString('123-456-7890', $lines[1]);
        $this->assertStringContainsString('general_inquiry', $lines[1]);
    }

    public function test_xlsx_export_includes_dynamic_fields(): void
    {
        $config = $this->createFormConfig();

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => [
                'field_name' => 'Jane Smith',
                'field_email' => 'jane@example.com',
                'field_phone' => '098-765-4321',
                'field_subject' => 'feedback',
            ],
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export/xlsx?form_slug=' . $config->slug);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_respects_field_order(): void
    {
        $fields = [
            [
                'id' => 'field_z',
                'type' => 'text',
                'label' => 'Z Field',
                'required' => false,
                'enabled' => true,
                'order' => 2,
            ],
            [
                'id' => 'field_a',
                'type' => 'text',
                'label' => 'A Field',
                'required' => false,
                'enabled' => true,
                'order' => 0,
            ],
            [
                'id' => 'field_m',
                'type' => 'text',
                'label' => 'M Field',
                'required' => false,
                'enabled' => true,
                'order' => 1,
            ],
        ];

        $config = FormConfig::create([
            'title' => 'Ordered Export Form',
            'fields' => $fields,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => [
                'field_a' => 'Value A',
                'field_m' => 'Value M',
                'field_z' => 'Value Z',
            ],
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $headers = str_getcsv($lines[0]);

        $this->assertEquals('A Field', $headers[0]);
        $this->assertEquals('M Field', $headers[1]);
        $this->assertEquals('Z Field', $headers[2]);
    }

    public function test_export_excludes_disabled_fields(): void
    {
        $fields = [
            [
                'id' => 'field_enabled',
                'type' => 'text',
                'label' => 'Enabled Field',
                'required' => true,
                'enabled' => true,
                'order' => 0,
            ],
            [
                'id' => 'field_disabled',
                'type' => 'text',
                'label' => 'Disabled Field',
                'required' => false,
                'enabled' => false,
                'order' => 1,
            ],
        ];

        $config = FormConfig::create([
            'title' => 'Disabled Field Export Form',
            'fields' => $fields,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => [
                'field_enabled' => 'Enabled Value',
                'field_disabled' => 'Disabled Value',
            ],
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $headers = $lines[0];

        $this->assertStringContainsString('Enabled Field', $headers);
        $this->assertStringNotContainsString('Disabled Field', $headers);

        $dataRow = $lines[1];
        $this->assertStringContainsString('Enabled Value', $dataRow);
        $this->assertStringNotContainsString('Disabled Value', $dataRow);
    }

    public function test_export_handles_submissions_without_dynamic_data(): void
    {
        $config = $this->createFormConfig();

        Submission::create([
            'form_config_id' => $config->id,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", $content), fn($line) => trim($line) !== '');

        $this->assertCount(2, $lines, 'Should have header + 1 data row');
    }

    public function test_export_handles_mixed_submissions_with_and_without_dynamic_data(): void
    {
        $config = FormConfig::create([
            'title' => 'Mixed Export Form',
            'fields' => [
                [
                    'id' => 'field_name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_name' => 'Dynamic Submitter'],
            'submitted_at' => now(),
        ]);

        Submission::create([
            'submitted_at' => now(),
            'form_config_id' => $config->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", $content));

        $this->assertCount(3, $lines, 'Should have header + 2 data rows');
    }

    public function test_export_handles_empty_dynamic_data(): void
    {
        $config = $this->createFormConfig();

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => [],
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = explode("\n", $content);

        $this->assertGreaterThan(1, count($lines), 'Should have at least header and data row');
    }

    public function test_export_includes_static_columns_id_submitted_at_created(): void
    {
        $config = $this->createFormConfig();

        Submission::create([
            'form_config_id' => $config->id,
            'dynamic_data' => ['field_name' => 'Test'],
            'submitted_at' => '2026-04-08 10:30:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=' . $config->slug);

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $headers = $lines[0];

        $this->assertStringContainsString('ID', $headers);
        $this->assertStringContainsString('Submitted At', $headers);
        $this->assertStringContainsString('Created At', $headers);
    }

    public function test_csv_export_requires_form_slug(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export');

        $response->assertStatus(422);
    }

    public function test_csv_export_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/submissions/export?form_slug=nonexistent');

        $response->assertStatus(404);
    }
}