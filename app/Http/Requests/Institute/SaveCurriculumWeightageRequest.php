<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class SaveCurriculumWeightageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'weightages' => ['required', 'array', 'min:1'],
            'weightages.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'weightages.*.weekly_periods' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
