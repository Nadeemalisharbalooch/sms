<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeStructureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('classId') !== null && ! $this->filled('class_id')) {
            $this->merge(['class_id' => $this->route('classId')]);
        }
    }

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
            'charge_months' => ['required', 'array'],
            'charge_months.*' => ['string', 'in:Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec'],
        ];
    }
}
