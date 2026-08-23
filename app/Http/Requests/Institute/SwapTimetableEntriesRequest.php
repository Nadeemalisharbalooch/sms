<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapTimetableEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_id' => ['required', 'integer', 'exists:timetable_entries,id'],
            'target_day_of_week' => ['required', 'string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'target_time_slot_id' => ['required', 'integer', 'exists:timetable_time_slots,id'],
        ];
    }
}
