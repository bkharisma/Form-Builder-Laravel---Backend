<?php

namespace Tests\Unit;

use Modules\FormBuilder\Services\FormFieldValidationService;
use Tests\TestCase;

class FormFieldValidationServiceTest extends TestCase
{
    private FormFieldValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormFieldValidationService();
    }

    public function test_validate_fields_returns_empty_errors_for_valid_fields(): void
    {
        $fields = [
            [
                'id' => 'field_name',
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
                'enabled' => true,
            ],
        ];

        $errors = $this->service->validateFields($fields);

        $this->assertEmpty($errors);
    }

    public function test_validate_fields_returns_empty_errors_for_empty_array(): void
    {
        $errors = $this->service->validateFields([]);

        $this->assertEmpty($errors);
    }

    public function test_validate_fields_requires_at_least_one_required_field(): void
    {
        $fields = [
            [
                'id' => 'field_name',
                'type' => 'text',
                'label' => 'Name',
                'required' => false,
                'enabled' => true,
            ],
        ];

        $errors = $this->service->validateFields($fields);

        $this->assertArrayHasKey('fields', $errors);
        $this->assertContains('At least one field must be required.', $errors['fields']);
    }

    public function test_validate_fields_detects_duplicate_ids(): void
    {
        $fields = [
            [
                'id' => 'field_name',
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
                'enabled' => true,
            ],
            [
                'id' => 'field_name',
                'type' => 'email',
                'label' => 'Email',
                'required' => false,
                'enabled' => true,
            ],
        ];

        $errors = $this->service->validateFields($fields);

        $this->assertArrayHasKey('fields', $errors);
    }

    public function test_validate_field_rejects_invalid_type(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'invalid_type',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('type', $errors);
    }

    public function test_validate_field_accepts_valid_types(): void
    {
        $validTypes = ['text', 'textarea', 'email', 'number', 'tel', 'select', 'checkbox', 'date', 'time'];

        foreach ($validTypes as $type) {
            $field = [
                'id' => 'field_test',
                'type' => $type,
                'label' => 'Test',
                'required' => true,
                'enabled' => true,
            ];

            $errors = $this->service->validateField($field, 0);

            $this->assertArrayNotHasKey('type', $errors, "Type {$type} should be valid");
        }
    }

    public function test_validate_field_requires_label(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => '',
            'required' => true,
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('label', $errors);
    }

    public function test_validate_field_rejects_label_over_255_chars(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => str_repeat('a', 256),
            'required' => true,
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('label', $errors);
    }

    public function test_validate_field_requires_id(): void
    {
        $field = [
            'id' => '',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('id', $errors);
    }

    public function test_validate_field_rejects_invalid_id_format(): void
    {
        $field = [
            'id' => 'invalid-id-with-dashes',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('id', $errors);

        $field['id'] = 'invalid id with spaces';
        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('id', $errors);
    }

    public function test_validate_field_accepts_valid_id_format(): void
    {
        $validIds = ['field_name', 'field123', 'FIELD_NAME', 'field_name_123'];

        foreach ($validIds as $id) {
            $field = [
                'id' => $id,
                'type' => 'text',
                'label' => 'Test',
                'required' => true,
                'enabled' => true,
            ];

            $errors = $this->service->validateField($field, 0);

            $this->assertArrayNotHasKey('id', $errors, "ID {$id} should be valid");
        }
    }

    public function test_validate_field_requires_required_flag(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('required', $errors);
    }

    public function test_validate_field_requires_enabled_flag(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('enabled', $errors);
    }

    public function test_validate_field_rejects_placeholder_over_255_chars(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'placeholder' => str_repeat('a', 256),
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('placeholder', $errors);
    }

    public function test_validate_field_rejects_help_text_over_1000_chars(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'help_text' => str_repeat('a', 1001),
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('help_text', $errors);
    }

    public function test_validate_text_min_max_length(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'min_length' => 5,
                'max_length' => 100,
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayNotHasKey('validation.min_length', $errors);
        $this->assertArrayNotHasKey('validation.max_length', $errors);
    }

    public function test_validate_text_rejects_min_over_max(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'min_length' => 100,
                'max_length' => 10,
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('validation.min_length', $errors);
    }

    public function test_validate_number_min_max_value(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'number',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'min_value' => 0,
                'max_value' => 100,
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayNotHasKey('validation.min_value', $errors);
        $this->assertArrayNotHasKey('validation.max_value', $errors);
    }

    public function test_validate_number_rejects_min_over_max(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'number',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'min_value' => 100,
                'max_value' => 10,
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('validation.min_value', $errors);
    }

    public function test_validate_select_requires_options(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'select',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('options', $errors);
    }

    public function test_validate_select_requires_at_least_one_option(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'select',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'options' => [],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('options', $errors);
    }

    public function test_validate_select_requires_option_label_and_value(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'select',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'options' => [
                ['label' => '', 'value' => 'val1'],
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('options.0.label', $errors);

        $field['options'] = [
            ['label' => 'Label', 'value' => ''],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('options.0.value', $errors);
    }

    public function test_validate_select_requires_unique_values(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'select',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'options' => [
                ['label' => 'Option 1', 'value' => 'same'],
                ['label' => 'Option 2', 'value' => 'same'],
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('options', $errors);
    }

    public function test_validate_email_rejects_regex(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'email',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'regex' => '.*',
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('validation.regex', $errors);
    }

    public function test_validate_regex_must_be_valid(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'regex' => '[invalid-regex',
            ],
        ];

        $errors = $this->service->validateField($field, 0);

        $this->assertArrayHasKey('validation.regex', $errors);

        $field['validation']['regex'] = '^[a-z]+$';
        $errors = $this->service->validateField($field, 0);

        $this->assertArrayNotHasKey('validation.regex', $errors);
    }

    public function test_validate_field_value_required(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'label' => 'Test',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertFalse($this->service->validateFieldValue($field, ''));
        $this->assertFalse($this->service->validateFieldValue($field, null));
        $this->assertTrue($this->service->validateFieldValue($field, 'value'));
    }

    public function test_validate_field_value_optional(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'required' => false,
            'enabled' => true,
        ];

        $this->assertTrue($this->service->validateFieldValue($field, ''));
        $this->assertTrue($this->service->validateFieldValue($field, null));
        $this->assertTrue($this->service->validateFieldValue($field, 'value'));
    }

    public function test_validate_field_value_email(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'email',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertFalse($this->service->validateFieldValue($field, 'not-an-email'));
        $this->assertTrue($this->service->validateFieldValue($field, 'test@example.com'));
    }

    public function test_validate_field_value_number(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'number',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertFalse($this->service->validateFieldValue($field, 'not-a-number'));
        $this->assertTrue($this->service->validateFieldValue($field, '42'));
        $this->assertTrue($this->service->validateFieldValue($field, 42));
    }

    public function test_validate_field_value_number_with_validation(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'number',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'min_value' => 10,
                'max_value' => 100,
            ],
        ];

        $this->assertFalse($this->service->validateFieldValue($field, '5'));
        $this->assertTrue($this->service->validateFieldValue($field, '50'));
        $this->assertFalse($this->service->validateFieldValue($field, '150'));
    }

    public function test_validate_field_value_text_with_validation(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'text',
            'required' => true,
            'enabled' => true,
            'validation' => [
                'min_length' => 5,
                'max_length' => 10,
                'regex' => '^[a-z]+$',
            ],
        ];

        $this->assertFalse($this->service->validateFieldValue($field, 'abc'));
        $this->assertFalse($this->service->validateFieldValue($field, 'abcdefghijk'));
        $this->assertFalse($this->service->validateFieldValue($field, 'ABC123'));
        $this->assertTrue($this->service->validateFieldValue($field, 'abcde'));
    }

    public function test_validate_field_value_select(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'select',
            'required' => true,
            'enabled' => true,
            'options' => [
                ['label' => 'Option 1', 'value' => 'opt1'],
                ['label' => 'Option 2', 'value' => 'opt2'],
            ],
        ];

        $this->assertFalse($this->service->validateFieldValue($field, 'invalid'));
        $this->assertTrue($this->service->validateFieldValue($field, 'opt1'));
        $this->assertTrue($this->service->validateFieldValue($field, 'opt2'));
    }

    public function test_validate_field_value_checkbox(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'checkbox',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertTrue($this->service->validateFieldValue($field, true));
        $this->assertTrue($this->service->validateFieldValue($field, false));
        $this->assertTrue($this->service->validateFieldValue($field, 0));
        $this->assertTrue($this->service->validateFieldValue($field, 1));
        $this->assertTrue($this->service->validateFieldValue($field, '0'));
        $this->assertTrue($this->service->validateFieldValue($field, '1'));
        $this->assertFalse($this->service->validateFieldValue($field, 'invalid'));
    }

    public function test_validate_field_value_date(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'date',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertFalse($this->service->validateFieldValue($field, 'not-a-date'));
        $this->assertFalse($this->service->validateFieldValue($field, '01-01-2024'));
        $this->assertTrue($this->service->validateFieldValue($field, '2024-01-01'));
    }

    public function test_validate_field_value_time(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'time',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertFalse($this->service->validateFieldValue($field, 'not-a-time'));
        $this->assertTrue($this->service->validateFieldValue($field, '10:30'));
        $this->assertTrue($this->service->validateFieldValue($field, '23:59'));
    }

    public function test_validate_field_value_tel(): void
    {
        $field = [
            'id' => 'field_test',
            'type' => 'tel',
            'required' => true,
            'enabled' => true,
        ];

        $this->assertTrue($this->service->validateFieldValue($field, '123-456-7890'));
        $this->assertFalse($this->service->validateFieldValue($field, str_repeat('a', 21)));
    }
}