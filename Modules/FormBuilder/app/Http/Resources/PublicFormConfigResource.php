<?php

namespace Modules\FormBuilder\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicFormConfigResource extends JsonResource
{
    public function toArray($request): array
    {
        $fields = collect($this->fields)
            ->filter(fn($field) => $field['enabled'] ?? false)
            ->sortBy('order')
            ->map(function ($field) {
                $publicField = [
                    'id' => $field['id'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'required' => $field['required'],
                    'enabled' => $field['enabled'] ?? true,
                    'is_unique' => $field['is_unique'] ?? false,
                    'placeholder' => $field['placeholder'] ?? null,
                    'help_text' => $field['help_text'] ?? null,
                    'order' => $field['order'] ?? 0,
                ];

                if (isset($field['conditional_logic'])) {
                    $publicField['conditional_logic'] = $field['conditional_logic'];
                }

                if (in_array($field['type'], ['text', 'textarea', 'number'])) {
                    $publicField['validation'] = [];
                    
                    if (isset($field['validation']['min_length'])) {
                        $publicField['validation']['min_length'] = $field['validation']['min_length'];
                    }
                    if (isset($field['validation']['max_length'])) {
                        $publicField['validation']['max_length'] = $field['validation']['max_length'];
                    }
                    if (isset($field['validation']['min_value'])) {
                        $publicField['validation']['min_value'] = $field['validation']['min_value'];
                    }
                    if (isset($field['validation']['max_value'])) {
                        $publicField['validation']['max_value'] = $field['validation']['max_value'];
                    }
                }

                if (in_array($field['type'], ['file_upload', 'image_upload']) && isset($field['validation'])) {
                    $publicField['validation'] = [];
                    if (isset($field['validation']['max_file_size'])) {
                        $publicField['validation']['max_file_size'] = $field['validation']['max_file_size'];
                    }
                    if (isset($field['validation']['allowed_extensions'])) {
                        $publicField['validation']['allowed_extensions'] = $field['validation']['allowed_extensions'];
                    }
                }

                if (in_array($field['type'], ['select', 'radio']) && isset($field['options'])) {
                    $publicField['options'] = $field['options'];
                }

                if ($field['type'] === 'radio') {
                    $publicField['layout_direction'] = $field['layout_direction'] ?? 'vertical';
                    if (isset($field['default_value'])) {
                        $publicField['default_value'] = $field['default_value'];
                    }
                }

                return $publicField;
            })
            ->values()
            ->toArray();

        return [
            'title' => $this->title,
            'description' => $this->description,
            'fields' => $fields,
        ];
    }
}
