<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'dob' => ['required', 'date', 'before:today'], 'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'guardian_name' => ['required', 'string', 'max:150'], 'guardian_phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string'], 'admission_date' => ['nullable', 'date'],
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'], 'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'], 'roll_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
