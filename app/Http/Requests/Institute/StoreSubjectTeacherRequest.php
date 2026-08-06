<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.subject_id' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'allocations.*.teacher_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}