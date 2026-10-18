<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class GetClassTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'integer', 'exists:classes,id', 'required_with:section_id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
        ];
    }
}
