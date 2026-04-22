<?php

namespace Modules\FormBuilder\Http\Requests;

use Modules\FormBuilder\Services\FormFieldValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class FormConfigRequest extends FormRequest
{
    protected ?FormFieldValidationService $validationService = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:published,draft,archived'],
            'fields' => ['sometimes', 'array'],
            'fields.*.id' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.type' => ['required_with:fields', 'string', 'in:text,textarea,email,number,tel,select,checkbox,date,time,file_upload,image_upload,signature,radio'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.required' => ['required_with:fields', 'boolean'],
            'fields.*.enabled' => ['required_with:fields', 'boolean'],
            'fields.*.is_unique' => ['nullable', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'fields.*.order' => ['nullable', 'integer'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.validation.min_length' => ['nullable', 'integer', 'min:0'],
            'fields.*.validation.max_length' => ['nullable', 'integer', 'min:1'],
            'fields.*.validation.min_value' => ['nullable', 'numeric'],
            'fields.*.validation.max_value' => ['nullable', 'numeric'],
            'fields.*.validation.regex' => ['nullable', 'string'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.label' => ['required_with:fields.*.options', 'string', 'max:255'],
            'fields.*.options.*.value' => ['required_with:fields.*.options', 'string', 'max:255'],
            'fields.*.max_file_size' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'fields.*.allowed_extensions' => ['nullable', 'array'],
            'fields.*.allowed_extensions.*' => ['string', 'max:20'],
            'fields.*.layout_direction' => ['nullable', 'string', 'in:horizontal,vertical'],
            'fields.*.default_value' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('fields')) {
                $this->validationService = app(FormFieldValidationService::class);

                $fields = $this->input('fields', []);
                $errors = $this->validationService->validateFields($fields);

                foreach ($errors as $field => $messages) {
                    foreach ((array) $messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }

                $uniqueCount = collect($fields)->where('is_unique', true)->count();
                if ($uniqueCount > 1) {
                    $validator->errors()->add('fields', 'Only one field can be marked as unique per form.');
                }

                $uniqueCapableTypes = ['text', 'email', 'tel', 'number'];
                foreach ($fields as $index => $field) {
                    if (($field['is_unique'] ?? false) && !in_array($field['type'], $uniqueCapableTypes)) {
                        $validator->errors()->add(
                            "fields.{$index}.is_unique",
                            "Field type '{$field['type']}' cannot be marked as unique. Only text, email, phone, and number fields are allowed."
                        );
                    }
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
