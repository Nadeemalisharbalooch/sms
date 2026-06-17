<?php

namespace App\Http\Requests\Institute;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInstituteRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('institutes', 'email')
                    ->ignore($this->route('institute'))
            ],

            'phone' => ['nullable', 'string', 'max:20'],

            'address' => ['nullable', 'string', 'max:1000'],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,svg,webp',
                'max:2048'
            ],

            'favicon' => [
                'nullable',
                'image',
                'mimes:ico,png',
                'max:1024'
            ],

            'attendance_mode' => [
                'required',
                Rule::in(['class', 'subject'])
            ],

            'is_active' => [
                'sometimes',
                'boolean'
            ]
        ];
    }
}
