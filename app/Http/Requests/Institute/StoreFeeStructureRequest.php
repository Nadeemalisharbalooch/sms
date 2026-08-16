<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'fee_category_id' => ['required', 'integer', 'exists:fee_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'recurrence' => ['required', Rule::in(['monthly', 'yearly', 'one-time'])],
        ];
    }
}
