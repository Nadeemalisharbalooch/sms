<?php

namespace App\Http\Requests\Institute;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditCurrentInstituteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $institute = $this->user()->instituteUsers()
            ->where('is_active', true)
            ->first()?->institute;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'email' => [
                'sometimes',
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('institutes', 'email')
                    ->ignore($institute?->id, 'id'),
            ],

            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],

            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],

            'logo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,svg,webp',
                'max:2048',
            ],

            'favicon' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:ico,png',
                'max:1024',
            ],

            'attendance_mode' => [
                'sometimes',
                'required',
                Rule::in(['class', 'subject']),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'attendance_mode' => 'attendance mode',
        ];
    }
}
