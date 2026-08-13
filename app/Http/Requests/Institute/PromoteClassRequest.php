<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_class_id' => ['required', 'integer', 'exists:classes,id'],
            'source_section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'target_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'target_class_id' => ['required', 'integer', 'exists:classes,id'],
            'target_section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'status' => ['required', Rule::in(['passed', 'failed'])],
        ];
    }
}
