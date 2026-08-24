<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class SetupAndGenerateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'timing' => ['nullable', 'array'],
            'timing.period_duration' => ['required_with:timing', 'integer', 'min:15', 'max:180'],
            'timing.standard_days' => ['required_with:timing', 'array'],
            'timing.standard_days.days' => ['nullable', 'array'],
            'timing.standard_days.days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'timing.standard_days.start_time' => ['required_with:timing.standard_days', 'date_format:H:i'],
            'timing.standard_days.end_time' => ['required_with:timing.standard_days', 'date_format:H:i'],
            'timing.standard_days.has_break' => ['nullable', 'boolean'],
            'timing.standard_days.break_name' => ['nullable', 'string'],
            'timing.standard_days.break_start' => ['nullable', 'date_format:H:i'],
            'timing.standard_days.break_end' => ['nullable', 'date_format:H:i'],

            'timing.friday' => ['nullable', 'array'],
            'timing.friday.days' => ['nullable', 'array'],
            'timing.friday.days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'timing.friday.start_time' => ['nullable', 'date_format:H:i'],
            'timing.friday.end_time' => ['nullable', 'date_format:H:i'],
            'timing.friday.has_break' => ['nullable', 'boolean'],
            'timing.friday.break_name' => ['nullable', 'string'],
            'timing.friday.break_start' => ['nullable', 'date_format:H:i'],
            'timing.friday.break_end' => ['nullable', 'date_format:H:i'],

            'curriculum' => ['nullable', 'array'],
            'curriculum.*.class_id' => ['required', 'integer', 'exists:classes,id'],
            'curriculum.*.weightages' => ['required', 'array'],
            'curriculum.*.weightages.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'curriculum.*.weightages.*.weekly_periods' => ['required', 'integer', 'min:1', 'max:50'],

            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'days' => ['nullable', 'array'],
            'days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'overwrite_existing' => ['nullable', 'boolean'],
        ];
    }
}
