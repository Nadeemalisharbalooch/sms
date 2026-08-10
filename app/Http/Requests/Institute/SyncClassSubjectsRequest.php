<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class SyncClassSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_ids' => ['present', 'array'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
        ];
    }
}
