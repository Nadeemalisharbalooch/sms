<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class GenerateVouchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $categoryInput = $this->input('fee_category_ids') ?? $this->input('fee_category_id');

        if ($categoryInput !== null) {
            if (is_string($categoryInput) && str_contains($categoryInput, ',')) {
                $categoryIds = array_filter(array_map('trim', explode(',', $categoryInput)), fn ($v) => $v !== '');
            } elseif (is_array($categoryInput)) {
                $categoryIds = $categoryInput;
            } else {
                $categoryIds = [$categoryInput];
            }

            $categoryIds = array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $categoryIds);

            $this->merge([
                'fee_category_ids' => array_values($categoryIds),
            ]);
        }

        $studentInput = $this->input('student_ids') ?? $this->input('student_id');

        if ($studentInput !== null) {
            if (is_string($studentInput) && str_contains($studentInput, ',')) {
                $studentIds = array_filter(array_map('trim', explode(',', $studentInput)), fn ($v) => $v !== '');
            } elseif (is_array($studentInput)) {
                $studentIds = $studentInput;
            } else {
                $studentIds = [$studentInput];
            }

            $studentIds = array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $studentIds);

            $this->merge([
                'student_ids' => array_values($studentIds),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'billing_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'due_date' => ['required', 'date'],
            'fee_category_id' => ['nullable'],
            'fee_category_ids' => ['required', 'array', 'min:1'],
            'fee_category_ids.*' => ['integer', 'exists:fee_categories,id'],
            'student_id' => ['nullable'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'fee_category_ids.required' => 'At least one fee category ID is required to generate vouchers.',
            'fee_category_ids.min' => 'At least one fee category ID must be provided.',
            'fee_category_ids.*.exists' => 'The selected fee category is invalid.',
            'student_ids.*.exists' => 'The selected student is invalid.',
        ];
    }
}
