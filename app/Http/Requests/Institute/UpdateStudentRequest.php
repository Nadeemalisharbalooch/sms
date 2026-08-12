<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'], 'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'dob' => ['sometimes', 'required', 'date', 'before:today'], 'gender' => ['sometimes', 'required', Rule::in(['male', 'female', 'other'])],
            'guardian_name' => ['sometimes', 'required', 'string', 'max:150'], 'guardian_phone' => ['sometimes', 'required', 'string', 'max:30'],
            'address' => ['nullable', 'string'], 'admission_date' => ['sometimes', 'required', 'date'], 'roll_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
