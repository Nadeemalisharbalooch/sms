<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class DeleteVouchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $studentInput = $this->input('student_ids') ?? $this->input('student_id');

        if ($studentInput !== null) {
            if (is_string($studentInput) && str_contains($studentInput, ',')) {
                $studentIds = array_filter(array_map('trim', explode(',', $studentInput)), fn ($v) => $v !== '');
            } elseif (is_array($studentInput)) {
                $studentIds = $studentInput;
            } else {
                $studentIds = [$studentInput];
            }

            $this->merge([
                'student_ids' => array_values(array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $studentIds)),
            ]);
        }

        $voucherInput = $this->input('voucher_ids') ?? $this->input('voucher_id');

        if ($voucherInput !== null) {
            if (is_string($voucherInput) && str_contains($voucherInput, ',')) {
                $voucherIds = array_filter(array_map('trim', explode(',', $voucherInput)), fn ($v) => $v !== '');
            } elseif (is_array($voucherInput)) {
                $voucherIds = $voucherInput;
            } else {
                $voucherIds = [$voucherInput];
            }

            $this->merge([
                'voucher_ids' => array_values(array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $voucherIds)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'billing_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'voucher_id' => ['nullable'],
            'voucher_ids' => ['nullable', 'array'],
            'voucher_ids.*' => ['integer', 'exists:fee_vouchers,id'],
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $hasFilter = $this->filled('billing_month')
                || $this->filled('class_id')
                || ! empty($this->input('student_ids'))
                || ! empty($this->input('voucher_ids'));

            if (! $hasFilter) {
                $v->errors()->add('filter', 'At least one filter criterion (billing_month, class_id, student_ids, or voucher_ids) must be provided.');
            }
        });
    }
}
