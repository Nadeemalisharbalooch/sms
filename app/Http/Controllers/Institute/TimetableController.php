<?php

namespace App\Http\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\ExportTimetableRequest;
use App\Http\Requests\Institute\GenerateTimetableRequest;
use App\Http\Requests\Institute\GetClassTimetableRequest;
use App\Http\Requests\Institute\GetTeacherTimetableRequest;
use App\Http\Requests\Institute\SaveCurriculumWeightageRequest;
use App\Http\Requests\Institute\SetupAndGenerateTimetableRequest;
use App\Http\Requests\Institute\SetupTimetableShiftsRequest;
use App\Http\Requests\Institute\StoreTimetableTimeSlotRequest;
use App\Http\Requests\Institute\SwapTimetableEntriesRequest;
use App\Http\Requests\Institute\UpdateTimetableTimeSlotRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\AcademicSession;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\SubjectAllocation;
use App\Models\TimetableEntry;
use App\Models\TimetableTimeSlot;
use App\Models\TimetableWorkload;
use App\Models\User;
use App\Services\ResponseService;
use App\Services\Timetable\TimetableExportService;
use App\Services\Timetable\TimetableGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TimetableController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * Step 1: Automated Shift & Time Slot Setup.
     * Generates non-overlapping periods for Standard Days and Friday.
     */
    public function setupShifts(SetupTimetableShiftsRequest $request, TimetableGeneratorService $generator): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $duration = (int) $validated['period_duration'];
        $standardConfig = $validated['standard_days'];
        $fridayConfig = $validated['friday'] ?? null;

        $createdSlots = $generator->setupShifts($institute, $duration, $standardConfig, $fridayConfig);

        return ResponseService::success($createdSlots, 'Time slots & shifts generated successfully');
    }

    /**
     * Step 2 (Read): Get Class Curriculum & Subject Allocation with Current Weekly Weightages.
     */
    public function getCurriculum(Request $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $classId = $request->integer('class_id');
        if (! $classId) {
            return ResponseService::error('Validation failed', 422, ['class_id' => ['The class_id parameter is required.']]);
        }

        $class = AcademicClass::whereKey($classId)->where('institute_id', $institute->id)->first();
        if ($class === null) {
            return ResponseService::notFound('Class not found in active institute');
        }

        $sessionId = $request->integer('session_id') ?: $this->activeSessionId($institute->id);
        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        // Fetch all subject allocations for this class in this session
        $allocations = SubjectAllocation::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->with(['subject', 'teacher'])
            ->get();

        // Fetch existing saved workloads
        $workloads = TimetableWorkload::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->get()
            ->keyBy('subject_id');

        // Fetch active slots to calculate total weekly slots
        $allSlots = TimetableTimeSlot::where('institute_id', $institute->id)->where('is_active', true)->where('is_break', false)->get();
        $totalWeeklyPeriods = 0;
        foreach (self::DAYS as $day) {
            $matching = $allSlots->filter(fn ($s) => empty($s->days) || in_array($day, $s->days, true));
            $totalWeeklyPeriods += $matching->count();
        }

        $subjectCount = $allocations->pluck('subject_id')->unique()->count();
        $defaultWeightage = $subjectCount > 0 ? (int) max(1, floor($totalWeeklyPeriods / $subjectCount)) : 5;

        $curriculum = [];
        $uniqueSubjectAllocations = $allocations->unique('subject_id');

        foreach ($uniqueSubjectAllocations as $alloc) {
            $savedWeightage = $workloads[$alloc->subject_id]->weekly_periods ?? $defaultWeightage;

            $curriculum[] = [
                'subject_id' => $alloc->subject->id,
                'subject_name' => $alloc->subject->name,
                'subject_code' => $alloc->subject->code,
                'teacher' => $alloc->teacher ? [
                    'id' => $alloc->teacher->id,
                    'name' => $alloc->teacher->name,
                    'email' => $alloc->teacher->email,
                ] : null,
                'weekly_periods' => $savedWeightage,
            ];
        }

        return ResponseService::success([
            'session_id' => $sessionId,
            'class' => ['id' => $class->id, 'name' => $class->name],
            'total_weekly_slots_available' => $totalWeeklyPeriods,
            'curriculum' => $curriculum,
        ], 'Class curriculum retrieved successfully');
    }

    /**
     * Step 2 (Write): Save Subject Weekly Weightages (Curriculum) for a specific Class / Grade.
     */
    public function saveCurriculum(SaveCurriculumWeightageRequest $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $classId = $validated['class_id'];
        $saved = [];

        DB::transaction(function () use ($sessionId, $classId, $validated, &$saved) {
            foreach ($validated['weightages'] as $item) {
                $workload = TimetableWorkload::updateOrCreate(
                    [
                        'session_id' => $sessionId,
                        'class_id' => $classId,
                        'subject_id' => $item['subject_id'],
                    ],
                    [
                        'weekly_periods' => $item['weekly_periods'],
                    ]
                );
                $saved[] = $workload;
            }
        });

        return ResponseService::success($saved, 'Subject weightages saved successfully');
    }

    /**
     * Unified All-In-One Wizard: Step 1 (Timing) + Step 2 (Curriculum) + Step 3 (Generate).
     */
    public function setupAndGenerate(SetupAndGenerateTimetableRequest $request, TimetableGeneratorService $generator): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        // 1. Process Step 1: Shifts & Timings (DaysConfig or Timing array)
        $activeDays = $validated['days'] ?? [];

        if (! empty($validated['days_config'])) {
            $duration = (int) ($validated['period_duration'] ?? 45);
            $generator->setupShiftsFromDaysConfig($institute, $duration, $validated['days_config']);

            // Auto-collect active days if not explicitly provided
            if (empty($activeDays)) {
                $activeDays = collect($validated['days_config'])
                    ->filter(fn ($d) => (bool) ($d['active'] ?? true))
                    ->pluck('name')
                    ->all();
            }
        } elseif (! empty($validated['timing'])) {
            $timing = $validated['timing'];
            $generator->setupShifts(
                $institute,
                (int) $timing['period_duration'],
                $timing['standard_days'],
                $timing['friday'] ?? null
            );
        }

        // 2. Process Step 2: Curriculum Weightages (from normalized_curriculum or curriculum)
        $workloadOverrides = [];
        $curriculumClasses = [];

        $curriculumData = $validated['normalized_curriculum'] ?? $validated['curriculum'] ?? [];
        if (! empty($curriculumData) && is_array($curriculumData)) {
            foreach ($curriculumData as $classCurriculum) {
                if (is_array($classCurriculum) && isset($classCurriculum['class_id'], $classCurriculum['weightages'])) {
                    $cId = (int) $classCurriculum['class_id'];
                    $curriculumClasses[] = $cId;

                    foreach ($classCurriculum['weightages'] as $w) {
                        $workloadOverrides[] = [
                            'class_id' => $cId,
                            'subject_id' => (int) $w['subject_id'],
                            'weekly_periods' => (int) $w['weekly_periods'],
                        ];
                    }
                }
            }
        }

        $classIdsToSchedule = $validated['class_ids'] ?? array_unique($curriculumClasses);

        // 3. Process Step 3: Run Clash-Free Generator
        try {
            $result = $generator->generate(
                $institute,
                $sessionId,
                $classIdsToSchedule,
                $activeDays,
                $validated['overwrite_existing'] ?? true,
                $workloadOverrides
            );

            return ResponseService::success($result, 'Timetable configured and generated successfully');
        } catch (\RuntimeException $e) {
            return ResponseService::error($e->getMessage(), 422);
        }
    }

    /**
     * List all time slots for the active institute.
     */
    public function indexSlots(Request $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $slots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->orderBy('sort_order')
            ->get();

        return ResponseService::success($slots, 'Time slots retrieved successfully');
    }

    /**
     * Store a new time slot.
     */
    public function storeSlot(StoreTimetableTimeSlotRequest $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();

        $nameError = $this->checkSlotNameUnique($institute->id, $validated['name']);
        if ($nameError !== null) {
            return $nameError;
        }

        $overlapError = $this->checkSlotOverlap($institute->id, $validated['start_time'], $validated['end_time']);
        if ($overlapError !== null) {
            return $overlapError;
        }

        $validated['institute_id'] = $institute->id;
        $validated['is_break'] = $validated['is_break'] ?? false;
        $validated['sort_order'] = $validated['sort_order'] ?? (TimetableTimeSlot::where('institute_id', $institute->id)->max('sort_order') + 1);

        $slot = TimetableTimeSlot::create($validated);

        return ResponseService::success($slot, 'Time slot created successfully', 201);
    }

    /**
     * Update an existing time slot.
     */
    public function updateSlot(UpdateTimetableTimeSlotRequest $request, TimetableTimeSlot $timeSlot): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null || $timeSlot->institute_id !== $institute->id) {
            return ResponseService::notFound('Time slot not found');
        }

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $nameError = $this->checkSlotNameUnique($institute->id, $validated['name'], $timeSlot->id);
            if ($nameError !== null) {
                return $nameError;
            }
        }

        $newStartTime = $validated['start_time'] ?? substr($timeSlot->start_time, 0, 5);
        $newEndTime = $validated['end_time'] ?? substr($timeSlot->end_time, 0, 5);

        if (isset($validated['start_time']) || isset($validated['end_time'])) {
            if ($newStartTime >= $newEndTime) {
                return ResponseService::error('The start time must be before the end time.', 422, [
                    'start_time' => ['The start time must be before the end time.'],
                ]);
            }

            $overlapError = $this->checkSlotOverlap($institute->id, $newStartTime, $newEndTime, $timeSlot->id);
            if ($overlapError !== null) {
                return $overlapError;
            }
        }

        $timeSlot->update($validated);

        return ResponseService::success($timeSlot, 'Time slot updated successfully');
    }

    /**
     * Delete a time slot.
     */
    public function destroySlot(Request $request, TimetableTimeSlot $timeSlot): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null || $timeSlot->institute_id !== $institute->id) {
            return ResponseService::notFound('Time slot not found');
        }

        $timeSlot->delete();

        return ResponseService::success(null, 'Time slot deleted successfully');
    }

    /**
     * Seed preset time slot structures.
     */
    public function seedPresetSlots(Request $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $preset = $request->input('preset', 'standard_6');

        $presets = [
            'standard_6' => [
                ['name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_break' => false, 'sort_order' => 1],
                ['name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_break' => false, 'sort_order' => 2],
                ['name' => 'Period 3', 'start_time' => '09:30', 'end_time' => '10:15', 'is_break' => false, 'sort_order' => 3],
                ['name' => 'Recess / Break', 'start_time' => '10:15', 'end_time' => '10:45', 'is_break' => true, 'sort_order' => 4],
                ['name' => 'Period 4', 'start_time' => '10:45', 'end_time' => '11:30', 'is_break' => false, 'sort_order' => 5],
                ['name' => 'Period 5', 'start_time' => '11:30', 'end_time' => '12:15', 'is_break' => false, 'sort_order' => 6],
                ['name' => 'Period 6', 'start_time' => '12:15', 'end_time' => '13:00', 'is_break' => false, 'sort_order' => 7],
            ],
            'standard_8' => [
                ['name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false, 'sort_order' => 1],
                ['name' => 'Period 2', 'start_time' => '08:40', 'end_time' => '09:20', 'is_break' => false, 'sort_order' => 2],
                ['name' => 'Period 3', 'start_time' => '09:20', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 3],
                ['name' => 'Period 4', 'start_time' => '10:00', 'end_time' => '10:40', 'is_break' => false, 'sort_order' => 4],
                ['name' => 'Lunch Break', 'start_time' => '10:40', 'end_time' => '11:15', 'is_break' => true, 'sort_order' => 5],
                ['name' => 'Period 5', 'start_time' => '11:15', 'end_time' => '11:55', 'is_break' => false, 'sort_order' => 6],
                ['name' => 'Period 6', 'start_time' => '11:55', 'end_time' => '12:35', 'is_break' => false, 'sort_order' => 7],
                ['name' => 'Period 7', 'start_time' => '12:35', 'end_time' => '13:15', 'is_break' => false, 'sort_order' => 8],
                ['name' => 'Period 8', 'start_time' => '13:15', 'end_time' => '13:55', 'is_break' => false, 'sort_order' => 9],
            ],
        ];

        $selectedPreset = $presets[$preset] ?? $presets['standard_6'];

        DB::transaction(function () use ($institute, $selectedPreset) {
            TimetableTimeSlot::where('institute_id', $institute->id)->delete();
            foreach ($selectedPreset as $slotData) {
                $slotData['institute_id'] = $institute->id;
                TimetableTimeSlot::create($slotData);
            }
        });

        $slots = TimetableTimeSlot::where('institute_id', $institute->id)->orderBy('sort_order')->get();

        return ResponseService::success($slots, 'Preset time slots created successfully');
    }

    /**
     * Auto-generate clash-free timetable.
     */
    public function generate(GenerateTimetableRequest $request, TimetableGeneratorService $generator): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        try {
            $result = $generator->generate(
                $institute,
                $sessionId,
                $validated['class_ids'] ?? [],
                $validated['days'] ?? [],
                $validated['overwrite_existing'] ?? true,
                $validated['periods_per_subject'] ?? []
            );

            return ResponseService::success($result, 'Timetable generated successfully');
        } catch (\RuntimeException $e) {
            return ResponseService::error($e->getMessage(), 422);
        }
    }

    /**
     * Get class timetable schedule grid.
     */
    public function classSchedule(GetClassTimetableRequest $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $class = AcademicClass::query()
            ->whereKey($validated['class_id'])
            ->where('institute_id', $institute->id)
            ->first();

        if ($class === null) {
            return ResponseService::notFound('Class not found in active institute');
        }

        $sectionId = $validated['section_id'] ?? null;
        $section = $sectionId !== null ? AcademicSection::find($sectionId) : null;

        $slots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $entries = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $class->id)
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->with(['subject', 'teacher', 'timeSlot'])
            ->get();

        $grid = [];
        foreach (self::DAYS as $day) {
            $daySlots = $slots->filter(fn ($s) => empty($s->days) || in_array($day, $s->days, true));
            $grid[$day] = [];

            foreach ($daySlots as $slot) {
                $entry = $entries->first(fn ($e) => $e->day_of_week === $day && $e->time_slot_id === $slot->id);

                $grid[$day][] = [
                    'time_slot_id' => $slot->id,
                    'time_slot_name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'is_break' => $slot->is_break,
                    'entry_id' => $entry?->id,
                    'subject' => $entry?->subject ? [
                        'id' => $entry->subject->id,
                        'name' => $entry->subject->name,
                        'code' => $entry->subject->code,
                    ] : null,
                    'teacher' => $entry?->teacher ? [
                        'id' => $entry->teacher->id,
                        'name' => $entry->teacher->name,
                        'email' => $entry->teacher->email,
                    ] : null,
                    'room_number' => $entry?->room_number,
                ];
            }
        }

        return ResponseService::success([
            'session_id' => $sessionId,
            'class' => ['id' => $class->id, 'name' => $class->name],
            'section' => $section ? ['id' => $section->id, 'name' => $section->name] : null,
            'time_slots' => $slots,
            'schedule' => $grid,
        ], 'Class timetable retrieved successfully');
    }

    /**
     * Get teacher's personal timetable schedule grid.
     */
    public function teacherSchedule(GetTeacherTimetableRequest $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);

        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $teacher = User::query()
            ->whereKey($validated['teacher_id'])
            ->whereHas('instituteUsers', fn ($q) => $q->where('institute_id', $institute->id)->where('is_active', true))
            ->first();

        if ($teacher === null) {
            return ResponseService::notFound('Teacher not found in active institute');
        }

        $slots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $entries = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->where('teacher_user_id', $teacher->id)
            ->with(['academicClass', 'section', 'subject', 'timeSlot'])
            ->get();

        $grid = [];
        foreach (self::DAYS as $day) {
            $daySlots = $slots->filter(fn ($s) => empty($s->days) || in_array($day, $s->days, true));
            $grid[$day] = [];

            foreach ($daySlots as $slot) {
                $entry = $entries->first(fn ($e) => $e->day_of_week === $day && $e->time_slot_id === $slot->id);

                $grid[$day][] = [
                    'time_slot_id' => $slot->id,
                    'time_slot_name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'is_break' => $slot->is_break,
                    'entry_id' => $entry?->id,
                    'class' => $entry?->academicClass ? [
                        'id' => $entry->academicClass->id,
                        'name' => $entry->academicClass->name,
                    ] : null,
                    'section' => $entry?->section ? [
                        'id' => $entry->section->id,
                        'name' => $entry->section->name,
                    ] : null,
                    'subject' => $entry?->subject ? [
                        'id' => $entry->subject->id,
                        'name' => $entry->subject->name,
                        'code' => $entry->subject->code,
                    ] : null,
                    'room_number' => $entry?->room_number,
                ];
            }
        }

        return ResponseService::success([
            'session_id' => $sessionId,
            'teacher' => ['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email],
            'time_slots' => $slots,
            'schedule' => $grid,
        ], 'Teacher timetable retrieved successfully');
    }

    /**
     * Get institute master grid timetable.
     */
    public function masterSchedule(Request $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $sessionId = $request->integer('session_id') ?: $this->activeSessionId($institute->id);
        if ($sessionId === null) {
            return ResponseService::error('Validation failed', 422, ['session_id' => ['No active academic session exists for the active institute.']]);
        }

        $classes = AcademicClass::query()
            ->where('institute_id', $institute->id)
            ->with('sections')
            ->orderBy('display_order')
            ->get();

        $slots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $entries = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->with(['academicClass', 'section', 'subject', 'teacher', 'timeSlot'])
            ->get();

        return ResponseService::success([
            'session_id' => $sessionId,
            'time_slots' => $slots,
            'classes' => $classes,
            'entries' => $entries,
        ], 'Master timetable retrieved successfully');
    }

    /**
     * Swap or move a timetable entry with real-time clash check.
     */
    public function swap(SwapTimetableEntriesRequest $request): JsonResponse
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sourceEntry = TimetableEntry::with(['academicClass', 'teacher'])->find($validated['entry_id']);

        if ($sourceEntry === null || $sourceEntry->academicClass->institute_id !== $institute->id) {
            return ResponseService::notFound('Timetable entry not found');
        }

        $targetDay = $validated['target_day_of_week'];
        $targetSlotId = $validated['target_time_slot_id'];

        $targetSlot = TimetableTimeSlot::where('id', $targetSlotId)->where('institute_id', $institute->id)->first();
        if ($targetSlot === null) {
            return ResponseService::notFound('Target time slot not found');
        }

        if ($targetSlot->is_break) {
            return ResponseService::error('Cannot schedule lectures during a break slot.', 422);
        }

        $teacherClash = TimetableEntry::query()
            ->where('session_id', $sourceEntry->session_id)
            ->where('teacher_user_id', $sourceEntry->teacher_user_id)
            ->where('day_of_week', $targetDay)
            ->where('time_slot_id', $targetSlotId)
            ->where('id', '!=', $sourceEntry->id)
            ->exists();

        if ($teacherClash) {
            return ResponseService::error('Teacher is already occupied in another class at the target time slot.', 422);
        }

        $targetEntry = TimetableEntry::query()
            ->where('session_id', $sourceEntry->session_id)
            ->where('class_id', $sourceEntry->class_id)
            ->when($sourceEntry->section_id !== null, fn ($q) => $q->where('section_id', $sourceEntry->section_id), fn ($q) => $q->whereNull('section_id'))
            ->where('day_of_week', $targetDay)
            ->where('time_slot_id', $targetSlotId)
            ->where('id', '!=', $sourceEntry->id)
            ->first();

        if ($targetEntry !== null) {
            $reverseTeacherClash = TimetableEntry::query()
                ->where('session_id', $sourceEntry->session_id)
                ->where('teacher_user_id', $targetEntry->teacher_user_id)
                ->where('day_of_week', $sourceEntry->day_of_week)
                ->where('time_slot_id', $sourceEntry->time_slot_id)
                ->where('id', '!=', $targetEntry->id)
                ->exists();

            if ($reverseTeacherClash) {
                return ResponseService::error('Swap failed: Target lecture teacher is occupied in the source slot.', 422);
            }
        }

        DB::transaction(function () use ($sourceEntry, $targetEntry, $targetDay, $targetSlotId) {
            if ($targetEntry !== null) {
                $origDay = $sourceEntry->day_of_week;
                $origSlot = $sourceEntry->time_slot_id;

                $targetEntry->update([
                    'day_of_week' => $origDay,
                    'time_slot_id' => $origSlot,
                ]);
            }

            $sourceEntry->update([
                'day_of_week' => $targetDay,
                'time_slot_id' => $targetSlotId,
            ]);
        });

        return ResponseService::success(null, 'Timetable adjusted successfully');
    }

    /**
     * Export timetable as HTML view (ready for PDF print), Excel, or JSON.
     */
    public function export(ExportTimetableRequest $request, TimetableExportService $exportService): Response
    {
        $institute = $this->activeInstitute($request);
        if ($institute === null) {
            return ResponseService::error('No active institute is associated with this user', 422);
        }

        $validated = $request->validated();
        $sessionId = $validated['session_id'] ?? $this->activeSessionId($institute->id);
        $session = AcademicSession::find($sessionId);

        if ($session === null) {
            return ResponseService::error('Academic session not found', 422);
        }

        $class = isset($validated['class_id']) ? AcademicClass::find($validated['class_id']) : null;
        $section = isset($validated['section_id']) ? AcademicSection::find($validated['section_id']) : null;
        $teacher = isset($validated['teacher_id']) ? User::find($validated['teacher_id']) : null;

        $type = $validated['type'];
        $format = $validated['format'] ?? 'html';
        $template = $validated['template'] ?? 'classic_grid';

        if ($format === 'excel') {
            return $exportService->exportExcel($institute, $session, $type, $class, $section, $teacher);
        }

        if ($format === 'json') {
            $slots = TimetableTimeSlot::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $entriesQuery = TimetableEntry::query()
                ->where('session_id', $session->id)
                ->with(['academicClass', 'section', 'subject', 'teacher', 'timeSlot']);

            if ($type === 'class' && $class !== null) {
                $entriesQuery->where('class_id', $class->id);
                if ($section !== null) {
                    $entriesQuery->where('section_id', $section->id);
                }
            } elseif ($type === 'teacher' && $teacher !== null) {
                $entriesQuery->where('teacher_user_id', $teacher->id);
            }

            $entries = $entriesQuery->get();

            $grid = [];
            foreach (self::DAYS as $day) {
                $daySlots = $slots->filter(fn ($s) => empty($s->days) || in_array($day, $s->days, true));
                $grid[$day] = [];

                foreach ($daySlots as $slot) {
                    $entry = $entries->first(fn ($e) => $e->day_of_week === $day && $e->time_slot_id === $slot->id);

                    $grid[$day][] = [
                        'time_slot_id' => $slot->id,
                        'time_slot_name' => $slot->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'is_break' => $slot->is_break,
                        'entry_id' => $entry?->id,
                        'class' => $entry?->academicClass ? [
                            'id' => $entry->academicClass->id,
                            'name' => $entry->academicClass->name,
                        ] : null,
                        'section' => $entry?->section ? [
                            'id' => $entry->section->id,
                            'name' => $entry->section->name,
                        ] : null,
                        'subject' => $entry?->subject ? [
                            'id' => $entry->subject->id,
                            'name' => $entry->subject->name,
                            'code' => $entry->subject->code,
                        ] : null,
                        'teacher' => $entry?->teacher ? [
                            'id' => $entry->teacher->id,
                            'name' => $entry->teacher->name,
                            'email' => $entry->teacher->email,
                        ] : null,
                        'room_number' => $entry?->room_number,
                    ];
                }
            }

            return ResponseService::success([
                'template' => $template,
                'session_id' => $sessionId,
                'type' => $type,
                'class' => $class ? ['id' => $class->id, 'name' => $class->name] : null,
                'section' => $section ? ['id' => $section->id, 'name' => $section->name] : null,
                'teacher' => $teacher ? ['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email] : null,
                'time_slots' => $slots,
                'schedule' => $grid,
            ], 'Timetable exported successfully');
        }

        $html = $exportService->renderHtml($institute, $session, $type, $class, $section, $teacher, $template);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function checkSlotNameUnique(int $instituteId, string $name, ?int $ignoreId = null): ?JsonResponse
    {
        $exists = TimetableTimeSlot::query()
            ->where('institute_id', $instituteId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            return ResponseService::error(
                "A time slot with the name '{$name}' already exists in this institute.",
                422,
                ['name' => ["The slot name '{$name}' has already been taken."]]
            );
        }

        return null;
    }

    private function checkSlotOverlap(int $instituteId, string $startTime, string $endTime, ?int $ignoreId = null): ?JsonResponse
    {
        $overlap = TimetableTimeSlot::query()
            ->where('institute_id', $instituteId)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->first();

        if ($overlap !== null) {
            $overlapStart = substr($overlap->start_time, 0, 5);
            $overlapEnd = substr($overlap->end_time, 0, 5);

            return ResponseService::error(
                "Time slot overlaps with existing slot '{$overlap->name}' ({$overlapStart} - {$overlapEnd}).",
                422,
                [
                    'start_time' => ["The time range {$startTime} - {$endTime} overlaps with '{$overlap->name}' ({$overlapStart} - {$overlapEnd})."],
                ]
            );
        }

        return null;
    }

    private function activeInstitute(Request $request): ?Institute
    {
        $instituteId = InstituteUser::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->value('institute_id');

        return $instituteId === null ? null : Institute::find($instituteId);
    }

    private function activeSessionId(int $instituteId): ?int
    {
        $sessionId = AcademicSession::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->value('id');

        return $sessionId === null ? null : (int) $sessionId;
    }
}
