<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function createFormConfig(array $overrides = []): FormConfig
    {
        return FormConfig::create(array_merge([
            'title' => 'Test Form',
            'status' => 'published',
            'fields' => [
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
                    'label' => 'Email',
                    'required' => true,
                    'enabled' => true,
                    'order' => 1,
                ],
                [
                    'id' => 'field_phone',
                    'type' => 'tel',
                    'label' => 'Phone',
                    'required' => false,
                    'enabled' => true,
                    'order' => 2,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_submission_with_dynamic_fields_succeeds(): void
    {
        $config = $this->createFormConfig();

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'John Doe',
                'field_email' => 'john@example.com',
                'field_phone' => '123-456-7890',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'dynamic_data' => [
                    'field_name' => 'John Doe',
                    'field_email' => 'john@example.com',
                    'field_phone' => '123-456-7890',
                ],
            ]);

        $this->assertDatabaseHas('submissions', [
            'dynamic_data->field_name' => 'John Doe',
            'dynamic_data->field_email' => 'john@example.com',
        ]);
    }

    public function test_submission_succeeds_without_form_config_validation(): void
    {
        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/nonexistent-slug', [
            'dynamic_data' => [
                'field_name' => 'Jane Doe',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(404);
    }

    public function test_submission_validates_required_dynamic_fields(): void
    {
        $config = $this->createFormConfig();

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_name']);
    }

    public function test_submission_validates_email_field(): void
    {
        $config = FormConfig::create([
            'title' => 'Email Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_email' => 'not-an-email',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_email']);
    }

    public function test_submission_validates_select_field(): void
    {
        $config = FormConfig::create([
            'title' => 'Select Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_subject',
                    'type' => 'select',
                    'label' => 'Subject',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'options' => [
                        ['label' => 'General Inquiry', 'value' => 'general_inquiry'],
                        ['label' => 'Feedback', 'value' => 'feedback'],
                    ],
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_subject' => 'invalid_value',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_subject']);
    }

    public function test_submission_validates_number_field(): void
    {
        $config = FormConfig::create([
            'title' => 'Number Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_age',
                    'type' => 'number',
                    'label' => 'Age',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'validation' => [
                        'min_value' => 0,
                        'max_value' => 100,
                    ],
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_age' => 'not-a-number',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_age']);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_age' => '150',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_age']);
    }

    public function test_submission_validates_text_length(): void
    {
        $config = FormConfig::create([
            'title' => 'Text Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'validation' => [
                        'min_length' => 2,
                        'max_length' => 50,
                    ],
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'A',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_name']);
    }

    public function test_submission_validates_regex_pattern(): void
    {
        $config = FormConfig::create([
            'title' => 'Regex Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_code',
                    'type' => 'text',
                    'label' => 'Code',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'validation' => [
                        'regex' => '^[A-Z]{3}[0-9]{3}$',
                    ],
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_code' => 'invalid',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_code']);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_code' => 'ABC123',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_submission_validates_date_field(): void
    {
        $config = FormConfig::create([
            'title' => 'Date Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_date',
                    'type' => 'date',
                    'label' => 'Date',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_date' => 'invalid-date',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_date']);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_date' => '2024-01-15',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_submission_validates_time_field(): void
    {
        $config = FormConfig::create([
            'title' => 'Time Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_time',
                    'type' => 'time',
                    'label' => 'Time',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_time' => 'invalid-time',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dynamic_data.field_time']);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_time' => '14:30',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_submission_ignores_disabled_fields(): void
    {
        $config = FormConfig::create([
            'title' => 'Disabled Field Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
                [
                    'id' => 'field_disabled',
                    'type' => 'text',
                    'label' => 'Disabled',
                    'required' => true,
                    'enabled' => false,
                    'order' => 1,
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'John Doe',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_submission_stores_form_config_id(): void
    {
        $config = $this->createFormConfig();

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => 'John Doe',
                'field_email' => 'john@example.com',
                'field_phone' => '123-456-7890',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('submissions', [
            'form_config_id' => $config->id,
        ]);
    }

    public function test_submission_with_all_field_types(): void
    {
        $config = FormConfig::create([
            'title' => 'All Fields Form',
            'status' => 'published',
            'fields' => [
                ['id' => 'field_text', 'type' => 'text', 'label' => 'Text', 'required' => true, 'enabled' => true, 'order' => 0],
                ['id' => 'field_textarea', 'type' => 'textarea', 'label' => 'Textarea', 'required' => false, 'enabled' => true, 'order' => 1],
                ['id' => 'field_email', 'type' => 'email', 'label' => 'Email', 'required' => false, 'enabled' => true, 'order' => 2],
                ['id' => 'field_number', 'type' => 'number', 'label' => 'Number', 'required' => false, 'enabled' => true, 'order' => 3],
                ['id' => 'field_tel', 'type' => 'tel', 'label' => 'Tel', 'required' => false, 'enabled' => true, 'order' => 4],
                ['id' => 'field_select', 'type' => 'select', 'label' => 'Select', 'required' => false, 'enabled' => true, 'order' => 5, 'options' => [['label' => 'A', 'value' => 'a']]],
                ['id' => 'field_checkbox', 'type' => 'checkbox', 'label' => 'Checkbox', 'required' => false, 'enabled' => true, 'order' => 6],
                ['id' => 'field_date', 'type' => 'date', 'label' => 'Date', 'required' => false, 'enabled' => true, 'order' => 7],
                ['id' => 'field_time', 'type' => 'time', 'label' => 'Time', 'required' => false, 'enabled' => true, 'order' => 8],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_text' => 'text value',
                'field_textarea' => 'textarea value',
                'field_email' => 'test@example.com',
                'field_number' => '42',
                'field_tel' => '123-456-7890',
                'field_select' => 'a',
                'field_checkbox' => true,
                'field_date' => '2024-01-15',
                'field_time' => '14:30',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);

        $submission = Submission::first();
        $this->assertEquals('text value', $submission->dynamic_data['field_text']);
        $this->assertEquals('test@example.com', $submission->dynamic_data['field_email']);
        $this->assertTrue($submission->dynamic_data['field_checkbox']);
    }
}