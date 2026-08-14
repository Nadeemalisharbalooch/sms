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
            'from_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'to_session_id' => ['required', 'integer', 'different:from_session_id', 'exists:academic_sessions,id'],
            'promotions' => ['required', 'array', 'min:1'],
            'promotions.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'promotions.*.promotion_status' => ['required', Rule::in(['promoted', 'retained', 'graduated', 'left'])],
            'promotions.*.class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'promotions.*.section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'promotions.*.roll_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
