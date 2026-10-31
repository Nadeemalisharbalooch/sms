<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectFeePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['voucher_id' => $this->route('voucherId')]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_id' => ['required', 'integer', 'exists:fee_vouchers,id'],
            'paid_amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
            'waive_late_fee' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_amount.gt' => 'The payment amount must be greater than zero.',
        ];
    }
}
