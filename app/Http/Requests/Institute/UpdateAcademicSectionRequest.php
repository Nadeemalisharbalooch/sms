<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $academicSection = $this->route('academic_section');
        $classId = $this->input('class_id', $academicSection->class_id);

        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sections', 'name')
                    ->where('class_id', $classId)
                    ->ignore($academicSection),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
