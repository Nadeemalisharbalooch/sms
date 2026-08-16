<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class GenerateVouchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'billing_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'due_date' => ['required', 'date'],
        ];
    }
}
