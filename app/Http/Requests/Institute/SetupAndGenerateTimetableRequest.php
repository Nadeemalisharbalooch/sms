<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;

class SetupAndGenerateTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Support periodDuration / period_duration
        if ($this->has('periodDuration') && ! $this->has('period_duration')) {
            $this->merge(['period_duration' => $this->input('periodDuration')]);
        }

        // Support daysConfig / days_config
        $daysConfig = $this->input('daysConfig') ?? $this->input('days_config');
        if ($daysConfig !== null && is_array($daysConfig)) {
            $normalizedDays = [];
            foreach ($daysConfig as $day) {
                $normalizedDays[] = [
                    'name' => strtolower($day['name'] ?? $day['day'] ?? ''),
                    'active' => (bool) ($day['active'] ?? $day['is_active'] ?? true),
                    'start_time' => $day['startTime'] ?? $day['start_time'] ?? null,
                    'end_time' => $day['endTime'] ?? $day['end_time'] ?? null,
                    'has_break' => (bool) ($day['hasBreak'] ?? $day['has_break'] ?? false),
                    'break_start' => $day['breakStart'] ?? $day['break_start'] ?? null,
                    'break_end' => $day['breakEnd'] ?? $day['break_end'] ?? null,
                    'break_name' => $day['breakName'] ?? $day['break_name'] ?? 'Recess / Break',
                ];
            }
            $this->merge(['days_config' => $normalizedDays]);
        }

        // Support curriculum as map {"1": {"3": 3, "4": 3}}
        $curriculum = $this->input('curriculum');
        if (is_array($curriculum)) {
            $normalizedCurriculum = [];
            $isAssocMap = false;

            foreach ($curriculum as $k => $v) {
                if (is_string($k) || is_int($k)) {
                    if (is_array($v) && ! isset($v['class_id'])) {
                        $isAssocMap = true;
                        break;
                    }
                }
            }

            if ($isAssocMap) {
                foreach ($curriculum as $rawClassId => $subjectsMap) {
                    $classId = (int) preg_replace('/\D/', '', (string) $rawClassId);
                    if ($classId <= 0 || ! is_array($subjectsMap)) {
                        continue;
                    }

                    $weightages = [];
                    foreach ($subjectsMap as $rawSubjectId => $rawCount) {
                        $subjectId = (int) preg_replace('/\D/', '', (string) $rawSubjectId);
                        $count = (int) preg_replace('/\D/', '', (string) $rawCount);

                        if ($subjectId > 0 && $count > 0) {
                            $weightages[] = [
                                'subject_id' => $subjectId,
                                'weekly_periods' => $count,
                            ];
                        }
                    }

                    if (! empty($weightages)) {
                        $normalizedCurriculum[] = [
                            'class_id' => $classId,
                            'weightages' => $weightages,
                        ];
                    }
                }
                $this->merge(['normalized_curriculum' => $normalizedCurriculum]);
            } else {
                $this->merge(['normalized_curriculum' => $curriculum]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'period_duration' => ['nullable', 'integer', 'min:15', 'max:180'],
            'days_config' => ['nullable', 'array'],
            'days_config.*.name' => ['required_with:days_config', 'string'],
            'days_config.*.active' => ['nullable', 'boolean'],
            'days_config.*.start_time' => ['nullable', 'date_format:H:i'],
            'days_config.*.end_time' => ['nullable', 'date_format:H:i'],
            'days_config.*.has_break' => ['nullable', 'boolean'],
            'days_config.*.break_start' => ['nullable', 'date_format:H:i'],
            'days_config.*.break_end' => ['nullable', 'date_format:H:i'],

            'timing' => ['nullable', 'array'],
            'timing.period_duration' => ['required_with:timing', 'integer', 'min:15', 'max:180'],
            'timing.standard_days' => ['required_with:timing', 'array'],
            'timing.standard_days.days' => ['nullable', 'array'],
            'timing.standard_days.start_time' => ['required_with:timing.standard_days', 'date_format:H:i'],
            'timing.standard_days.end_time' => ['required_with:timing.standard_days', 'date_format:H:i'],
            'timing.standard_days.has_break' => ['nullable', 'boolean'],
            'timing.standard_days.break_start' => ['nullable', 'date_format:H:i'],
            'timing.standard_days.break_end' => ['nullable', 'date_format:H:i'],

            'timing.friday' => ['nullable', 'array'],
            'timing.friday.days' => ['nullable', 'array'],
            'timing.friday.start_time' => ['nullable', 'date_format:H:i'],
            'timing.friday.end_time' => ['nullable', 'date_format:H:i'],
            'timing.friday.has_break' => ['nullable', 'boolean'],
            'timing.friday.break_start' => ['nullable', 'date_format:H:i'],
            'timing.friday.break_end' => ['nullable', 'date_format:H:i'],

            'curriculum' => ['nullable'],
            'normalized_curriculum' => ['nullable', 'array'],
            'normalized_curriculum.*.class_id' => ['required_with:normalized_curriculum', 'integer', 'exists:classes,id'],
            'normalized_curriculum.*.weightages' => ['required_with:normalized_curriculum', 'array'],
            'normalized_curriculum.*.weightages.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'normalized_curriculum.*.weightages.*.weekly_periods' => ['required', 'integer', 'min:1', 'max:50'],

            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'days' => ['nullable', 'array'],
            'days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'overwrite_existing' => ['nullable', 'boolean'],
        ];
    }
}
