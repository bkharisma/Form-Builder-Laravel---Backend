<?php

namespace Modules\FormBuilder\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormFieldValidationService
{
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'email',
        'number',
        'tel',
        'select',
        'checkbox',
        'date',
        'time',
        'file_upload',
        'image_upload',
        'signature',
        'radio',
    ];

    public function validateFields(array $fields): array
    {
        $errors = [];

        if (empty($fields)) {
            return $errors;
        }

        foreach ($fields as $index => $field) {
            $fieldErrors = $this->validateField($field, $index);
            if (!empty($fieldErrors)) {
                $errors["fields.{$index}"] = $fieldErrors;
            }
        }

        $hasRequired = collect($fields)->contains('required', true);
        if (!$hasRequired) {
            $errors['fields'] = ['At least one field must be required.'];
        }

        $fieldIds = collect($fields)->pluck('id')->filter()->toArray();
        if (count($fieldIds) !== count(array_unique($fieldIds))) {
            $errors['fields'][] = ['Field IDs must be unique.'];
        }

        return $errors;
    }

    public function validateField(array $field, int $index): array
    {
        $errors = [];

        if (!isset($field['type']) || !in_array($field['type'], self::FIELD_TYPES)) {
            $errors['type'] = ['Invalid field type. Must be one of: ' . implode(', ', self::FIELD_TYPES)];
        }

        if (!isset($field['label']) || empty(trim($field['label']))) {
            $errors['label'] = ['Field label is required.'];
        } elseif (strlen($field['label']) > 255) {
            $errors['label'] = ['Field label must not exceed 255 characters.'];
        }

        if (!isset($field['id']) || empty(trim($field['id']))) {
            $errors['id'] = ['Field ID is required.'];
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $field['id'])) {
            $errors['id'] = ['Field ID must only contain letters, numbers, and underscores.'];
        }

        if (!isset($field['required']) || !is_bool($field['required'])) {
            $errors['required'] = ['Field required flag must be a boolean.'];
        }

        if (!isset($field['enabled']) || !is_bool($field['enabled'])) {
            $errors['enabled'] = ['Field enabled flag must be a boolean.'];
        }

        if (isset($field['placeholder']) && strlen($field['placeholder']) > 255) {
            $errors['placeholder'] = ['Placeholder must not exceed 255 characters.'];
        }

        if (isset($field['help_text']) && strlen($field['help_text']) > 1000) {
            $errors['help_text'] = ['Help text must not exceed 1000 characters.'];
        }

        $typeErrors = $this->validateTypeSpecificProperties($field);
        $errors = array_merge($errors, $typeErrors);

        return $errors;
    }

    protected function validateTypeSpecificProperties(array $field): array
    {
        $errors = [];
        $type = $field['type'] ?? null;

        switch ($type) {
            case 'text':
            case 'textarea':
                $errors = $this->validateTextProperties($field);
                break;

            case 'number':
                $errors = $this->validateNumberProperties($field);
                break;

            case 'select':
                $errors = $this->validateSelectProperties($field);
                break;

            case 'email':
                if (isset($field['validation']['regex'])) {
                    $errors['validation.regex'] = ['Regex validation is not supported for email fields.'];
                }
                break;

            case 'file_upload':
                $errors = $this->validateFileUploadProperties($field);
                break;

            case 'image_upload':
                $errors = $this->validateImageUploadProperties($field);
                break;

            case 'radio':
                $errors = $this->validateRadioProperties($field);
                break;
        }

        if (isset($field['validation']['regex'])) {
            if (!$this->isValidRegex($field['validation']['regex'])) {
                $errors['validation.regex'] = ['Invalid regex pattern.'];
            }
        }

        return $errors;
    }

    protected function validateTextProperties(array $field): array
    {
        $errors = [];

        if (isset($field['validation']['min_length'])) {
            if (!is_int($field['validation']['min_length']) || $field['validation']['min_length'] < 0) {
                $errors['validation.min_length'] = ['Minimum length must be a non-negative integer.'];
            }
        }

        if (isset($field['validation']['max_length'])) {
            if (!is_int($field['validation']['max_length']) || $field['validation']['max_length'] < 1) {
                $errors['validation.max_length'] = ['Maximum length must be a positive integer.'];
            }
        }

        if (isset($field['validation']['min_length']) && isset($field['validation']['max_length'])) {
            if ($field['validation']['min_length'] > $field['validation']['max_length']) {
                $errors['validation.min_length'] = ['Minimum length cannot exceed maximum length.'];
            }
        }

        return $errors;
    }

    protected function validateNumberProperties(array $field): array
    {
        $errors = [];

        if (isset($field['validation']['min_value'])) {
            if (!is_numeric($field['validation']['min_value'])) {
                $errors['validation.min_value'] = ['Minimum value must be a number.'];
            }
        }

        if (isset($field['validation']['max_value'])) {
            if (!is_numeric($field['validation']['max_value'])) {
                $errors['validation.max_value'] = ['Maximum value must be a number.'];
            }
        }

        if (isset($field['validation']['min_value']) && isset($field['validation']['max_value'])) {
            if ($field['validation']['min_value'] > $field['validation']['max_value']) {
                $errors['validation.min_value'] = ['Minimum value cannot exceed maximum value.'];
            }
        }

        return $errors;
    }

    protected function validateSelectProperties(array $field): array
    {
        $errors = [];

        if (!isset($field['options']) || !is_array($field['options']) || empty($field['options'])) {
            $errors['options'] = ['Select field must have at least one option.'];
            return $errors;
        }

        foreach ($field['options'] as $index => $option) {
            if (!isset($option['label']) || empty(trim($option['label']))) {
                $errors["options.{$index}.label"] = ['Option label is required.'];
            }
            if (!isset($option['value']) || empty(trim($option['value']))) {
                $errors["options.{$index}.value"] = ['Option value is required.'];
            }
        }

        $values = collect($field['options'])->pluck('value')->toArray();
        if (count($values) !== count(array_unique($values))) {
            $errors['options'] = ['Option values must be unique.'];
        }

        return $errors;
    }

    protected function validateFileUploadProperties(array $field): array
    {
        $errors = [];

        if (isset($field['validation']['max_file_size'])) {
            $maxSize = $field['validation']['max_file_size'];
            if (!is_int($maxSize) || $maxSize < 1 || $maxSize > 51200) {
                $errors['validation.max_file_size'] = ['Max file size must be an integer between 1 and 51200 KB.'];
            }
        }

        if (isset($field['validation']['allowed_extensions'])) {
            $extensions = $field['validation']['allowed_extensions'];
            if (!is_array($extensions)) {
                $errors['validation.allowed_extensions'] = ['Allowed extensions must be an array.'];
            } else {
                foreach ($extensions as $index => $ext) {
                    if (!is_string($ext) || !preg_match('/^\w+$/', $ext)) {
                        $errors["validation.allowed_extensions.{$index}"] = ['Each extension must be a valid string.'];
                    }
                }
            }
        }

        return $errors;
    }

    protected function validateImageUploadProperties(array $field): array
    {
        $errors = [];

        if (isset($field['validation']['max_file_size'])) {
            $maxSize = $field['validation']['max_file_size'];
            if (!is_int($maxSize) || $maxSize < 1 || $maxSize > 10240) {
                $errors['validation.max_file_size'] = ['Max file size must be an integer between 1 and 10240 KB.'];
            }
        }

        return $errors;
    }

    protected function validateRadioProperties(array $field): array
    {
        $errors = [];

        if (!isset($field['options']) || !is_array($field['options']) || empty($field['options'])) {
            $errors['options'] = ['Radio field must have at least one option.'];
            return $errors;
        }

        foreach ($field['options'] as $index => $option) {
            if (!isset($option['label']) || empty(trim($option['label']))) {
                $errors["options.{$index}.label"] = ['Option label is required.'];
            }
            if (!isset($option['value']) || empty(trim($option['value']))) {
                $errors["options.{$index}.value"] = ['Option value is required.'];
            }
        }

        $values = collect($field['options'])->pluck('value')->toArray();
        if (count($values) !== count(array_unique($values))) {
            $errors['options'] = ['Option values must be unique.'];
        }

        if (isset($field['layout_direction'])) {
            if (!in_array($field['layout_direction'], ['horizontal', 'vertical'])) {
                $errors['layout_direction'] = ['Layout direction must be horizontal or vertical.'];
            }
        }

        if (isset($field['default_value'])) {
            if (!in_array($field['default_value'], $values)) {
                $errors['default_value'] = ['Default value must match one of the option values.'];
            }
        }

        return $errors;
    }

    protected function isValidRegex(string $pattern): bool
    {
        try {
            preg_match('/' . $pattern . '/', '');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function validateFieldValue(array $field, $value): bool
    {
        if ($field['required']) {
            $isEmpty = match ($field['type']) {
                'checkbox' => !is_bool($value) && !in_array($value, [0, 1, '0', '1'], true),
                default => empty($value),
            };
            if ($isEmpty) {
                return false;
            }
        }

        if (!$field['required']) {
            $isEmpty = match ($field['type']) {
                'checkbox' => false,
                default => empty($value),
            };
            if ($isEmpty) {
                return true;
            }
        }

        switch ($field['type']) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'number':
                if (!is_numeric($value)) {
                    return false;
                }
                if (isset($field['validation']['min_value']) && $value < $field['validation']['min_value']) {
                    return false;
                }
                if (isset($field['validation']['max_value']) && $value > $field['validation']['max_value']) {
                    return false;
                }
                return true;

            case 'tel':
                return strlen($value) <= 20;

            case 'text':
            case 'textarea':
                if (isset($field['validation']['min_length']) && strlen($value) < $field['validation']['min_length']) {
                    return false;
                }
                if (isset($field['validation']['max_length']) && strlen($value) > $field['validation']['max_length']) {
                    return false;
                }
                if (isset($field['validation']['regex'])) {
                    return preg_match('/' . $field['validation']['regex'] . '/', $value) === 1;
                }
                return true;

            case 'select':
                $validValues = collect($field['options'] ?? [])->pluck('value')->toArray();
                return in_array($value, $validValues);

            case 'checkbox':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', true, false]);

            case 'date':
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

            case 'time':
                return preg_match('/^\d{2}:\d{2}$/', $value) === 1;

            case 'file_upload':
            case 'image_upload':
                return is_string($value) && !empty($value);

            case 'signature':
                if (empty($value)) {
                    return true;
                }
                return is_string($value) && str_starts_with($value, 'data:image/');

            case 'radio':
                $validValues = collect($field['options'] ?? [])->pluck('value')->toArray();
                return in_array($value, $validValues);
        }

        return true;
    }
}
