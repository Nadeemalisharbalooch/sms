<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'target_class_id' => ['required', 'integer', 'exists:classes,id'],
            'target_section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'roll_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['passed', 'failed'])],
        ];
    }
}
