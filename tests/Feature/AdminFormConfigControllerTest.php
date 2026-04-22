<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFormConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_index_returns_empty_list_when_no_configs_exist(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/form-configs');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_returns_paginated_configs(): void
    {
        FormConfig::create([
            'title' => 'First Form',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        FormConfig::create([
            'title' => 'Second Form',
            'fields' => [
                ['id' => 'field_2', 'type' => 'email', 'label' => 'Email', 'required' => true, 'enabled' => true],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/form-configs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_includes_user_relationships(): void
    {
        FormConfig::create([
            'title' => 'Test Form',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/form-configs');

        $response->assertStatus(200);

        $firstItem = $response->json('data.0');
        $this->assertEquals($this->admin->id, $firstItem['created_by']['id']);
        $this->assertEquals($this->admin->id, $firstItem['updated_by']['id']);
    }

    public function test_store_creates_new_config(): void
    {
        $fields = [
            [
                'id' => 'field_name',
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'enabled' => true,
                'order' => 0,
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Contact Form',
                'fields' => $fields,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Contact Form',
                    'fields' => $fields,
                ],
            ]);

        $this->assertDatabaseHas('form_configs', [
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_store_validates_field_structure(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'test',
                        'type' => 'invalid_type',
                        'label' => '',
                        'required' => 'not_a_bool',
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_at_least_one_required_field(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_1',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => false,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_update_modifies_existing_config(): void
    {
        $config = FormConfig::create([
            'title' => 'Original Form',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Old Name', 'required' => true, 'enabled' => true],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $newFields = [
            [
                'id' => 'field_1',
                'type' => 'text',
                'label' => 'New Name',
                'required' => true,
                'enabled' => true,
                'order' => 0,
            ],
            [
                'id' => 'field_2',
                'type' => 'email',
                'label' => 'Email',
                'required' => true,
                'enabled' => true,
                'order' => 1,
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/form-configs/{$config->slug}", [
                'fields' => $newFields,
            ]);

        $response->assertStatus(200);

        $config->refresh();
        $this->assertCount(2, $config->fields);
        $this->assertEquals('New Name', $config->fields[0]['label']);
    }

    public function test_update_validates_field_structure(): void
    {
        $config = FormConfig::create([
            'title' => 'Test Form',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/form-configs/{$config->slug}", [
                'fields' => [
                    [
                        'id' => 'test',
                        'type' => 'invalid',
                        'label' => 'Test',
                        'required' => true,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_destroy_deletes_config(): void
    {
        $config = FormConfig::create([
            'title' => 'Delete Me',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name', 'required' => true, 'enabled' => true],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/form-configs/{$config->slug}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('form_configs', ['id' => $config->id]);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/admin/form-configs');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_can_access_own_forms(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/form-configs');

        $response->assertStatus(200);
    }

    public function test_store_validates_duplicate_field_ids(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_name',
                        'type' => 'text',
                        'label' => 'Name 1',
                        'required' => true,
                        'enabled' => true,
                    ],
                    [
                        'id' => 'field_name',
                        'type' => 'text',
                        'label' => 'Name 2',
                        'required' => false,
                        'enabled' => true,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields']);
    }

    public function test_store_validates_select_field_options(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'Test Form',
                'fields' => [
                    [
                        'id' => 'field_purpose',
                        'type' => 'select',
                        'label' => 'Purpose',
                        'required' => true,
                        'enabled' => true,
                        'options' => [],
                    ],
                ],
            ]);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('fields.0', $errors);
        $this->assertStringContainsString('Select field must have at least one option', json_encode($errors));
    }

    public function test_store_accepts_all_field_types(): void
    {
        $fields = [
            ['id' => 'field_text', 'type' => 'text', 'label' => 'Text', 'required' => false, 'enabled' => true],
            ['id' => 'field_textarea', 'type' => 'textarea', 'label' => 'Textarea', 'required' => true, 'enabled' => true],
            ['id' => 'field_email', 'type' => 'email', 'label' => 'Email', 'required' => false, 'enabled' => true],
            ['id' => 'field_number', 'type' => 'number', 'label' => 'Number', 'required' => false, 'enabled' => true],
            ['id' => 'field_tel', 'type' => 'tel', 'label' => 'Tel', 'required' => false, 'enabled' => true],
            ['id' => 'field_select', 'type' => 'select', 'label' => 'Select', 'required' => false, 'enabled' => true, 'options' => [['label' => 'A', 'value' => 'a']]],
            ['id' => 'field_checkbox', 'type' => 'checkbox', 'label' => 'Checkbox', 'required' => false, 'enabled' => true],
            ['id' => 'field_date', 'type' => 'date', 'label' => 'Date', 'required' => false, 'enabled' => true],
            ['id' => 'field_time', 'type' => 'time', 'label' => 'Time', 'required' => false, 'enabled' => true],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/form-configs', [
                'title' => 'All Fields Form',
                'fields' => $fields,
            ]);

        $response->assertStatus(201);
    }

    public function test_index_includes_all_field_properties(): void
    {
        FormConfig::create([
            'title' => 'Test Form',
            'fields' => [
                [
                    'id' => 'field_name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'placeholder' => 'Enter name',
                    'help_text' => 'Help',
                    'validation' => ['min_length' => 2, 'max_length' => 100],
                ],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/form-configs');

        $response->assertStatus(200);

        $field = $response->json('data.0.fields.0');
        $this->assertArrayHasKey('placeholder', $field);
        $this->assertArrayHasKey('help_text', $field);
        $this->assertArrayHasKey('validation', $field);
        $this->assertEquals(2, $field['validation']['min_length']);
    }
}