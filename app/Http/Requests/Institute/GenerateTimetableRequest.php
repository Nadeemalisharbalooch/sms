<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Support curriculum map format: {"1": {"3": 3, "4": 3}}
        // Normalize to periods_per_subject format so generate() receives workload overrides
        $curriculum = $this->input('curriculum');
        if (is_array($curriculum) && ! $this->has('periods_per_subject')) {
            $periodsPerSubject = [];
            $isAssocMap = false;

            foreach ($curriculum as $k => $v) {
                if ((is_string($k) || is_int($k)) && is_array($v) && ! isset($v['class_id'])) {
                    $isAssocMap = true;
                    break;
                }
            }

            if ($isAssocMap) {
                foreach ($curriculum as $rawClassId => $subjectsMap) {
                    $classId = (int) preg_replace('/\D/', '', (string) $rawClassId);
                    if ($classId <= 0 || ! is_array($subjectsMap)) {
                        continue;
                    }

                    foreach ($subjectsMap as $rawSubjectId => $rawCount) {
                        $subjectId = (int) preg_replace('/\D/', '', (string) $rawSubjectId);
                        $count = (int) preg_replace('/\D/', '', (string) $rawCount);

                        if ($subjectId > 0 && $count > 0) {
                            $periodsPerSubject[] = [
                                'class_id' => $classId,
                                'subject_id' => $subjectId,
                                'weekly_periods' => $count,
                            ];
                        }
                    }
                }

                if (! empty($periodsPerSubject)) {
                    $this->merge(['periods_per_subject' => $periodsPerSubject]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'days' => ['nullable', 'array', 'min:1'],
            'days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'overwrite_existing' => ['nullable', 'boolean'],
            'periods_per_subject' => ['nullable', 'array'],
            'periods_per_subject.*.class_id' => ['required_with:periods_per_subject', 'integer', 'exists:classes,id'],
            'periods_per_subject.*.subject_id' => ['required_with:periods_per_subject', 'integer', 'exists:subjects,id'],
            'periods_per_subject.*.weekly_periods' => ['required_with:periods_per_subject', 'integer', 'min:1', 'max:20'],
        ];
    }
}
