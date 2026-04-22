<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_form_config_validation_empty_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [],
            ]);

        $response->assertStatus(201);
    }

    public function test_form_config_validation_missing_field_properties(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'type' => 'text',
                        'label' => 'Name',
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_form_config_validation_numeric_values(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_number',
                        'type' => 'number',
                        'label' => 'Age',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'min_value' => 'not-a-number',
                            'max_value' => 'not-a-number',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_form_config_validation_negative_values(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_number',
                        'type' => 'number',
                        'label' => 'Temperature',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'min_value' => -100,
                            'max_value' => 100,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $config = FormConfig::first();
        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_number' => '-50',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_form_config_validation_decimal_numbers(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_price',
                        'type' => 'number',
                        'label' => 'Price',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'min_value' => 0.01,
                            'max_value' => 999.99,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $config = FormConfig::first();
        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_price' => '19.99',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_form_config_validation_special_characters_in_id(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field-name',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form 2',
                'fields' => [
                    [
                        'id' => 'field name',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form 3',
                'fields' => [
                    [
                        'id' => 'field_name',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_form_config_validation_long_label(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_1',
                        'type' => 'text',
                        'label' => str_repeat('a', 256),
                        'required' => true,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_form_config_validation_whitespace_in_label(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_1',
                        'type' => 'text',
                        'label' => '   ',
                        'required' => true,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_form_config_validation_textarea_with_validation(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_message',
                        'type' => 'textarea',
                        'label' => 'Message',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'min_length' => 10,
                            'max_length' => 500,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $config = FormConfig::first();
        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_message' => str_repeat('a', 501),
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_form_config_validation_select_with_many_options(): void
    {
        $options = [];
        for ($i = 1; $i <= 100; $i++) {
            $options[] = ['label' => "Option $i", 'value' => "opt_$i"];
        }

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_select',
                        'type' => 'select',
                        'label' => 'Options',
                        'required' => true,
                        'enabled' => true,
                        'options' => $options,
                    ],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_form_config_validation_email_cannot_have_regex(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_email',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'regex' => '.*',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_form_config_validation_regex_pattern(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_code',
                        'type' => 'text',
                        'label' => 'Code',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'regex' => '[invalid',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form 2',
                'fields' => [
                    [
                        'id' => 'field_code',
                        'type' => 'text',
                        'label' => 'Code',
                        'required' => true,
                        'enabled' => true,
                        'validation' => [
                            'regex' => '^[A-Z]{3}[0-9]{3}$',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_submission_validates_checkbox_type(): void
    {
        $config = FormConfig::create([
            'title' => 'Checkbox Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_agree',
                    'type' => 'checkbox',
                    'label' => 'I Agree',
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
                'field_agree' => true,
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_agree' => 'not-a-boolean',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_submission_handles_missing_optional_fields(): void
    {
        $config = FormConfig::create([
            'title' => 'Optional Fields Form',
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
                    'id' => 'field_phone',
                    'type' => 'tel',
                    'label' => 'Phone',
                    'required' => false,
                    'enabled' => true,
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

        $submission = Submission::first();
        $this->assertEquals('John Doe', $submission->dynamic_data['field_name']);
        $this->assertArrayNotHasKey('field_phone', $submission->dynamic_data);
    }

    public function test_submission_handles_empty_string_fields(): void
    {
        $config = FormConfig::create([
            'title' => 'Empty String Form',
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
                    'id' => 'field_notes',
                    'type' => 'textarea',
                    'label' => 'Notes',
                    'required' => false,
                    'enabled' => true,
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
                'field_notes' => '',
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_submission_handles_unicode_characters(): void
    {
        $config = FormConfig::create([
            'title' => 'Unicode Form',
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
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $unicodeName = '张伟 émojis 🎉';

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => $unicodeName,
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('submissions', [
            'dynamic_data->field_name' => $unicodeName,
        ]);
    }

    public function test_submission_handles_max_length_exactly(): void
    {
        $config = FormConfig::create([
            'title' => 'Max Length Form',
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
                        'max_length' => 10,
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
                'field_name' => str_repeat('a', 10),
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(201);

        $mockService = $this->mock(TurnstileService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/submissions/' . $config->slug, [
            'dynamic_data' => [
                'field_name' => str_repeat('a', 11),
            ],
            'cf_turnstile_response' => 'valid-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_public_form_config_excludes_regex(): void
    {
        FormConfig::create([
            'title' => 'Regex Public Form',
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
                        'min_length' => 6,
                    ],
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $config = FormConfig::first();

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $field = $response->json('data.fields.0');

        if (isset($field['validation'])) {
            $this->assertArrayNotHasKey('regex', $field['validation']);
            $this->assertEquals(6, $field['validation']['min_length']);
        }
    }

    public function test_form_config_order_preserved(): void
    {
        $fields = [
            ['id' => 'field_c', 'type' => 'text', 'label' => 'C', 'required' => true, 'enabled' => true, 'order' => 2],
            ['id' => 'field_a', 'type' => 'text', 'label' => 'A', 'required' => false, 'enabled' => true, 'order' => 0],
            ['id' => 'field_b', 'type' => 'text', 'label' => 'B', 'required' => false, 'enabled' => true, 'order' => 1],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Ordered Form',
                'fields' => $fields,
            ]);

        $response->assertStatus(201);

        $storedFields = FormConfig::first()->fields;

        $this->assertEquals('field_c', $storedFields[0]['id']);
        $this->assertEquals('field_a', $storedFields[1]['id']);
        $this->assertEquals('field_b', $storedFields[2]['id']);
    }
}