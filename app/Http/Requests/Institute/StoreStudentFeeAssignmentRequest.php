<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentFeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'fee_category_id' => ['required', 'integer', 'exists:fee_categories,id'],
            // Negative values represent student-specific discounts.
            'amount' => ['required', 'numeric'],
        ];
    }
}
