<?php

namespace App\Http\Requests\Institute;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeRecordsRequest extends FormRequest
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
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'status' => ['nullable', 'string', Rule::in(['unpaid', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled'])],
            'billing_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'due_date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'due_date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:due_date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
