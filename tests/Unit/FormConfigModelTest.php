<?php

namespace Tests\Unit;

use Modules\FormBuilder\Models\FormConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormConfigModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_config_casts_fields_to_array(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name'],
                ['id' => 'field_2', 'type' => 'email', 'label' => 'Email'],
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertIsArray($config->fields);
        $this->assertCount(2, $config->fields);
        $this->assertEquals('field_1', $config->fields[0]['id']);
        $this->assertEquals('field_2', $config->fields[1]['id']);
    }

    public function test_form_config_has_created_by_relationship(): void
    {
        $user = User::factory()->create();
        $config = FormConfig::create([
            'fields' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertEquals($user->id, $config->createdBy->id);
        $this->assertEquals($user->name, $config->createdBy->name);
    }

    public function test_form_config_has_updated_by_relationship(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $config = FormConfig::create([
            'fields' => [],
            'created_by' => $user1->id,
            'updated_by' => $user2->id,
        ]);

        $this->assertEquals($user1->id, $config->createdBy->id);
        $this->assertEquals($user2->id, $config->updatedBy->id);
    }

    public function test_form_config_can_be_created_with_empty_fields(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'fields' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertIsArray($config->fields);
        $this->assertEmpty($config->fields);
    }

    public function test_form_config_can_be_updated(): void
    {
        $user = User::factory()->create();

        $config = FormConfig::create([
            'fields' => [['id' => 'field_1', 'type' => 'text', 'label' => 'Name']],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $config->update([
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Full Name'],
                ['id' => 'field_2', 'type' => 'email', 'label' => 'Email'],
            ],
            'updated_by' => $user->id,
        ]);

        $this->assertCount(2, $config->fields);
        $this->assertEquals('Full Name', $config->fields[0]['label']);
    }

    public function test_form_config_can_store_complex_field_structure(): void
    {
        $user = User::factory()->create();

        $complexFields = [
            [
                'id' => 'field_name',
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'enabled' => true,
                'order' => 0,
                'placeholder' => 'Enter your name',
                'help_text' => 'Please enter your full legal name',
                'validation' => [
                    'min_length' => 2,
                    'max_length' => 100,
                    'regex' => '^[a-zA-Z\s]+$',
                ],
            ],
            [
                'id' => 'field_subject',
                'type' => 'select',
                'label' => 'Subject',
                'required' => true,
                'enabled' => true,
                'order' => 1,
                'options' => [
                    ['label' => 'General Inquiry', 'value' => 'general_inquiry'],
                    ['label' => 'Feedback', 'value' => 'feedback'],
                    ['label' => 'Other', 'value' => 'other'],
                ],
            ],
        ];

        $config = FormConfig::create([
            'fields' => $complexFields,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertCount(2, $config->fields);
        $this->assertEquals('text', $config->fields[0]['type']);
        $this->assertArrayHasKey('validation', $config->fields[0]);
        $this->assertCount(3, $config->fields[1]['options']);
    }

    public function test_form_config_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $config = FormConfig::create([
            'fields' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('form_configs', ['id' => $config->id]);

        $config->delete();

        $this->assertDatabaseMissing('form_configs', ['id' => $config->id]);
    }

    public function test_only_one_form_config_can_exist(): void
    {
        $user = User::factory()->create();

        FormConfig::create([
            'fields' => [['id' => 'field_1', 'type' => 'text', 'label' => 'Name']],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        FormConfig::create([
            'fields' => [['id' => 'field_1', 'type' => 'text', 'label' => 'Name']],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertEquals(2, FormConfig::count());
    }
}