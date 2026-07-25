<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')
                    ->where('institute_id', $subject->institute_id)
                    ->ignore($subject),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')
                    ->where('institute_id', $subject->institute_id)
                    ->ignore($subject),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
