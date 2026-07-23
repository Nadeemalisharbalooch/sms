<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $academicSession = $this->route('academic_session');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('academic_sessions', 'name')
                    ->where('institute_id', $academicSession->institute_id)
                    ->ignore($academicSession),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
