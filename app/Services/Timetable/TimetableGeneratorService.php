<?php

namespace App\Services\Timetable;

use App\Models\AcademicClass;
use App\Models\AcademicSection;
use App\Models\Institute;
use App\Models\SubjectAllocation;
use App\Models\TimetableEntry;
use App\Models\TimetableTimeSlot;
use App\Models\TimetableWorkload;
use Illuminate\Support\Facades\DB;

class TimetableGeneratorService
{
    private const DEFAULT_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * Generate timetable for an institute session and classes.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   created_count: int,
     *   classes_scheduled: int,
     *   unassigned_count: int,
     *   days: array,
     *   slots_per_day: int
     * }
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

        // Fetch active non-break time slots
        $timeSlots = TimetableTimeSlot::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->get();

        if ($timeSlots->isEmpty()) {
            throw new \RuntimeException('No active lecture time slots found. Please configure time slots first.');
        }

        // Fetch target classes
        $classesQuery = AcademicClass::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true);

        if (! empty($classIds)) {
            $classesQuery->whereIn('id', $classIds);
        }

        $classes = $classesQuery->get();

        if ($classes->isEmpty()) {
            throw new \RuntimeException('No active classes found to schedule.');
        }

        $targetClassIds = $classes->pluck('id')->all();

        // Fetch existing subject allocations
        $allocations = SubjectAllocation::query()
            ->where('session_id', $sessionId)
            ->whereIn('class_id', $targetClassIds)
            ->with(['subject', 'teacher'])
            ->get();

        if ($allocations->isEmpty()) {
            throw new \RuntimeException('No subject-teacher allocations found for the selected classes in this session.');
        }

        // Save custom workload overrides if provided
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

        // Fetch custom workloads
        $workloads = TimetableWorkload::query()
            ->where('session_id', $sessionId)
            ->whereIn('class_id', $targetClassIds)
            ->get()
            ->keyBy(fn ($w) => $w->class_id.'_'.$w->subject_id);

        // Group allocations by Class and Section
        $groupedAllocations = $allocations->groupBy(fn ($item) => $item->class_id.'_'.($item->section_id ?? 'null'));

        $totalSlotsPerWeek = count($days) * $timeSlots->count();

        // In-memory occupancy matrices
        // teacherBusy[teacher_id][day][slot_id] = bool
        $teacherBusy = [];
        // classBusy[class_id_section_id][day][slot_id] = entry
        $classBusy = [];
        // dailySubjectCount[class_section][subject_id][day] = int
        $dailySubjectCount = [];

        // If not overwriting, load existing entries into occupancy matrix
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

            // Determine periods per subject
            $subjectCount = $classAllocations->count();
            $defaultPeriodsPerSubject = $subjectCount > 0
                ? (int) floor($totalSlotsPerWeek / $subjectCount)
                : 0;

            // Build lecture pool
            $lecturePool = [];
            foreach ($classAllocations as $alloc) {
                $workloadKey = $classId.'_'.$alloc->subject_id;
                $periodsNeeded = isset($workloads[$workloadKey])
                    ? $workloads[$workloadKey]->weekly_periods
                    : max(1, $defaultPeriodsPerSubject);

                for ($i = 0; $i < $periodsNeeded; $i++) {
                    $lecturePool[] = [
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'subject_id' => $alloc->subject_id,
                        'teacher_user_id' => $alloc->teacher_user_id,
                    ];
                }
            }

            // Shuffle slightly to distribute subjects across days
            shuffle($lecturePool);

            // Sort pool so teachers with most commitments are scheduled first
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

                // Loop through all days and slots to find the best conflict-free slot
                foreach ($days as $day) {
                    foreach ($timeSlots as $slot) {
                        $slotId = $slot->id;

                        // Hard constraint 1: Class must be free
                        if (isset($classBusy[$groupKey][$day][$slotId])) {
                            continue;
                        }

                        // Hard constraint 2: Teacher must be free
                        if (isset($teacherBusy[$lecture['teacher_user_id']][$day][$slotId])) {
                            continue;
                        }

                        // Soft constraint scoring
                        $currentDailyCount = $dailySubjectCount[$groupKey][$lecture['subject_id']][$day] ?? 0;

                        // Prefer slots where this subject is not taught yet on this day (score penalty if already taught today)
                        $score = 100 - ($currentDailyCount * 50);

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
            'days' => $days,
            'slots_per_day' => $timeSlots->count(),
        ];
    }
}
