<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class SetupTimetableShiftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_duration' => ['required', 'integer', 'min:15', 'max:180'], // duration in minutes (e.g. 40, 45, 60)
            'standard_days' => ['required', 'array'],
            'standard_days.days' => ['nullable', 'array'],
            'standard_days.days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'standard_days.start_time' => ['required', 'date_format:H:i', 'before:standard_days.end_time'],
            'standard_days.end_time' => ['required', 'date_format:H:i', 'after:standard_days.start_time'],
            'standard_days.has_break' => ['nullable', 'boolean'],
            'standard_days.break_name' => ['nullable', 'string', 'max:100'],
            'standard_days.break_start' => ['nullable', 'required_if:standard_days.has_break,true', 'date_format:H:i'],
            'standard_days.break_end' => ['nullable', 'required_if:standard_days.has_break,true', 'date_format:H:i', 'after:standard_days.break_start'],

            'friday' => ['nullable', 'array'],
            'friday.days' => ['nullable', 'array'],
            'friday.days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
            'friday.start_time' => ['nullable', 'required_with:friday', 'date_format:H:i'],
            'friday.end_time' => ['nullable', 'required_with:friday', 'date_format:H:i', 'after:friday.start_time'],
            'friday.has_break' => ['nullable', 'boolean'],
            'friday.break_name' => ['nullable', 'string', 'max:100'],
            'friday.break_start' => ['nullable', 'required_if:friday.has_break,true', 'date_format:H:i'],
            'friday.break_end' => ['nullable', 'required_if:friday.has_break,true', 'date_format:H:i', 'after:friday.break_start'],
        ];
    }
}
