<?php

namespace Modules\FormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dynamic_data' => ['sometimes', 'array'],
            'submitted_at' => ['sometimes', 'date'],
        ];
    }
}
