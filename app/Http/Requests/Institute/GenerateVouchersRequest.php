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
            'billing_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
        ];
    }
}
