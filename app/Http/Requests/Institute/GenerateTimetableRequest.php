<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'days' => ['nullable', 'array', 'min:1'],
            'days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'overwrite_existing' => ['nullable', 'boolean'],
            'periods_per_subject' => ['nullable', 'array'],
            'periods_per_subject.*.class_id' => ['required_with:periods_per_subject', 'integer', 'exists:classes,id'],
            'periods_per_subject.*.subject_id' => ['required_with:periods_per_subject', 'integer', 'exists:subjects,id'],
            'periods_per_subject.*.weekly_periods' => ['required_with:periods_per_subject', 'integer', 'min:1', 'max:20'],
        ];
    }
}
