<?php

namespace Modules\FormBuilder\Http\Requests;

use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\SubmissionFile;
use Modules\FormBuilder\Services\FormFieldValidationService;
use Modules\FormBuilder\Services\ConditionalLogicService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    protected ?FormConfig $formConfig = null;
    protected ?FormFieldValidationService $validationService = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $formSlug = $this->route('formSlug');
        $this->formConfig = FormConfig::where('slug', $formSlug)->first();

        $rules = [
            'dynamic_data' => ['required', 'array'],
            'cf_turnstile_response' => ['required', 'string'],
        ];

        if (!$this->formConfig) {
            return $rules;
        }

        $this->validationService = app(FormFieldValidationService::class);
        $conditionalLogicService = app(ConditionalLogicService::class);
        
        $dynamicData = $this->input('dynamic_data', []);

        $fields = collect($this->formConfig->fields)
            ->filter(fn($f) => $f['enabled'] ?? false)
            ->filter(fn($f) => $conditionalLogicService->isFieldVisible($f, $dynamicData));

        foreach ($fields as $field) {
            $fieldRules = [];

            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type']) {
                case 'text':
                case 'textarea':
                    $fieldRules[] = 'string';
                    if (isset($field['validation']['min_length'])) {
                        $fieldRules[] = 'min:' . $field['validation']['min_length'];
                    }
                    if (isset($field['validation']['max_length'])) {
                        $fieldRules[] = 'max:' . $field['validation']['max_length'];
                    }
                    break;

                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;

                case 'number':
                    $fieldRules[] = 'numeric';
                    if (isset($field['validation']['min_value'])) {
                        $fieldRules[] = 'min:' . $field['validation']['min_value'];
                    }
                    if (isset($field['validation']['max_value'])) {
                        $fieldRules[] = 'max:' . $field['validation']['max_value'];
                    }
                    break;

                case 'tel':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:20';
                    break;

                case 'select':
                    if (isset($field['options'])) {
                        $values = collect($field['options'])->pluck('value')->implode(',');
                        $fieldRules[] = 'in:' . $values;
                    }
                    break;

                case 'checkbox':
                    $fieldRules[] = 'boolean';
                    break;

                case 'date':
                    $fieldRules[] = 'date';
                    $fieldRules[] = 'date_format:Y-m-d';
                    break;

                case 'time':
                    $fieldRules[] = 'date_format:H:i';
                    break;

                case 'file_upload':
                case 'image_upload':
                    $fieldRules[] = 'string';
                    break;

                case 'signature':
                    $fieldRules[] = 'string';
                    break;

                case 'radio':
                    if (isset($field['options'])) {
                        $values = collect($field['options'])->pluck('value')->implode(',');
                        $fieldRules[] = 'in:' . $values;
                    }
                    $fieldRules[] = 'string';
                    break;
            }

            $rules["dynamic_data.{$field['id']}"] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $token = $this->input('cf_turnstile_response');

            $turnstileService = app(\App\Services\TurnstileService::class);

            if (!$turnstileService->verify($token, $this->ip())) {
                $validator->errors()->add(
                    'cf_turnstile_response',
                    $turnstileService->getErrorMessage() ?? 'Verification failed. Please try again.'
                );
            }

            if ($this->formConfig && $this->validationService) {
                $conditionalLogicService = app(ConditionalLogicService::class);
                $dynamicData = $this->input('dynamic_data', []);
                
                $fields = collect($this->formConfig->fields)
                    ->filter(fn($f) => $f['enabled'] ?? false)
                    ->filter(fn($f) => $conditionalLogicService->isFieldVisible($f, $dynamicData));

                foreach ($fields as $field) {
                    $value = $dynamicData[$field['id']] ?? null;

                    if (isset($field['validation']['regex']) && $value) {
                        if (!preg_match('/' . $field['validation']['regex'] . '/', $value)) {
                            $validator->errors()->add(
                                "dynamic_data.{$field['id']}",
                                "The {$field['label']} field format is invalid."
                            );
                        }
                    }

                    if (in_array($field['type'], ['file_upload', 'image_upload']) && !empty($value)) {
                        $fileRecord = SubmissionFile::find($value);
                        if (!$fileRecord) {
                            $validator->errors()->add(
                                "dynamic_data.{$field['id']}",
                                "The uploaded file for {$field['label']} is invalid."
                            );
                        }
                    }

                    if ($field['type'] === 'signature' && !empty($value)) {
                        if (!is_string($value) || !str_starts_with($value, 'data:image/')) {
                            $validator->errors()->add(
                                "dynamic_data.{$field['id']}",
                                "The signature for {$field['label']} is invalid."
                            );
                        }
                    }
                }
            }
        });
    }

    public function getFormConfig(): ?FormConfig
    {
        return $this->formConfig;
    }
}
