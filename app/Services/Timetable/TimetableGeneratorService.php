<?php

namespace App\Services\Timetable;

use App\Models\AcademicClass;
use App\Models\Institute;
use App\Models\SubjectAllocation;
use App\Models\TimetableEntry;
use App\Models\TimetableTimeSlot;
use App\Models\TimetableWorkload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimetableGeneratorService
{
    private const DEFAULT_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * Step 1: Automatically generate and store non-overlapping Time Slots for Standard Days & Friday.
     *
     * @return array<TimetableTimeSlot>
     */
    public function setupShifts(
        Institute $institute,
        int $durationMinutes,
        array $standardConfig,
        ?array $fridayConfig = null
    ): array {
        $slotsToCreate = [];
        $sortOrder = 1;

        // 1. Standard Days Slots Generation
        $standardDays = $standardConfig['days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'saturday'];
        $stdStart = Carbon::createFromFormat('H:i', $standardConfig['start_time']);
        $stdEnd = Carbon::createFromFormat('H:i', $standardConfig['end_time']);
        $stdHasBreak = (bool) ($standardConfig['has_break'] ?? false);
        $stdBreakStart = $stdHasBreak && ! empty($standardConfig['break_start']) ? Carbon::createFromFormat('H:i', $standardConfig['break_start']) : null;
        $stdBreakEnd = $stdHasBreak && ! empty($standardConfig['break_end']) ? Carbon::createFromFormat('H:i', $standardConfig['break_end']) : null;
        $stdBreakName = $standardConfig['break_name'] ?? 'Recess / Break';

        $current = $stdStart->copy();
        $periodIndex = 1;

        while ($current->lt($stdEnd)) {
            // Check if current time is inside or at break start
            if ($stdHasBreak && $stdBreakStart !== null && $stdBreakEnd !== null && $current->equalTo($stdBreakStart)) {
                $slotsToCreate[] = [
                    'institute_id' => $institute->id,
                    'name' => $stdBreakName,
                    'start_time' => $stdBreakStart->format('H:i'),
                    'end_time' => $stdBreakEnd->format('H:i'),
                    'is_break' => true,
                    'days' => $standardDays,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $current = $stdBreakEnd->copy();
                continue;
            }

            // Check if next period would cross break start
            if ($stdHasBreak && $stdBreakStart !== null && $current->lt($stdBreakStart) && $current->copy()->addMinutes($durationMinutes)->gt($stdBreakStart)) {
                // If remaining gap is at least 15 mins, add a shortened period or snap to break
                $next = $stdBreakStart->copy();
            } else {
                $next = $current->copy()->addMinutes($durationMinutes);
                if ($next->gt($stdEnd)) {
                    $next = $stdEnd->copy();
                }
            }

            if ($next->lte($current)) {
                break;
            }

            $slotsToCreate[] = [
                'institute_id' => $institute->id,
                'name' => "Period {$periodIndex}",
                'start_time' => $current->format('H:i'),
                'end_time' => $next->format('H:i'),
                'is_break' => false,
                'days' => $standardDays,
                'sort_order' => $sortOrder++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $periodIndex++;
            $current = $next->copy();
        }

        // 2. Friday Custom Slots Generation (if provided)
        if ($fridayConfig !== null && ! empty($fridayConfig['start_time']) && ! empty($fridayConfig['end_time'])) {
            $friDays = $fridayConfig['days'] ?? ['friday'];
            $friStart = Carbon::createFromFormat('H:i', $fridayConfig['start_time']);
            $friEnd = Carbon::createFromFormat('H:i', $fridayConfig['end_time']);
            $friHasBreak = (bool) ($fridayConfig['has_break'] ?? false);
            $friBreakStart = $friHasBreak && ! empty($fridayConfig['break_start']) ? Carbon::createFromFormat('H:i', $fridayConfig['break_start']) : null;
            $friBreakEnd = $friHasBreak && ! empty($fridayConfig['break_end']) ? Carbon::createFromFormat('H:i', $fridayConfig['break_end']) : null;
            $friBreakName = $fridayConfig['break_name'] ?? 'Friday Break';

            $currentFri = $friStart->copy();
            $friPeriodIndex = 1;

            while ($currentFri->lt($friEnd)) {
                if ($friHasBreak && $friBreakStart !== null && $friBreakEnd !== null && $currentFri->equalTo($friBreakStart)) {
                    $slotsToCreate[] = [
                        'institute_id' => $institute->id,
                        'name' => $friBreakName,
                        'start_time' => $friBreakStart->format('H:i'),
                        'end_time' => $friBreakEnd->format('H:i'),
                        'is_break' => true,
                        'days' => $friDays,
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $currentFri = $friBreakEnd->copy();
                    continue;
                }

                if ($friHasBreak && $friBreakStart !== null && $currentFri->lt($friBreakStart) && $currentFri->copy()->addMinutes($durationMinutes)->gt($friBreakStart)) {
                    $next = $friBreakStart->copy();
                } else {
                    $next = $currentFri->copy()->addMinutes($durationMinutes);
                    if ($next->gt($friEnd)) {
                        $next = $friEnd->copy();
                    }
                }

                if ($next->lte($currentFri)) {
                    break;
                }

                $slotsToCreate[] = [
                    'institute_id' => $institute->id,
                    'name' => "Friday Period {$friPeriodIndex}",
                    'start_time' => $currentFri->format('H:i'),
                    'end_time' => $next->format('H:i'),
                    'is_break' => false,
                    'days' => $friDays,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $friPeriodIndex++;
                $currentFri = $next->copy();
            }
        }

        // Wipe old slots and insert newly configured ones
        DB::transaction(function () use ($institute, $slotsToCreate) {
            TimetableTimeSlot::where('institute_id', $institute->id)->delete();
            foreach ($slotsToCreate as $slot) {
                TimetableTimeSlot::create($slot);
            }
        });

        return TimetableTimeSlot::where('institute_id', $institute->id)->orderBy('sort_order')->get()->all();
    }

    /**
     * Generate and store Time Slots from full Days Configuration array.
     *
     * @return array<TimetableTimeSlot>
     */
    public function setupShiftsFromDaysConfig(
        Institute $institute,
        int $durationMinutes,
        array $daysConfig
    ): array {
        $slotsToCreate = [];
        $sortOrder = 1;

        foreach ($daysConfig as $dayConfig) {
            $isActive = (bool) ($dayConfig['active'] ?? $dayConfig['is_active'] ?? true);
            if (! $isActive) {
                continue;
            }

            $dayName = strtolower($dayConfig['name'] ?? $dayConfig['day'] ?? '');
            if (! in_array($dayName, self::DEFAULT_DAYS, true) && $dayName !== 'sunday') {
                continue;
            }

            $startTime = $dayConfig['start_time'] ?? $dayConfig['startTime'] ?? null;
            $endTime = $dayConfig['end_time'] ?? $dayConfig['endTime'] ?? null;

            if (empty($startTime) || empty($endTime)) {
                continue;
            }

            $hasBreak = (bool) ($dayConfig['has_break'] ?? $dayConfig['hasBreak'] ?? false);
            $breakStart = $hasBreak ? ($dayConfig['break_start'] ?? $dayConfig['breakStart'] ?? null) : null;
            $breakEnd = $hasBreak ? ($dayConfig['break_end'] ?? $dayConfig['breakEnd'] ?? null) : null;
            $breakName = $dayConfig['break_name'] ?? $dayConfig['breakName'] ?? 'Recess / Break';

            $start = Carbon::createFromFormat('H:i', $startTime);
            $end = Carbon::createFromFormat('H:i', $endTime);
            $bStart = $breakStart ? Carbon::createFromFormat('H:i', $breakStart) : null;
            $bEnd = $breakEnd ? Carbon::createFromFormat('H:i', $breakEnd) : null;

            $current = $start->copy();
            $periodIndex = 1;

            while ($current->lt($end)) {
                if ($hasBreak && $bStart !== null && $bEnd !== null && $current->equalTo($bStart)) {
                    $slotsToCreate[] = [
                        'institute_id' => $institute->id,
                        'name' => ucfirst($dayName).' '.$breakName,
                        'start_time' => $bStart->format('H:i'),
                        'end_time' => $bEnd->format('H:i'),
                        'is_break' => true,
                        'days' => [$dayName],
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $current = $bEnd->copy();
                    continue;
                }

                if ($hasBreak && $bStart !== null && $current->lt($bStart) && $current->copy()->addMinutes($durationMinutes)->gt($bStart)) {
                    $next = $bStart->copy();
                } else {
                    $next = $current->copy()->addMinutes($durationMinutes);
                    if ($next->gt($end)) {
                        $next = $end->copy();
                    }
                }

                if ($next->lte($current)) {
                    break;
                }

                $slotsToCreate[] = [
                    'institute_id' => $institute->id,
                    'name' => ucfirst($dayName)." Period {$periodIndex}",
                    'start_time' => $current->format('H:i'),
                    'end_time' => $next->format('H:i'),
                    'is_break' => false,
                    'days' => [$dayName],
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $periodIndex++;
                $current = $next->copy();
            }
        }

        DB::transaction(function () use ($institute, $slotsToCreate) {
            TimetableTimeSlot::where('institute_id', $institute->id)->delete();
            foreach ($slotsToCreate as $slot) {
                TimetableTimeSlot::create($slot);
            }
        });

        return TimetableTimeSlot::where('institute_id', $institute->id)->orderBy('sort_order')->get()->all();
    }

    /**
     * Generate clash-free timetable for active classes.
     */
    public function generate(
        Institute $institute,
        int $sessionId,
        array $classIds = [],
        array $days = [],
        bool $overwrite = true,
        array $workloadOverrides = []
    ): array {
        $days = empty($days) ? self::DEFAULT_DAYS : $days;

        // Fetch all active non-break time slots for this institute
        $allSlots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get();

        if ($allSlots->isEmpty()) {
            throw new \RuntimeException('No active lecture time slots found. Please configure time slots / shifts first.');
        }

        // Map slots available per day (e.g. Standard days slots vs Friday slots)
        $daySlots = [];
        foreach ($days as $day) {
            $matchingSlots = $allSlots->filter(function (TimetableTimeSlot $slot) use ($day) {
                if (empty($slot->days)) {
                    return true;
                }

                return in_array($day, $slot->days, true);
            })->values();

            if ($matchingSlots->isNotEmpty()) {
                $daySlots[$day] = $matchingSlots;
            }
        }

        if (empty($daySlots)) {
            throw new \RuntimeException('No matching time slots found for the selected days.');
        }

        // Fetch target classes with their active sections
        $classesQuery = AcademicClass::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->with(['sections' => fn ($q) => $q->where('is_active', true)->orderBy('name')]);

        if (! empty($classIds)) {
            $classesQuery->whereIn('id', $classIds);
        }

        $classes = $classesQuery->get();

        if ($classes->isEmpty()) {
            throw new \RuntimeException('No active classes found to schedule.');
        }

        $targetClassIds = $classes->pluck('id')->all();

        // Fetch existing subject allocations (Teacher ↔ Subject ↔ Class)
        $allocations = SubjectAllocation::query()
            ->where('session_id', $sessionId)
            ->whereIn('class_id', $targetClassIds)
            ->with(['subject', 'teacher'])
            ->get();

        if ($allocations->isEmpty()) {
            throw new \RuntimeException('No subject-teacher allocations found for the selected classes in this session.');
        }

        // Save custom workload overrides if provided (Step 2)
        if (! empty($workloadOverrides)) {
            foreach ($workloadOverrides as $override) {
                TimetableWorkload::updateOrCreate(
                    [
                        'session_id' => $sessionId,
                        'class_id' => $override['class_id'],
                        'subject_id' => $override['subject_id'],
                    ],
                    [
                        'weekly_periods' => $override['weekly_periods'],
                    ]
                );
            }
        }

        // Fetch custom workloads (Curriculum weightage)
        $workloads = TimetableWorkload::query()
            ->where('session_id', $sessionId)
            ->whereIn('class_id', $targetClassIds)
            ->get()
            ->keyBy(fn ($w) => $w->class_id.'_'.$w->subject_id);

        // Build scheduling groups per class and per section
        $groupedAllocations = [];

        foreach ($classes as $class) {
            $activeSections = $class->sections;

            if ($activeSections->isNotEmpty()) {
                // Class has sections: generate timetable for each section
                foreach ($activeSections as $section) {
                    $sectionAllocs = $allocations->where('class_id', $class->id)->where('section_id', $section->id);

                    // Fallback to class-level allocations if section has no specific allocations
                    if ($sectionAllocs->isEmpty()) {
                        $classLevelAllocs = $allocations->where('class_id', $class->id)->whereNull('section_id');
                        $sectionAllocs = $classLevelAllocs->map(function ($alloc) use ($section) {
                            $cloned = clone $alloc;
                            $cloned->section_id = $section->id;

                            return $cloned;
                        });
                    }

                    if ($sectionAllocs->isNotEmpty()) {
                        $groupedAllocations[$class->id.'_'.$section->id] = $sectionAllocs;
                    }
                }
            } else {
                // Class has no sections: generate for class level
                $classAllocs = $allocations->where('class_id', $class->id);
                if ($classAllocs->isNotEmpty()) {
                    $groupedAllocations[$class->id.'_null'] = $classAllocs;
                }
            }
        }

        if (empty($groupedAllocations)) {
            throw new \RuntimeException('No subject allocations could be mapped to classes or sections.');
        }

        // Calculate total available slots across selected days
        $totalSlotsPerWeek = array_sum(array_map(fn ($slots) => count($slots), $daySlots));

        // In-memory occupancy matrices
        // teacherBusy[teacher_id][day][slot_id] = bool
        $teacherBusy = [];
        // classBusy[class_id_section_id][day][slot_id] = bool
        $classBusy = [];
        // dailySubjectCount[class_section][subject_id][day] = int
        $dailySubjectCount = [];

        if (! $overwrite) {
            $existingEntries = TimetableEntry::query()
                ->where('session_id', $sessionId)
                ->get();

            foreach ($existingEntries as $entry) {
                $secKey = $entry->class_id.'_'.($entry->section_id ?? 'null');
                $teacherBusy[$entry->teacher_user_id][$entry->day_of_week][$entry->time_slot_id] = true;
                $classBusy[$secKey][$entry->day_of_week][$entry->time_slot_id] = true;
                $dailySubjectCount[$secKey][$entry->subject_id][$entry->day_of_week] =
                    ($dailySubjectCount[$secKey][$entry->subject_id][$entry->day_of_week] ?? 0) + 1;
            }
        }

        $allEntriesToInsert = [];
        $unassignedLecturesCount = 0;
        $classesScheduledCount = 0;

        foreach ($groupedAllocations as $groupKey => $classAllocations) {
            $first = $classAllocations->first();
            $classId = $first->class_id;
            $sectionId = $first->section_id;

            $classesScheduledCount++;

            $subjectCount = $classAllocations->count();
            $defaultPeriodsPerSubject = $subjectCount > 0
                ? (int) max(1, floor($totalSlotsPerWeek / $subjectCount))
                : 1;

            // Build lecture pool according to Step 2 Subject Weightages
            $lecturePool = [];
            foreach ($classAllocations as $alloc) {
                $workloadKey = $classId.'_'.$alloc->subject_id;
                $periodsNeeded = isset($workloads[$workloadKey])
                    ? $workloads[$workloadKey]->weekly_periods
                    : $defaultPeriodsPerSubject;

                for ($i = 0; $i < $periodsNeeded; $i++) {
                    $lecturePool[] = [
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'subject_id' => $alloc->subject_id,
                        'teacher_user_id' => $alloc->teacher_user_id,
                    ];
                }
            }

            // Shuffle pool to distribute subjects nicely
            shuffle($lecturePool);

            // Sort pool so teachers with most commitments are scheduled first (heuristic)
            usort($lecturePool, function ($a, $b) use ($allocations) {
                $teacherACount = $allocations->where('teacher_user_id', $a['teacher_user_id'])->count();
                $teacherBCount = $allocations->where('teacher_user_id', $b['teacher_user_id'])->count();

                return $teacherBCount <=> $teacherACount;
            });

            // Schedule each lecture in the pool
            foreach ($lecturePool as $lecture) {
                $assigned = false;
                $bestSlot = null;
                $bestScore = -999999;

                // Loop through all days and that day's specific available slots
                foreach ($daySlots as $day => $slotsForDay) {
                    foreach ($slotsForDay as $slot) {
                        $slotId = $slot->id;

                        // Hard constraint 1: Class must be free in this slot
                        if (isset($classBusy[$groupKey][$day][$slotId])) {
                            continue;
                        }

                        // Hard constraint 2: Teacher must be free in this slot across entire school
                        if (isset($teacherBusy[$lecture['teacher_user_id']][$day][$slotId])) {
                            continue;
                        }

                        // Soft constraint scoring: Even distribution
                        $currentDailyCount = $dailySubjectCount[$groupKey][$lecture['subject_id']][$day] ?? 0;
                        $score = 100 - ($currentDailyCount * 60);

                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestSlot = ['day' => $day, 'slot_id' => $slotId];
                        }
                    }
                }

                if ($bestSlot !== null) {
                    $day = $bestSlot['day'];
                    $slotId = $bestSlot['slot_id'];

                    $entryData = [
                        'session_id' => $sessionId,
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'subject_id' => $lecture['subject_id'],
                        'teacher_user_id' => $lecture['teacher_user_id'],
                        'time_slot_id' => $slotId,
                        'day_of_week' => $day,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $allEntriesToInsert[] = $entryData;
                    $classBusy[$groupKey][$day][$slotId] = true;
                    $teacherBusy[$lecture['teacher_user_id']][$day][$slotId] = true;
                    $dailySubjectCount[$groupKey][$lecture['subject_id']][$day] =
                        ($dailySubjectCount[$groupKey][$lecture['subject_id']][$day] ?? 0) + 1;

                    $assigned = true;
                }

                if (! $assigned) {
                    $unassignedLecturesCount++;
                }
            }
        }

        // Persist to Database within a transaction
        DB::transaction(function () use ($sessionId, $targetClassIds, $overwrite, $allEntriesToInsert) {
            if ($overwrite) {
                TimetableEntry::query()
                    ->where('session_id', $sessionId)
                    ->whereIn('class_id', $targetClassIds)
                    ->delete();
            }

            if (! empty($allEntriesToInsert)) {
                TimetableEntry::insert($allEntriesToInsert);
            }
        });

        return [
            'success' => true,
            'message' => 'Timetable generated successfully.',
            'created_count' => count($allEntriesToInsert),
            'classes_scheduled' => $classesScheduledCount,
            'unassigned_count' => $unassignedLecturesCount,
            'days' => array_keys($daySlots),
            'total_weekly_periods' => $totalSlotsPerWeek,
        ];
    }
}
