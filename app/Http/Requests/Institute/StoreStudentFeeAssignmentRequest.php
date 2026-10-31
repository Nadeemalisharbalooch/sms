<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentFeeAssignmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('studentId') !== null && ! $this->filled('student_id')) {
            $this->merge(['student_id' => $this->route('studentId')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'fee_category_id' => ['required', 'integer', 'exists:fee_categories,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_discount' => ['required', 'boolean'],
            'charge_months' => ['required', 'array'],
            'charge_months.*' => ['string', 'in:Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec'],
        ];
    }
}
