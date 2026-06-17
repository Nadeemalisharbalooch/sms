<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreInstituteRequest extends FormRequest
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
        ];
    }

     public function attributes(): array
    {
        return [
            'attendance_mode' => 'attendance mode',
        ];
    }


}
