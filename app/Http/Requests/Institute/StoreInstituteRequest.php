<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreInstituteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('institute')) {
            return;
        }

        $institute = (array) $this->input('institute', []);
        $settings = (array) $this->input('settings', []);
        $session = (array) $this->input('session', []);

        // The new API groups fields for readability; keep normalized flat
        // values for the existing persistence and upload handling paths.
        $this->merge([
            ...$institute,
            ...$settings,
            'academic_session' => $session,
        ]);
    }

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
            'short_name' => ['nullable', 'string', 'max:50'],
            'referral_id' => ['nullable', 'string', 'max:100'],
            'academic_session' => ['required', 'array'],
            'academic_session.name' => ['required', 'string', 'max:100'],
            'academic_session.start_date' => ['required', 'date_format:Y-m-d'],
            'academic_session.end_date' => ['required', 'date_format:Y-m-d', 'after:academic_session.start_date'],

            'email' => [
                'nullable',
                // DNS validation rejects valid addresses when a domain has no
                // mail records configured yet (for example, during setup).
                'email:rfc',
                'max:255',
                'unique:institutes,email'
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20'
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000'
            ],

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
            'currency_symbol' => ['required_with:settings', 'string', 'max:10'],
            'timezone' => ['required_with:settings', 'timezone'],
            'default_fee_due_date' => ['required_with:settings', 'integer', 'between:1,31'],
            'academic_structure' => ['sometimes', 'array'],
            'academic_structure.*' => ['required', 'array'],
            'academic_structure.*.class_name' => ['required', 'string', 'max:100', 'distinct'],
            'academic_structure.*.sections' => ['sometimes', 'array'],
            'academic_structure.*.sections.*' => ['required', 'string', 'max:100', 'distinct'],

            'role_ids' => [
                'sometimes',
                'nullable',
            ],
            'role_ids.*' => [
                'exists:roles,id',
            ],
            'role' => [
                'sometimes',
                'nullable',
            ],
            'role.*' => [
                'exists:roles,id',
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
