<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportTimetableRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // The class-specific endpoint implies the export type, so clients only
        // need to send class_id (plus the optional format/session/template).
        if ($this->routeIs('institutes.timetable.export.classes') && ! $this->filled('type')) {
            $this->merge(['type' => 'class']);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['class', 'teacher', 'master'])],
            'class_id' => ['required_if:type,class', 'nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'teacher_id' => ['required_if:type,teacher', 'nullable', 'integer', 'exists:users,id'],
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'template' => ['nullable', 'string', Rule::in(['classic_grid', 'compact_card', 'teacher_schedule', 'master_matrix'])],
            'format' => ['nullable', 'string', Rule::in(['html', 'pdf', 'excel', 'json'])],
        ];
    }
}
