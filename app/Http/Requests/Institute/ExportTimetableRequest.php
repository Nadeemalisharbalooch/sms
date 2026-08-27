<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportTimetableRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Automatically default type to 'class' if class_id is present or route is export/classes
        if (! $this->filled('type')) {
            if ($this->routeIs('institutes.timetable.export.classes') || $this->filled('class_id')) {
                $this->merge(['type' => 'class']);
            } elseif ($this->filled('teacher_id')) {
                $this->merge(['type' => 'teacher']);
            }
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
            'download' => ['nullable', 'boolean'],
            'disposition' => ['nullable', 'string', Rule::in(['inline', 'attachment'])],
        ];
    }
}
