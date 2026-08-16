<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectFeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fee_voucher_id' => ['required', 'integer', 'exists:fee_vouchers,id'],
            'amount_paid' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bank'])],
            'payment_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_paid.gt' => 'The payment amount must be greater than zero.',
        ];
    }
}
