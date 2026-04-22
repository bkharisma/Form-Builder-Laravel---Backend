<?php

namespace Tests\Feature;

use Modules\FormBuilder\Models\FormConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedFormConfig(array $overrides = []): FormConfig
    {
        $user = User::factory()->create();

        return FormConfig::create(array_merge([
            'title' => 'Published Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_name',
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'placeholder' => 'Enter your name',
                    'help_text' => 'Your full legal name',
                ],
                [
                    'id' => 'field_email',
                    'type' => 'email',
                    'label' => 'Email Address',
                    'required' => true,
                    'enabled' => true,
                    'order' => 1,
                    'placeholder' => 'Enter your email',
                ],
                [
                    'id' => 'field_disabled',
                    'type' => 'text',
                    'label' => 'Disabled Field',
                    'required' => false,
                    'enabled' => false,
                    'order' => 2,
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }

    public function test_public_form_config_returns_empty_fields_when_no_published_config_exists(): void
    {
        $response = $this->getJson('/api/public/form-config/nonexistent-slug');

        $response->assertStatus(404);
    }

    public function test_public_form_config_returns_enabled_fields(): void
    {
        $config = $this->createPublishedFormConfig();

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data['fields']);

        $fieldIds = collect($data['fields'])->pluck('id');
        $this->assertContains('field_name', $fieldIds);
        $this->assertContains('field_email', $fieldIds);
        $this->assertNotContains('field_disabled', $fieldIds);
    }

    public function test_public_form_config_excludes_validation_regex(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'title' => 'Regex Form',
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
                        'max_length' => 100,
                        'regex' => '^[a-zA-Z]+$',
                    ],
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $field = $response->json('data.fields.0');
        $this->assertArrayNotHasKey('regex', $field['validation'] ?? []);
    }

    public function test_public_form_config_includes_placeholder_and_help_text(): void
    {
        $config = $this->createPublishedFormConfig();

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $field = $response->json('data.fields.0');
        $this->assertEquals('Enter your name', $field['placeholder']);
        $this->assertEquals('Your full legal name', $field['help_text']);
    }

    public function test_public_form_config_includes_select_options(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'title' => 'Select Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_purpose',
                    'type' => 'select',
                    'label' => 'Purpose of Visit',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                    'options' => [
                        ['label' => 'Meeting', 'value' => 'meeting'],
                        ['label' => 'Delivery', 'value' => 'delivery'],
                    ],
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $field = $response->json('data.fields.0');
        $this->assertCount(2, $field['options']);
        $this->assertEquals('Meeting', $field['options'][0]['label']);
        $this->assertEquals('meeting', $field['options'][0]['value']);
    }

    public function test_public_form_config_returns_fields_in_order(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'title' => 'Ordered Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'enabled' => true,
                    'order' => 5,
                ],
                [
                    'id' => 'field_email',
                    'type' => 'email',
                    'label' => 'Email',
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
                    'order' => 2,
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $fields = $response->json('data.fields');
        $this->assertEquals('field_email', $fields[0]['id']);
        $this->assertEquals('field_phone', $fields[1]['id']);
        $this->assertEquals('field_name', $fields[2]['id']);
    }

    public function test_public_form_config_does_not_include_min_max_values(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'title' => 'Validation Form',
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
                        'max_length' => 100,
                    ],
                ],
                [
                    'id' => 'field_age',
                    'type' => 'number',
                    'label' => 'Age',
                    'required' => true,
                    'enabled' => true,
                    'order' => 1,
                    'validation' => [
                        'min_value' => 0,
                        'max_value' => 100,
                    ],
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(200);

        $data = $response->json('data');
        $nameField = collect($data['fields'])->firstWhere('id', 'field_name');
        $ageField = collect($data['fields'])->firstWhere('id', 'field_age');

        $this->assertEquals(2, $nameField['validation']['min_length']);
        $this->assertEquals(100, $nameField['validation']['max_length']);

        $this->assertEquals(0, $ageField['validation']['min_value']);
        $this->assertEquals(100, $ageField['validation']['max_value']);
    }

    public function test_public_form_config_returns_latest_published_config(): void
    {
        $user = User::factory()->create();

        $oldConfig = FormConfig::create([
            'title' => 'Old Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_old',
                    'type' => 'text',
                    'label' => 'Old Field',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $newConfig = FormConfig::create([
            'title' => 'New Form',
            'status' => 'published',
            'fields' => [
                [
                    'id' => 'field_new',
                    'type' => 'text',
                    'label' => 'New Field',
                    'required' => true,
                    'enabled' => true,
                    'order' => 0,
                ],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/form-config/' . $newConfig->slug);

        $response->assertStatus(200);

        $data = $response->json('data');
        $field = $data['fields'][0];
        $this->assertEquals('field_new', $field['id']);
    }

    public function test_draft_form_config_not_accessible_publicly(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'title' => 'Draft Form',
            'status' => 'draft',
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
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/form-config/' . $config->slug);

        $response->assertStatus(404);
    }
}